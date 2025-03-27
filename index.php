<?php
// Check if database config exists, otherwise redirect to installer
if (!file_exists(__DIR__ . '/config/database.php')) {
    header('Location: install.php');
    exit;
}

require 'public/index.php';
?>