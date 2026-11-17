<?php
class Installer
{
    private $conn;
    private $createdTables = [];
    private $stagedConfig;

    public function install($adminUser, $adminPass, $adminEmail, $host, $name, $user, $pass, $smtpHost, $smtpPort, $smtpUser, $smtpPass)
    {
        $this->conn = null;
        $this->createdTables = [];
        $this->stagedConfig = null;
        $lock = null;
        $configPath = __DIR__ . '/../config/database.php';

        try {
            $lock = @fopen(__DIR__ . '/../config/install.lock', 'c+');
            if (!$lock) {
                throw new InstallerError('Unable to open the installation lock. Please make sure the config directory is writable.');
            }
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                throw new InstallerError('Another installation is running. Please wait for it to finish before trying again.');
            }
            $this->checkConfigPath($configPath);
            $schemas = $this->loadSchemas();

            try {
                $this->conn = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
            } catch (PDOException $e) {
                error_log('Database connection test failed: ' . $e->getMessage());
                $errorMessage = 'Unable to connect to the database. Check the connection settings and server error log.';
                $errorCode = $e->errorInfo[1] ?? $e->getCode();

                if ($errorCode == 2002) {
                    $errorMessage = "Cannot connect to database server at '$host'. Please verify the hostname is correct and the server is running.";
                } elseif ($errorCode == 1045) {
                    $errorMessage = "Access denied to database. Please verify your username and password.";
                } elseif ($errorCode == 1049) {
                    $errorMessage = "Database '$name' does not exist. Please create it first or check the name.";
                } elseif ($errorCode == 1044) {
                    $errorMessage = "Access denied for user '$user' to database '$name'. This could mean either: 
                    1) The database '$name' doesn't exist (verify the database name is correct)
                    2) The user '$user' doesn't have sufficient permissions to access this database";
                }

