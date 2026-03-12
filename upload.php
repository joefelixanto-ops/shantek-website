<?php
// ═══════════════════════════════════════════════
//  upload.php — Shantek Image Upload Handler
//  Upload this to your GoDaddy public_html/shantek/
// ═══════════════════════════════════════════════

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST only']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

// Check password
if (!isset($body['password']) || $body['password'] !== 'shantek123') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$filename = basename($body['filename'] ?? '');
$data     = $body['data'] ?? '';

if (!$filename || !$data) {
    echo json_encode(['success' => false, 'message' => 'Missing filename or data']);
    exit;
}

// Sanitize filename — only allow safe characters
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename);
$filename = strtolower($filename);

// Only allow image extensions
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
    echo json_encode(['success' => false, 'message' => 'Only image files allowed']);
    exit;
}

// Create images folder if it doesn't exist
$imgDir = __DIR__ . '/images/';
if (!is_dir($imgDir)) {
    mkdir($imgDir, 0755, true);
}

// Decode base64 image
$imageData = $data;
if (strpos($data, ',') !== false) {
    $imageData = substr($data, strpos($data, ',') + 1);
}
$decoded = base64_decode($imageData);

if (!$decoded) {
    echo json_encode(['success' => false, 'message' => 'Invalid image data']);
    exit;
}

// Save file
$filePath = $imgDir . $filename;
$result = file_put_contents($filePath, $decoded);

if ($result !== false) {
    echo json_encode([
        'success'  => true,
        'filename' => $filename,
        'url'      => 'images/' . $filename,
        'size'     => strlen($decoded),
        'message'  => 'Image uploaded successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save image. Check folder permissions.']);
}
?>
