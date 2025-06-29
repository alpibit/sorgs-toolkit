<?php
class Email
{
    public static function sendAlert($monitor, $result, $to, $subject = null)
    {
        $settings = self::getSmtpSettings();

        if (empty($settings['smtp_host']) || empty($settings['smtp_port']) || empty($settings['smtp_user']) || empty($settings['smtp_pass'])) {
            error_log("SMTP settings are incomplete. Unable to send alert email.");
            return false;
        }

        // For new installations or test environments, avoid sending emails with dummy settings
        if ($settings['smtp_host'] === 'test' || $settings['smtp_host'] === 'localhost') {
            error_log("Skipping email alert - SMTP host is set to '{$settings['smtp_host']}'. Configure proper SMTP settings.");
            return true; // Return true to avoid flooding logs during initial setup
        }

        $smtp = new SmtpClient(
            $settings['smtp_host'],
            $settings['smtp_port'],
            $settings['smtp_user'],
            $settings['smtp_pass']
        );

        // Only enable debug in development environments
        $smtp->setDebug(defined('DEBUG_MODE') && DEBUG_MODE === true);

        try {
            $smtp->connect();

            $from = 'noreply@' . $_SERVER['HTTP_HOST'];

            if ($subject === null) {
                $subject = "Alert: {$monitor['name']} is {$result['status']}!";
            }

            $alertType = $result['alert_type'] ?? 'down';

            if ($alertType === 'recovery') {
                $body = "RECOVERY NOTIFICATION\r\n";
                $body .= "===================\r\n\r\n";
                $body .= "Monitor: {$monitor['name']}\r\n";
                $body .= "URL: {$monitor['url']}\r\n";
                $body .= "Status: BACK ONLINE ✅\r\n";
                $body .= "Response Time: {$result['response_time']} ms\r\n";

                if (isset($result['downtime_duration'])) {
                    $body .= "\r\nDowntime Summary:\r\n";
                    $body .= "Total Duration: {$result['downtime_duration']}\r\n";
                    $body .= "Failed Checks: {$result['consecutive_failures']}\r\n";
                }
            } elseif ($alertType === 'still_down') {
                $body = "ONGOING ISSUE NOTIFICATION\r\n";
                $body .= "========================\r\n\r\n";
                $body .= "Monitor: {$monitor['name']}\r\n";
                $body .= "URL: {$monitor['url']}\r\n";
                $body .= "Status: STILL {$result['status']} ⚠️\r\n";
                $body .= "Failed Checks: {$result['consecutive_failures']}\r\n";
            } else {
                $body = "DOWNTIME ALERT\r\n";
                $body .= "=============\r\n\r\n";
                $body .= "Monitor: {$monitor['name']}\r\n";
                $body .= "URL: {$monitor['url']}\r\n";
                $body .= "Status: {$result['status']}\r\n";
                $body .= "Message: {$result['message']}\r\n";
            }

            $body .= "HTTP Status Code: {$result['http_code']}\r\n";

            if (!empty($result['error'])) {
                $body .= "Error: {$result['error']}\r\n";
            }

            // Add SSL certificate information if available
            if (isset($result['ssl_info']) && !empty($result['ssl_info'])) {
                $body .= "\r\nSSL Certificate Information:\r\n";
                $body .= "Issued To: {$result['ssl_info']['subject']}\r\n";
                $body .= "Issued By: {$result['ssl_info']['issuer']}\r\n";
                $body .= "Valid From: {$result['ssl_info']['valid_from']}\r\n";
                $body .= "Valid Until: {$result['ssl_info']['valid_to']}\r\n";

                // Calculate days until expiry
                if (isset($result['ssl_info']['valid_to_time'])) {
                    $daysRemaining = ceil(($result['ssl_info']['valid_to_time'] - time()) / (60 * 60 * 24));
                    $body .= "Days Until Expiry: {$daysRemaining}\r\n";

                    if ($daysRemaining <= 30) {
                        $body .= "\r\n⚠️ WARNING: Certificate expires in {$daysRemaining} days! ⚠️\r\n";
                    }
                }
            }

            $body .= "\r\nCheck Time: " . date('Y-m-d H:i:s') . "\r\n";

            $smtp->sendMail($from, $to, $subject, $body);
            $smtp->quit();

            error_log("Alert email sent successfully to $to for monitor: {$monitor['name']} (Type: $alertType)");
            return true;
        } catch (Exception $e) {
            error_log("Failed to send alert email to $to. Error: " . $e->getMessage());
            return false;
        }
    }

    private static function getSmtpSettings()
    {
        $db = new Database();
        $conn = $db->connect();

        $settings = [];
        $sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass')";
        $result = $conn->query($sql);

        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }
}
