<?php
/**
 * CabEvac - Admin Barangays API (Protected)
 *
 * POST   /api/admin/barangays.php      - Create barangay
 * PUT    /api/admin/barangays.php?id=X - Update barangay
 * DELETE /api/admin/barangays.php?id=X - Delete barangay
 *
 * @package CabEvac
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/barangay_admin.php';

setApiHeaders();
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'POST':
        handleCreateBarangay();
        break;
    case 'PUT':
        if ($id === null) { jsonError('Barangay ID is required', 400); }
        handleUpdateBarangay($id);
        break;
    case 'DELETE':
        if ($id === null) { jsonError('Barangay ID is required', 400); }
        handleDeleteBarangay($id);
        break;
    default:
        jsonError('Method not allowed', 405);
}

function handleCreateBarangay(): void
{
    $body = getJsonBody();
    $errors = validateBarangayPayload($body);
    if ($errors !== []) {
        jsonError(implode(', ', $errors), 422);
    }

    $data = normalizeBarangayPayload($body);
    $db = getDb();

    if (barangayNameExists($db, $data['name'])) {
        jsonError('Barangay name already exists', 422);
    }

    try {
        $stmt = $db->prepare(
            'INSERT INTO barangays
                (name, pcode, population, population_density, area_sqkm, flood_risk_level, land_use, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            sanitizeInput($data['name']),
            nullableSanitize($data['pcode']),
            $data['population'],
            $data['population_density'],
            $data['area_sqkm'],
            $data['flood_risk_level'],
            nullableSanitize($data['land_use']),
            nullableSanitize($data['description']),
        ]);

        $barangay = fetchAdminBarangayById($db, (int)$db->lastInsertId());
        jsonResponse($barangay, 201);
    } catch (Exception $e) {
        logError('Create barangay failed', ['message' => $e->getMessage()]);
        jsonError('Failed to create barangay', 500);
    }
}

function handleUpdateBarangay(int $id): void
{
    $body = getJsonBody();
    $errors = validateBarangayPayload($body);
    if ($errors !== []) {
        jsonError(implode(', ', $errors), 422);
    }

    $db = getDb();
    if (fetchAdminBarangayById($db, $id) === null) {
        jsonError('Barangay not found', 404);
    }

    $data = normalizeBarangayPayload($body);
    if (barangayNameExists($db, $data['name'], $id)) {
        jsonError('Barangay name already exists', 422);
    }

    try {
        $stmt = $db->prepare(
            'UPDATE barangays
             SET name = ?, pcode = ?, population = ?, population_density = ?,
                 area_sqkm = ?, flood_risk_level = ?, land_use = ?, description = ?
             WHERE id = ?'
        );
        $stmt->execute([
            sanitizeInput($data['name']),
            nullableSanitize($data['pcode']),
            $data['population'],
            $data['population_density'],
            $data['area_sqkm'],
            $data['flood_risk_level'],
            nullableSanitize($data['land_use']),
            nullableSanitize($data['description']),
            $id,
        ]);

        jsonResponse(fetchAdminBarangayById($db, $id));
    } catch (Exception $e) {
        logError('Update barangay failed', ['message' => $e->getMessage(), 'id' => $id]);
        jsonError('Failed to update barangay', 500);
    }
}

function handleDeleteBarangay(int $id): void
{
    $db = getDb();
    if (fetchAdminBarangayById($db, $id) === null) {
        jsonError('Barangay not found', 404);
    }

    $counts = fetchBarangayDependencyCounts($db, $id);
    if ($counts['center_count'] > 0 || $counts['flood_zone_count'] > 0) {
        jsonError(
            barangayDeleteBlockedMessage($counts['center_count'], $counts['flood_zone_count']),
            409
        );
    }

    try {
        $stmt = $db->prepare('DELETE FROM barangays WHERE id = ?');
        $stmt->execute([$id]);
        jsonResponse(['deleted' => true]);
    } catch (Exception $e) {
        logError('Delete barangay failed', ['message' => $e->getMessage(), 'id' => $id]);
        jsonError('Failed to delete barangay', 500);
    }
}

function fetchAdminBarangayById(PDO $db, int $id): ?array
{
    $stmt = $db->prepare(
        'SELECT b.id, b.name, b.pcode, b.population, b.population_density,
                b.area_sqkm, b.flood_risk_level, b.land_use, b.description,
                COUNT(DISTINCT ec.id) AS center_count,
                COUNT(DISTINCT fhz.id) AS flood_zone_count
         FROM barangays b
         LEFT JOIN evacuation_centers ec ON ec.barangay_id = b.id
         LEFT JOIN flood_hazard_zones fhz ON fhz.barangay_id = b.id
         WHERE b.id = ?
         GROUP BY b.id, b.name, b.pcode, b.population, b.population_density,
                  b.area_sqkm, b.flood_risk_level, b.land_use, b.description
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $barangay = $stmt->fetch();

    return $barangay !== false ? $barangay : null;
}

function fetchBarangayDependencyCounts(PDO $db, int $id): array
{
    $centerStmt = $db->prepare('SELECT COUNT(*) FROM evacuation_centers WHERE barangay_id = ?');
    $centerStmt->execute([$id]);
    $zoneStmt = $db->prepare('SELECT COUNT(*) FROM flood_hazard_zones WHERE barangay_id = ?');
    $zoneStmt->execute([$id]);

    return [
        'center_count' => (int)$centerStmt->fetchColumn(),
        'flood_zone_count' => (int)$zoneStmt->fetchColumn(),
    ];
}

function barangayNameExists(PDO $db, string $name, ?int $ignoreId = null): bool
{
    if ($ignoreId !== null) {
        $stmt = $db->prepare('SELECT id FROM barangays WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1');
        $stmt->execute([$name, $ignoreId]);
    } else {
        $stmt = $db->prepare('SELECT id FROM barangays WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
    }

    return $stmt->fetch() !== false;
}

function nullableSanitize(?string $value): ?string
{
    return $value === null ? null : sanitizeInput($value);
}
