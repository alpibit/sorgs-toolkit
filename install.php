<?php

if (!defined('CONFIG_INCLUDED')) {
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/config/autoload.php';
    define('CONFIG_INCLUDED', true);
}

app_send_security_headers();
app_session_start();

$errors = [];
$success = false;
$formValues = [
    'db_host' => 'localhost',
    'db_name' => '',
    'db_user' => '',
    'admin_user' => '',
    'admin_email' => '',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_user' => ''
];

if (file_exists(__DIR__ . '/config/database.php')) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Already Installed</title>
        <link rel="stylesheet" href="/public/assets/css/styles.css">
        <meta http-equiv="refresh" content="15;url=/public/login.php">
    </head>
    <body>
        <div class="sorgs-container">
            <h1>Sorgs System</h1>
            <div class="sorgs-message error">
                <p>The system is already installed, so the installer is locked. It stays disabled for as long as <code>config/database.php</code> exists.</p>
                <p><strong>Need to reinstall?</strong> Delete <code>config/database.php</code> from the application directory; for a clean reinstall, also drop all tables from your existing database or use a fresh one.</p>
            </div>
            <p>If you just want to use the existing installation, you can proceed to login:</p>
            <a class="sorgs-button sorgs-button-primary" href="/public/login.php">Go to Login</a>
        </div>
    </body>
    </html>';
    exit;
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request token.');
    }

    $input = $_POST;
    foreach ($formValues as $field => $default) {
        $formValues[$field] = is_string($_POST[$field] ?? null) ? $_POST[$field] : '';
        if (isset($input[$field]) && is_string($input[$field])) {
            $input[$field] = trim($input[$field]);
        }
    }

    $validator = new FormValidator($input);
    $host = $validator->text('db_host', 'the database host', true, null, null);
    $name = $validator->text('db_name', 'the database name', true, null, null);
    $user = $validator->text('db_user', 'the database user', true, null, null);
    $adminUser = $validator->text('admin_user', 'the admin username', true, 50);
    $adminEmail = $validator->email('admin_email', 'admin email', true, 100);
    $smtpHost = $validator->text('smtp_host', 'the SMTP host', true);
    $smtpPort = $validator->integer('smtp_port', 'SMTP port', 1, 65535);
    $smtpUser = $validator->text('smtp_user', 'the SMTP username', true);
    $smtpPass = $validator->text('smtp_pass', 'SMTP password');

    foreach (['db_pass' => 'Database password', 'admin_pass' => 'Admin password'] as $field => $label) {
        if (array_key_exists($field, $_POST) && !is_string($_POST[$field])) {
            $validator->addError($field, "$label must be text.");
        }
    }
    $pass = is_string($_POST['db_pass'] ?? null) ? $_POST['db_pass'] : '';
    $adminPass = is_string($_POST['admin_pass'] ?? null) ? $_POST['admin_pass'] : '';

    if (strlen($adminUser) < 5) $validator->addError('admin_user', 'Admin username should be at least 5 characters long.');
    if (strlen($adminPass) < 8) $validator->addError('admin_pass', 'Admin password should be at least 8 characters long.');
    if (strpos($adminPass, "\0") !== false) $validator->addError('admin_pass', 'Admin password cannot contain a null byte.');
    if ($smtpPass === '') $validator->addError('smtp_pass', 'Please enter the SMTP password.');
    $errors = $validator->getErrors();

    if ($errors) {
        http_response_code(400);
    } else {
        try {
            $installer = new Installer();
            $success = $installer->install($adminUser, $adminPass, $adminEmail, $host, $name, $user, $pass, $smtpHost, $smtpPort, $smtpUser, $smtpPass);
            if ($success) {
                $success = true;
            } else {
                $errors[] = "Installation failed for an unknown reason.";
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sorgs System Installation</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/styles.css">
</head>

<body>
    <div class="sorgs-container">
        <h1>Sorgs System Installation</h1>

        <?php if ($success): ?>
            <p class="sorgs-message success">Installation completed successfully! You can now start using the system.</p>
            <a class="sorgs-button sorgs-button-primary" href="<?php echo BASE_URL; ?>/public/login.php">Go to Login</a>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="sorgs-message error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo csrf_field(); ?>
                <div class="sorgs-settings-section">
                    <h2>Database Configuration</h2>
                    <label for="db_host">Database Host</label>
                    <input type="text" name="db_host" id="db_host" required value="<?php echo htmlspecialchars($formValues['db_host']); ?>">

                    <label for="db_name">Database Name</label>
                    <input type="text" name="db_name" id="db_name" required value="<?php echo htmlspecialchars($formValues['db_name']); ?>">

                    <label for="db_user">Database User</label>
                    <input type="text" name="db_user" id="db_user" required value="<?php echo htmlspecialchars($formValues['db_user']); ?>">

                    <label for="db_pass">Database Password</label>
                    <input type="password" name="db_pass" id="db_pass">
                </div>

                <div class="sorgs-settings-section">
                    <h2>Admin Configuration</h2>
                    <label for="admin_email">Admin Email</label>
                    <input type="email" name="admin_email" id="admin_email" required value="<?php echo htmlspecialchars($formValues['admin_email']); ?>">

                    <label for="admin_user">Admin Username</label>
                    <input type="text" name="admin_user" id="admin_user" required value="<?php echo htmlspecialchars($formValues['admin_user']); ?>">

                    <label for="admin_pass">Admin Password</label>
                    <input type="password" name="admin_pass" id="admin_pass" required>
                </div>

                <div class="sorgs-settings-section">
                    <h2>SMTP Configuration</h2>
                    <label for="smtp_host">SMTP Host</label>
                    <input type="text" name="smtp_host" id="smtp_host" required value="<?php echo htmlspecialchars($formValues['smtp_host']); ?>">

                    <label for="smtp_port">SMTP Port</label>
                    <input type="number" name="smtp_port" id="smtp_port" required value="<?php echo htmlspecialchars($formValues['smtp_port']); ?>">

                    <label for="smtp_user">SMTP Username</label>
                    <input type="text" name="smtp_user" id="smtp_user" required value="<?php echo htmlspecialchars($formValues['smtp_user']); ?>">

                    <label for="smtp_pass">SMTP Password</label>
                    <input type="password" name="smtp_pass" id="smtp_pass" required>
                </div>

                <input type="submit" value="Install">
            </form>
        <?php endif; ?>
    </div>
</body>

</html>
