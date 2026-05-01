<?php
/**
 * CabEvac — Admin Centers API (Protected)
 * 
 * POST   /api/admin/centers.php          — Create center
 * PUT    /api/admin/centers.php?id=X     — Update center
 * DELETE /api/admin/centers.php?id=X     — Delete center
 * PATCH  /api/admin/centers.php?id=X     — Toggle status
 * 
 * @package CabEvac
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';

setApiHeaders();
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'POST':
        handleCreateCenter();
        break;
    case 'PUT':
        if ($id === null) { jsonError('Center ID is required', 400); }
        handleUpdateCenter($id);
        break;
    case 'DELETE':
        if ($id === null) { jsonError('Center ID is required', 400); }
        handleDeleteCenter($id);
        break;
    case 'PATCH':
        if ($id === null) { jsonError('Center ID is required', 400); }
        handleToggleStatus($id);
        break;
    default:
        jsonError('Method not allowed', 405);
}

/**
 * Create a new evacuation center.
 */
function handleCreateCenter(): void
{
    $body = getJsonBody();

    $errors = validateCenterData($body);
    if ($errors !== []) {
        jsonError(implode(', ', $errors), 422);
    }

    $db = getDb();

    try {
        $db->beginTransaction();

        $stmt = $db->prepare(
            'INSERT INTO evacuation_centers (name, description, latitude, longitude, capacity, barangay_id, category_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            sanitizeInput($body['name']),
            isset($body['description']) ? sanitizeInput($body['description']) : null,
            (float)$body['latitude'],
            (float)$body['longitude'],
            isset($body['capacity']) && $body['capacity'] !== '' ? (int)$body['capacity'] : null,
            (int)$body['barangay_id'],
            (int)($body['category_id'] ?? 1),
            in_array($body['status'] ?? 'active', ['active', 'inactive'], true) ? $body['status'] : 'active',
        ]);

        $newId = (int)$db->lastInsertId();

        // Update barangay metadata if provided
        updateBarangayMeta($db, (int)$body['barangay_id'], $body);

        $db->commit();

        $center = fetchCenterById($db, $newId);
        jsonResponse($center, 201);

    } catch (Exception $e) {
        $db->rollBack();
        logError('Create center failed', ['message' => $e->getMessage()]);
        jsonError('Failed to create evacuation center', 500);
    }
}

/**
 * Update an existing evacuation center.
 * 
 * @param int $id Center ID
 */
