<?php
require_once __DIR__ . '/config.php';

$token = $_POST['token'] ?? '';
if (!$token) {
    header('Location: /?payment=failed');
    exit;
}

if (!defined('IYZICO_API_KEY') || !IYZICO_API_KEY) {
    header('Location: /?payment=error');
    exit;
}

$requestBody = json_encode([
    'locale'         => 'tr',
    'conversationId' => 'cb-' . time(),
    'token'          => $token,
], JSON_UNESCAPED_UNICODE);

$randomKey = uniqid('', true);
$hash      = base64_encode(hash('sha256', IYZICO_API_KEY . IYZICO_SECRET_KEY . $randomKey . $requestBody, true));

$baseUrl = (defined('IYZICO_SANDBOX') && IYZICO_SANDBOX)
    ? 'https://sandbox-api.iyzipay.com'
    : 'https://api.iyzipay.com';

$ch = curl_init($baseUrl . '/payment/iyzipos/checkoutform/auth/ecom/detail');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestBody,
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

$result    = json_decode($res, true);
$payStatus = $result['paymentStatus'] ?? $result['status'] ?? '';
$orderId   = $result['basketId'] ?? '';

if ($payStatus === 'SUCCESS') {
    if ($orderId) {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'] ?? 'akgulyayinevi.com';
        $ch2   = curl_init($proto . '://' . $host . '/api/sync.php');
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'action' => 'update_order_status',
                'id'     => $orderId,
                'status' => 'Ödendi',
            ], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-admin-secret: ' . $ADMIN_SECRET,
            ],
            CURLOPT_TIMEOUT        => 10,
        ]);
        curl_exec($ch2);
        curl_close($ch2);
    }
    header('Location: /?payment=success&order=' . urlencode($orderId));
} else {
    header('Location: /?payment=failed&order=' . urlencode($orderId));
}
exit;
