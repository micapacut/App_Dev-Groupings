<?php
/**
 * List uploaded 3D files (optional: for "load previous" in viewer)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$dir = __DIR__ . '/../uploads/3d/';
$allowed = ['glb', 'gltf', 'obj'];
$files = [];

if (is_dir($dir)) {
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed, true)) {
            $files[] = ['name' => $f, 'url' => 'uploads/3d/' . $f];
        }
    }
}
usort($files, fn($a, $b) => strcmp($b['name'], $a['name'])); // newest first

echo json_encode(['files' => $files]);
