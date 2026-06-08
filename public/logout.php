<?php
if (!defined('CONFIG_INCLUDED')) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/autoload.php';
    define('CONFIG_INCLUDED', true);
}

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate($_POST['csrf_token'] ?? null)) {
    header('Location: /public/index.php');
    exit;
}

$user = new User();
$user->logout();

header('Location: /public/login.php');
exit;
