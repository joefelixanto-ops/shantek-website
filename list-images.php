<?php
// ═══════════════════════════════════════════════
//  list-images.php — Shantek Image Library Scanner
//  Upload this to your GoDaddy public_html/shantek/
// ═══════════════════════════════════════════════

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$imgDir = __DIR__ . '/images/';
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!is_dir($imgDir)) {
    echo json_encode(['images' => [], 'count' => 0, 'message' => 'Images folder not found']);
    exit;
}

$files = scandir($imgDir);
$images = [];

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) continue;
    $fullPath = $imgDir . $file;
    $images[] = [
        'name' => $file,
        'url'  => 'images/' . $file,
        'size' => filesize($fullPath),
        'modified' => filemtime($fullPath),
    ];
}

// Sort by most recently modified first
usort($images, function($a, $b) { return $b['modified'] - $a['modified']; });

echo json_encode([
    'success' => true,
    'images'  => array_column($images, 'name'),
    'details' => $images,
    'count'   => count($images),
]);
?>
