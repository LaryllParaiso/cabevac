<?php
/**
 * CabEvac — Flood Hazard Zones API
 * 
 * GET /api/flood-zones.php              — List all flood zones
 * GET /api/flood-zones.php?risk=high    — Filter by risk level
 * GET /api/flood-zones.php?barangay_id=1 — Filter by barangay
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

try {
    $risk = isset($_GET['risk']) ? sanitizeInput($_GET['risk']) : null;
    $barangayId = isset($_GET['barangay_id']) ? (int)$_GET['barangay_id'] : null;

    $validRisks = ['very_high', 'high', 'moderate'];

    $where = [];
    $params = [];

    if ($risk !== null && $risk !== '') {
        if (!in_array($risk, $validRisks, true)) {
            jsonError('Invalid risk level. Valid: ' . implode(', ', $validRisks));
        }
        $where[] = 'fhz.risk_level = ?';
        $params[] = $risk;
    }

    if ($barangayId !== null) {
        $where[] = 'fhz.barangay_id = ?';
        $params[] = $barangayId;
    }

    $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT fhz.id, fhz.risk_level, fhz.area_sqkm, fhz.geometry,
                   b.name AS barangay, b.id AS barangay_id
            FROM flood_hazard_zones fhz
            JOIN barangays b ON fhz.barangay_id = b.id
            {$whereClause}
            ORDER BY
              FIELD(fhz.risk_level, 'very_high', 'high', 'moderate'),
              b.name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $zones = $stmt->fetchAll();

    // Parse geometry from JSON string to object
    foreach ($zones as &$zone) {
        $zone['geometry'] = json_decode($zone['geometry'], true);
    }
    unset($zone);

    header('Cache-Control: public, max-age=3600');

    jsonResponse($zones);

} catch (Exception $e) {
    logError('API flood-zones error', ['message' => $e->getMessage()]);
    jsonError('Internal server error', 500);
}
