<?php

spl_autoload_register(function ($className) {
    $directories = [
        __DIR__ . '/../classes/',
        __DIR__ . '/../classes/checks/'
    ];

    foreach ($directories as $directory) {
        $file = $directory . str_replace('\\', '/', $className) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
