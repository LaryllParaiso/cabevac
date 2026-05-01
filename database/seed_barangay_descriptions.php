<?php
/**
 * CabEvac — Barangay Descriptions Seeder
 *
 * Loads flood_risk_level, land_use, and description from
 * database/barangay_descriptions.json into the barangays table.
 *
 * The JSON file is generated from `Barangay_Descriptions.xlsx`.
 *
 * Usage (from CLI or browser):
 *   php database/seed_barangay_descriptions.php
 *
 * Safe to re-run — uses UPSERT on the unique `name` column so
 * existing barangays get their description fields refreshed and
 * any missing barangays are inserted with name only.
 *
 * @package CabEvac
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

/**
 * Map free-text flood risk values from the spreadsheet to the
 * ENUM('low','moderate','high','very_high') values used in the DB.
 *
 * @param  string $raw
 * @return string|null
 */
function normalizeFloodRisk(string $raw): ?string
{
    $key = strtolower(trim($raw));
    $map = [
        'low'        => 'low',
        'moderate'   => 'moderate',
        'medium'     => 'moderate',
        'high'       => 'high',
        'very high'  => 'very_high',
        'very-high'  => 'very_high',
        'very_high'  => 'very_high',
    ];
    return $map[$key] ?? null;
}

$jsonPath = __DIR__ . '/barangay_descriptions.json';
if (!is_file($jsonPath)) {
    fwrite(STDERR, "[seed] Missing JSON file: {$jsonPath}\n");
    exit(1);
}

$raw = file_get_contents($jsonPath);
$records = json_decode($raw, true);
if (!is_array($records)) {
    fwrite(STDERR, "[seed] Invalid JSON in {$jsonPath}\n");
    exit(1);
}

$db = getDb();

$upsert = $db->prepare(
    'INSERT INTO barangays (name, flood_risk_level, land_use, description)
     VALUES (:name, :risk, :land_use, :description)
     ON DUPLICATE KEY UPDATE
        flood_risk_level = COALESCE(VALUES(flood_risk_level), flood_risk_level),
        land_use         = COALESCE(VALUES(land_use),         land_use),
        description      = COALESCE(VALUES(description),      description)'
);

/**
 * Normalize a barangay name for fuzzy matching:
 *   - Lowercase
 *   - Strip trailing parenthetical qualifiers like "(Pob.)" or "(Aduas)"
 *   - Expand common abbreviations (Sta. → Santa, Sto. → Santo)
 *   - Collapse whitespace
 */
function normalizeBarangayName(string $name): string
{
    $n = strtolower(trim($name));
    // Drop any "(...)" suffix
    $n = preg_replace('/\s*\([^)]*\)\s*$/', '', $n);
    // Expand abbreviations
    $n = str_replace(
        ['sta.', 'sto.', 'pob.'],
        ['santa', 'santo', 'poblacion'],
        $n
    );
    // Drop the word "district" (used inconsistently between sources)
    $n = preg_replace('/\bdistrict\b/', '', $n);
    // Collapse whitespace
    $n = preg_replace('/\s+/', ' ', $n);
    return trim($n);
}

$inserted = 0;
$updated  = 0;
$skipped  = 0;
$fuzzy    = 0;

// Pre-fetch all existing barangays once for fuzzy matching.
// Multiple DB rows can share the same normalized name (e.g.
// "Sanbermicristi" and "Sanbermicristi (Pob.)"), so the map
// holds an array of ids per normalized key.
$existing = $db->query('SELECT id, name FROM barangays')->fetchAll(PDO::FETCH_ASSOC);
$normalizedToIds = [];
foreach ($existing as $b) {
    $key = normalizeBarangayName((string)$b['name']);
    $normalizedToIds[$key][] = (int)$b['id'];
}

$updateById = $db->prepare(
    'UPDATE barangays SET
        flood_risk_level = COALESCE(:risk,        flood_risk_level),
        land_use         = COALESCE(:land_use,    land_use),
        description      = COALESCE(:description, description)
     WHERE id = :id'
);

$db->beginTransaction();
try {
    foreach ($records as $row) {
        $name = isset($row['name']) ? trim((string)$row['name']) : '';
        if ($name === '') { $skipped++; continue; }

        $risk     = isset($row['flood_risk_level']) ? normalizeFloodRisk((string)$row['flood_risk_level']) : null;
        $landUse  = isset($row['land_use'])    ? trim((string)$row['land_use'])    : null;
        $descText = isset($row['description']) ? trim((string)$row['description']) : null;
        $landUse  = $landUse  !== '' ? $landUse  : null;
        $descText = $descText !== '' ? $descText : null;

        // 1) Try exact match / insert via UPSERT on unique name
        $existedBefore = (int)$db->query(
            'SELECT COUNT(*) FROM barangays WHERE name = ' . $db->quote($name)
        )->fetchColumn() > 0;

        $upsert->execute([
            ':name'        => $name,
            ':risk'        => $risk,
            ':land_use'    => $landUse,
            ':description' => $descText,
        ]);

        if ($existedBefore) { $updated++; } else { $inserted++; }

        // 2) Also fuzzy-match other DB rows whose normalized name matches
        //    this Excel record (handles "(Pob.)" suffix, "Sta."/"Pob." etc.).
        $normalized = normalizeBarangayName($name);
        $matchedIds = $normalizedToIds[$normalized] ?? [];
        foreach ($matchedIds as $dbId) {
            $updateById->execute([
                ':risk'        => $risk,
                ':land_use'    => $landUse,
                ':description' => $descText,
                ':id'          => $dbId,
            ]);
            if ($updateById->rowCount() > 0) { $fuzzy++; }
        }
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, "[seed] Failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "[seed] Done. Inserted: {$inserted}, Updated: {$updated}, Fuzzy-matched: {$fuzzy}, Skipped: {$skipped}\n";
