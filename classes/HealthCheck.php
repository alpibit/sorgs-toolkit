<?php

/**
 * Base class for all health checks
 */
abstract class HealthCheck
{
    protected $name;
    protected $critical;

    public function __construct($name, $critical = false)
    {
        $this->name = $name;
        $this->critical = $critical;
    }

    /**
     * Get the name of this health check
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if this is a critical check (blocks monitoring if failed)
     */
    public function isCritical(): bool
    {
        return $this->critical;
    }

    /**
     * Execute the health check
     * @return array ['success' => bool, 'message' => string, 'duration' => float]
     */
    public function execute(): array
    {
        $startTime = microtime(true);

        try {
            $result = $this->check();
            $result['duration'] = round((microtime(true) - $startTime) * 1000, 2); // in ms
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Check failed with exception: ' . $e->getMessage(),
                'duration' => round((microtime(true) - $startTime) * 1000, 2)
            ];
        }
    }

    /**
     * Perform the actual health check
     * @return array ['success' => bool, 'message' => string]
     */
    abstract protected function check(): array;
}
