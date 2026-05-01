<?php
/**
 * CabEvac — Evacuation Centers API
 * 
 * GET /api/centers.php           — List all centers
 * GET /api/centers.php?id=1      — Get single center
 * GET /api/centers.php?search=x  — Search by name
 * GET /api/centers.php?status=x  — Filter by status
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
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : null;
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : null;
    $barangayId = isset($_GET['barangay_id']) ? (int)$_GET['barangay_id'] : null;

    // Single center by ID
    if ($id !== null) {
        $stmt = $db->prepare(
            'SELECT ec.id, ec.name, ec.description, ec.latitude, ec.longitude,
                    ec.capacity, ec.status, ec.created_at, ec.updated_at,
                    b.name AS barangay, b.id AS barangay_id,
                    c.name AS category
             FROM evacuation_centers ec
             JOIN barangays b ON ec.barangay_id = b.id
             JOIN categories c ON ec.category_id = c.id
             WHERE ec.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $center = $stmt->fetch();

        if ($center === false) {
            jsonError('Evacuation center not found', 404);
        }

        // Fetch images
        $imgStmt = $db->prepare(
            'SELECT id, image_path, alt_text, sort_order, display_name
             FROM evacuation_center_images
             WHERE evacuation_center_id = ?
             ORDER BY sort_order ASC'
        );
        $imgStmt->execute([$id]);
        $center['images'] = $imgStmt->fetchAll();

        jsonResponse($center);
    }

    // Build query for listing
    $where = [];
    $params = [];

    if ($search !== null && $search !== '') {
        $where[] = '(ec.name LIKE ? OR b.name LIKE ?)';
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($status !== null && in_array($status, ['active', 'inactive'], true)) {
        $where[] = 'ec.status = ?';
        $params[] = $status;
    }

    if ($barangayId !== null) {
        $where[] = 'ec.barangay_id = ?';
        $params[] = $barangayId;
    }

    $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT ec.id, ec.name, ec.description, ec.latitude, ec.longitude,
                   ec.capacity, ec.status, ec.created_at, ec.updated_at,
                   b.name AS barangay, b.id AS barangay_id,
                   c.name AS category
            FROM evacuation_centers ec
            JOIN barangays b ON ec.barangay_id = b.id
            JOIN categories c ON ec.category_id = c.id
            {$whereClause}
            ORDER BY ec.name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $centers = $stmt->fetchAll();

    // Fetch images for each center (batch query)
    if ($centers !== []) {
        $centerIds = array_column($centers, 'id');
        $placeholders = implode(',', array_fill(0, count($centerIds), '?'));

        $imgStmt = $db->prepare(
            "SELECT id, evacuation_center_id, image_path, alt_text, sort_order, display_name
             FROM evacuation_center_images
             WHERE evacuation_center_id IN ({$placeholders})
             ORDER BY sort_order ASC"
        );
        $imgStmt->execute($centerIds);
        $allImages = $imgStmt->fetchAll();

        // Group images by center ID
        $imageMap = [];
        foreach ($allImages as $img) {
            $cid = $img['evacuation_center_id'];
            unset($img['evacuation_center_id']);
            $imageMap[$cid][] = $img;
        }

        // Attach images to centers
        foreach ($centers as &$center) {
            $center['images'] = $imageMap[$center['id']] ?? [];
        }
        unset($center);
    }

    jsonResponse($centers);

} catch (Exception $e) {
    logError('API centers error', ['message' => $e->getMessage()]);
    jsonError('Internal server error', 500);
}
