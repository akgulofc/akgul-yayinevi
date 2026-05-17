<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, x-admin-secret');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if (!$GITHUB_TOKEN) { http_response_code(500); echo json_encode(['error' => 'Sunucu hatası']); exit; }

$owner   = 'akgulofc';
$syncUrl  = "https://api.github.com/repos/{$owner}/{$PRIVATE_REPO_NAME}/contents/sync.json";
$usersUrl = "https://api.github.com/repos/{$owner}/{$PRIVATE_REPO_NAME}/contents/users.json";
$authUrl  = "https://api.github.com/repos/{$owner}/{$PRIVATE_REPO_NAME}/contents/authors.json";
$ghHdrs  = [
    "Authorization: token {$GITHUB_TOKEN}",
    "Accept: application/vnd.github.v3+json",
    "Content-Type: application/json",
    "User-Agent: akgul-admin-bot",
];

function gh_get($url, $hdrs) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $hdrs]);
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code' => $code, 'body' => $res];
}
function gh_put($url, $hdrs, $data) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => $hdrs, CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE)]);
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code' => $code, 'body' => $res];
}

function loadSync($url, $hdrs) {
    $r = gh_get($url, $hdrs);
    if ($r['code'] !== 200) {
        return [['orders'=>[],'cekilis'=>null,'askida'=>null,'settings'=>[],'editorPick'=>[],'content'=>[],'specialPages'=>[]], null];
    }
    $d    = json_decode($r['body'], true);
    $data = json_decode(base64_decode(str_replace("\n", '', $d['content'] ?? '')), true);
    if (!is_array($data)) $data = [];
    if (!isset($data['orders']))       $data['orders']       = [];
    if (!isset($data['cekilis']))      $data['cekilis']      = null;
    if (!isset($data['askida']))       $data['askida']       = null;
    if (!isset($data['settings']))     $data['settings']     = [];
    if (!isset($data['editorPick']))   $data['editorPick']   = [];
    if (!isset($data['content']))      $data['content']      = [];
    if (!isset($data['specialPages'])) $data['specialPages'] = [];
    return [$data, $d['sha'] ?? null];
}

