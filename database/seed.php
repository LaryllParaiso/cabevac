<?php
/**
 * CabEvac — Database Seeder
 * 
 * Parses all GeoJSON files from source data and imports them into MySQL.
 * Uses per-section transactions to avoid packet size issues with large files.
 * 
 * Run from CLI: php database/seed.php [path-to-source-data]
 * 
 * @package CabEvac
 */

declare(strict_types=1);

$startTime = microtime(true);

require_once __DIR__ . '/../includes/db.php';

/** Base path to original QGIS-exported GeoJSON files */
$sourceBase = '';

// 1. Check CLI argument
if (isset($argv[1]) && is_dir($argv[1])) {
    $sourceBase = realpath($argv[1]);
}

// 2. Fallback: try relative to seed.php (works when cabevac is alongside source)
if ($sourceBase === '' || $sourceBase === false) {
    $tryRelative = realpath(__DIR__ . '/../../Cab_evacuation project');
    if ($tryRelative !== false && is_dir($tryRelative)) {
        $sourceBase = $tryRelative;
    }
}

if ($sourceBase === '' || $sourceBase === false || !is_dir($sourceBase)) {
    echo "ERROR: Source data directory not found.\n\n";
    echo "Usage: php database/seed.php [path-to-source-geojson-folder]\n";
    echo "Example: php database/seed.php \"C:\\Users\\HP VICTUS\\Downloads\\Cab_evacuation project\\Cab_evacuation project\"\n";
    exit(1);
}

echo "=== CabEvac Database Seeder ===\n";
echo "Source: {$sourceBase}\n\n";

$db = getDb();

// Note: max_allowed_packet must be set to 64M in my.ini and MySQL restarted

/**
 * Read and decode a GeoJSON file.
 * 
 * @param string $filePath Absolute path to .geojson file
 * @return array<string, mixed>|null Decoded GeoJSON or null on failure
 */
function readGeoJson(string $filePath): ?array
{
    if (!file_exists($filePath)) {
        echo "  WARNING: File not found: {$filePath}\n";
        return null;
    }

    $content = file_get_contents($filePath);

    if ($content === false) {
        echo "  WARNING: Cannot read: {$filePath}\n";
        return null;
    }

    $data = json_decode($content, true);

    if ($data === null) {
        echo "  WARNING: Invalid JSON in: {$filePath}\n";
        return null;
    }

    return $data;
}

$barangayCount = 0;
$centerCount = 0;
$floodCount = 0;
$layerCount = 0;

// ============================================
// 1. SEED BARANGAYS from pop den.geojson
// ============================================
echo "[1/6] Seeding barangays...\n";

try {
    $db->beginTransaction();

    $popDenPath = $sourceBase . '/Population density/pop den.geojson';
    $popDenData = readGeoJson($popDenPath);

    if ($popDenData !== null && isset($popDenData['features'])) {
        $stmtBarangay = $db->prepare(
            'INSERT INTO barangays (name, population_density, object_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE population_density = VALUES(population_density)'
        );

        foreach ($popDenData['features'] as $feature) {
            $props = $feature['properties'] ?? [];
            $name = trim($props['ADM4_EN'] ?? $props['adm4_en'] ?? '');

            if ($name === '') {
                continue;
            }

            $popDensity = $props['pop den'] ?? $props['pop_den'] ?? null;
            $objectId = $props['OBJECTID'] ?? $props['objectid'] ?? null;

            $stmtBarangay->execute([
                $name,
                $popDensity !== null ? (float)$popDensity : null,
                $objectId !== null ? (int)$objectId : null,
            ]);
            $barangayCount++;
        }
    }

    $db->commit();
    echo "  Inserted {$barangayCount} barangays.\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "  ERROR: {$e->getMessage()}\n";
    exit(1);
}

