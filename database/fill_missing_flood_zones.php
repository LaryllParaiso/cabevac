<?php

/**
 * One-off migration — Fill missing flood_hazard_zones polygons.
 *
 * For every barangay row with `flood_risk_level` set to
 * very_high / high / moderate, ensure there is a matching
 * polygon in `flood_hazard_zones`.
 *
 * If a polygon is missing, copy the barangay's boundary
 * geometry from the `cabanatuan-boundary` GIS layer and:
 *
 *   1. INSERT it into `flood_hazard_zones`.
 *   2. Append the feature to the corresponding source file
 *      in `Cab_evacuation project/Flood risk/<level>.geojson`
 *      so the project GIS data stays in sync.
 *
 * Idempotent — re-running skips rows that already match.
 *
 * Usage:
 *   php database/fill_missing_flood_zones.php
 *
 * @package CabEvac
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Normalize a barangay name for fuzzy matching.
 * Mirrors the logic in seed_barangay_descriptions.php.
 *
 * @param  string $name
 * @return string
 */
function normalizeName(string $name): string
{
    $n = strtolower(trim($name));
    $n = preg_replace('/\s*\([^)]*\)\s*$/', '', $n);
    $n = str_replace(
        ['sta.', 'sto.', 'pob.'],
        ['santa', 'santo', 'poblacion'],
        $n
    );
    $n = preg_replace('/\bdistrict\b/', '', $n);
    $n = preg_replace('/\s+/', ' ', $n);
    return trim($n);
}

/**
 * Compute the spherical area of a [lng,lat] ring in km².
 * Uses the standard spherical-excess formula good enough for
 * city-scale polygons.
 *
 * @param  array<int,array{0:float,1:float}> $ring
 * @return float
 */
function ringAreaSqKm(array $ring): float
{
    $r  = 6378137.0; // WGS-84 equatorial radius in metres
    $a  = 0.0;
    $n  = count($ring);
    if ($n < 3) { return 0.0; }
    for ($i = 0; $i < $n; $i++) {
        $p1 = $ring[$i];
        $p2 = $ring[($i + 1) % $n];
        $a += deg2rad((float) $p2[0] - (float) $p1[0])
            * (2 + sin(deg2rad((float) $p1[1]))
                 + sin(deg2rad((float) $p2[1])));
    }
    $a = abs($a * $r * $r / 2.0);
    return $a / 1_000_000.0; // m² → km²
}

/**
 * Walk a GeoJSON Polygon / MultiPolygon and sum its outer-ring areas.
 *
 * @param  array $geometry
 * @return float
 */
function geometryAreaSqKm(array $geometry): float
{
    $type   = $geometry['type'] ?? '';
    $coords = $geometry['coordinates'] ?? [];
    $sum    = 0.0;
    if ($type === 'Polygon' && isset($coords[0])) {
        $sum += ringAreaSqKm($coords[0]);
    } elseif ($type === 'MultiPolygon') {
        foreach ($coords as $poly) {
            if (isset($poly[0])) { $sum += ringAreaSqKm($poly[0]); }
        }
    }
    return $sum;
}

// ─── Connect ─────────────────────────────────────────────────────────────────

$pdo = getDb();

echo "── Fill missing flood_hazard_zones ──\n";

// ─── 1. Load boundary GeoJSON ────────────────────────────────────────────────

$row = $pdo->query(
    "SELECT geojson_data FROM gis_layers WHERE slug = 'cabanatuan-boundary' LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if ($row === false || empty($row['geojson_data'])) {
    fwrite(STDERR, "ERROR: gis_layers.cabanatuan-boundary not found.\n");
    exit(1);
}

$boundary = json_decode($row['geojson_data'], true);
if (!isset($boundary['features']) || !is_array($boundary['features'])) {
    fwrite(STDERR, "ERROR: invalid boundary GeoJSON structure.\n");
    exit(1);
}

/** @var array<string,array> $featureByName */
$featureByName = [];
foreach ($boundary['features'] as $f) {
    $name = $f['properties']['ADM4_EN']
        ?? $f['properties']['ADM3_EN']
        ?? $f['properties']['name']
        ?? '';
    if ($name === '') { continue; }
    $featureByName[normalizeName((string) $name)] = $f;
}

echo "  Boundary features indexed: " . count($featureByName) . "\n";

// ─── 2. Find barangays missing a hazard polygon ──────────────────────────────

$stmt = $pdo->query(
    "SELECT b.id, b.name, b.flood_risk_level
       FROM barangays b
      WHERE b.flood_risk_level IN ('very_high', 'high', 'moderate')
        AND NOT EXISTS (
              SELECT 1 FROM flood_hazard_zones f
               WHERE f.barangay_id = b.id
            )
      ORDER BY b.flood_risk_level, b.name"
);
$missing = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "  Barangays missing a hazard polygon: " . count($missing) . "\n";

if (count($missing) === 0) {
    echo "Nothing to do.\n";
    exit(0);
}

