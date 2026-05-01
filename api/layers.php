<?php
/**
 * CabEvac — GIS Layers API
 * 
 * GET /api/layers.php             — List all layers (metadata only)
 * GET /api/layers.php?type=buffer — Filter by layer type
 * GET /api/layers.php?slug=x      — Get specific layer with GeoJSON data
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
    $type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : null;
    $slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : null;

    $validTypes = ['buffer', 'isochrone', 'road', 'boundary', 'network_analysis', 'population_density', 'coverage', 'travel_routes'];

    // Get specific layer by slug (includes full GeoJSON data)
    if ($slug !== null && $slug !== '') {
        $stmt = $db->prepare(
            'SELECT id, name, slug, layer_type, geojson_data, is_visible, sort_order
             FROM gis_layers
             WHERE slug = ?
             LIMIT 1'
        );
        $stmt->execute([$slug]);
        $layer = $stmt->fetch();

        if ($layer === false) {
            jsonError('Layer not found', 404);
        }

        // Parse geojson_data from string to object for proper JSON response
        $geojsonDecoded = json_decode($layer['geojson_data'], true);
        $layer['geojson_data'] = $geojsonDecoded;

        // Add cache header for static layers
        header('Cache-Control: public, max-age=3600');

        jsonResponse($layer);
    }

    // Filter by type (includes full GeoJSON data)
    if ($type !== null && $type !== '') {
        if (!in_array($type, $validTypes, true)) {
            jsonError('Invalid layer type. Valid types: ' . implode(', ', $validTypes));
        }

        $stmt = $db->prepare(
            'SELECT id, name, slug, layer_type, geojson_data, is_visible, sort_order
             FROM gis_layers
             WHERE layer_type = ?
             ORDER BY sort_order ASC'
        );
        $stmt->execute([$type]);
        $layers = $stmt->fetchAll();

        // Parse geojson_data for each layer
        foreach ($layers as &$layer) {
            $layer['geojson_data'] = json_decode($layer['geojson_data'], true);
        }
        unset($layer);

        header('Cache-Control: public, max-age=3600');

        jsonResponse($layers);
    }

    // List all layers (metadata only — no geojson_data to keep response small)
    $stmt = $db->query(
        'SELECT id, name, slug, layer_type, is_visible, sort_order, created_at
         FROM gis_layers
         ORDER BY sort_order ASC, name ASC'
    );
    $layers = $stmt->fetchAll();

    jsonResponse($layers);

} catch (Exception $e) {
    logError('API layers error', ['message' => $e->getMessage()]);
    jsonError('Internal server error', 500);
}
