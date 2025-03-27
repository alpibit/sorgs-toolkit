<?php
if (!defined('CONFIG_INCLUDED')) {
    if (!file_exists(__DIR__ . '/../config/database.php')) {
        header('Location: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://')
            . $_SERVER['HTTP_HOST'] . '/install.php');
        exit;
    }

    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/autoload.php';
    define('CONFIG_INCLUDED', true);
}

session_start();

$user = new User();

// Redirect if already logged in
if ($user->isLoggedIn() && $user->isAdmin()) {
    header('Location: ' . BASE_URL . '/public/index.php');
    exit;
}

$error = '';

// Generate a simple math captcha
function generateMathCaptcha()
{
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $_SESSION['captcha_answer'] = $num1 + $num2;
    return "What is $num1 + $num2?";
}

// Initialize captcha if it doesn't exist
if (!isset($_SESSION['captcha_question']) || !isset($_SESSION['captcha_answer'])) {
    $_SESSION['captcha_question'] = generateMathCaptcha();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $captcha_answer = isset($_POST['captcha_answer']) ? intval($_POST['captcha_answer']) : 0;
    $expected_answer = $_SESSION['captcha_answer']; // Store the current answer before generating a new one

    usleep(rand(200000, 500000));

    if ($user->isLoginBlocked()) {
        $error = 'Too many failed login attempts. Please try again after 15 minutes.';
        $_SESSION['captcha_question'] = generateMathCaptcha();
    } elseif ($captcha_answer !== $expected_answer) {
        $user->trackFailedLogin();
        $error = 'Incorrect captcha answer.';
        $_SESSION['captcha_question'] = generateMathCaptcha();
    } elseif ($user->login($username, $password) && $user->isAdmin()) {
        $user->resetLoginAttempts();
        header('Location: ' . BASE_URL . '/public/index.php');
        exit;
    } else {
        $user->trackFailedLogin();
        $error = 'Invalid username or password.';
        $_SESSION['captcha_question'] = generateMathCaptcha();
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
                <label for="captcha_answer"><?php echo htmlspecialchars($_SESSION['captcha_question']); ?></label>
                <input type="text" id="captcha_answer" name="captcha_answer" required>
            </div>

            <div class="sorgs-form-group">
                <input type="submit" value="Login">
            </div>
        </form>
    </div>
</body>

</html>