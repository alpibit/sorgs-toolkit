<?php
class TelegramNotifier
{
    private $botToken;
    private $defaultChatId;
    private $apiUrl = 'https://api.telegram.org/bot';

    public function __construct($botToken = null, $defaultChatId = null)
    {
        if (!$botToken) {
            $db = new Database();
            $conn = $db->connect();
            $stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'telegram_bot_token'");
            $this->botToken = $stmt->fetchColumn();

            $stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'telegram_default_chat_id'");
            $this->defaultChatId = $stmt->fetchColumn();
        } else {
            $this->botToken = $botToken;
            $this->defaultChatId = $defaultChatId;
        }

        // Debug log the configuration
        error_log("TelegramNotifier initialized with bot token: " . ($this->botToken ? 'Set' : 'Not set'));
        error_log("TelegramNotifier default chat ID: " . ($this->defaultChatId ?: 'Not set'));
    }

    public function sendMessage($message, $chatId = null)
    {
        if (!$this->botToken) {
            error_log("Telegram notification failed: Bot token not configured");
            return false;
        }

        $chatId = $chatId ?: $this->defaultChatId;
        if (!$chatId) {
            error_log("Telegram notification failed: No chat ID provided");
            return false;
        }

        $url = $this->apiUrl . $this->botToken . '/sendMessage';
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        // Debug log the request
        error_log("Sending Telegram notification to URL: " . $url);
        error_log("Telegram message data: " . print_r($data, true));

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        // Get the response headers to check HTTP status
        $responseHeaders = $http_response_header ?? [];
        $httpStatus = $this->getHttpStatusCode($responseHeaders);

        if ($result === false) {
            $error = error_get_last();
            error_log("Telegram API request failed: " . ($error['message'] ?? 'Unknown error'));
            error_log("HTTP Status: " . $httpStatus);
            return false;
        }

        // Decode and log the response
        $response = json_decode($result, true);
        if (!$response || !isset($response['ok'])) {
            error_log("Invalid Telegram API response: " . $result);
            return false;
        }

        if (!$response['ok']) {
            error_log("Telegram API error: " . ($response['description'] ?? 'Unknown error'));
            error_log("Error code: " . ($response['error_code'] ?? 'None'));
            return false;
        }

        error_log("Telegram notification sent successfully");
        return true;
    }

    private function getHttpStatusCode($headers)
    {
        foreach ($headers as $header) {
            if (preg_match('#HTTP/[0-9\.]+\s+([0-9]+)#', $header, $matches)) {
                return intval($matches[1]);
            }
        }
        return 0;
    }

    public function testConnection()
    {
        if (!$this->botToken || !$this->defaultChatId) {
            error_log("Cannot test Telegram connection: Missing configuration");
            return false;
        }

        $testMessage = "🔔 Test notification from " . APP_NAME . " Uptime Monitor";
        return $this->sendMessage($testMessage);
    }
}