                throw new InstallerError($errorMessage);
            }

            $this->checkIfTablesExist();
            $this->writeConfigFile($host, $name, $user, $pass);
            $this->setupTables($schemas);

            if (!$this->conn->beginTransaction()) {
                throw new Exception('Unable to begin the installation transaction.');
            }
            $this->setDefaultSettings($adminEmail);
            $this->createAdminUser($adminUser, $adminPass, $adminEmail);
            $this->setSmtpSettings($smtpHost, $smtpPort, $smtpUser, $smtpPass);
            $this->setMailFrom($smtpUser, $adminEmail);
            $this->flagAsInstalled();
            if (!$this->conn->commit()) {
                throw new Exception('Unable to commit the installation transaction.');
            }

            $this->checkConfigPath($configPath);
            if (!@rename($this->stagedConfig, $configPath)) {
                throw new InstallerError('Unable to publish the database configuration. Please check the config directory permissions.');
            }
            $this->stagedConfig = null;

            return true;
        } catch (Throwable $e) {
            error_log('Installation failed: ' . $e->getMessage());

            if (!$this->cleanup()) {
                throw new InstallerError('Installation failed and cleanup could not finish. Check the server error log for files or tables that still need cleanup before retrying.');
            }

            if ($e instanceof InstallerError) {
                throw $e;
            }

            $errorMessage = 'Installation failed. The reason is in the server error log.';
            if (function_exists('app_debug_enabled') && app_debug_enabled()) {
                $errorMessage .= ' ' . $e->getMessage();
            }

            throw new InstallerError($errorMessage);
        } finally {
            if (is_resource($lock)) {
                fclose($lock);
            }
            $this->conn = null;
        }
    }

    private function checkConfigPath($configPath)
    {
        clearstatcache(true, $configPath);
        if (file_exists($configPath) || is_link($configPath)) {
            throw new InstallerError('Database configuration already exists. The installer cannot overwrite it.');
        }
    }

    private function checkIfTablesExist()
    {
        $tablesToCheck = ['monitors', 'settings', 'users'];
        foreach ($tablesToCheck as $table) {
            try {
                $this->conn->query("SELECT 1 FROM `$table` LIMIT 1");
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? null) === 1146) {
                    continue;
                }
                throw $e;
            }
            throw new InstallerError("The database already contains a table named '$table'. It seems an installation already exists. Please use a different database, or clear the existing tables.");
        }
    }

    private function writeConfigFile($host, $name, $user, $pass)
    {
        $configContent = "<?php\n\n";
        $configContent .= "define('DB_HOST', " . var_export($host, true) . ");\n";
        $configContent .= "define('DB_NAME', " . var_export($name, true) . ");\n";
        $configContent .= "define('DB_USER', " . var_export($user, true) . ");\n";
        $configContent .= "define('DB_PASS', " . var_export($pass, true) . ");\n\n";
        $configContent .= "?>";

        $path = __DIR__ . '/../config/.database-' . bin2hex(random_bytes(16)) . '.php';
        $mask = umask(0077);
        try {
            $file = @fopen($path, 'x');
        } finally {
            umask($mask);
        }
        if (!$file) {
            throw new InstallerError('Unable to stage the database configuration. Please make sure the config directory is writable.');
        }
        $this->stagedConfig = $path;

        try {
            if (!chmod($path, 0600)) {
                throw new InstallerError('Unable to secure the database configuration permissions.');
            }
            if (fwrite($file, $configContent) !== strlen($configContent) || !fflush($file)) {
                throw new InstallerError('Unable to write the database configuration. Check available disk space and config directory permissions.');
            }
        } finally {
            fclose($file);
        }
    }

    private function loadSchemas()
    {
        $schemas = [];
        foreach (['settings', 'users', 'monitors'] as $table) {
            $path = __DIR__ . '/../sql/' . $table . '.sql';
            $sql = @file_get_contents($path);
            if ($sql === false || trim($sql) === '') {
                throw new InstallerError("Unable to read the schema for '$table'. Check the application SQL files.");
            }
            $schemas[$table] = array_values(array_filter(array_map('trim', explode(';', $sql))));
            if (!preg_match('/\ACREATE TABLE `' . $table . '`\s*\(/i', $schemas[$table][0] ?? '')) {
                throw new InstallerError("Invalid schema for '$table'. Check the application SQL files.");
            }
        }

        return $schemas;
    }

    private function setupTables($schemas)
    {
        foreach ($schemas as $table => $statements) {
            if ($this->conn->exec(array_shift($statements)) === false) {
                throw new Exception("Failed to create table '$table'.");
            }
            $this->createdTables[] = $table;
            foreach ($statements as $statement) {
                if ($this->conn->exec($statement) === false) {
                    throw new Exception("Failed to complete the schema for '$table'.");
                }
            }
        }
    }

    private function cleanup()
    {
        $complete = true;
        if ($this->conn !== null) {
            try {
                if ($this->conn->inTransaction() && !$this->conn->rollBack()) {
                    throw new Exception('Unable to roll back the installation transaction.');
                }
            } catch (Throwable $e) {
                error_log('Installation rollback failed: ' . $e->getMessage());
                $complete = false;
            }
        }
        foreach (array_reverse($this->createdTables) as $table) {
            try {
                if ($this->conn->exec("DROP TABLE IF EXISTS `$table`") === false) {
                    throw new Exception("Unable to remove table '$table'.");
                }
            } catch (Throwable $e) {
                error_log("Installation cleanup failed for '$table': " . $e->getMessage());
                $complete = false;
            }
        }
        if ($this->stagedConfig !== null && !@unlink($this->stagedConfig)) {
            error_log('Unable to remove staged database configuration: ' . $this->stagedConfig);
            $complete = false;
        }
        return $complete;
    }

    private function setDefaultSettings($adminEmail)
    {
        $defaultSettings = [
            ['timezone', 'UTC'],
            ['date_format', 'Y-m-d'],
            ['time_format', 'H:i:s'],
            ['check_interval', '300'],
            ['notification_email', ''],
            ['admin_email', $adminEmail],
            ['installed', 'false'],
            ['alert_cooldown', '3600'],
            ['mail_from', ''],
            ['telegram_bot_token', ''],
            ['telegram_default_chat_id', '']
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

    private function setMailFrom($smtpUser, $adminEmail)
    {
        $from = '';

        if (filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
            $from = $smtpUser;
        } elseif (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $from = $adminEmail;
        }

        $this->insertOrUpdateSetting('mail_from', $from);
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
