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

    public function addMonitor($name, $url, $checkInterval, $expectedStatusCode = 200, $expectedKeyword = '')
    {
        $sql = "INSERT INTO monitors (name, url, check_interval, expected_status_code, expected_keyword) 
                VALUES (:name, :url, :interval, :status_code, :keyword)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':name' => $name,
            ':url' => $url,
            ':interval' => $checkInterval,
            ':status_code' => $expectedStatusCode,
            ':keyword' => $expectedKeyword
        ]);
    }

    public function updateMonitor($id, $name, $url, $checkInterval, $expectedStatusCode = 200, $expectedKeyword = '')
    {
        $sql = "UPDATE monitors SET name = :name, url = :url, check_interval = :interval, 
                expected_status_code = :status_code, expected_keyword = :keyword 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':url' => $url,
            ':interval' => $checkInterval,
            ':status_code' => $expectedStatusCode,
            ':keyword' => $expectedKeyword
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
        }
    }

    public function checkSite($monitor)
    {
        $ch = curl_init($monitor['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
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
            'error' => $error
        ];

        if ($error) {
            $result['status'] = 'down';
            $result['message'] = "CURL Error: $error";
        } elseif ($info['http_code'] != $monitor['expected_status_code']) {
            $result['status'] = 'down';
            $result['message'] = "Unexpected HTTP status code: Expected {$monitor['expected_status_code']}, got {$info['http_code']}";
        } elseif (!empty($monitor['expected_keyword']) && strpos($response, $monitor['expected_keyword']) === false) {
            $result['status'] = 'down';
            $result['message'] = "Expected keyword not found in the response";
        }

        $this->updateMonitorStatus($monitor['id'], $result);

        if ($result['status'] === 'down') {
            $this->handleDowntime($monitor, $result);
        } else {
            $this->resetAlertStatus($monitor['id']);
        }

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
            error_log("Alert for monitor '{$monitor['name']}' suppressed due to cooldown period.");
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

    private function sendAlert($monitor, $result)
    {
        if (Email::sendAlert($monitor, $result)) {
            error_log("Alert sent for monitor '{$monitor['name']}'. URL: {$monitor['url']}");
        } else {
            error_log("Failed to send alert for monitor '{$monitor['name']}'. URL: {$monitor['url']}");
        }
    }
}
