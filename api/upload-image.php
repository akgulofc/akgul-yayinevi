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

if (!$GITHUB_TOKEN) {
    http_response_code(500); echo json_encode(['error' => 'GITHUB_TOKEN tanımlı değil']); exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); echo json_encode(['error' => 'Dosya yüklenemedi']); exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$mime    = mime_content_type($_FILES['file']['tmp_name']);
if (!in_array($mime, $allowed)) {
    http_response_code(400); echo json_encode(['error' => 'Sadece JPG, PNG, WEBP, GIF yüklenebilir']); exit;
}

if ($_FILES['file']['size'] > 5 * 1024 * 1024) {
    http_response_code(400); echo json_encode(['error' => 'Dosya 5MB\'dan küçük olmalıdır']); exit;
}

$ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
$filename = uniqid('img_', true) . '.' . $ext;
$path     = "images/{$filename}";

$fileContent   = file_get_contents($_FILES['file']['tmp_name']);
$base64Content = base64_encode($fileContent);

$owner  = 'akgulofc';
$repo   = 'akgul-yayinevi';
$apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/contents/{$path}";
$headers = [
    "Authorization: token {$GITHUB_TOKEN}",
    "Accept: application/vnd.github.v3+json",
    "Content-Type: application/json",
    "User-Agent: akgul-admin-bot",
];

$putData = [
    'message'   => "Görsel: {$filename}",
    'content'   => $base64Content,
    'committer' => ['name' => 'Akgül Admin', 'email' => 'akgulyayinevi@gmail.com'],
];

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PUT',
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_POSTFIELDS     => json_encode($putData, JSON_UNESCAPED_UNICODE),
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code >= 200 && $code < 300) {
    $url = "https://raw.githubusercontent.com/{$owner}/{$repo}/main/{$path}";
    echo json_encode(['url' => $url]);
} else {
    $err = json_decode($res, true);
    http_response_code(500);
    echo json_encode(['error' => 'GitHub yükleme hatası: ' . ($err['message'] ?? $res)]);
}
