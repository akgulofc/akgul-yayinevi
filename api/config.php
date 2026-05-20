<?php
// akgul_config.php'yi olası konumlarda ara
$_candidates = [
    dirname(__DIR__, 2) . '/akgul_config.php', // public_html'nin üstü
    dirname(__DIR__, 3) . '/akgul_config.php', // 2 üst
    dirname(__DIR__)    . '/akgul_config.php', // public_html içi
];
foreach ($_candidates as $_p) {
    if (is_file($_p)) {
        require_once $_p;
        return;
    }
}
// Hiçbiri bulunamazsa boş default
$GROQ_API_KEY      = '';
$GITHUB_TOKEN      = '';
$ADMIN_SECRET      = ''; // akgul_config.php'de tanımlanmalı
$PRIVATE_REPO_NAME = 'akgul-data';

// iyzico — akgul_config.php'de tanımlı değilse placeholder
if (!defined('IYZICO_API_KEY'))    define('IYZICO_API_KEY',    '');
if (!defined('IYZICO_SECRET_KEY')) define('IYZICO_SECRET_KEY', '');
if (!defined('IYZICO_SANDBOX'))    define('IYZICO_SANDBOX',    true);

// SMTP — akgul_config.php'de tanımlanmalı; buradaki değerler boş kalır
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', '');
    define('SMTP_PORT', 465);
    define('SMTP_USER', '');
    define('SMTP_PASS', '');
    define('SMTP_FROM', '');
    define('SMTP_NAME', 'Akgül Yayınevi');
}
