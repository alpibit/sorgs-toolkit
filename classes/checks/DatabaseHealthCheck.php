<?php

/**
 * Check database connectivity and basic operations
 */
class DatabaseHealthCheck extends HealthCheck
{
    private $db;

    public function __construct($db = null)
    {
        parent::__construct('Database Connectivity', true);
        $this->db = $db;
    }

    protected function check(): array
    {
        try {
            // Get database connection
            if (!$this->db) {
                $this->db = new Database();
            }
            $conn = $this->db->connect();

            // Test 1: Basic connectivity
            $stmt = $conn->query("SELECT 1 as test");
            if (!$stmt) {
                return [
                    'success' => false,
                    'message' => 'Failed to execute test query'
                ];
            }

            // Test 2: Check if critical tables exist
            $tables = ['monitors', 'settings', 'users'];
            $stmt = $conn->query("SHOW TABLES");
            $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $missingTables = array_diff($tables, $existingTables);
            if (!empty($missingTables)) {
                return [
                    'success' => false,
                    'message' => 'Missing critical tables: ' . implode(', ', $missingTables)
                ];
            }

            // Test 3: Check if we can write (test on settings table)
            $testKey = 'health_check_' . time();
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $testValue = date('Y-m-d H:i:s');
            $stmt->execute([$testKey, $testValue, $testValue]);

            // Clean up test entry
            $stmt = $conn->prepare("DELETE FROM settings WHERE setting_key = ?");
            $stmt->execute([$testKey]);

            return [
                'success' => true,
                'message' => 'Database is healthy and all critical tables exist'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Unexpected error: ' . $e->getMessage()
            ];
        }
    }
}
