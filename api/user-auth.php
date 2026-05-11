<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }

$token = getenv('GITHUB_TOKEN');
$repo  = getenv('PRIVATE_REPO_NAME') ?: 'akgul-data';
$owner = 'akgulofc';

if (!$token) { http_response_code(500); echo json_encode(['error' => 'Sunucu yapılandırma hatası']); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['action'])) { http_response_code(400); echo json_encode(['error' => 'Geçersiz istek']); exit; }

$action = $body['action'];
$apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/contents/users.json";
$ghHeaders = [
    "Authorization: token {$token}",
    "Accept: application/vnd.github.v3+json",
    "Content-Type: application/json",
    "User-Agent: akgul-admin-bot",
];

function gh_get($url, $headers) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $res];
}

function gh_put($url, $headers, $data) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE)]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $res];
}

$r       = gh_get($apiUrl, $ghHeaders);
$users   = [];
$fileSha = null;
if ($r['code'] === 200) {
    $data    = json_decode($r['body'], true);
    $fileSha = $data['sha'];
    $users   = json_decode(base64_decode(str_replace("\n", '', $data['content'])), true) ?: [];
}

if ($action === 'login') {
    $email = $body['email'] ?? '';
    $pass  = $body['pass']  ?? '';
    if (!$email || !$pass) { http_response_code(400); echo json_encode(['error' => 'E-posta ve şifre gerekli']); exit; }
    $found = null;
    foreach ($users as $u) {
        if (strtolower($u['email']) === strtolower($email) && $u['pass'] === $pass) { $found = $u; break; }
    }
    if (!$found) { http_response_code(401); echo json_encode(['error' => 'E-posta veya şifre hatalı']); exit; }
    unset($found['pass']);
    echo json_encode(['user' => $found], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'register') {
    $name  = $body['name']   ?? '';
    $email = $body['email']  ?? '';
    $pass  = $body['pass']   ?? '';
    if (!$name || !$email || !$pass) { http_response_code(400); echo json_encode(['error' => 'Ad, e-posta ve şifre gerekli']); exit; }
    foreach ($users as $u) {
        if (strtolower($u['email']) === strtolower($email)) { http_response_code(409); echo json_encode(['error' => 'Bu e-posta zaten kayıtlı']); exit; }
    }
    $newUser = ['name' => $name, 'email' => $email, 'pass' => $pass, 'role' => $body['role'] ?? 'okur', 'joined' => $body['joined'] ?? date('Y-m-d')];
    $users[] = $newUser;
    $putData = ['message' => "Yeni üye: {$name}", 'content' => base64_encode(json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), 'committer' => ['name' => 'Akgül System', 'email' => 'akgulyayinevi@gmail.com']];
    if ($fileSha) $putData['sha'] = $fileSha;
    gh_put($apiUrl, $ghHeaders, $putData);
    unset($newUser['pass']);
    echo json_encode(['user' => $newUser], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Geçersiz action']);
