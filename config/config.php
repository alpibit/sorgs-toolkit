<?php

if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $domain = $_SERVER['HTTP_HOST'];
    
    define('BASE_URL', $protocol . $domain . '/');
    
}

define('APP_NAME', 'Sorgs');
define('APP_VERSION', '0.1');
define('DEBUG_MODE', true);
define('ADMIN_EMAIL', ' [email protected]');