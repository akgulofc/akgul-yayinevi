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
