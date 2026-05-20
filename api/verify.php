<?php
require_once __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? '');
if (!$token) { header('Location: https://akgulyayinevi.com/#auth?err=invalid'); exit; }

/* ── GitHub'dan users.json oku ── */
$owner   = 'akgulofc';
$apiUrl  = "https://api.github.com/repos/{$owner}/{$PRIVATE_REPO_NAME}/contents/users.json";
$ghHdrs  = [
    "Authorization: token {$GITHUB_TOKEN}",
    "Accept: application/vnd.github.v3+json",
    "Content-Type: application/json",
    "User-Agent: akgul-admin-bot",
];

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $ghHdrs]);
$res = curl_exec($ch);
$cod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($cod !== 200) { header('Location: https://akgulyayinevi.com/#auth?err=server'); exit; }

$file  = json_decode($res, true);
$sha   = $file['sha'];
$users = json_decode(base64_decode(str_replace("\n", '', $file['content'] ?? '')), true);
if (!is_array($users)) { header('Location: https://akgulyayinevi.com/#auth?err=server'); exit; }

/* ── Token'ı bul ── */
$found = false;
foreach ($users as &$u) {
    if (($u['verifyToken'] ?? '') === $token) {
        // Token süresi: 24 saat
        if (isset($u['verifyExpiry']) && time() > $u['verifyExpiry']) {
            header('Location: https://akgulyayinevi.com/#auth?err=expired'); exit;
        }
        if ($u['verified'] ?? false) {
            $found = true; break; // Zaten doğrulanmış, başarı sayfasına yönlendir
        }
        $u['verified']    = true;
        $u['verifyToken'] = null;
        $u['verifyExpiry']= null;
        $found = true;
        break;
    }
}
unset($u);

if (!$found) { header('Location: https://akgulyayinevi.com/#auth?err=invalid'); exit; }

/* ── Güncellenmiş users.json'ı GitHub'a kaydet ── */
$payload = json_encode([
    'message' => 'verify: email doğrulandı',
    'content' => base64_encode(json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)),
    'sha'     => $sha,
], JSON_UNESCAPED_UNICODE);

$ch2 = curl_init($apiUrl);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PUT',
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => $ghHdrs,
]);
curl_exec($ch2);
curl_close($ch2);

/* ── Başarı: kullanıcıyı giriş sayfasına yönlendir ── */
header('Location: https://akgulyayinevi.com/#auth?verified=1');
exit;