// Build barangay lookup map: name (lowercase) => id
$barangayLookup = [];
$rows = $db->query('SELECT id, name FROM barangays')->fetchAll();
foreach ($rows as $row) {
    $barangayLookup[strtolower(trim($row['name']))] = (int)$row['id'];
}

// ============================================
// 2. SEED EVACUATION CENTERS
// ============================================
echo "\n[2/6] Seeding evacuation centers...\n";

try {
    $db->beginTransaction();

    $evacPath = $sourceBase . '/Evacuation centers/evacuation centers.geojson';
    $evacData = readGeoJson($evacPath);

    if ($evacData !== null && isset($evacData['features'])) {
        $stmtCenter = $db->prepare(
            'INSERT INTO evacuation_centers (name, latitude, longitude, barangay_id, category_id, status)
             VALUES (?, ?, ?, ?, 1, \'active\')'
        );

        foreach ($evacData['features'] as $feature) {
            $props = $feature['properties'] ?? [];
            $name = trim($props['Facility Name'] ?? $props['facility_name'] ?? '');
            $barangay = trim($props['Barangay'] ?? $props['barangay'] ?? '');
            $lat = $props['Latitude'] ?? $props['latitude'] ?? null;
            $lng = $props['Longitude'] ?? $props['longitude'] ?? null;

            if ($name === '' || $lat === null || $lng === null) {
                echo "  WARNING: Skipping center with missing data: {$name}\n";
                continue;
            }

            $barangayId = $barangayLookup[strtolower($barangay)] ?? null;

            if ($barangayId === null) {
                $insertBrgy = $db->prepare('INSERT IGNORE INTO barangays (name) VALUES (?)');
                $insertBrgy->execute([$barangay]);
                $barangayId = (int)$db->lastInsertId();

                if ($barangayId === 0) {
                    $fetchId = $db->prepare('SELECT id FROM barangays WHERE name = ? LIMIT 1');
                    $fetchId->execute([$barangay]);
                    $found = $fetchId->fetch();
                    $barangayId = $found !== false ? (int)$found['id'] : null;
                }

                if ($barangayId !== null) {
                    $barangayLookup[strtolower($barangay)] = $barangayId;
                }
            }

            if ($barangayId === null) {
                echo "  WARNING: Cannot match barangay '{$barangay}' for center '{$name}'\n";
                continue;
            }

            $stmtCenter->execute([
                $name,
                (float)$lat,
                (float)$lng,
                $barangayId,
            ]);
            $centerCount++;
        }
    }

    $db->commit();
    echo "  Inserted {$centerCount} evacuation centers.\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "  ERROR: {$e->getMessage()}\n";
    exit(1);
}

// ============================================
// 3. SEED FLOOD HAZARD ZONES
// ============================================
echo "\n[3/6] Seeding flood hazard zones...\n";

try {
    $db->beginTransaction();

    $floodFiles = [
        'very_high' => $sourceBase . '/Flood risk/very high risk.geojson',
        'high' => $sourceBase . '/Flood risk/high risk.geojson',
        'moderate' => $sourceBase . '/Flood risk/moderate risk.geojson',
    ];

    $stmtFlood = $db->prepare(
        'INSERT INTO flood_hazard_zones (barangay_id, risk_level, area_sqkm, geometry)
         VALUES (?, ?, ?, ?)'
    );

    foreach ($floodFiles as $riskLevel => $filePath) {
        $floodData = readGeoJson($filePath);

        if ($floodData === null || !isset($floodData['features'])) {
            continue;
        }

        foreach ($floodData['features'] as $feature) {
            $props = $feature['properties'] ?? [];
            $barangayName = trim($props['ADM4_EN'] ?? $props['adm4_en'] ?? '');
            $areaSqkm = $props['AREA_SQKM'] ?? $props['area_sqkm'] ?? null;
            $geometry = $feature['geometry'] ?? null;

            if ($barangayName === '' || $geometry === null) {
                continue;
            }

            $barangayId = $barangayLookup[strtolower($barangayName)] ?? null;

            if ($barangayId === null) {
                $insertBrgy = $db->prepare('INSERT IGNORE INTO barangays (name) VALUES (?)');
                $insertBrgy->execute([$barangayName]);
                $barangayId = (int)$db->lastInsertId();

                if ($barangayId === 0) {
                    $fetchId = $db->prepare('SELECT id FROM barangays WHERE name = ? LIMIT 1');
                    $fetchId->execute([$barangayName]);
                    $found = $fetchId->fetch();
                    $barangayId = $found !== false ? (int)$found['id'] : null;
                }

                if ($barangayId !== null) {
                    $barangayLookup[strtolower($barangayName)] = $barangayId;
                }
            }

            if ($barangayId === null) {
                echo "  WARNING: Cannot match barangay '{$barangayName}' for flood zone\n";
                continue;
            }

            $stmtFlood->execute([
                $barangayId,
                $riskLevel,
                $areaSqkm !== null ? (float)$areaSqkm : null,
                json_encode($geometry),
            ]);
            $floodCount++;
        }
    }

    $db->commit();
    echo "  Inserted {$floodCount} flood hazard zones.\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "  ERROR: {$e->getMessage()}\n";
    exit(1);
}

// ============================================
// 4. SEED GIS LAYERS — Small files (one transaction)
// ============================================
echo "\n[4/6] Seeding GIS layers (small files)...\n";

try {
    $db->beginTransaction();

    $stmtLayer = $db->prepare(
        'INSERT INTO gis_layers (name, slug, layer_type, geojson_data, is_visible, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $smallLayers = [
        ['500m Buffer', 'buffer-500m', 'buffer', $sourceBase . '/Buffer Analysis/500m buffer.geojson', 0, 10],
        ['1km Buffer', 'buffer-1km', 'buffer', $sourceBase . '/Buffer Analysis/1km buffer.geojson', 0, 11],
        ['2km Buffer', 'buffer-2km', 'buffer', $sourceBase . '/Buffer Analysis/2km buffer.geojson', 0, 12],
        ['Isochrone Layers', 'isochrone-layers', 'isochrone', $sourceBase . '/Isochrones/isochrones layer.geojson', 0, 20],
        ['Cabanatuan Boundary', 'cabanatuan-boundary', 'boundary', $sourceBase . '/boundary/cabanatuan boundary.geojson', 1, 1],
        ['Population Density', 'population-density', 'population_density', $sourceBase . '/Population density/population density.geojson', 0, 30],
    ];

    foreach ($smallLayers as [$name, $slug, $type, $path, $visible, $order]) {
        $content = file_exists($path) ? file_get_contents($path) : null;

        if ($content === null || $content === false) {
            echo "  WARNING: Cannot read {$path}\n";
            continue;
        }

        $stmtLayer->execute([$name, $slug, $type, $content, $visible, $order]);
        $layerCount++;
        echo "  + {$name}\n";
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    echo "  ERROR: {$e->getMessage()}\n";
    exit(1);
}

// ============================================
// 5. SEED GIS LAYERS — Road Network (LARGE — each file in its own transaction)
// ============================================
echo "\n[5/6] Seeding road network layers (large files)...\n";

$roadLayers = [
    ['Road Network', 'road-network', 'road', $sourceBase . '/road/road network.geojson', 0, 40],
    ['Cabanatuan Highway', 'cabanatuan-highway', 'road', $sourceBase . '/road/cabanatuan highway.geojson', 0, 41],
];

foreach ($roadLayers as [$name, $slug, $type, $path, $visible, $order]) {
    $content = file_exists($path) ? file_get_contents($path) : null;

    if ($content === null || $content === false) {
        echo "  WARNING: Cannot read {$path}\n";
        continue;
    }

    $sizeMB = round(strlen($content) / 1024 / 1024, 2);

    try {
        $db->beginTransaction();

        $stmtRoad = $db->prepare(
            'INSERT INTO gis_layers (name, slug, layer_type, geojson_data, is_visible, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmtRoad->execute([$name, $slug, $type, $content, $visible, $order]);

        $db->commit();
        $layerCount++;
        echo "  + {$name} ({$sizeMB} MB) ✓\n";
    } catch (Exception $e) {
        $db->rollBack();
        echo "  WARNING: Skipped {$name} ({$sizeMB} MB) — {$e->getMessage()}\n";
        echo "  TIP: Increase max_allowed_packet in my.ini to 64M and restart MySQL\n";
    }

    // Free memory
    unset($content);
}

// ============================================
// 6. SEED GIS LAYERS — Network Analysis + Coverage (one transaction)
// ============================================
echo "\n[6/6] Seeding network analysis and coverage layers...\n";

try {
    $db->beginTransaction();

    $stmtLayer = $db->prepare(
        'INSERT INTO gis_layers (name, slug, layer_type, geojson_data, is_visible, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    // Travel time route
    $travelTimePath = $sourceBase . '/Network Analysis/travel time.geojson';

    if (file_exists($travelTimePath)) {
        $content = file_get_contents($travelTimePath);

        if ($content !== false) {
            $stmtLayer->execute(['Travel Time Routes', 'travel-time', 'network_analysis', $content, 0, 50]);
            $layerCount++;
            echo "  + Travel Time Routes\n";
            unset($content);
        }
    }

    // Individual barangay network analysis routes
    $networkDir = $sourceBase . '/Network Analysis';
    $networkFiles = glob($networkDir . '/*.geojson');
    $skipFiles = ['travel time.geojson', 'nodes.geojson'];
    $routeCount = 0;

    if ($networkFiles !== false) {
        foreach ($networkFiles as $filePath) {
            $filename = basename($filePath);

            if (in_array($filename, $skipFiles, true)) {
                continue;
            }

            $barangayName = pathinfo($filename, PATHINFO_FILENAME);
            $content = file_get_contents($filePath);

            if ($content === false) {
                continue;
            }

            $slug = 'network-' . slugify($barangayName);
            $displayName = 'Route: ' . ucwords($barangayName);

            $stmtLayer->execute([$displayName, $slug, 'network_analysis', $content, 0, 51]);
            $layerCount++;
            $routeCount++;
            unset($content);
        }

        echo "  + {$routeCount} barangay route files\n";
    }

    // Evacuation center coverage polygons
    $coverageDir = $sourceBase . '/Evacuation centers';
    $coverageFiles = glob($coverageDir . '/*.geojson');
    $skipCoverage = ['evacuation centers.geojson'];
    $coverageCount = 0;

    if ($coverageFiles !== false) {
        foreach ($coverageFiles as $filePath) {
            $filename = basename($filePath);

            if (in_array($filename, $skipCoverage, true)) {
                continue;
            }

            $centerName = pathinfo($filename, PATHINFO_FILENAME);
            $content = file_get_contents($filePath);

            if ($content === false) {
                continue;
            }

            $slug = 'coverage-' . slugify($centerName);
            $displayName = 'Coverage: ' . ucwords($centerName);

            $stmtLayer->execute([$displayName, $slug, 'coverage', $content, 0, 60]);
            $layerCount++;
            $coverageCount++;
            unset($content);
        }

        echo "  + {$coverageCount} evacuation center coverage polygons\n";
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    echo "  ERROR: {$e->getMessage()}\n";
    exit(1);
}

// ============================================
// SUMMARY
// ============================================
$elapsed = round(microtime(true) - $startTime, 2);

echo "\n=== SEED COMPLETE ===\n";
echo "Barangays:          {$barangayCount}\n";
echo "Evacuation Centers: {$centerCount}\n";
echo "Flood Hazard Zones: {$floodCount}\n";
echo "GIS Layers:         {$layerCount}\n";
echo "Time:               {$elapsed}s\n";
echo "=====================\n";
