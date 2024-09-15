<?php
class Email
{
    public static function sendAlert($monitor, $result)
    {
        $settings = self::getSmtpSettings();

        if (empty($settings['smtp_host']) || empty($settings['smtp_port']) || empty($settings['smtp_user']) || empty($settings['smtp_pass'])) {
            error_log("SMTP settings are incomplete. Unable to send alert email.");
            return false;
        }

        $smtp = new SmtpClient(
            $settings['smtp_host'],
            $settings['smtp_port'],
            $settings['smtp_user'],
            $settings['smtp_pass']
        );

        // Enable debug mode for troubleshooting
        $smtp->setDebug(true);

        try {
            $smtp->connect();

            $from = 'noreply@' . $_SERVER['HTTP_HOST'];
            $to = $settings['notification_email'] ?? ADMIN_EMAIL;
            $subject = "Alert: {$monitor['name']} is {$result['status']}!";

            $body = "Monitor: {$monitor['name']}\r\n";
            $body .= "URL: {$monitor['url']}\r\n";
            $body .= "Status: {$result['status']}\r\n";
            $body .= "Message: {$result['message']}\r\n";
            $body .= "HTTP Status Code: {$result['http_code']}\r\n";
            $body .= "Response Time: {$result['response_time']} ms\r\n";
            $body .= "Download Size: {$result['download_size']} bytes\r\n";
            if (!empty($result['error'])) {
                $body .= "Error: {$result['error']}\r\n";
            }
            $body .= "Last Check Time: " . date('Y-m-d H:i:s') . "\r\n";
            $body .= "Please check and take necessary action.";

            $smtp->sendMail($from, $to, $subject, $body);
            $smtp->quit();

            error_log("Alert email sent successfully for monitor: {$monitor['name']}");
            return true;
        } catch (Exception $e) {
            error_log("Failed to send alert email. Error: " . $e->getMessage());
            return false;
        }
    }

    private static function getSmtpSettings()
    {
        $db = new Database();
        $conn = $db->connect();

        $settings = [];
        $sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'notification_email')";
        $result = $conn->query($sql);

        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }
}
