<?php
class Installer
{
    private $conn;

    public function install($adminUser, $adminPass, $adminEmail, $host, $name, $user, $pass, $smtpHost, $smtpPort, $smtpUser, $smtpPass)
    {
        try {
            $this->writeConfigFile($host, $name, $user, $pass);

            require_once 'config/database.php';

            $db = new Database();
            $this->conn = $db->connect();

            $this->setupTables();
            $this->setDefaultSettings();
            $this->createAdminUser($adminUser, $adminPass, $adminEmail);
            $this->setSmtpSettings($smtpHost, $smtpPort, $smtpUser, $smtpPass);
            $this->flagAsInstalled();

            return true;
        } catch (Exception $e) {
            error_log("Installation failed: " . $e->getMessage());
            throw new Exception("Installation failed: " . $e->getMessage());
        }
    }

    private function writeConfigFile($host, $name, $user, $pass)
    {
        $configContent = "<?php\n\n";
        $configContent .= "define('DB_HOST', '" . addslashes($host) . "');\n";
        $configContent .= "define('DB_NAME', '" . addslashes($name) . "');\n";
        $configContent .= "define('DB_USER', '" . addslashes($user) . "');\n";
        $configContent .= "define('DB_PASS', '" . addslashes($pass) . "');\n\n";
        $configContent .= "?>";

        $configPath = __DIR__ . '/../config/database.php';
        if (file_put_contents($configPath, $configContent) === false) {
            throw new Exception("Unable to write config file");
        }
    }

    private function setupTables()
    {
        $sqlFiles = ['sql/settings.sql', 'sql/users.sql', 'sql/monitors.sql'];
        foreach ($sqlFiles as $file) {
            $this->executeSQLFromFile($file);
        }
    }

    private function executeSQLFromFile($filePath)
    {
        $fullPath = __DIR__ . '/../' . $filePath;
        if (!file_exists($fullPath)) {
            throw new Exception("SQL file not found: $fullPath");
        }

        $query = file_get_contents($fullPath);
        if ($query === false) {
            throw new Exception("Unable to read SQL file: $fullPath");
        }

        if (empty(trim($query))) {
            throw new Exception("SQL file is empty: $fullPath");
        }

        $statements = explode(';', $query);
        foreach ($statements as $statement) {
            if (trim($statement) != '') {
                $stmt = $this->conn->prepare($statement);
                if ($stmt === false) {
                    throw new Exception("Failed to prepare SQL query from file: $fullPath");
                }
                if (!$stmt->execute()) {
                    throw new Exception("Failed to execute SQL query from file: $fullPath. Error: " . implode(", ", $stmt->errorInfo()));
                }
            }
        }
    }

    private function setDefaultSettings()
    {
        $defaultSettings = [
            ['timezone', 'UTC'],
            ['date_format', 'Y-m-d'],
            ['time_format', 'H:i:s'],
            ['check_interval', '300'],
            ['admin_email', ''],
            ['installed', 'false'],
            ['alert_cooldown', '3600']
        ];

        foreach ($defaultSettings as $setting) {
            $this->insertOrUpdateSetting($setting[0], $setting[1]);
        }
    }

    private function createAdminUser($username, $password, $email)
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, email, role) VALUES (:username, :hashedPassword, :email, 'admin')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':hashedPassword', $hashedPassword);
        $stmt->bindParam(':email', $email);
        if (!$stmt->execute()) {
            throw new Exception("Failed to create admin user");
        }
    }

    private function setSmtpSettings($smtpHost, $smtpPort, $smtpUser, $smtpPass)
    {
        $smtpSettings = [
            ['smtp_host', $smtpHost],
            ['smtp_port', $smtpPort],
            ['smtp_user', $smtpUser],
            ['smtp_pass', $smtpPass]
        ];

        foreach ($smtpSettings as $setting) {
            $this->insertOrUpdateSetting($setting[0], $setting[1]);
        }
    }

    private function insertOrUpdateSetting($key, $value)
    {
        $stmt = $this->conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = :value");
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert or update setting: $key");
        }
    }

    private function flagAsInstalled()
    {
        $this->insertOrUpdateSetting('installed', 'true');
    }
}
