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

$mood  = $body['mood']  ?? '';
$books = $body['books'] ?? [];

$prompt = "Kullanıcının ruh hali: \"{$mood}\". Aşağıdaki kitap kataloğundan bu ruh haline en uygun 4-6 kitabı seç. Her kitap için kısa ve samimi bir Türkçe neden yaz (1-2 cümle). Yanıtı SADECE geçerli JSON olarak ver:\n{\"recommendations\":[{\"id\":KITAP_ID,\"reason\":\"neden\"}]}\n\nKatalog:\n" . json_encode($books, JSON_UNESCAPED_UNICODE);

$payload = json_encode([
    'model'           => 'llama-3.3-70b-versatile',
    'messages'        => [
        ['role' => 'system', 'content' => "Sen Akgül Yayınevi'nin kitap öneri asistanısın. Sadece JSON formatında yanıt ver."],
        ['role' => 'user',   'content' => $prompt],
    ],
    'response_format' => ['type' => 'json_object'],
    'temperature'     => 0.7,
    'max_tokens'      => 1024,
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

echo $raw['choices'][0]['message']['content'];
