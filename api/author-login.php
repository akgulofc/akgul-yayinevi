<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }

if (!$GITHUB_TOKEN) { http_response_code(500); echo json_encode(['error' => 'Sunucu yapılandırma hatası']); exit; }

$body     = json_decode(file_get_contents('php://input'), true);
$email    = $body['email']    ?? '';
$password = $body['password'] ?? '';
if (!$email || !$password) { http_response_code(400); echo json_encode(['error' => 'E-posta ve şifre gerekli']); exit; }

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

if ($cod !== 200) { http_response_code(503); echo json_encode(['error' => 'Sunucu verisi okunamadı']); exit; }

$data    = json_decode($res, true);
$fileSha = $data['sha'];
$authors = json_decode(base64_decode(str_replace("\n", '', $data['content'])), true);
if (!$authors) { http_response_code(503); echo json_encode(['error' => 'Sunucu verisi okunamadı']); exit; }

$found = null;
$needsRehash = false;
foreach ($authors as &$a) {
    if (!isset($a['membership']['email'])) continue;
    if (strtolower($a['membership']['email']) !== strtolower($email)) continue;
    $stored = $a['membership']['password'] ?? '';
    // bcrypt hash
    if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
        if (password_verify($password, $stored)) { $found = &$a; break; }
    } else {
        // plain-text (legacy) — doğrula ve otomatik hash'le
        if ($stored === $password) {
            $found = &$a;
            $found['membership']['password'] = password_hash($password, PASSWORD_BCRYPT);
            $needsRehash = true;
            break;
        }
    }
}

if (!$found) { http_response_code(401); echo json_encode(['error' => 'E-posta veya şifre hatalı']); exit; }
if (($found['membership']['status'] ?? '') === 'suspended') { http_response_code(403); echo json_encode(['error' => 'Üyeliğiniz askıya alınmıştır. Yayınevi ile iletişime geçin.']); exit; }

$found['membership']['lastLogin'] = date('Y-m-d');

$putData = ['message' => "Yazar girişi: {$found['name']}", 'content' => base64_encode(json_encode($authors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), 'sha' => $fileSha, 'committer' => ['name' => 'Akgül System', 'email' => 'akgulyayinevi@gmail.com']];
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($putData, JSON_UNESCAPED_UNICODE)]);
curl_exec($ch);
curl_close($ch);

$safeMembership = $found['membership'];
unset($safeMembership['password']);
$token = hash('sha256', strtolower($email) . '|' . date('Y-m-d') . '|' . $ADMIN_SECRET);
echo json_encode(['author' => array_merge($found, ['membership' => $safeMembership]), 'token' => $token], JSON_UNESCAPED_UNICODE);
