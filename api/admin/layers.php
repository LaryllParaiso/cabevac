<?php
/**
 * CabEvac — Admin Layers API
 *
 * POST /api/admin/layers.php
 *   { action: 'toggle_visibility', id: int, visible: 0|1 }
 *   { action: 'delete', id: int }
 *
 * @package CabEvac
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

setApiHeaders();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body) || !isset($body['action'])) {
    jsonError('Missing action', 400);
}

$db     = getDb();
$action = (string)$body['action'];
$id     = isset($body['id']) ? (int)$body['id'] : 0;

if ($id <= 0) {
    jsonError('Invalid layer ID', 400);
}

try {
    if ($action === 'toggle_visibility') {
        $visible = isset($body['visible']) ? (int)(bool)$body['visible'] : 0;
        $stmt = $db->prepare('UPDATE gis_layers SET is_visible = ? WHERE id = ?');
        $stmt->execute([$visible, $id]);
        if ($stmt->rowCount() === 0) {
            jsonError('Layer not found', 404);
        }
        jsonResponse(['updated' => true]);
    }

    if ($action === 'delete') {
        $stmt = $db->prepare('DELETE FROM gis_layers WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            jsonError('Layer not found', 404);
        }
        jsonResponse(['deleted' => true]);
    }

    if ($action === 'delete_flood_zone') {
        $stmt = $db->prepare('DELETE FROM flood_hazard_zones WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            jsonError('Flood zone not found', 404);
        }
        jsonResponse(['deleted' => true]);
    }

    jsonError('Unknown action', 400);

} catch (Exception $e) {
    logError('Admin layers API error', ['message' => $e->getMessage()]);
    jsonError('Internal server error', 500);
}
