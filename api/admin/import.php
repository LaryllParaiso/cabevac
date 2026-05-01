<?php
/**
 * CabEvac — Admin Data Import API (Protected)
 *
 * POST /api/admin/import.php — Bulk import GeoJSON or CSV
 *
 * @package CabEvac
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';

setApiHeaders();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$importType = $_POST['import_type'] ?? '';
$layerType  = $_POST['layer_type']  ?? '';
$riskLevel  = $_POST['risk_level']  ?? '';

if (!isset($_FILES['file'])) {
    jsonError('No file provided');
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonError('File upload error');
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($extension, ['geojson', 'json', 'csv'], true)) {
    jsonError('Invalid file type. Allowed: .geojson, .json, .csv');
}

$content = file_get_contents($file['tmp_name']);

if ($content === false || $content === '') {
    jsonError('Cannot read uploaded file');
}

$db = getDb();

try {
    $db->beginTransaction();

    $importedCount = 0;

    if ($extension === 'csv') {
        $importedCount = importCsvCenters($db, $file['tmp_name']);
    } else {
        $data = json_decode($content, true);
        if ($data === null) {
            jsonError('Invalid JSON format');
        }

        if ($importType === 'evacuation_centers') {
            $importedCount = importGeoJsonCenters($db, $data);
        } elseif ($importType === 'gis_layer') {
            $importedCount = importGisLayer($db, $data, $layerType, pathinfo($file['name'], PATHINFO_FILENAME));
        } elseif ($importType === 'flood_hazard') {
            $importedCount = importFloodHazard($db, $data, $riskLevel);
        } else {
            jsonError('Invalid import_type. Use: evacuation_centers, gis_layer, or flood_hazard');
        }
    }

    if ($importedCount === 0) {
        $db->rollBack();
        $hint = ($importType === 'evacuation_centers')
            ? 'No valid features found. Make sure your GeoJSON has Point features with "Facility Name", "Barangay", "Latitude", and "Longitude" properties.'
            : 'No valid features found in the uploaded file.';
        jsonError($hint, 422);
    }

    $db->commit();

    jsonResponse([
        'message' => "Successfully imported {$importedCount} record(s)",
        'count' => $importedCount,
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    logError('Import failed', ['message' => $e->getMessage()]);
    jsonError('Import failed: ' . $e->getMessage(), 500);
}

/**
 * Import evacuation centers from GeoJSON.
 *
 * @param PDO $db
 * @param array<string, mixed> $data
 * @return int Number of imported records
 */
