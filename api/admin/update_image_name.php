<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

setApiHeaders();

header('Content-Type: application/json');

requireAdmin();

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['id']) || !isset($data['display_name'])) {
        jsonResponse(['error' => 'Missing required fields'], 400);
    }

    $id = (int)$data['id'];
    $displayName = trim($data['display_name']);

    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid image ID'], 400);
    }

    $db = getDb();
    $stmt = $db->prepare('UPDATE evacuation_center_images SET display_name = ? WHERE id = ?');
    $stmt->execute([$displayName, $id]);

    if ($stmt->rowCount() > 0) {
        jsonResponse(['success' => true]);
    } else {
        // It might be 0 if the name was identical, so check if the record exists
        $check = $db->prepare('SELECT id FROM evacuation_center_images WHERE id = ?');
        $check->execute([$id]);
        if ($check->fetch()) {
            jsonResponse(['success' => true]); // No rows updated because data was the same
        } else {
            jsonResponse(['error' => 'Image not found'], 404);
        }
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error'], 500);
}
