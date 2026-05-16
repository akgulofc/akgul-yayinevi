<?php
// Gerçek config dosyasını public_html'nin bir üst dizininde ara
// Örnek yol: /domains/akgulyayinevi.com/akgul_config.php
$_cfg = dirname(__DIR__, 2) . '/akgul_config.php';
if (is_file($_cfg)) {
    require_once $_cfg;
    return;
}
// Yedek (local geliştirme / config bulunamazsa)
$GROQ_API_KEY      = '';
$GITHUB_TOKEN      = '';
$ADMIN_SECRET      = 'akgul2026';
$PRIVATE_REPO_NAME = 'akgul-data';