function saveSync($url, $hdrs, $data, $sha, $msg) {
    $p = [
        'message'   => $msg,
        'content'   => base64_encode(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
        'committer' => ['name' => 'Akgül System', 'email' => 'akgulyayinevi@gmail.com'],
    ];
    if ($sha) $p['sha'] = $sha;
    $r = gh_put($url, $hdrs, $p);
    return $r['code'] >= 200 && $r['code'] < 300;
}

function verifyToken($email, $token, $secret) {
    foreach ([0, -1] as $d) {
        $date = date('Y-m-d', strtotime("{$d} days"));
        if (hash_equals(hash('sha256', strtolower($email) . '|' . $date . '|' . $secret), $token)) return true;
    }
    return false;
}

function verifyUser($email, $usersUrl, $authUrl, $hdrs) {
    $r = gh_get($usersUrl, $hdrs);
    if ($r['code'] === 200) {
        $d = json_decode($r['body'], true);
        $users = json_decode(base64_decode(str_replace("\n", '', $d['content'] ?? '')), true) ?: [];
        foreach ($users as $u) { if (strtolower($u['email'] ?? '') === $email) return true; }
    }
    $r = gh_get($authUrl, $hdrs);
    if ($r['code'] === 200) {
        $d = json_decode($r['body'], true);
        $authors = json_decode(base64_decode(str_replace("\n", '', $d['content'] ?? '')), true) ?: [];
        foreach ($authors as $a) { if (strtolower($a['membership']['email'] ?? '') === $email) return true; }
    }
    return false;
}

/* ── GET: tüm sync verisi ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    [$sync] = loadSync($syncUrl, $ghHdrs);
    echo json_encode($sync, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['action'])) { http_response_code(400); echo json_encode(['error' => 'Geçersiz istek']); exit; }

$action      = $body['action'];
$adminSecret = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
$isAdmin     = $ADMIN_SECRET && $adminSecret === $ADMIN_SECRET;

/* ── add_order: sipariş kaydet (auth gerekmez) ── */
if ($action === 'add_order') {
    $order = $body['order'] ?? null;
    if (!$order || !($order['id'] ?? '')) { http_response_code(400); echo json_encode(['error' => 'Sipariş verisi eksik']); exit; }
    [$sync, $sha] = loadSync($syncUrl, $ghHdrs);
    array_unshift($sync['orders'], $order);
    if (count($sync['orders']) > 500) $sync['orders'] = array_slice($sync['orders'], 0, 500);
    if (!saveSync($syncUrl, $ghHdrs, $sync, $sha, "Sipariş: {$order['id']}")) {
        http_response_code(500); echo json_encode(['error' => 'Kaydetme hatası']); exit;
    }
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── join_cekilis: çekilişe katıl (token auth) ── */
if ($action === 'join_cekilis') {
    $email       = strtolower(trim($body['email'] ?? ''));
    $token       = trim($body['token'] ?? '');
    $participant = $body['participant'] ?? null;
    if (!$email || !$token || !$participant) { http_response_code(400); echo json_encode(['error' => 'Eksik parametre']); exit; }
    if (!verifyToken($email, $token, $ADMIN_SECRET)) { http_response_code(401); echo json_encode(['error' => 'Oturum süresi doldu']); exit; }
    [$sync, $sha] = loadSync($syncUrl, $ghHdrs);
    if (!$sync['cekilis']) $sync['cekilis'] = ['participants' => []];
    if (!isset($sync['cekilis']['participants'])) $sync['cekilis']['participants'] = [];
    foreach ($sync['cekilis']['participants'] as $p) {
        if (strtolower($p['email'] ?? '') === $email) {
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE); exit;
        }
    }
    $sync['cekilis']['participants'][] = [
        'email'    => $email,
        'name'     => substr(strip_tags($participant['name'] ?? ''), 0, 100),
        'igUser'   => substr(strip_tags($participant['igUser'] ?? ''), 0, 60),
        'joinedAt' => date('c'),
    ];
    if (!saveSync($syncUrl, $ghHdrs, $sync, $sha, "Çekiliş katılım: {$email}")) {
        http_response_code(500); echo json_encode(['error' => 'Kaydetme hatası']); exit;
    }
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── claim_askida: askıda kitap talep et (token auth) ── */
if ($action === 'claim_askida') {
    $email  = strtolower(trim($body['email'] ?? ''));
    $token  = trim($body['token'] ?? '');
    $itemId = trim($body['itemId'] ?? '');
    if (!$email || !$token || !$itemId) { http_response_code(400); echo json_encode(['error' => 'Eksik parametre']); exit; }
    if (!verifyToken($email, $token, $ADMIN_SECRET)) { http_response_code(401); echo json_encode(['error' => 'Oturum süresi doldu']); exit; }
    [$sync, $sha] = loadSync($syncUrl, $ghHdrs);
    if ($sync['askida'] && isset($sync['askida']['items'])) {
        foreach ($sync['askida']['items'] as &$item) {
            if (($item['id'] ?? '') === $itemId && ($item['status'] ?? '') === 'available') {
                $item['status']    = 'claimed';
                $item['claimedAt'] = date('j.n.Y');
                $item['claimedBy'] = $email;
                break;
            }
        }
        unset($item);
    }
    if (!saveSync($syncUrl, $ghHdrs, $sync, $sha, "Askıda talep: {$itemId}")) {
        http_response_code(500); echo json_encode(['error' => 'Kaydetme hatası']); exit;
    }
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── admin_save: admin herhangi bir veriyi kaydeder ── */
if (!$isAdmin) { http_response_code(401); echo json_encode(['error' => 'Yetkisiz']); exit; }

if ($action === 'admin_save') {
    $key   = $body['key']   ?? '';
    $value = $body['value'] ?? null;
    $allowed = ['orders', 'cekilis', 'askida', 'settings', 'editorPick', 'content', 'specialPages', 'pressProcess'];
    if (!in_array($key, $allowed, true)) { http_response_code(400); echo json_encode(['error' => 'Geçersiz key']); exit; }
    [$sync, $sha] = loadSync($syncUrl, $ghHdrs);
    $sync[$key] = $value;
    if (!saveSync($syncUrl, $ghHdrs, $sync, $sha, "Admin: {$key} güncellendi")) {
        http_response_code(500); echo json_encode(['error' => 'Kaydetme hatası']); exit;
    }
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400); echo json_encode(['error' => 'Geçersiz action']);
