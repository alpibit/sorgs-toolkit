<?php
if (!defined('CONFIG_INCLUDED')) {
    if (!file_exists(__DIR__ . '/../../config/database.php')) {
        header('Location: /install.php');
        exit;
    }

    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/autoload.php';
    define('CONFIG_INCLUDED', true);
}

app_send_security_headers();
app_session_start();

$user = new User();
$db = new Database();

if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: /public/login.php');
    exit;
}

$message = '';
$errors = [];
$retrySecrets = false;
$conn = $db->connect();
$stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request token.');
    }

    $validator = new FormValidator($_POST);
    $fields = [
        'timezone' => 'Timezone',
        'date_format' => 'Date Format',
        'time_format' => 'Time Format',
        'check_interval' => 'Default Check Interval',
        'admin_email' => 'Admin Email',
        'smtp_host' => 'SMTP Host',
        'smtp_port' => 'SMTP Port',
        'smtp_user' => 'SMTP Username',
        'mail_from' => 'From Address',
        'telegram_bot_token' => 'Telegram Bot Token',
        'telegram_default_chat_id' => 'Default Chat ID'
    ];
    $settingsToUpdate = [];
    foreach ($fields as $setting => $label) {
        if (!array_key_exists($setting, $_POST)) {
            $validator->addError($setting, "$label is missing from the request.");
        }
        if ($setting === 'smtp_port') {
            $value = $validator->integer($setting, $label, 1, 65535, false);
        } elseif ($setting === 'check_interval') {
            $value = $validator->integer($setting, $label, -2147483648, 2147483647, false);
        } elseif ($setting === 'admin_email' || $setting === 'mail_from') {
            $value = $validator->email($setting, $label);
        } else {
            $value = $validator->text($setting, $label);
        }
        $settingsToUpdate[$setting] = $value;
        if ($setting !== 'telegram_bot_token') {
            $settings[$setting] = $value;
        }
    }

    $smtpPass = $validator->text('smtp_pass', 'SMTP Password');
    $retrySecrets = $smtpPass !== '' || $settingsToUpdate['telegram_bot_token'] !== ($settings['telegram_bot_token'] ?? '');
    if ($smtpPass !== '') {
        $settingsToUpdate['smtp_pass'] = $smtpPass;
    }

    $errors = $validator->getErrors();
    if ($errors) {
        http_response_code(400);
    } else {
        try {
            if (!$conn->beginTransaction()) {
                throw new RuntimeException('Could not start settings transaction.');
            }
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?");
            foreach ($settingsToUpdate as $setting => $value) {
                if (!$stmt->execute([$setting, $value, $value])) {
                    throw new RuntimeException('Could not save setting.');
                }
            }
            if (!$conn->commit()) {
                throw new RuntimeException('Could not commit settings transaction.');
            }
            $settings['telegram_bot_token'] = $settingsToUpdate['telegram_bot_token'];
            $message = "Settings updated successfully.";
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                try {
                    $conn->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log('Failed to roll back settings transaction (' . get_class($rollbackError) . ').');
                }
            }
            error_log('Failed to save settings (' . get_class($e) . ').');
            $errors[] = 'Could not save settings. Please try again.';
            http_response_code(500);
        }
    }
}
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
        <?php if ($errors): ?>
            <div class="sorgs-message error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($retrySecrets): ?>
                    <p>Re-enter any changed SMTP password or Telegram bot token before saving again.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="sorgs-form">
            <?php echo csrf_field(); ?>
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
                    <small class="sorgs-form-help">This chat receives all monitor notifications in addition to monitor-specific chats. Each chat receives the alert once.</small>
                </div>
                <div class="sorgs-form-group">
                    <button type="button" id="test-telegram-btn" data-csrf="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" class="sorgs-button sorgs-button-secondary">
                        Test Telegram Connection
                    </button>
                    <small class="sorgs-form-help">The test uses the saved bot token and default chat ID. Save Settings before testing changes.</small>
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

                <div class="sorgs-form-group">
                    <label for="mail_from">From Address:</label>
                    <input type="email" id="mail_from" name="mail_from" value="<?php echo htmlspecialchars($settings['mail_from'] ?? ''); ?>">
                    <small class="sorgs-form-help">Address alerts are sent from. Most providers require this to match the SMTP username, otherwise the mail is rejected.</small>
                </div>
            </div>

            <div class="sorgs-form-group">
                <input type="submit" value="Save Settings" class="sorgs-button sorgs-button-primary">
            </div>
        </form>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/scripts.js"></script>
</body>

</html>
