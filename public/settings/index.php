<?php
if (!defined('CONFIG_INCLUDED')) {
    if (!file_exists(__DIR__ . '/../../config/database.php')) {
        header('Location: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://')
            . $_SERVER['HTTP_HOST'] . '/install.php');
        exit;
    }

    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/autoload.php';
    define('CONFIG_INCLUDED', true);
}

session_start();

$user = new User();
$db = new Database();

if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: ' . BASE_URL . '/public/login.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("Processing settings update. POST data: " . print_r($_POST, true));

    $conn = $db->connect();

    $settingsToUpdate = [
        'timezone',
        'date_format',
        'time_format',
        'check_interval',
        'admin_email',
        'smtp_host',
        'smtp_port',
        'smtp_user',
        'telegram_bot_token',
        'telegram_default_chat_id'
    ];

    foreach ($settingsToUpdate as $setting) {
        if (isset($_POST[$setting])) {
            $value = $_POST[$setting];
            error_log("Updating setting $setting with value: $value");
            $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            if ($stmt->execute([$value, $setting])) {
                error_log("Successfully updated $setting");
            } else {
                error_log("Failed to update $setting: " . print_r($stmt->errorInfo(), true));
            }
        }
    }

    // Handle SMTP password separately
    if (!empty($_POST['smtp_pass'])) {
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'smtp_pass'");
        if ($stmt->execute([$_POST['smtp_pass']])) {
            error_log("Successfully updated SMTP password");
        } else {
            error_log("Failed to update SMTP password: " . print_r($stmt->errorInfo(), true));
        }
    }

    $message = "Settings updated successfully.";
}

// Fetch current settings
$conn = $db->connect();
$stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Debug log current settings
error_log("Current settings: " . print_r($settings, true));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/styles.css">
</head>

<body>
    <div class="sorgs-container">
        <h1>Settings</h1>
        <nav class="sorgs-nav">
            <ul>
                <li><a href="<?php echo BASE_URL; ?>/public/index.php" class="sorgs-button sorgs-button-secondary">Back to Dashboard</a></li>
            </ul>
        </nav>

        <?php if ($message): ?>
            <div class="sorgs-message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="post" class="sorgs-form">
            <div class="sorgs-settings-section">
                <h2>General Settings</h2>
                <div class="sorgs-form-group">
                    <label for="timezone">Timezone:</label>
                    <input type="text" id="timezone" name="timezone" value="<?php echo htmlspecialchars($settings['timezone'] ?? ''); ?>">
                </div>

                <div class="sorgs-form-group">
                    <label for="date_format">Date Format:</label>
                    <input type="text" id="date_format" name="date_format" value="<?php echo htmlspecialchars($settings['date_format'] ?? ''); ?>">
                </div>

                <div class="sorgs-form-group">
                    <label for="time_format">Time Format:</label>
                    <input type="text" id="time_format" name="time_format" value="<?php echo htmlspecialchars($settings['time_format'] ?? ''); ?>">
                </div>

                <div class="sorgs-form-group">
                    <label for="check_interval">Default Check Interval (seconds):</label>
                    <input type="number" id="check_interval" name="check_interval" value="<?php echo htmlspecialchars($settings['check_interval'] ?? ''); ?>">
                </div>
            </div>

            <div class="sorgs-settings-section">
                <h2>Telegram Settings</h2>
                <div class="sorgs-form-group">
                    <label for="telegram_bot_token">Telegram Bot Token:</label>
                    <input type="text" id="telegram_bot_token" name="telegram_bot_token" value="<?php echo htmlspecialchars($settings['telegram_bot_token'] ?? ''); ?>">
                    <small class="sorgs-form-help">Get this from BotFather in Telegram</small>
                </div>
                <div class="sorgs-form-group">
                    <label for="telegram_default_chat_id">Default Chat ID:</label>
                    <input type="text" id="telegram_default_chat_id" name="telegram_default_chat_id" value="<?php echo htmlspecialchars($settings['telegram_default_chat_id'] ?? ''); ?>">
                    <small class="sorgs-form-help">This will receive all notifications if no specific chat IDs are set</small>
                </div>
                <div class="sorgs-form-group">
                    <button type="button" onclick="testTelegramConnection()" class="sorgs-button sorgs-button-secondary">
                        Test Telegram Connection
                    </button>
                </div>
            </div>

            <div class="sorgs-settings-section">
                <h2>Admin Settings</h2>
                <div class="sorgs-form-group">
                    <label for="admin_email">Admin Email:</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>">
                </div>
            </div>

            <div class="sorgs-settings-section">
                <h2>SMTP Settings</h2>
                <div class="sorgs-form-group">
                    <label for="smtp_host">SMTP Host:</label>
                    <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>">
                </div>

                <div class="sorgs-form-group">
                    <label for="smtp_port">SMTP Port:</label>
                    <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? ''); ?>">
                </div>

                <div class="sorgs-form-group">
                    <label for="smtp_user">SMTP Username:</label>
                    <input type="text" id="smtp_user" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>">
                </div>

                <div class="sorgs-form-group">
                    <label for="smtp_pass">SMTP Password:</label>
                    <input type="password" id="smtp_pass" name="smtp_pass" placeholder="Enter new password to change">
                </div>
            </div>

            <div class="sorgs-form-group">
                <input type="submit" value="Save Settings" class="sorgs-button sorgs-button-primary">
            </div>
        </form>
    </div>

    <script>
        function testTelegramConnection() {
            fetch('test_telegram.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Telegram test message sent successfully!');
                    } else {
                        alert('Failed to send Telegram test message: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('Error testing Telegram connection: ' + error);
                });
        }
    </script>
</body>

</html>