function handleUpdateCenter(int $id): void
{
    $body = getJsonBody();

    $errors = validateCenterData($body);
    if ($errors !== []) {
        jsonError(implode(', ', $errors), 422);
    }

    $db = getDb();

    // Verify center exists
    $existing = fetchCenterById($db, $id);
    if ($existing === null) {
        jsonError('Evacuation center not found', 404);
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare(
            'UPDATE evacuation_centers
             SET name = ?, description = ?, latitude = ?, longitude = ?,
                 capacity = ?, barangay_id = ?, category_id = ?, status = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([
            sanitizeInput($body['name']),
            isset($body['description']) ? sanitizeInput($body['description']) : null,
            (float)$body['latitude'],
            (float)$body['longitude'],
            isset($body['capacity']) && $body['capacity'] !== '' ? (int)$body['capacity'] : null,
            (int)$body['barangay_id'],
            (int)($body['category_id'] ?? 1),
            in_array($body['status'] ?? 'active', ['active', 'inactive'], true) ? $body['status'] : 'active',
            $id,
        ]);

        // Update barangay metadata if provided
        updateBarangayMeta($db, (int)$body['barangay_id'], $body);

        $db->commit();

        $updated = fetchCenterById($db, $id);
        jsonResponse($updated);

    } catch (Exception $e) {
        $db->rollBack();
        logError('Update center failed', ['message' => $e->getMessage(), 'id' => $id]);
        jsonError('Failed to update evacuation center', 500);
    }
}

/**
 * Delete an evacuation center and its images.
 * 
 * @param int $id Center ID
 */
function handleDeleteCenter(int $id): void
{
    $db = getDb();

    $existing = fetchCenterById($db, $id);
    if ($existing === null) {
        jsonError('Evacuation center not found', 404);
    }

    try {
        $db->beginTransaction();

        // Delete image files from disk
        $imgStmt = $db->prepare('SELECT image_path FROM evacuation_center_images WHERE evacuation_center_id = ?');
        $imgStmt->execute([$id]);
        $images = $imgStmt->fetchAll();

        foreach ($images as $img) {
            $filePath = __DIR__ . '/../../' . ltrim($img['image_path'], '/');
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // DB cascade will delete images
        $stmt = $db->prepare('DELETE FROM evacuation_centers WHERE id = ?');
        $stmt->execute([$id]);

        $db->commit();

        jsonResponse(['message' => 'Evacuation center deleted successfully']);

    } catch (Exception $e) {
        $db->rollBack();
        logError('Delete center failed', ['message' => $e->getMessage(), 'id' => $id]);
        jsonError('Failed to delete evacuation center', 500);
    }
}

/**
 * Toggle evacuation center status.
 * 
 * @param int $id Center ID
 */
function handleToggleStatus(int $id): void
{
    $body = getJsonBody();
    $newStatus = $body['status'] ?? null;

    if (!in_array($newStatus, ['active', 'inactive'], true)) {
        jsonError('Invalid status. Must be "active" or "inactive"');
    }

    $db = getDb();

    $stmt = $db->prepare('UPDATE evacuation_centers SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$newStatus, $id]);

    if ($stmt->rowCount() === 0) {
        jsonError('Evacuation center not found', 404);
    }

    jsonResponse(['message' => "Status updated to {$newStatus}", 'status' => $newStatus]);
}

/**
 * Validate center data input.
 * 
 * @param array<string, mixed> $data Input data
 * @return array<string> Array of error messages
 */
function validateCenterData(array $data): array
{
    $errors = [];

    if (empty($data['name']) || trim($data['name']) === '') {
        $errors[] = 'Name is required';
    }

    if (!isset($data['latitude']) || !is_numeric($data['latitude'])) {
        $errors[] = 'Valid latitude is required';
    } elseif (!validateCoordinates((float)$data['latitude'], (float)($data['longitude'] ?? 0))) {
        $errors[] = 'Coordinates out of valid range';
    }

    if (!isset($data['longitude']) || !is_numeric($data['longitude'])) {
        $errors[] = 'Valid longitude is required';
    }

    if (empty($data['barangay_id']) || !is_numeric($data['barangay_id'])) {
        $errors[] = 'Barangay is required';
    }

    return $errors;
}

/**
 * Update barangay metadata (land_use, description) if provided in request.
 *
 * @param PDO $db
 * @param int $barangayId
 * @param array<string, mixed> $body
 */
function updateBarangayMeta(PDO $db, int $barangayId, array $body): void
{
    $hasLandUse = isset($body['barangay_land_use']);
    $hasDesc    = isset($body['barangay_description']);

    if (!$hasLandUse && !$hasDesc) {
        return;
    }

    $fields = [];
    $values = [];

    if ($hasLandUse) {
        $fields[] = 'land_use = ?';
        $values[] = sanitizeInput($body['barangay_land_use']);
    }
    if ($hasDesc) {
        $fields[] = 'description = ?';
        $values[] = sanitizeInput($body['barangay_description']);
    }

    $values[] = $barangayId;

    $stmt = $db->prepare(
        'UPDATE barangays SET ' . implode(', ', $fields) . ' WHERE id = ?'
    );
    $stmt->execute($values);
}

/**
 * Fetch a single center by ID with joined data.
 * 
 * @param PDO $db Database connection
 * @param int $id Center ID
 * @return array<string, mixed>|null
 */
function fetchCenterById(PDO $db, int $id): ?array
{
    $stmt = $db->prepare(
        'SELECT ec.*, b.name AS barangay, c.name AS category
         FROM evacuation_centers ec
         JOIN barangays b ON ec.barangay_id = b.id
         JOIN categories c ON ec.category_id = c.id
         WHERE ec.id = ?
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $center = $stmt->fetch();

    return $center !== false ? $center : null;
}
