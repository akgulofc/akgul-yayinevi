<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, x-admin-secret');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if (!$GITHUB_TOKEN) { http_response_code(500); echo json_encode(['error' => 'Sunucu hatası']); exit; }

$owner    = 'akgulofc';
$blogUrl  = "https://api.github.com/repos/{$owner}/{$PRIVATE_REPO_NAME}/contents/blog.json";
$usersUrl = "https://api.github.com/repos/{$owner}/{$PRIVATE_REPO_NAME}/contents/users.json";
$authUrl  = "https://api.github.com/repos/{$owner}/{$PRIVATE_REPO_NAME}/contents/authors.json";
$ghHdrs   = [
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
function loadJson($url, $hdrs) {
    $r = gh_get($url, $hdrs);
    if ($r['code'] !== 200) return [[], null];
    $d = json_decode($r['body'], true);
    return [json_decode(base64_decode(str_replace("\n", '', $d['content'] ?? '')), true) ?: [], $d['sha'] ?? null];
}
function saveJson($url, $hdrs, $data, $sha, $msg) {
    $p = [
        'message'   => $msg,
        'content'   => base64_encode(json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
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

/* ── GET: return all posts ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    [$posts] = loadJson($blogUrl, $ghHdrs);
    echo json_encode(['posts' => $posts], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['action'])) { http_response_code(400); echo json_encode(['error' => 'Geçersiz istek']); exit; }

$action      = $body['action'];
$adminSecret = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
$isAdmin     = $ADMIN_SECRET && $adminSecret === $ADMIN_SECRET;

/* ── action=publish: yazar/kullanıcı yeni yazı gönderir ── */
if ($action === 'publish') {
    $email = strtolower(trim($body['email'] ?? ''));
    $token = trim($body['token'] ?? '');
    $post  = $body['post'] ?? null;
    if (!$email || !$token || !$post) { http_response_code(400); echo json_encode(['error' => 'Eksik parametre']); exit; }
    if (!verifyToken($email, $token, $ADMIN_SECRET)) {
        http_response_code(401); echo json_encode(['error' => 'Oturum süresi doldu, tekrar giriş yapın']); exit;
    }
    // Kullanıcı kimliği doğrula
    [$users]   = loadJson($usersUrl, $ghHdrs);
    [$authors] = loadJson($authUrl,  $ghHdrs);
    $ok = false;
    foreach ($users   as $u) { if (strtolower($u['email'] ?? '') === $email) { $ok = true; break; } }
    if (!$ok) foreach ($authors as $a) { if (strtolower($a['membership']['email'] ?? '') === $email) { $ok = true; break; } }
    if (!$ok) { http_response_code(403); echo json_encode(['error' => 'Kullanıcı bulunamadı']); exit; }

    $tz = new DateTimeZone('Europe/Istanbul');
    $safePost = [
        'id'       => (int)(microtime(true) * 1000),
        'title'    => substr(strip_tags($post['title']    ?? ''), 0, 200),
        'author'   => substr(strip_tags($post['author']   ?? ''), 0, 100),
        'initials' => substr(strip_tags($post['initials'] ?? ''), 0, 4),
        'color'    => preg_match('/^#[0-9a-fA-F]{3,8}$/', $post['color'] ?? '') ? $post['color'] : '#2D4A3E',
        'date'     => (new DateTime('now', $tz))->format('j F Y'),
        'cat'      => substr(strip_tags($post['cat']      ?? ''), 0, 60),
        'type'     => 'article',
        'excerpt'  => substr(strip_tags($post['excerpt']  ?? ''), 0, 300),
        'body'     => substr($post['body'] ?? '', 0, 60000),
        'bg'       => 'linear-gradient(135deg,#2D4A3E,#1a3a2e)',
        'images'   => [],
        'isManset' => false,
        'sonDakika'=> false,
    ];
    if (!$safePost['title']) { http_response_code(400); echo json_encode(['error' => 'Başlık boş olamaz']); exit; }

    [$posts, $sha] = loadJson($blogUrl, $ghHdrs);
    array_unshift($posts, $safePost);
    if (!saveJson($blogUrl, $ghHdrs, $posts, $sha, "Yeni yazı: {$safePost['title']}")) {
        http_response_code(500); echo json_encode(['error' => 'Kaydetme hatası']); exit;
    }
    echo json_encode(['success' => true, 'posts' => $posts], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Admin-only: secret header gerekli ── */
if (!$isAdmin) { http_response_code(401); echo json_encode(['error' => 'Yetkisiz']); exit; }
[$posts, $sha] = loadJson($blogUrl, $ghHdrs);

if ($action === 'admin_sync') {
    $newPosts = $body['posts'] ?? null;
    if (!is_array($newPosts)) { http_response_code(400); echo json_encode(['error' => 'posts array gerekli']); exit; }
    if (!saveJson($blogUrl, $ghHdrs, $newPosts, $sha, 'Admin: blog senkronize edildi')) {
        http_response_code(500); echo json_encode(['error' => 'Kaydetme hatası']); exit;
    }
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'admin_publish') {
    $post = $body['post'] ?? null;
    if (!$post || !($post['title'] ?? '')) { http_response_code(400); echo json_encode(['error' => 'Başlık gerekli']); exit; }
    if ($post['isManset'] ?? false) { foreach ($posts as &$p) $p['isManset'] = false; unset($p); }
    array_unshift($posts, $post);
    if (!saveJson($blogUrl, $ghHdrs, $posts, $sha, "Admin yazı: {$post['title']}")) {
        http_response_code(500); echo json_encode(['error' => 'Kaydetme hatası']); exit;
    }
    echo json_encode(['success' => true, 'posts' => $posts], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'admin_delete') {
    $id    = $body['id'] ?? null;
    $posts = array_values(array_filter($posts, fn($p) => $p['id'] != $id));
    if (!saveJson($blogUrl, $ghHdrs, $posts, $sha, "Silindi: {$id}")) {
        http_response_code(500); echo json_encode(['error' => 'Kaydetme hatası']); exit;
    }
    echo json_encode(['success' => true, 'posts' => $posts], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'admin_manset') {
    $id = $body['id'] ?? null;
    foreach ($posts as &$p) { $p['isManset'] = ($p['id'] == $id); } unset($p);
    if (!saveJson($blogUrl, $ghHdrs, $posts, $sha, "Manşet: {$id}")) {
        http_response_code(500); echo json_encode(['error' => 'Kaydetme hatası']); exit;
    }
    echo json_encode(['success' => true, 'posts' => $posts], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'admin_sondakika') {
    $id  = $body['id'] ?? null;
    $cur = false;
    foreach ($posts as $p) { if ($p['id'] == $id) { $cur = $p['sonDakika'] ?? false; break; } }
    foreach ($posts as &$p) { $p['sonDakika'] = ($p['id'] == $id) ? !$cur : false; } unset($p);
    if (!saveJson($blogUrl, $ghHdrs, $posts, $sha, "SonDakika: {$id}")) {
        http_response_code(500); echo json_encode(['error' => 'Kaydetme hatası']); exit;
    }
    echo json_encode(['success' => true, 'posts' => $posts], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400); echo json_encode(['error' => 'Geçersiz action']);
