<?php
class UptimeMonitor
{
    private $db;
    private $alertCooldownPeriod = 3600; // 1 hour

    public function __construct($db = null)
    {
        if ($db === null) {
            $db = new Database();
        }
        $this->db = $db->connect();
    }

    public function addMonitor($name, $url, $checkInterval, $expectedStatusCode = 200, $expectedKeyword = '', $notificationEmails = '', $telegramChatIds = '')
    {
        $sql = "INSERT INTO monitors (name, url, check_interval, expected_status_code, expected_keyword, notification_emails, telegram_chat_ids) 
                VALUES (:name, :url, :interval, :status_code, :keyword, :emails, :telegram_chat_ids)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':name' => $name,
            ':url' => $url,
            ':interval' => $checkInterval,
            ':status_code' => $expectedStatusCode,
            ':keyword' => $expectedKeyword,
            ':emails' => $notificationEmails,
            ':telegram_chat_ids' => $telegramChatIds
        ]);
    }

    public function updateMonitor($id, $name, $url, $checkInterval, $expectedStatusCode = 200, $expectedKeyword = '', $notificationEmails = '', $telegramChatIds = '')
    {
        $sql = "UPDATE monitors SET 
                name = :name, 
                url = :url, 
                check_interval = :interval, 
                expected_status_code = :status_code, 
                expected_keyword = :keyword, 
                notification_emails = :emails,
                telegram_chat_ids = :telegram_chat_ids 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':url' => $url,
            ':interval' => $checkInterval,
            ':status_code' => $expectedStatusCode,
            ':keyword' => $expectedKeyword,
            ':emails' => $notificationEmails,
            ':telegram_chat_ids' => $telegramChatIds
        ]);
    }

    public function deleteMonitor($id)
    {
        $sql = "DELETE FROM monitors WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function getMonitor($id)
    {
        $sql = "SELECT * FROM monitors WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllMonitors()
    {
        $sql = "SELECT * FROM monitors";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function checkDueMonitors()
    {
        $sql = "SELECT * FROM monitors WHERE last_check_time IS NULL OR last_check_time <= DATE_SUB(NOW(), INTERVAL check_interval SECOND)";
        $stmt = $this->db->query($sql);
        $monitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($monitors as $monitor) {
            $result = $this->checkSite($monitor);
            error_log("Checked {$monitor['name']}: {$result['status']} - {$result['message']}");

            if ($result['status'] === 'down') {
                $this->handleDowntime($monitor, $result);
            } else {
                $this->resetAlertStatus($monitor['id']);
            }
        }
    }

    public function checkSite($monitor, $retryAttempts = 3, $timeout = 15)
    {
        $attempts = 0;
        while ($attempts < $retryAttempts) {
            $attempts++;

            $ch = curl_init($monitor['url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_USERAGENT => 'Generic Uptime Monitor/1.0',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                    'Cache-Control: no-cache',
                    'Pragma: no-cache',
                    'X-Uptime-Check: true'
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
            ]);

            $response = curl_exec($ch);
            $info = curl_getinfo($ch);
            $error = curl_error($ch);
            curl_close($ch);

            $result = [
                'status' => 'up',
                'message' => 'Site is up and functioning correctly.',
                'http_code' => $info['http_code'],
                'response_time' => round($info['total_time'] * 1000, 2), // in milliseconds
                'download_size' => $info['size_download'],
                'error' => $error,
                'attempt' => $attempts
            ];

            // Check if the request was successful
            $isSuccess = true;
            $failureReason = '';

            if ($error) {
                $isSuccess = false;
                $failureReason = "CURL Error: $error";
            } elseif ($info['http_code'] != $monitor['expected_status_code']) {
                $isSuccess = false;
                $failureReason = "Unexpected HTTP status code: Expected {$monitor['expected_status_code']}, got {$info['http_code']}";
            } elseif (!empty($monitor['expected_keyword']) && strpos($response, $monitor['expected_keyword']) === false) {
                $isSuccess = false;
                $failureReason = "Expected keyword not found in the response";
            }

            // If successful, return immediately
            if ($isSuccess) {
                if ($attempts > 1) {
                    $result['message'] .= " (Succeeded after $attempts attempts)";
                }
                $this->updateMonitorStatus($monitor['id'], $result);
                return $result;
            }

            // If this was the last attempt, mark as down
            if ($attempts >= $retryAttempts) {
                $result['status'] = 'down';
                $result['message'] = $failureReason . " (Failed after $attempts attempts)";
                $this->updateMonitorStatus($monitor['id'], $result);
                return $result;
            }

            // Log retry attempt
            error_log("Monitor check failed for {$monitor['name']} (Attempt $attempts/$retryAttempts): $failureReason. Retrying...");

            // Wait briefly before retry (exponential backoff)
            $waitTime = pow(2, $attempts - 1) * 3000000; // Convert to microseconds
            usleep($waitTime);
        }

        // Fallback
        return $result;
    }

    private function updateMonitorStatus($monitorId, $result)
    {
        $sql = "UPDATE monitors SET 
                last_check_time = NOW(), 
                last_status = :status, 
                last_response_time = :response_time, 
                last_status_code = :http_code,
                last_error = :error
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $monitorId,
            ':status' => $result['status'],
            ':response_time' => $result['response_time'],
            ':http_code' => $result['http_code'],
            ':error' => $result['error']
        ]);
    }

    private function handleDowntime($monitor, $result)
    {
        $lastAlertTime = $this->getLastAlertTime($monitor['id']);
        $currentTime = time();

        if ($lastAlertTime === null || ($currentTime - $lastAlertTime) >= $this->alertCooldownPeriod) {
            $this->sendAlert($monitor, $result);
            $this->updateLastAlertTime($monitor['id'], $currentTime);
        } else {
            $nextAlertTime = $lastAlertTime + $this->alertCooldownPeriod;
            error_log("Alert for monitor '{$monitor['name']}' suppressed due to cooldown period. Next alert possible at: " . date('Y-m-d H:i:s', $nextAlertTime));
        }
    }

    private function getLastAlertTime($monitorId)
    {
        $sql = "SELECT last_alert_time FROM monitors WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $monitorId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['last_alert_time'] ? strtotime($result['last_alert_time']) : null;
    }

    private function updateLastAlertTime($monitorId, $time)
    {
        $sql = "UPDATE monitors SET last_alert_time = FROM_UNIXTIME(:time) WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $monitorId, ':time' => $time]);
    }

    private function resetAlertStatus($monitorId)
    {
        $sql = "UPDATE monitors SET last_alert_time = NULL WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $monitorId]);
    }

    public function sendAlert($monitor, $result)
    {
        $allSent = true;

        // Prepare alert message (used for both email and Telegram)
        $status = ucfirst($result['status']);
        $message = "🚨 Alert for {$monitor['name']}\n\n";
        $message .= "Status: $status\n";
        $message .= "URL: {$monitor['url']}\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";

        if (isset($result['http_code'])) {
            $message .= "HTTP Status: {$result['http_code']}\n";
        }

        if (isset($result['response_time'])) {
            $message .= "Response Time: {$result['response_time']}ms\n";
        }

        if (!empty($result['error'])) {
            $message .= "Error: {$result['error']}\n";
        }

        // Handle email notifications
        $adminEmail = $this->getAdminEmail();
        $notificationEmails = !empty($monitor['notification_emails']) ?
            array_filter(explode(' ', $monitor['notification_emails'])) : [];

        if ($adminEmail) {
            $notificationEmails[] = $adminEmail;
        }

        $uniqueEmails = array_unique(array_filter($notificationEmails));

        foreach ($uniqueEmails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                if (Email::sendAlert($monitor, $result, $email)) {
                    error_log("Email alert sent to $email for monitor '{$monitor['name']}'. URL: {$monitor['url']}");
                } else {
                    error_log("Failed to send email alert to $email for monitor '{$monitor['name']}'. URL: {$monitor['url']}");
                    $allSent = false;
                }
            } else {
                error_log("Invalid email address: $email. Skipping alert for monitor '{$monitor['name']}'.");
                $allSent = false;
            }
        }

        // Handle Telegram notifications
        if (!empty($monitor['telegram_chat_ids'])) {
            $telegram = new TelegramNotifier();
            $chatIds = array_filter(array_map('trim', explode(',', $monitor['telegram_chat_ids'])));

            foreach ($chatIds as $chatId) {
                if ($telegram->sendMessage($message, $chatId)) {
                    error_log("Telegram alert sent to chat ID $chatId for monitor '{$monitor['name']}'. URL: {$monitor['url']}");
                } else {
                    error_log("Failed to send Telegram alert to chat ID $chatId for monitor '{$monitor['name']}'. URL: {$monitor['url']}");
                    $allSent = false;
                }
            }
        }

        // Send to default Telegram chat if configured and not already included
        $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'telegram_default_chat_id'");
        $defaultChatId = $stmt->fetchColumn();

        if ($defaultChatId && !in_array($defaultChatId, explode(',', $monitor['telegram_chat_ids'] ?? ''))) {
            $telegram = new TelegramNotifier();
            if ($telegram->sendMessage($message)) {
                error_log("Telegram alert sent to default chat ID for monitor '{$monitor['name']}'. URL: {$monitor['url']}");
            } else {
                error_log("Failed to send Telegram alert to default chat ID for monitor '{$monitor['name']}'. URL: {$monitor['url']}");
                $allSent = false;
            }
        }

        return $allSent;
    }

    private function getAdminEmail()
    {
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_email'");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}
