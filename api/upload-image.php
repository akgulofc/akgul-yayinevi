<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, x-admin-secret');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }

$secret = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
if (!$ADMIN_SECRET || !$secret || $secret !== $ADMIN_SECRET) {
    http_response_code(401); echo json_encode(['error' => 'Yetkisiz erişim']); exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); echo json_encode(['error' => 'Dosya yüklenemedi']); exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$mime    = mime_content_type($_FILES['file']['tmp_name']);
if (!in_array($mime, $allowed)) {
    http_response_code(400); echo json_encode(['error' => 'Sadece JPG, PNG, WEBP, GIF yüklenebilir']); exit;
}

$ext      = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime];
$filename = uniqid('img_', true) . '.' . $ext;
$dir      = dirname(__DIR__) . '/images/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir . $filename)) {
    http_response_code(500); echo json_encode(['error' => 'Dosya kaydedilemedi']); exit;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$url      = "{$protocol}://{$host}/images/{$filename}";

echo json_encode(['url' => $url]);