function importGeoJsonCenters(PDO $db, array $data): int
{
    $features = $data['features'] ?? [];
    $count = 0;

    $stmt = $db->prepare(
        'INSERT INTO evacuation_centers (name, latitude, longitude, barangay_id, category_id, status)
         VALUES (?, ?, ?, ?, 1, \'active\')'
    );

    foreach ($features as $feature) {
        $props = $feature['properties'] ?? [];
        $name = trim($props['Facility Name'] ?? $props['name'] ?? '');
        $barangay = trim($props['Barangay'] ?? $props['barangay'] ?? '');
        $geom = $feature['geometry'] ?? null;

        if ($name === '') { continue; }

        $lat = $props['Latitude'] ?? $props['latitude'] ?? null;
        $lng = $props['Longitude'] ?? $props['longitude'] ?? null;

        if ($lat === null && $geom !== null && $geom['type'] === 'Point') {
            $lng = $geom['coordinates'][0] ?? null;
            $lat = $geom['coordinates'][1] ?? null;
        }

        if ($lat === null || $lng === null) { continue; }

        $barangayId = findOrCreateBarangay($db, $barangay);

        $stmt->execute([$name, (float)$lat, (float)$lng, $barangayId]);
        $count++;
    }

    return $count;
}

/**
 * Import flood hazard zone features from GeoJSON.
 *
 * @param PDO $db
 * @param array<string, mixed> $data
 * @param string $riskLevel
 * @return int
 */
function importFloodHazard(PDO $db, array $data, string $riskLevel): int
{
    $validRisks = ['very_high', 'high', 'moderate'];
    if (!in_array($riskLevel, $validRisks, true)) {
        throw new RuntimeException('Invalid risk_level. Use: very_high, high, or moderate');
    }

    $features = $data['features'] ?? [];
    $count = 0;

    // Upsert per barangay+risk: update if exists, insert if not
    $checkStmt = $db->prepare(
        'SELECT id FROM flood_hazard_zones WHERE barangay_id = ? AND risk_level = ? LIMIT 1'
    );
    $updateStmt = $db->prepare(
        'UPDATE flood_hazard_zones SET geometry = ?, area_sqkm = ? WHERE barangay_id = ? AND risk_level = ?'
    );
    $insertStmt = $db->prepare(
        'INSERT INTO flood_hazard_zones (barangay_id, risk_level, area_sqkm, geometry) VALUES (?, ?, ?, ?)'
    );

    foreach ($features as $feature) {
        $props = $feature['properties'] ?? [];
        $geom  = $feature['geometry']   ?? null;

        if ($geom === null) { continue; }

        $barangayName = trim(
            $props['ADM4_EN'] ?? $props['barangay'] ?? $props['Barangay'] ?? ''
        );
        if ($barangayName === '') { continue; }

        $areaSqkm = isset($props['AREA_SQKM']) ? (float)$props['AREA_SQKM'] : null;
        $geomJson = json_encode($geom);
        $barangayId = findOrCreateBarangay($db, $barangayName);

        // Upsert: update if exists, insert if not
        $checkStmt->execute([$barangayId, $riskLevel]);
        if ($checkStmt->fetch() !== false) {
            $updateStmt->execute([$geomJson, $areaSqkm, $barangayId, $riskLevel]);
        } else {
            $insertStmt->execute([$barangayId, $riskLevel, $areaSqkm, $geomJson]);
        }

        $count++;
    }

    return $count;
}

/**
 * Import a GIS layer from GeoJSON.
 *
 * @param PDO $db
 * @param array<string, mixed> $data
 * @param string $layerType
 * @param string $filename
 * @return int
 */
function importGisLayer(PDO $db, array $data, string $layerType, string $filename): int
{
    $validTypes = ['buffer', 'isochrone', 'road', 'boundary', 'network_analysis', 'population_density', 'coverage', 'travel_routes'];

    if (!in_array($layerType, $validTypes, true)) {
        throw new RuntimeException('Invalid layer_type');
    }

    $slug = slugify($filename);
    $name = ucwords(str_replace(['-', '_'], ' ', $filename));

    // Map specific layer types to system-standard slugs if needed
    $systemSlugMap = [
        'population_density' => 'pop-density',
        'road'               => 'road-network',
        'boundary'           => 'cabanatuan-boundary',
        'travel_routes'      => 'travel-time',
    ];

    if (isset($systemSlugMap[$layerType])) {
        $slug = $systemSlugMap[$layerType];
        // If it's a system layer, keep the standard name too
        $name = ucwords(str_replace('-', ' ', $slug));
    }

    // Normalize buffer slugs: any filename variant → system standard slug
    if ($layerType === 'buffer') {
        $bufferSlugMap = [
            '500m-buffer' => 'buffer-500m',
            'buffer-500m' => 'buffer-500m',
            '500-buffer'  => 'buffer-500m',
            '1km-buffer'  => 'buffer-1km',
            'buffer-1km'  => 'buffer-1km',
            '1-km-buffer' => 'buffer-1km',
            '2km-buffer'  => 'buffer-2km',
            'buffer-2km'  => 'buffer-2km',
            '2-km-buffer' => 'buffer-2km',
        ];
        if (isset($bufferSlugMap[$slug])) {
            $slug = $bufferSlugMap[$slug];
            $name = ucwords(str_replace('-', ' ', $slug));
        }
    }

    $stmt = $db->prepare(
        'INSERT INTO gis_layers (name, slug, layer_type, geojson_data, is_visible, sort_order)
         VALUES (?, ?, ?, ?, 0, 99)
         ON DUPLICATE KEY UPDATE 
            geojson_data = VALUES(geojson_data),
            layer_type = VALUES(layer_type),
            name = VALUES(name)'
    );
    $stmt->execute([$name, $slug, $layerType, json_encode($data)]);

    // For boundary layers, also upsert every barangay feature into the barangays table
    // so the City Boundary modal reflects all barangays in the file.
    if ($layerType === 'boundary') {
        $features = $data['features'] ?? [];
        $upsertStmt = $db->prepare(
            'INSERT INTO barangays (name, pcode, area_sqkm)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                pcode     = COALESCE(VALUES(pcode),     pcode),
                area_sqkm = COALESCE(VALUES(area_sqkm), area_sqkm)'
        );
        foreach ($features as $feature) {
            $props = $feature['properties'] ?? [];
            $barangayName = trim($props['ADM4_EN'] ?? $props['adm4_en'] ?? '');
            if ($barangayName === '') { continue; }
            $pcode    = $props['ADM4_PCODE'] ?? $props['adm4_pcode'] ?? null;
            $areaSqkm = isset($props['AREA_SQKM']) ? (float)$props['AREA_SQKM'] : null;
            $upsertStmt->execute([$barangayName, $pcode, $areaSqkm]);
        }
    }

    return 1;
}

/**
 * Import evacuation centers from CSV.
 *
 * @param PDO $db
 * @param string $filePath
 * @return int
 */
function importCsvCenters(PDO $db, string $filePath): int
{
    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        throw new RuntimeException('Cannot read CSV file');
    }

    $headers = fgetcsv($handle);
    if ($headers === false) {
        throw new RuntimeException('CSV file is empty');
    }

    $headers = array_map('strtolower', array_map('trim', $headers));
    $count = 0;

    $stmt = $db->prepare(
        'INSERT INTO evacuation_centers (name, latitude, longitude, barangay_id, category_id, status)
         VALUES (?, ?, ?, ?, 1, \'active\')'
    );

    while (($row = fgetcsv($handle)) !== false) {
        $data = array_combine($headers, $row);
        if ($data === false) { continue; }

        $name = trim($data['name'] ?? $data['facility name'] ?? '');
        $lat = $data['latitude'] ?? $data['lat'] ?? null;
        $lng = $data['longitude'] ?? $data['lng'] ?? $data['lon'] ?? null;
        $barangay = trim($data['barangay'] ?? '');

        if ($name === '' || $lat === null || $lng === null) { continue; }

        $barangayId = findOrCreateBarangay($db, $barangay);

        $stmt->execute([$name, (float)$lat, (float)$lng, $barangayId]);
        $count++;
    }

    fclose($handle);
    return $count;
}

/**
 * Find barangay by name or create if it doesn't exist.
 *
 * @param PDO $db
 * @param string $name
 * @return int Barangay ID
 */
function findOrCreateBarangay(PDO $db, string $name): int
{
    if (trim($name) === '') {
        $name = 'Unknown';
    }

    $stmt = $db->prepare('SELECT id FROM barangays WHERE LOWER(name) = LOWER(?) LIMIT 1');
    $stmt->execute([trim($name)]);
    $row = $stmt->fetch();

    if ($row !== false) {
        return (int)$row['id'];
    }

    $insertStmt = $db->prepare('INSERT INTO barangays (name) VALUES (?)');
    $insertStmt->execute([trim($name)]);

    return (int)$db->lastInsertId();
}
