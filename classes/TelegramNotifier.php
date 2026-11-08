<?php
class TelegramNotifier
{
    private $botToken;
    private $defaultChatId;
    private $apiUrl = 'https://api.telegram.org/bot';
    private $lastError;

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

        $this->defaultChatId = trim((string) $this->defaultChatId);

        app_log_debug('Telegram notifier initialized.');
        if (!empty($this->defaultChatId)) {
            app_log_debug('Telegram notifier default chat configured: ' . app_mask_value($this->defaultChatId));
        }
    }

    public function sendMessage($message, $chatId = null)
    {
        $this->lastError = null;
        if (!$this->botToken) {
            $this->lastError = 'Telegram bot token is not configured.';
            error_log("Telegram notification failed: Bot token not configured");
            return false;
        }

        $chatId = trim((string) ($chatId ?? ''));
        $chatId = $chatId !== '' ? $chatId : $this->defaultChatId;
        if (!$chatId) {
            $this->lastError = 'No Telegram chat ID is configured.';
            error_log("Telegram notification failed: No chat ID provided");
            return false;
        }

        $url = $this->apiUrl . $this->botToken . '/sendMessage';
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        app_log_debug(
            'Sending Telegram notification to chat '
            . app_mask_value($chatId)
            . ' (message length: '
            . strlen($message)
            . ' bytes).'
        );

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
            $this->lastError = 'Could not reach Telegram. Check the server network connection and try again.';
            $error = error_get_last();
            error_log("Telegram API request failed: " . $this->redactToken($error['message'] ?? 'Unknown error'));
            error_log("HTTP Status: " . $httpStatus);
            return false;
        }

        // Decode and log the response
        $response = json_decode($result, true);
        if (!is_array($response) || !isset($response['ok']) || !is_bool($response['ok'])) {
            $this->lastError = 'Telegram returned an invalid response. Try again later.';
            error_log("Invalid Telegram API response: " . $this->redactToken($result));
            return false;
        }

        if (!$response['ok']) {
            $this->lastError = "Telegram API error: " . $this->redactToken($response['description'] ?? 'Unknown error');
            error_log($this->lastError);
            error_log("Error code: " . $this->redactToken($response['error_code'] ?? 'None'));
            return false;
        }

        app_log_debug('Telegram notification sent successfully to chat ' . app_mask_value($chatId));
        return true;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    private function redactToken($message)
    {
        $message = is_scalar($message) ? (string) $message : 'Invalid error details';

        return strtr($message, [
            $this->botToken => '[REDACTED]',
            rawurlencode($this->botToken) => '[REDACTED]',
            urlencode($this->botToken) => '[REDACTED]'
        ]);
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
            $this->lastError = 'Telegram bot token and default chat ID must be configured.';
            error_log("Cannot test Telegram connection: Missing configuration");
            return false;
        }

        $testMessage = "🔔 Test notification from " . APP_NAME . " Uptime Monitor";
        return $this->sendMessage(htmlspecialchars($testMessage, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    /**
     * Check if a bot token is configured
     * 
     * @return bool True if the bot token is configured, false otherwise
     */
    public function isBotTokenConfigured()
    {
        return !empty($this->botToken) && $this->botToken !== 'test';
    }
}
