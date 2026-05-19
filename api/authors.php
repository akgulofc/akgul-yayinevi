<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, x-admin-secret');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if (!$GITHUB_TOKEN) { http_response_code(500); echo json_encode(['error' => 'Sunucu hatası']); exit; }

$owner   = 'akgulofc';
$apiUrl  = "https://api.github.com/repos/{$owner}/{$PRIVATE_REPO_NAME}/contents/authors.json";
$headers = [
    "Authorization: token {$GITHUB_TOKEN}",
    "Accept: application/vnd.github.v3+json",
    "Content-Type: application/json",
    "User-Agent: akgul-admin-bot",
];

$ch  = curl_init($apiUrl);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
$res = curl_exec($ch);
$cod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($cod !== 200) { http_response_code(502); echo json_encode(['error' => 'Yazarlar okunamadı']); exit; }

$data    = json_decode($res, true);
$authors = json_decode(base64_decode(str_replace("\n", '', $data['content'] ?? '')), true);
if (!is_array($authors)) { echo json_encode([]); exit; }

$secret  = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
$isAdmin = $ADMIN_SECRET && $secret === $ADMIN_SECRET;

// Admin değilse membership.password alanını gizle
if (!$isAdmin) {
    foreach ($authors as &$a) {
        if (isset($a['membership']['password'])) unset($a['membership']['password']);
    }
    unset($a);
}

echo json_encode($authors, JSON_UNESCAPED_UNICODE);
