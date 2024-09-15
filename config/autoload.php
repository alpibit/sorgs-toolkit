<?php

spl_autoload_register(function ($className) {
    $baseDir = __DIR__ . '/../classes/';
    $file = $baseDir . str_replace('\\', '/', $className) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
