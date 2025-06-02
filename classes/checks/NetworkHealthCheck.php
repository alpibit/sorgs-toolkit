<?php

/**
 * Check network connectivity to ensure we can reach external services
 */
class NetworkHealthCheck extends HealthCheck
{
    private $endpoints = [
        'https://1.1.1.1' => 'Cloudflare DNS',
        'https://8.8.8.8' => 'Google DNS',
        'https://dns.google' => 'Google DNS HTTPS'
    ];

    public function __construct()
    {
        parent::__construct('Network Connectivity', true);
    }

    protected function check(): array
    {
        $failedEndpoints = [];
        $successCount = 0;

        foreach ($this->endpoints as $endpoint => $name) {
            if ($this->checkEndpoint($endpoint)) {
                $successCount++;
                // If we can reach at least one endpoint, we have internet
                break;
            } else {
                $failedEndpoints[] = $name;
            }
        }

        if ($successCount > 0) {
            return [
                'success' => true,
                'message' => 'Network connectivity verified'
            ];
        }

        // Additional check: Can we resolve DNS?
        $testHosts = ['google.com', 'cloudflare.com', 'github.com'];
        $dnsSuccess = false;

        foreach ($testHosts as $host) {
            if (gethostbyname($host) !== $host) {
                $dnsSuccess = true;
                break;
            }
        }

        if (!$dnsSuccess) {
            return [
                'success' => false,
                'message' => 'DNS resolution failed. Cannot resolve any test hosts.'
            ];
        }

        return [
            'success' => false,
            'message' => 'Cannot reach any test endpoints: ' . implode(', ', $failedEndpoints) . '. DNS works but HTTP(S) requests fail.'
        ];
    }

    private function checkEndpoint(string $url): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // For IP addresses
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => false
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // We just need to know we can connect, any HTTP response is fine
        return $result !== false && $httpCode > 0;
    }
}
