<?php
/**
 * 3D file upload API
 * Accepts: .glb, .gltf, .obj (and optionally .mtl for OBJ)
 * Saves to uploads/ and returns URL for viewer
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$allowedExtensions = ['glb', 'gltf', 'obj', 'mtl'];
$uploadDir = __DIR__ . '/../uploads/3d/';
$maxSize = 20 * 1024 * 1024; // 20 MB

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['model']) || $_FILES['model']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['model']['error'] ?? -1;
    echo json_encode(['error' => 'Upload failed. Code: ' . $code]);
    exit;
}

$file = $_FILES['model'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions, true)) {
    echo json_encode(['error' => 'Only .glb, .gltf, .obj, .mtl are allowed.']);
    exit;
}

if ($file['size'] > $maxSize) {
    echo json_encode(['error' => 'File too large. Max 20 MB.']);
    exit;
}

$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
$filename = date('Ymd_His') . '_' . $safeName;
$path = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $path)) {
    echo json_encode(['error' => 'Could not save file.']);
    exit;
}

// Public URL path (relative to project root)
$urlPath = 'uploads/3d/' . $filename;

echo json_encode([
    'success' => true,
    'url'     => $urlPath,
    'filename' => $filename,
]);
