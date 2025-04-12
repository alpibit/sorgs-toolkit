<?php

if (!defined('CONFIG_INCLUDED')) {
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/config/autoload.php';
    define('CONFIG_INCLUDED', true);
}

$errors = [];
$success = false;

if (file_exists(BASE_URL . '/config/database.php')) {
    die("The system is already installed. If you want to reinstall, please delete the config/database.php file first.");
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $host = trim($_POST['db_host']);
    $name = trim($_POST['db_name']);
    $user = trim($_POST['db_user']);
    $pass = trim($_POST['db_pass']);
    $adminUser = trim($_POST['admin_user']);
    $adminPass = trim($_POST['admin_pass']);
    $adminEmail = trim($_POST['admin_email']);
    $smtpHost = trim($_POST['smtp_host']);
    $smtpPort = trim($_POST['smtp_port']);
    $smtpUser = trim($_POST['smtp_user']);
    $smtpPass = trim($_POST['smtp_pass']);

    // Validation
    if (empty($host)) $errors[] = "Please enter the database host.";
    if (empty($name)) $errors[] = "Please enter the database name.";
    if (empty($user)) $errors[] = "Please enter the database user.";
    if (empty($adminUser)) $errors[] = "Please enter the admin username.";
    if (empty($adminPass)) $errors[] = "Please enter the admin password.";
    if (empty($adminEmail)) $errors[] = "Please enter the admin email.";
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = "Please enter a valid admin email.";
    if (strlen($adminUser) < 5) $errors[] = "Admin username should be at least 5 characters long.";
    if (strlen($adminPass) < 8) $errors[] = "Admin password should be at least 8 characters long.";
    if (empty($smtpHost)) $errors[] = "Please enter the SMTP host.";
    if (empty($smtpPort)) $errors[] = "Please enter the SMTP port.";
    if (empty($smtpUser)) $errors[] = "Please enter the SMTP username.";
    if (empty($smtpPass)) $errors[] = "Please enter the SMTP password.";

    if (empty($errors)) {
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
                <div class="sorgs-settings-section">
                    <h2>Database Configuration</h2>
                    <label for="db_host">Database Host</label>
                    <input type="text" name="db_host" id="db_host" required value="<?php echo isset($_POST['db_host']) ? htmlspecialchars($_POST['db_host']) : 'localhost'; ?>">

                    <label for="db_name">Database Name</label>
                    <input type="text" name="db_name" id="db_name" required value="<?php echo isset($_POST['db_name']) ? htmlspecialchars($_POST['db_name']) : ''; ?>">

                    <label for="db_user">Database User</label>
                    <input type="text" name="db_user" id="db_user" required value="<?php echo isset($_POST['db_user']) ? htmlspecialchars($_POST['db_user']) : ''; ?>">

                    <label for="db_pass">Database Password</label>
                    <input type="password" name="db_pass" id="db_pass">
                </div>

                <div class="sorgs-settings-section">
                    <h2>Admin Configuration</h2>
                    <label for="admin_email">Admin Email</label>
                    <input type="email" name="admin_email" id="admin_email" required value="<?php echo isset($_POST['admin_email']) ? htmlspecialchars($_POST['admin_email']) : ''; ?>">

                    <label for="admin_user">Admin Username</label>
                    <input type="text" name="admin_user" id="admin_user" required value="<?php echo isset($_POST['admin_user']) ? htmlspecialchars($_POST['admin_user']) : ''; ?>">

                    <label for="admin_pass">Admin Password</label>
                    <input type="password" name="admin_pass" id="admin_pass" required>
                </div>

                <div class="sorgs-settings-section">
                    <h2>SMTP Configuration</h2>
                    <label for="smtp_host">SMTP Host</label>
                    <input type="text" name="smtp_host" id="smtp_host" required value="<?php echo isset($_POST['smtp_host']) ? htmlspecialchars($_POST['smtp_host']) : ''; ?>">

                    <label for="smtp_port">SMTP Port</label>
                    <input type="number" name="smtp_port" id="smtp_port" required value="<?php echo isset($_POST['smtp_port']) ? htmlspecialchars($_POST['smtp_port']) : '587'; ?>">

                    <label for="smtp_user">SMTP Username</label>
                    <input type="text" name="smtp_user" id="smtp_user" required value="<?php echo isset($_POST['smtp_user']) ? htmlspecialchars($_POST['smtp_user']) : ''; ?>">

                    <label for="smtp_pass">SMTP Password</label>
                    <input type="password" name="smtp_pass" id="smtp_pass" required>
                </div>

                <input type="submit" value="Install">
            </form>
        <?php endif; ?>
    </div>
</body>

</html>