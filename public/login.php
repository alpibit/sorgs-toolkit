<?php
if (!defined('CONFIG_INCLUDED')) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/autoload.php';
    define('CONFIG_INCLUDED', true);
}

session_start();

$user = new User();

if ($user->isLoggedIn() && $user->isAdmin()) {
    header('Location: ' . BASE_URL . '/public/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($user->login($username, $password) && $user->isAdmin()) {
        header('Location: ' . BASE_URL . '/public/index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/styles.css">
</head>

<body>
    <div class="sorgs-container sorgs-login-container">
        <h1>Login to Sorgs</h1>
        <?php if ($error): ?>
            <p class="sorgs-message error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <div class="sorgs-form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="sorgs-form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="sorgs-form-group">
                <input type="submit" value="Login">
            </div>
        </form>
    </div>
</body>

</html>