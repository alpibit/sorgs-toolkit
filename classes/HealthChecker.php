<?php

/**
 * Main health checker that runs all registered checks
 */
class HealthChecker
{
    private $checks = [];
    private $results = [];

    /**
     * Add a health check
     */
    public function addCheck(HealthCheck $check): void
    {
        $this->checks[] = $check;
    }

    /**
     * Run only critical checks
     * @return bool True if all critical checks pass
     */
    public function runCriticalChecks(): bool
    {
        $this->results = [];
        $allPassed = true;

        foreach ($this->checks as $check) {
            if (!$check->isCritical()) {
                continue;
            }

            $result = $check->execute();
            $this->results[$check->getName()] = $result;

            if (!$result['success']) {
                $allPassed = false;
                error_log("Critical health check failed: {$check->getName()} - {$result['message']}");
            }
        }

        return $allPassed;
    }

    /**
     * Run all checks
     * @return array Results of all checks
     */
    public function runAllChecks(): array
    {
        $this->results = [];
        $summary = [
            'timestamp' => date('Y-m-d H:i:s'),
            'overall_status' => 'healthy',
            'critical_checks_passed' => true,
            'total_checks' => count($this->checks),
            'passed_checks' => 0,
            'failed_checks' => 0,
            'checks' => []
        ];

        foreach ($this->checks as $check) {
            $result = $check->execute();
            $result['critical'] = $check->isCritical();

            $this->results[$check->getName()] = $result;
            $summary['checks'][$check->getName()] = $result;

            if ($result['success']) {
                $summary['passed_checks']++;
            } else {
                $summary['failed_checks']++;
                if ($check->isCritical()) {
                    $summary['critical_checks_passed'] = false;
                    $summary['overall_status'] = 'critical';
                } elseif ($summary['overall_status'] !== 'critical') {
                    $summary['overall_status'] = 'degraded';
                }
            }

            // Log failures
            if (!$result['success']) {
                error_log("Health check failed: {$check->getName()} - {$result['message']} (Duration: {$result['duration']}ms)");
            }
        }

        return $summary;
    }

    /**
     * Get the last results
     */
    public function getLastResults(): array
    {
        return $this->results;
    }

    /**
     * Log results to a file
     */
    public function logResults(?string $logFile = null): void
    {
        if ($logFile === null) {
            $logFile = __DIR__ . '/../logs/health_checks.log';
        }

        // Ensure the logs directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logEntry = date('Y-m-d H:i:s') . ' - ' . json_encode($this->results) . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
