<?php
header('Content-Type: application/json');
$base = dirname(__DIR__, 2);
$p1   = $base . '/akgul_config.php';
$p2   = dirname(__DIR__, 3) . '/akgul_config.php';
$p3   = dirname(__DIR__)    . '/akgul_config.php';

echo json_encode([
    'base'         => $base,
    'p1_exists'    => is_file($p1),
    'p2_exists'    => is_file($p2),
    'p3_exists'    => is_file($p3),
    'open_basedir' => ini_get('open_basedir'),
]);
