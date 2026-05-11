<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }

$apiKey = getenv('GROQ_API_KEY');
if (!$apiKey) { http_response_code(500); echo json_encode(['error' => 'GROQ_API_KEY eksik']); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['error' => 'Geçersiz istek']); exit; }

$messages = $body['messages'] ?? [];
$data     = $body['data']     ?? [];

$storeContext = isset($data['books'])
    ? "Mevcut kitaplar (" . count($data['books']) . " adet): " . json_encode(array_slice($data['books'], 0, 30), JSON_UNESCAPED_UNICODE) .
      "\nYazarlar: " . json_encode(array_slice($data['authors'] ?? [], 0, 10), JSON_UNESCAPED_UNICODE) .
      "\nBlog yazıları: " . json_encode(array_slice($data['blog'] ?? [], 0, 5), JSON_UNESCAPED_UNICODE)
    : '';

$systemPrompt = "Sen Akgül Yayınevi'nin samimi ve bilgili kitap asistanısın. Müşterilere kitap önerisi yapar, yayınevi hakkında bilgi verirsin. Kısa ve sıcak yanıtlar ver, Türkçe konuş.\n\n{$storeContext}";

$payload = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'messages'    => array_merge([['role' => 'system', 'content' => $systemPrompt]], array_slice($messages, -10)),
    'temperature' => 0.7,
    'max_tokens'  => 512,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', "Authorization: Bearer {$apiKey}"],
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$raw = json_decode($res, true);
if ($code !== 200) { http_response_code(502); echo json_encode(['error' => $raw['error']['message'] ?? 'Groq hatası']); exit; }

echo json_encode(['reply' => $raw['choices'][0]['message']['content']], JSON_UNESCAPED_UNICODE);
