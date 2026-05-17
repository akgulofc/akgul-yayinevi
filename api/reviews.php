<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, x-admin-secret');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if (!$GITHUB_TOKEN) { http_response_code(500); echo json_encode(['error' => 'Sunucu hatası']); exit; }

$owner      = 'akgulofc';
$reviewsUrl = "https://api.github.com/repos/{$owner}/{$PRIVATE_REPO_NAME}/contents/reviews.json";
$usersUrl   = "https://api.github.com/repos/{$owner}/{$PRIVATE_REPO_NAME}/contents/users.json";
$authUrl    = "https://api.github.com/repos/{$owner}/{$PRIVATE_REPO_NAME}/contents/authors.json";
$ghHdrs     = [
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
function loadReviewsFile($url, $hdrs) {
    $r = gh_get($url, $hdrs);
    if ($r['code'] !== 200) return [['books' => new stdClass(), 'box' => [], 'ai' => [], 'home' => []], null];
    $d    = json_decode($r['body'], true);
    $data = json_decode(base64_decode(str_replace("\n", '', $d['content'] ?? '')), true);
    if (!is_array($data)) $data = [];
    if (!isset($data['books']))   $data['books']   = new stdClass();
    if (!isset($data['box']))     $data['box']     = [];
    if (!isset($data['ai']))      $data['ai']      = [];
    if (!isset($data['home']))    $data['home']    = [];
    if (!isset($data['gift']))    $data['gift']    = [];
    if (!isset($data['askida']))  $data['askida']  = [];
    if (!isset($data['cekilis'])) $data['cekilis'] = [];
    return [$data, $d['sha'] ?? null];
}
function saveReviewsFile($url, $hdrs, $data, $sha, $msg) {
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

/* ── GET: tüm yorumlar ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    [$reviews] = loadReviewsFile($reviewsUrl, $ghHdrs);
    echo json_encode($reviews, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['action'])) { http_response_code(400); echo json_encode(['error' => 'Geçersiz istek']); exit; }

$action      = $body['action'];
$adminSecret = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
$isAdmin     = $ADMIN_SECRET && $adminSecret === $ADMIN_SECRET;

/* ── add: kullanıcı yorum ekler ── */
if ($action === 'add') {
    $email  = strtolower(trim($body['email'] ?? ''));
    $token  = trim($body['token'] ?? '');
    $type   = $body['type'] ?? '';
    $review = $body['review'] ?? null;

    if (!$email || !$token || !$type || !$review) {
        http_response_code(400); echo json_encode(['error' => 'Eksik parametre']); exit;
    }
    if (!in_array($type, ['book', 'box', 'ai', 'home', 'gift', 'askida', 'cekilis'], true)) {
        http_response_code(400); echo json_encode(['error' => 'Geçersiz type']); exit;
    }
    if (!verifyToken($email, $token, $ADMIN_SECRET)) {
        http_response_code(401); echo json_encode(['error' => 'Oturum süresi doldu']); exit;
    }

    // Kullanıcıyı doğrula
    $ok = false;
    $r  = gh_get($usersUrl, $ghHdrs);
    if ($r['code'] === 200) {
        $d     = json_decode($r['body'], true);
        $users = json_decode(base64_decode(str_replace("\n", '', $d['content'] ?? '')), true) ?: [];
        foreach ($users as $u) { if (strtolower($u['email'] ?? '') === $email) { $ok = true; break; } }
    }
    if (!$ok) {
        $r = gh_get($authUrl, $ghHdrs);
        if ($r['code'] === 200) {
            $d       = json_decode($r['body'], true);
            $authors = json_decode(base64_decode(str_replace("\n", '', $d['content'] ?? '')), true) ?: [];
            foreach ($authors as $a) { if (strtolower($a['membership']['email'] ?? '') === $email) { $ok = true; break; } }
        }
    }
    if (!$ok) { http_response_code(403); echo json_encode(['error' => 'Kullanıcı bulunamadı']); exit; }

    $safeReview = [
        'u' => substr(strip_tags($review['u'] ?? ''), 0, 80),
        's' => max(1, min(5, (int)($review['s'] ?? 5))),
        't' => substr(strip_tags($review['t'] ?? ''), 0, 500),
        'd' => date('j.n.Y'),
    ];
    if ($type === 'box' && isset($review['mood'])) {
        $safeReview['mood'] = substr(strip_tags($review['mood']), 0, 40);
    }
    if (!$safeReview['t']) { http_response_code(400); echo json_encode(['error' => 'Yorum boş olamaz']); exit; }

    [$reviews, $sha] = loadReviewsFile($reviewsUrl, $ghHdrs);

    if ($type === 'book') {
        $bookId = (string)($body['bookId'] ?? '');
        if (!$bookId) { http_response_code(400); echo json_encode(['error' => 'bookId gerekli']); exit; }
        if (!isset($reviews['books'][$bookId])) $reviews['books'][$bookId] = [];
        array_unshift($reviews['books'][$bookId], $safeReview);
    } else {
        array_unshift($reviews[$type], $safeReview);
    }

    if (!saveReviewsFile($reviewsUrl, $ghHdrs, $reviews, $sha, "Yorum ({$type})")) {
        http_response_code(500); echo json_encode(['error' => 'Kaydetme hatası']); exit;
    }
    echo json_encode(['success' => true, 'reviews' => $reviews], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Admin: silme ── */
if (!$isAdmin) { http_response_code(401); echo json_encode(['error' => 'Yetkisiz']); exit; }

[$reviews, $sha] = loadReviewsFile($reviewsUrl, $ghHdrs);

if ($action === 'admin_delete') {
    $type   = $body['type']   ?? '';
    $index  = (int)($body['index'] ?? -1);
    $bookId = (string)($body['bookId'] ?? '');

    if ($type === 'book' && $bookId) {
        if (isset($reviews['books'][$bookId][$index])) {
            array_splice($reviews['books'][$bookId], $index, 1);
            if (empty($reviews['books'][$bookId])) unset($reviews['books'][$bookId]);
        }
    } elseif (in_array($type, ['box', 'ai', 'home', 'gift', 'askida', 'cekilis'], true)) {
        if (isset($reviews[$type][$index])) array_splice($reviews[$type], $index, 1);
    }

    if (!saveReviewsFile($reviewsUrl, $ghHdrs, $reviews, $sha, "Yorum silindi ({$type})")) {
        http_response_code(500); echo json_encode(['error' => 'Kaydetme hatası']); exit;
    }
    echo json_encode(['success' => true, 'reviews' => $reviews], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400); echo json_encode(['error' => 'Geçersiz action']);
