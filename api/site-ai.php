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

$storeContext = isset($data['books'])
    ? "Mevcut kitaplar (" . count($data['books']) . " adet): " . json_encode(array_slice($data['books'], 0, 30), JSON_UNESCAPED_UNICODE) .
      "\nYazarlar: " . json_encode(array_slice($data['authors'] ?? [], 0, 10), JSON_UNESCAPED_UNICODE) .
      "\nBlog yazıları: " . json_encode(array_slice($data['blog'] ?? [], 0, 5), JSON_UNESCAPED_UNICODE)
    : '';

$faq = <<<'FAQ'

Sık sorulan sorular ve yanıtları:
- Kargo ne kadar sürer? → Ödeme onayından itibaren 3-7 iş günü içinde kargoya verilir.
- Kargo ücreti nedir? → 500 TL ve üzeri siparişlerde ücretsiz, altında 35 TL.
- Nasıl sipariş verebilirim? → Sepete ekleyip siparişi onaylayın, iyzico güvenli ödeme sayfasında kredi/banka kartıyla ödeme yapabilirsiniz.
- İade ve cayma hakkı? → Teslimattan itibaren 14 gün içinde gerekçesiz iade hakkınız var. Kargo ücreti tarafımızdan karşılanır.
- İmzalı kitap alabilir miyim? → Evet, hediye sepeti özelliğinde imzalı kopya seçeneği mevcuttur.
- Toplu sipariş yapabilir miyim? → Evet, 10+ adet için %10, 50+ adet için %15 indirim ve ücretsiz kargo uygulanır. Toplu sipariş formu sitemizdedir.
- Ödeme yöntemleri? → Banka havalesi/EFT ve iyzico üzerinden kredi/banka kartı.
- Sürpriz kitap kutusu nedir? → Ruh halinize göre seçilmiş, kraft ambalajlı, el yazısı not kartlı özel kitap paketi.
- Askıda kitap nedir? → Topluluk kitaplığı projesidir. Kitap bırakabilir veya talep edebilirsiniz.
- Yazar başvurusu nasıl yapılır? → "Eser Başvuru" sayfasından başvuru formunu doldurabilirsiniz.
- Adres ve iletişim? → Tavşantepe Mah. TOKİ FG/5 A Blok K:5 No:12, Adana. Tel: +90 536 648 30 96. E-posta: akgulyayinevi@gmail.com
FAQ;

$systemPrompt = "Sen Akgül Yayınevi'nin samimi ve bilgili kitap asistanısın. Müşterilere kitap önerisi yapar, yayınevi ve satış süreçleri hakkında bilgi verirsin. Kısa ve sıcak yanıtlar ver, Türkçe konuş.\n\n{$storeContext}\n\n{$faq}";

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
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', "Authorization: Bearer {$GROQ_API_KEY}"],
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$raw = json_decode($res, true);
if ($code !== 200) { http_response_code(502); echo json_encode(['error' => $raw['error']['message'] ?? 'Groq hatası']); exit; }

echo json_encode(['reply' => $raw['choices'][0]['message']['content']], JSON_UNESCAPED_UNICODE);
