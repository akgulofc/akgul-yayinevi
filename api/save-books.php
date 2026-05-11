<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, x-admin-secret');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }

$secret   = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
$expected = getenv('ADMIN_SECRET');
if (!$expected || !$secret || $secret !== $expected) { http_response_code(401); echo json_encode(['error' => 'Yetkisiz erişim']); exit; }

$token = getenv('GITHUB_TOKEN');
if (!$token) { http_response_code(500); echo json_encode(['error' => 'GITHUB_TOKEN tanımlı değil']); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['books']) || !is_array($body['books'])) { http_response_code(400); echo json_encode(['error' => 'Geçersiz istek gövdesi']); exit; }

$books   = $body['books'];
$owner   = 'akgulofc';
$repo    = 'akgul-yayinevi';
$apiUrl  = "https://api.github.com/repos/{$owner}/{$repo}/contents/data/books.json";
$headers = [
    "Authorization: token {$token}",
    "Accept: application/vnd.github.v3+json",
    "Content-Type: application/json",
    "User-Agent: akgul-admin-bot",
];

$ch  = curl_init($apiUrl);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
$res = curl_exec($ch);
$cod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$sha = null;
if ($cod === 200) $sha = json_decode($res, true)['sha'] ?? null;

$putData = ['message' => 'Admin: kitap listesi güncellendi', 'content' => base64_encode(json_encode($books, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), 'committer' => ['name' => 'Akgül Admin', 'email' => 'akgulyayinevi@gmail.com']];
if ($sha) $putData['sha'] = $sha;

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($putData, JSON_UNESCAPED_UNICODE)]);
$res = curl_exec($ch);
$cod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($cod < 200 || $cod >= 300) { http_response_code(500); echo json_encode(['error' => "GitHub API hatası: {$res}"]); exit; }

echo json_encode(['success' => true]);
