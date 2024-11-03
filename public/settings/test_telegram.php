<?php
if (!defined('CONFIG_INCLUDED')) {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/autoload.php';
    define('CONFIG_INCLUDED', true);
}

// Prevent caching
header('Cache-Control: no-cache, must-revalidate');
header('Content-Type: application/json');

// Start session and check authentication
session_start();

$user = new User();
if (!$user->isLoggedIn() || !$user->isAdmin()) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

try {
    // Get bot token from settings
    $db = new Database();
    $conn = $db->connect();
    $stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'telegram_bot_token'");
    $botToken = $stmt->fetchColumn();

    $stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'telegram_default_chat_id'");
    $defaultChatId = $stmt->fetchColumn();

    if (empty($botToken)) {
        echo json_encode([
            'success' => false,
            'error' => 'Telegram bot token is not configured'
        ]);
        exit;
    }

    if (empty($defaultChatId)) {
        echo json_encode([
            'success' => false,
            'error' => 'Default chat ID is not configured'
        ]);
        exit;
    }

    $telegram = new TelegramNotifier();
    $testMessage = "🔔 Test notification from " . APP_NAME . " Uptime Monitor\n" .
        "Time: " . date('Y-m-d H:i:s') . "\n" .
        "If you see this message, your Telegram notifications are working correctly!";

    $result = $telegram->sendMessage($testMessage, $defaultChatId);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Test message sent successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to send test message'
        ]);
    }
} catch (Exception $e) {
    error_log("Telegram test error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
