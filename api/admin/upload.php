<?php
/**
 * CabEvac — Admin Image Upload API (Protected)
 * 
 * POST   /api/admin/upload.php          — Upload image
 * DELETE /api/admin/upload.php?id=X     — Delete image
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

switch ($method) {
    case 'POST':
        handleUpload();
        break;
    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id === null) { jsonError('Image ID is required'); }
        handleDeleteImage($id);
        break;
    default:
        jsonError('Method not allowed', 405);
}

/**
 * Handle image upload.
 */
function handleUpload(): void
{
    $centerId = isset($_POST['evacuation_center_id']) ? (int)$_POST['evacuation_center_id'] : null;

    if ($centerId === null || $centerId <= 0) {
        jsonError('Evacuation center ID is required');
    }

    if (!isset($_FILES['image'])) {
        jsonError('No image file provided');
    }

    $file = $_FILES['image'];

    // Validate
    $validationResult = validateImageFile($file);
    if ($validationResult !== true) {
        jsonError($validationResult);
    }

    // Verify center exists
    $db = getDb();
    $stmt = $db->prepare('SELECT id FROM evacuation_centers WHERE id = ? LIMIT 1');
    $stmt->execute([$centerId]);

    if ($stmt->fetch() === false) {
        jsonError('Evacuation center not found', 404);
    }

    // Generate unique filename and move file
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = generateUniqueFilename($extension);
    $uploadDir = __DIR__ . '/../../uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        jsonError('Failed to save uploaded file', 500);
    }

    // Insert DB record
    $altText = isset($_POST['alt_text']) ? sanitizeInput($_POST['alt_text']) : null;
    $imagePath = 'uploads/' . $filename;

    try {
    $insertStmt = $db->prepare(
            'INSERT INTO evacuation_center_images (evacuation_center_id, image_path, alt_text, sort_order, display_name)
             VALUES (?, ?, ?, ?, ?)'
        );

        // Get next sort order
        $orderStmt = $db->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1
             FROM evacuation_center_images
             WHERE evacuation_center_id = ?'
        );
        $orderStmt->execute([$centerId]);
        $nextOrder = (int)$orderStmt->fetchColumn();

        $originalFilename = $file['name'];

        $insertStmt->execute([$centerId, $imagePath, $altText, $nextOrder, $originalFilename]);

        $imageId = (int)$db->lastInsertId();

        jsonResponse([
            'id' => $imageId,
            'image_path' => $imagePath,
            'alt_text' => $altText,
            'sort_order' => $nextOrder,
            'display_name' => $originalFilename,
        ], 201);

    } catch (Exception $e) {
        // Cleanup file if DB insert fails
        if (file_exists($destination)) {
            unlink($destination);
        }
        logError('Image upload DB insert failed', ['message' => $e->getMessage()]);
        jsonError('Failed to save image record', 500);
    }
}

/**
 * Handle image deletion.
 * 
 * @param int $id Image ID
 */
function handleDeleteImage(int $id): void
{
    $db = getDb();

    $stmt = $db->prepare(
        'SELECT id, image_path FROM evacuation_center_images WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $image = $stmt->fetch();

    if ($image === false) {
        jsonError('Image not found', 404);
    }

    // Delete file from disk
    $filePath = __DIR__ . '/../../' . ltrim($image['image_path'], '/');
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Delete DB record
    $deleteStmt = $db->prepare('DELETE FROM evacuation_center_images WHERE id = ?');
    $deleteStmt->execute([$id]);

    jsonResponse(['message' => 'Image deleted successfully']);
}
