<?php
require 'includes/db.php';
$db = getDb();

$basePath = 'C:/Users/HP VICTUS/Downloads/Cab_evacuation project/Cab_evacuation project';

$layers = [
    'buffer-500m' => $basePath . '/Buffer Analysis/500m buffer.geojson',
    'buffer-1km' => $basePath . '/Buffer Analysis/1km buffer.geojson',
    'buffer-2km' => $basePath . '/Buffer Analysis/2km buffer.geojson'
];

foreach ($layers as $slug => $path) {
    $content = file_get_contents($path);
    if ($content !== false) {
        $stmt = $db->prepare("UPDATE gis_layers SET geojson_data = ? WHERE slug = ?");
        $stmt->execute([$content, $slug]);
        echo "Updated DB for: $slug\n";
    } else {
        echo "Failed to read: $path\n";
    }
}
echo "Done!\n";
