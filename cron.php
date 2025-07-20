<?php
$lockFile = __DIR__ . '/cron.lock';
$lockFilePointer = fopen($lockFile, 'w');

if (!$lockFilePointer) {
    die('Cannot create lock file');
}

if (!flock($lockFilePointer, LOCK_EX | LOCK_NB)) {
    echo "Cron job is already running. Exiting.\n";
    exit;
}

if (!file_exists(__DIR__ . '/config/database.php')) {
    echo "Database configuration not found. Please run the installer first.\n";
    exit(1);
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/autoload.php';

// Run health checks first
try {
    $healthChecker = new HealthChecker();

    // Add critical checks
    $healthChecker->addCheck(new DatabaseHealthCheck());
    $healthChecker->addCheck(new NetworkHealthCheck());

    // Run critical checks
    echo "Running health checks...\n";
    if (!$healthChecker->runCriticalChecks()) {
        $results = $healthChecker->getLastResults();
        echo "Critical health checks failed!\n";
        foreach ($results as $checkName => $result) {
            if (!$result['success']) {
                echo "  - $checkName: {$result['message']} (took {$result['duration']}ms)\n";
            }
        }

        // Log the failure
        $healthChecker->logResults();

        // TODO: Notify admin
        exit(1);
    }

    echo "Health checks passed. Proceeding with monitoring...\n";
} catch (Exception $e) {
    echo "Health check system error: " . $e->getMessage() . "\n";
    error_log("Cron job health check failed with exception: " . $e->getMessage());
    exit(1);
}

// Proceed with normal monitoring
try {
    $monitor = new UptimeMonitor();
    $monitor->checkDueMonitors();
    echo "Monitoring completed successfully.\n";
} catch (Exception $e) {
    echo "Monitoring error: " . $e->getMessage() . "\n";
    error_log("Cron job monitoring failed: " . $e->getMessage());
    exit(1);
}

echo "Cron job completed at " . date('Y-m-d H:i:s') . "\n";

flock($lockFilePointer, LOCK_UN);
fclose($lockFilePointer);
unlink($lockFile);
