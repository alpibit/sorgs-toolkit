<?php
if (!defined('CONFIG_INCLUDED')) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/autoload.php';
    define('CONFIG_INCLUDED', true);
}

session_start();

$user = new User();
$user->logout();

header('Location: ' . BASE_URL . '/public/login.php');
exit;
