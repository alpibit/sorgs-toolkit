<?php
if (!defined('CONFIG_INCLUDED')) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/autoload.php';
    define('CONFIG_INCLUDED', true);
}

session_start();

$user = new User();
$monitor = new UptimeMonitor();

if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: ' . BASE_URL . '/public/login.php');
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';

switch ($action) {
    case 'add':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $result = $monitor->addMonitor(
                $_POST['name'],
                $_POST['url'],
                $_POST['check_interval'],
                $_POST['expected_status_code'],
                $_POST['expected_keyword'],
                $_POST['notification_emails']
            );
            if ($result) {
                $message = "Monitor added successfully.";
            } else {
                $message = "Failed to add monitor.";
            }
            header('Location: index.php?message=' . urlencode($message));
            exit;
        }
        break;

    case 'edit':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $result = $monitor->updateMonitor(
                $_POST['id'],
                $_POST['name'],
                $_POST['url'],
                $_POST['check_interval'],
                $_POST['expected_status_code'],
                $_POST['expected_keyword'],
                $_POST['notification_emails']
            );
            if ($result) {
                $message = "Monitor updated successfully.";
            } else {
                $message = "Failed to update monitor.";
            }
            header('Location: index.php?message=' . urlencode($message));
            exit;
        }
        $monitorData = $monitor->getMonitor($_GET['id']);
        break;

    case 'delete':
        if (isset($_GET['id'])) {
            $result = $monitor->deleteMonitor($_GET['id']);
            if ($result) {
                $message = "Monitor deleted successfully.";
            } else {
                $message = "Failed to delete monitor.";
            }
            header('Location: index.php?message=' . urlencode($message));
            exit;
        }
        break;

    case 'check':
        if (isset($_GET['id'])) {
            $monitorData = $monitor->getMonitor($_GET['id']);
            if ($monitorData) {
                $result = $monitor->checkSite($monitorData);
                $message = "Monitor status: " . ucfirst($result['status']) . ". " . $result['message'];
                if (isset($result['http_code'])) {
                    $message .= " HTTP Status Code: " . $result['http_code'];
                }
            } else {
                $message = "Monitor not found.";
            }
            header('Location: index.php?message=' . urlencode($message));
            exit;
        }
        break;

    case 'test_email':
        if (isset($_GET['id'])) {
            $monitorData = $monitor->getMonitor($_GET['id']);
            if ($monitorData) {
                $testResult = [
                    'status' => 'down',
                    'message' => 'This is a test alert email.',
                    'http_code' => 404,
                    'response_time' => 1000,
                    'download_size' => 0,
                    'error' => null
                ];
                if ($monitor->sendAlert($monitorData, $testResult)) {
                    $message = "Test alert email(s) sent successfully for monitor '{$monitorData['name']}'.";
                } else {
                    $message = "Some or all test alert emails failed to send for monitor '{$monitorData['name']}'. Check error logs for details.";
                }
            } else {
                $message = "Monitor not found.";
            }
            header('Location: index.php?message=' . urlencode($message));
            exit;
        }
        break;
    case 'test_email':
        if (isset($_GET['id'])) {
            $monitorData = $monitor->getMonitor($_GET['id']);
            if ($monitorData) {
                $testResult = [
                    'status' => 'down',
                    'message' => 'This is a test alert email.',
                    'http_code' => 404,
                    'response_time' => 1000,
                    'download_size' => 0,
                    'error' => null
                ];
                if ($monitor->sendAlert($monitorData, $testResult)) {
                    $message = "Test alert email(s) sent successfully for monitor '{$monitorData['name']}'.";
                } else {
                    $message = "Some or all test alert emails failed to send for monitor '{$monitorData['name']}'. Check error logs for details.";
                }
            } else {
                $message = "Monitor not found.";
            }
            header('Location: index.php?message=' . urlencode($message));
            exit;
        }
        break;
}

$monitors = $monitor->getAllMonitors();

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/styles.css">
</head>

