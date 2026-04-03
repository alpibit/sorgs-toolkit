<?php

if (!function_exists('app_env_bool')) {
    function app_env_bool(string $name, bool $default = false): bool
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return $default;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('app_current_host')) {
    function app_current_host(): string
    {
        $host = '';

        if (!empty($_SERVER['HTTP_HOST'])) {
            $host = trim((string) $_SERVER['HTTP_HOST']);
        } elseif (!empty($_SERVER['SERVER_NAME'])) {
            $host = trim((string) $_SERVER['SERVER_NAME']);
        }

        if ($host === '') {
            return 'localhost';
        }

        return preg_replace('/:\d+$/', '', $host);
    }
}

if (!function_exists('app_debug_enabled')) {
    function app_debug_enabled(): bool
    {
        return defined('DEBUG_MODE') && DEBUG_MODE === true;
    }
}

if (!function_exists('app_log_debug')) {
    function app_log_debug(string $message): void
    {
        if (app_debug_enabled()) {
            error_log($message);
        }
    }
}

if (!function_exists('app_mask_value')) {
    function app_mask_value($value, int $visibleStart = 2, int $visibleEnd = 2): string
    {
        $string = trim((string) $value);

        if ($string === '') {
            return '[empty]';
        }

        $length = strlen($string);
        if ($length <= ($visibleStart + $visibleEnd)) {
            return str_repeat('*', max(1, $length));
        }

        return substr($string, 0, $visibleStart)
            . str_repeat('*', max(4, $length - ($visibleStart + $visibleEnd)))
            . substr($string, -$visibleEnd);
    }
}

if (!function_exists('app_mask_email')) {
    function app_mask_email($email): string
    {
        $email = trim((string) $email);

        if ($email === '' || strpos($email, '@') === false) {
            return app_mask_value($email, 1, 1);
        }

        [$localPart, $domainPart] = explode('@', $email, 2);

        return app_mask_value($localPart, 1, 1) . '@' . app_mask_value($domainPart, 1, 1);
    }
}

if (!defined('CLI_MODE')) {
    define('CLI_MODE', PHP_SAPI === 'cli');
}

if (!defined('BASE_URL')) {
    if (CLI_MODE) {
        define('BASE_URL', '/');
    } else {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        define('BASE_URL', $protocol . app_current_host() . '/');
    }
}

define('APP_NAME', 'Sorgs');
define('APP_VERSION', '0.1');
define('DEBUG_MODE', app_env_bool('APP_DEBUG', false));
define('ADMIN_EMAIL', ' [email protected]');