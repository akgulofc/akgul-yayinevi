<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit; }

if (!defined('IYZICO_API_KEY') || !IYZICO_API_KEY) {
    http_response_code(500); echo json_encode(['error' => 'iyzico yapılandırılmamış']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['error' => 'Geçersiz istek']); exit; }

$order = $body['order'] ?? [];

$proto       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host        = $_SERVER['HTTP_HOST'] ?? 'akgulyayinevi.com';
$callbackUrl = $proto . '://' . $host . '/api/iyzico-callback.php';

$basketItems = [];
foreach ($order['items'] ?? [] as $item) {
    $basketItems[] = [
        'id'        => (string)($item['id'] ?? 'item'),
        'name'      => mb_substr($item['title'] ?? 'Kitap', 0, 100),
        'category1' => 'Kitap',
        'itemType'  => 'PHYSICAL',
        'price'     => number_format((float)($item['lineTotal'] ?? 0), 2, '.', ''),
    ];
}
if (($order['shipping'] ?? 0) > 0) {
    $basketItems[] = [
        'id'        => 'shipping',
        'name'      => 'Kargo',
        'category1' => 'Kargo',
        'itemType'  => 'VIRTUAL',
        'price'     => number_format((float)$order['shipping'], 2, '.', ''),
    ];
}

$total     = number_format((float)($order['total'] ?? 0), 2, '.', '');
$nameParts = explode(' ', trim($order['customer'] ?? 'Musteri'), 2);
$firstName = $nameParts[0];
$lastName  = $nameParts[1] ?? '-';
$ip        = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')[0];
$email     = !empty($order['email']) ? $order['email'] : 'musteri@akgulyayinevi.com';
$rawPhone  = preg_replace('/\D/', '', $order['phone'] ?? '5000000000');
if (strlen($rawPhone) === 10) $rawPhone = '90' . $rawPhone;
elseif (str_starts_with($rawPhone, '0')) $rawPhone = '9' . $rawPhone;
$phone     = '+' . $rawPhone;
$ordId     = $order['id'] ?? ('AK' . time());

$requestBody = [
    'locale'              => 'tr',
    'conversationId'      => $ordId,
    'price'               => $total,
    'paidPrice'           => $total,
    'currency'            => 'TRY',
    'basketId'            => $ordId,
    'paymentGroup'        => 'PRODUCT',
    'callbackUrl'         => $callbackUrl,
    'enabledInstallments' => [1, 2, 3, 6, 9, 12],
    'buyer' => [
        'id'                  => 'B' . preg_replace('/\D/', '', $order['phone'] ?? '1'),
        'name'                => $firstName,
        'surname'             => $lastName,
        'identityNumber'      => '11111111111',
        'email'               => $email,
        'gsmNumber'           => $phone,
        'registrationDate'    => date('Y-m-d H:i:s'),
        'lastLoginDate'       => date('Y-m-d H:i:s'),
        'registrationAddress' => mb_substr($order['address'] ?? 'Turkiye', 0, 200),
        'city'                => mb_substr($order['city'] ?? 'Istanbul', 0, 50),
        'country'             => 'Turkey',
        'ip'                  => $ip,
    ],
    'shippingAddress' => [
        'contactName' => mb_substr($order['customer'] ?? 'Musteri', 0, 60),
        'city'        => mb_substr($order['city'] ?? 'Istanbul', 0, 50),
        'country'     => 'Turkey',
        'address'     => mb_substr($order['address'] ?? 'Turkiye', 0, 200),
    ],
    'billingAddress' => [
        'contactName' => mb_substr($order['customer'] ?? 'Musteri', 0, 60),
        'city'        => mb_substr($order['city'] ?? 'Istanbul', 0, 50),
        'country'     => 'Turkey',
        'address'     => mb_substr($order['address'] ?? 'Turkiye', 0, 200),
    ],
    'basketItems' => $basketItems,
];

$bodyJson  = json_encode($requestBody, JSON_UNESCAPED_UNICODE);
$randomKey = uniqid('', true);
$hash      = base64_encode(hash('sha256', IYZICO_API_KEY . IYZICO_SECRET_KEY . $randomKey . $bodyJson, true));

$baseUrl = (defined('IYZICO_SANDBOX') && IYZICO_SANDBOX)
    ? 'https://sandbox-api.iyzipay.com'
    : 'https://api.iyzipay.com';

$ch = curl_init($baseUrl . '/payment/iyzipos/checkoutform/initialize/auth/ecom');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $bodyJson,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "Authorization: IYZWSv2 hash={$hash},randomkey={$randomKey}",
        'x-iyzi-rnd: ' . $randomKey,
        'x-iyzi-client-version: iyzipay-php-2.x.x',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 30,
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($res, true);
if ($code !== 200 || ($result['status'] ?? '') !== 'success') {
    http_response_code(502);
    echo json_encode(['error' => $result['errorMessage'] ?? 'iyzico ödeme başlatılamadı']);
    exit;
}

echo json_encode([
    'checkoutFormContent' => $result['checkoutFormContent'],
    'token'               => $result['token'],
], JSON_UNESCAPED_UNICODE);
