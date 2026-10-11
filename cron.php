<?php
if (PHP_SAPI !== 'cli') {
    ob_start();
    register_shutdown_function(function () {
        ob_end_clean();
    });
}

$lockFile = __DIR__ . '/cron.lock';
$lockFilePointer = fopen($lockFile, 'c+');

if (!$lockFilePointer) {
    error_log('Cannot open cron lock file.');
    exit(1);
}

if (!flock($lockFilePointer, LOCK_EX | LOCK_NB, $wouldBlock)) {
    fclose($lockFilePointer);
    if (!$wouldBlock) {
        error_log('Cannot acquire cron lock.');
        exit(1);
    }
    echo "Cron job is already running. Exiting.\n";
    exit(0);
}

$lastStartTime = stream_get_contents($lockFilePointer);
if ($lastStartTime === false || ($lastStartTime !== '' && (
    !preg_match('/\A[0-9]+\.[0-9]{6}\n\z/', $lastStartTime)
    || !is_finite((float) $lastStartTime)
))) {
    error_log('Cannot read a valid cron start time.');
    fclose($lockFilePointer);
    exit(1);
}

$startTime = microtime(true);
if ($lastStartTime !== '' && $startTime - (float) $lastStartTime < 30) {
    echo "Cron job is within its 30-second cooldown. Exiting.\n";
    fclose($lockFilePointer);
    exit(0);
}

$startTimeRecord = sprintf("%.6F\n", $startTime);
if (!rewind($lockFilePointer)
    || fwrite($lockFilePointer, $startTimeRecord) !== strlen($startTimeRecord)
    || !ftruncate($lockFilePointer, strlen($startTimeRecord))
    || !fflush($lockFilePointer)) {
    error_log('Cannot save cron start time.');
    fclose($lockFilePointer);
    exit(1);
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
} catch (Throwable $e) {
    echo "Health check system error: " . $e->getMessage() . "\n";
    error_log("Cron job health check failed with exception: " . $e->getMessage());
    exit(1);
}

// Proceed with normal monitoring
try {
    $monitor = new UptimeMonitor();
    $monitor->checkDueMonitors();
    echo "Monitoring completed successfully.\n";
} catch (Throwable $e) {
    echo "Monitoring error: " . $e->getMessage() . "\n";
    error_log("Cron job monitoring failed: " . $e->getMessage());
    exit(1);
}

echo "Cron job completed at " . date('Y-m-d H:i:s') . "\n";

flock($lockFilePointer, LOCK_UN);
fclose($lockFilePointer);
