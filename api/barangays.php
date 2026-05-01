<?php
/**
 * CabEvac — Barangays API
 * 
 * GET /api/barangays.php            — List all barangays
 * GET /api/barangays.php?id=1       — Get single barangay
 * GET /api/barangays.php?search=x   — Search by name
 * 
 * @package CabEvac
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

setApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$db = getDb();

/**
 * Normalize a barangay name for fuzzy matching.
 * Mirrors the logic in database/seed_barangay_descriptions.php so a
 * boundary-feature name like "Sanbermicristi (Pob.)" resolves to the
 * same row as the Excel-style "Sanbermicristi".
 *
 * @param  string $name
 * @return string
 */
function normalizeBarangayLookup(string $name): string
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

/** Standard SELECT column list — keep in sync between handlers. */
const BARANGAY_COLUMNS =
    'id, name, pcode, population, population_density, area_sqkm,
     flood_risk_level, land_use, description';

try {
    $id     = isset($_GET['id'])     ? (int)$_GET['id']                    : null;
    $name   = isset($_GET['name'])   ? sanitizeInput((string)$_GET['name']) : null;
    $search = isset($_GET['search']) ? sanitizeInput((string)$_GET['search']) : null;

    // Single barangay by ID
    if ($id !== null) {
        $stmt = $db->prepare(
            'SELECT ' . BARANGAY_COLUMNS . '
             FROM barangays WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $barangay = $stmt->fetch();

        if ($barangay === false) { jsonError('Barangay not found', 404); }
        jsonResponse($barangay);
    }

    // Single barangay by name (used by boundary click handler).
    // Tries exact match first, then falls back to normalized matching.
    if ($name !== null && $name !== '') {
        $stmt = $db->prepare(
            'SELECT ' . BARANGAY_COLUMNS . '
             FROM barangays WHERE LOWER(name) = LOWER(?) LIMIT 1'
        );
        $stmt->execute([$name]);
        $barangay = $stmt->fetch();

        if ($barangay === false) {
            $needle = normalizeBarangayLookup($name);
            $all = $db->query('SELECT ' . BARANGAY_COLUMNS . ' FROM barangays')->fetchAll();
            foreach ($all as $row) {
                if (normalizeBarangayLookup((string)$row['name']) === $needle) {
                    $barangay = $row;
                    break;
                }
            }
        }

        if ($barangay === false || $barangay === null) {
            jsonError('Barangay not found', 404);
        }
        jsonResponse($barangay);
    }

    // List / search
    $where  = [];
    $params = [];
    if ($search !== null && $search !== '') {
        $where[]  = 'name LIKE ?';
        $params[] = "%{$search}%";
    }
    $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare(
        'SELECT ' . BARANGAY_COLUMNS . "
         FROM barangays
         {$whereClause}
         ORDER BY name ASC"
    );
    $stmt->execute($params);
    jsonResponse($stmt->fetchAll());

} catch (Exception $e) {
    logError('API barangays error', ['message' => $e->getMessage()]);
    jsonError('Internal server error', 500);
}
