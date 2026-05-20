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
$ADMIN_SECRET      = 'akgul2026';
$PRIVATE_REPO_NAME = 'akgul-data';

// SMTP — akgul_config.php'de tanımlı değilse buradaki değerler kullanılır
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.hostinger.com');
    define('SMTP_PORT', 465);
    define('SMTP_USER', 'bilgi@akgulyayinevi.com');
    define('SMTP_PASS', '814135Aa+');
    define('SMTP_FROM', 'bilgi@akgulyayinevi.com');
    define('SMTP_NAME', 'Akgül Yayınevi');
}