<body>
    <div class="sorgs-container">
        <h1>Dashboard</h1>
        <nav class="sorgs-nav">
            <ul>
                <li><a href="<?php echo BASE_URL; ?>/public/index.php">Monitors</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/index.php?action=add">Add Monitor</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/settings/index.php">Settings</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/logout.php">Logout</a></li>
            </ul>
        </nav>

        <?php if ($message): ?>
            <div class="sorgs-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($action == 'add' || $action == 'edit'): ?>
            <h2><?php echo $action == 'add' ? 'Add' : 'Edit'; ?> Monitor</h2>
            <form method="post" action="" class="sorgs-form">
                <?php if ($action == 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $monitorData['id']; ?>">
                <?php endif; ?>
                <div class="sorgs-form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" required value="<?php echo isset($monitorData['name']) ? htmlspecialchars($monitorData['name']) : ''; ?>">
                </div>

                <div class="sorgs-form-group">
                    <label for="url">URL:</label>
                    <input type="url" id="url" name="url" required value="<?php echo isset($monitorData['url']) ? htmlspecialchars($monitorData['url']) : ''; ?>">
                </div>

                <div class="sorgs-form-group">
                    <label for="check_interval">Check Interval (seconds):</label>
                    <input type="number" id="check_interval" name="check_interval" required value="<?php echo isset($monitorData['check_interval']) ? htmlspecialchars($monitorData['check_interval']) : '300'; ?>">
                </div>

                <div class="sorgs-form-group">
                    <label for="expected_status_code">Expected Status Code:</label>
                    <input type="number" id="expected_status_code" name="expected_status_code" required value="<?php echo isset($monitorData['expected_status_code']) ? htmlspecialchars($monitorData['expected_status_code']) : '200'; ?>">
                </div>

                <div class="sorgs-form-group">
                    <label for="expected_keyword">Expected Keyword:</label>
                    <input type="text" id="expected_keyword" name="expected_keyword" value="<?php echo isset($monitorData['expected_keyword']) ? htmlspecialchars($monitorData['expected_keyword']) : ''; ?>">
                </div>

                <div class="sorgs-form-group">
                    <label for="notification_emails">Notification Emails (space-separated):</label>
                    <input type="text" id="notification_emails" name="notification_emails" value="<?php echo isset($monitorData['notification_emails']) ? htmlspecialchars($monitorData['notification_emails']) : ''; ?>">
                </div>

                <div class="sorgs-form-group">
                    <input type="submit" value="<?php echo $action == 'add' ? 'Add' : 'Update'; ?> Monitor">
                </div>
            </form>
        <?php else: ?>
            <div class="sorgs-dashboard-actions">
                <h2>Monitors</h2>
                <a href="<?php echo BASE_URL; ?>/public/index.php?action=add" class="sorgs-button">Add New Monitor</a>
            </div>
            <table class="sorgs-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>URL</th>
                        <th>Check Interval</th>
                        <th>Last Check</th>
                        <th>Status</th>
                        <th>Notification Emails</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monitors as $m): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['name']); ?></td>
                            <td><?php echo htmlspecialchars($m['url']); ?></td>
                            <td><?php echo htmlspecialchars($m['check_interval']); ?> seconds</td>
                            <td><?php echo $m['last_check_time'] ? htmlspecialchars($m['last_check_time']) : 'Never'; ?></td>
                            <td><?php echo $m['last_status'] ? htmlspecialchars(ucfirst($m['last_status'])) : 'Unknown'; ?></td>
                            <td><?php echo htmlspecialchars($m['notification_emails']); ?></td>
                            <td class="sorgs-actions">
                                <a href="index.php?action=edit&id=<?php echo $m['id']; ?>" class="sorgs-button sorgs-button-small sorgs-button-secondary">Edit</a>
                                <a href="index.php?action=delete&id=<?php echo $m['id']; ?>" onclick="return confirm('Are you sure you want to delete this monitor?')" class="sorgs-button sorgs-button-small sorgs-button-danger">Delete</a>
                                <a href="index.php?action=check&id=<?php echo $m['id']; ?>" class="sorgs-button sorgs-button-small sorgs-button-primary">Check</a>
                                <a href="index.php?action=test_email&id=<?php echo $m['id']; ?>" class="sorgs-button sorgs-button-small">Test</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>

</html>