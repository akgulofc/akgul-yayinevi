<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }

if (!$GROQ_API_KEY) { http_response_code(500); echo json_encode(['error' => 'GROQ_API_KEY eksik']); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['error' => 'Geçersiz istek']); exit; }

$messages = $body['messages'] ?? [];
$data     = $body['data']     ?? [];

$storeContext = "Kitaplar: " . json_encode(array_slice($data['books'] ?? [], 0, 40), JSON_UNESCAPED_UNICODE) .
    "\nSiparişler: " . json_encode(array_slice($data['orders'] ?? [], 0, 20), JSON_UNESCAPED_UNICODE) .
    "\nBlog yazıları: " . json_encode(array_slice($data['blogPosts'] ?? [], 0, 20), JSON_UNESCAPED_UNICODE) .
    "\nYazarlar: " . json_encode(array_slice($data['authors'] ?? [], 0, 20), JSON_UNESCAPED_UNICODE) .
    "\nKullanıcılar: " . json_encode(array_slice($data['users'] ?? [], 0, 20), JSON_UNESCAPED_UNICODE);

$systemPrompt = "Sen Akgül Yayınevi'nin admin asistanısın. Kitap, sipariş, blog ve yazar yönetimi konusunda yardım edersin.

Eğer kullanıcı bir işlem yapmamı isterse, yanıtını MUTLAKA şu JSON formatında ver:
{\"reply\":\"Kullanıcıya mesaj\",\"actions\":[{\"tool\":\"araç_adı\",\"params\":{...}}]}

Kullanılabilir araçlar:
- add_book: {title, author, price, cat, badge, desc}
- update_book: {id, title?, author?, price?, desc?, badge?}
- delete_book: {id}
- update_order_status: {id, status} — geçerli durumlar: Beklemede, Ödendi, Hazırlanıyor, Kargoda, Tamamlandı, İptal
- bulk_update_orders: {from_status, to_status} — aynı geçerli durumlar
- add_blog_post: {title, author, cat, status, content}
- navigate_to: {page} (p-dash/p-books/p-orders/p-users/p-blog/p-reviews/p-authors/p-settings/p-analytics/p-press/p-basvurular/p-askida/p-cekilis)

Eğer sadece bilgi veriyorsan düz metin yanıt ver. Türkçe konuş, kısa ve net ol.

Mevcut veriler:{$storeContext}";

$payload = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'messages'    => array_merge([['role' => 'system', 'content' => $systemPrompt]], array_slice($messages, -10)),
    'temperature' => 0.4,
    'max_tokens'  => 1024,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', "Authorization: Bearer {$GROQ_API_KEY}"],
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$raw = json_decode($res, true);
if ($code !== 200) { http_response_code(502); echo json_encode(['error' => $raw['error']['message'] ?? 'Groq hatası']); exit; }

$content = trim($raw['choices'][0]['message']['content']);
$reply   = $content;
$actions = [];

$jsonMatch = [];
if (preg_match('/\{[\s\S]*\}/', $content, $jsonMatch)) {
    $parsed = json_decode($jsonMatch[0], true);
    if ($parsed) {
        if (isset($parsed['reply']))   $reply   = $parsed['reply'];
        if (isset($parsed['actions'])) $actions = $parsed['actions'];
    }
}

echo json_encode(['reply' => $reply, 'actions' => $actions], JSON_UNESCAPED_UNICODE);
