<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/autoload.php';

$monitor = new UptimeMonitor();
$monitor->checkDueMonitors();

echo "Cron job completed at " . date('Y-m-d H:i:s') . "\n";