// ─── 3. Track features already present (avoid duplicate INSERTs) ─────────────

/** @var array<string,bool> $existingFeatureKeys */
$existingFeatureKeys = [];
$existing = $pdo->query(
    "SELECT DISTINCT b.name, f.risk_level
       FROM flood_hazard_zones f
       JOIN barangays b ON b.id = f.barangay_id"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($existing as $e) {
    $existingFeatureKeys[normalizeName($e['name']) . '|' . $e['risk_level']] = true;
}

// ─── 4. Prepare statements + GeoJSON file paths ──────────────────────────────

$insert = $pdo->prepare(
    "INSERT INTO flood_hazard_zones
       (barangay_id, risk_level, area_sqkm, geometry)
       VALUES (:bid, :risk, :area, :geom)"
);

$gjBaseDir = dirname(__DIR__) . '/Cab_evacuation project/Flood risk';
$gjPaths = [
    'very_high' => $gjBaseDir . '/very high risk.geojson',
    'high'      => $gjBaseDir . '/high risk.geojson',
    'moderate'  => $gjBaseDir . '/moderate risk.geojson',
];

/** @var array<string,array> $gjData */
$gjData = [];
foreach ($gjPaths as $level => $path) {
    if (!file_exists($path)) {
        fwrite(STDERR, "WARNING: missing GeoJSON file: {$path}\n");
        $gjData[$level] = ['type' => 'FeatureCollection', 'name' => $level . ' risk', 'features' => []];
        continue;
    }
    $gjData[$level] = json_decode((string) file_get_contents($path), true);
    if (!is_array($gjData[$level]) || !isset($gjData[$level]['features'])) {
        fwrite(STDERR, "WARNING: malformed JSON in {$path}\n");
        $gjData[$level] = ['type' => 'FeatureCollection', 'name' => $level . ' risk', 'features' => []];
    }
}

// ─── 5. Process each missing row ─────────────────────────────────────────────

$inserted     = 0;
$skippedDup   = 0;
$skippedNoGeo = 0;
$gjAdded      = ['very_high' => 0, 'high' => 0, 'moderate' => 0];

foreach ($missing as $b) {
    $key  = normalizeName($b['name']);
    $risk = $b['flood_risk_level'];

    // Skip duplicate logical barangays (same key + same risk already inserted)
    if (isset($existingFeatureKeys[$key . '|' . $risk])) {
        printf("  - skip dup     %-30s [%s]\n", $b['name'], $risk);
        $skippedDup++;
        continue;
    }

    // Find boundary feature
    if (!isset($featureByName[$key])) {
        printf("  ! no geometry  %-30s [%s]\n", $b['name'], $risk);
        $skippedNoGeo++;
        continue;
    }
    $feature = $featureByName[$key];
    $geom    = $feature['geometry'] ?? null;
    if ($geom === null) {
        printf("  ! null geom    %-30s [%s]\n", $b['name'], $risk);
        $skippedNoGeo++;
        continue;
    }

    $areaSqKm = geometryAreaSqKm($geom);
    $geomJson = json_encode($geom, JSON_UNESCAPED_SLASHES);

    // Insert into DB
    $insert->execute([
        ':bid'  => (int) $b['id'],
        ':risk' => $risk,
        ':area' => $areaSqKm,
        ':geom' => $geomJson,
    ]);
    $inserted++;
    $existingFeatureKeys[$key . '|' . $risk] = true;

    // Append to source GeoJSON file
    if (isset($gjData[$risk])) {
        $newFeature = [
            'type'       => 'Feature',
            'properties' => array_merge(
                $feature['properties'] ?? [],
                [
                    'AREA_SQKM'  => $areaSqKm,
                    'risk_level' => $risk,
                    '_added_by'  => 'fill_missing_flood_zones',
                ]
            ),
            'geometry'   => $geom,
        ];
        $gjData[$risk]['features'][] = $newFeature;
        $gjAdded[$risk]++;
    }

    printf("  + added        %-30s [%s]  area=%.4f km²\n", $b['name'], $risk, $areaSqKm);
}

// ─── 6. Persist updated GeoJSON files ────────────────────────────────────────

foreach ($gjPaths as $level => $path) {
    if ($gjAdded[$level] === 0) { continue; }
    $json = json_encode($gjData[$level], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        fwrite(STDERR, "ERROR: failed to encode GeoJSON for {$level}\n");
        continue;
    }
    if (file_put_contents($path, $json) === false) {
        fwrite(STDERR, "ERROR: failed to write {$path}\n");
        continue;
    }
    echo "  Wrote {$gjAdded[$level]} new feature(s) to {$path}\n";
}

// ─── 7. Summary ──────────────────────────────────────────────────────────────

echo "──────────────────────────────────\n";
echo "Inserted:           {$inserted}\n";
echo "Skipped (dup):      {$skippedDup}\n";
echo "Skipped (no geom):  {$skippedNoGeo}\n";
echo "Done.\n";
