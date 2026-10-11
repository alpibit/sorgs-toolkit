<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$fixture = sys_get_temp_dir() . '/sorgs-cron-test-' . bin2hex(random_bytes(6));
$processes = [];
$processNumber = 0;
$exitCode = 0;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function setTime(float $time): void
{
    global $fixture;
    file_put_contents($fixture . '/clock', sprintf('%.6F', $time));
}

function events(): string
{
    global $fixture;
    return is_file($fixture . '/events') ? file_get_contents($fixture . '/events') : '';
}

function startProcess(array $command): int
{
    global $fixture, $processes, $processNumber;
    $id = ++$processNumber;
    $output = $fixture . '/process-' . $id . '.out';
    $error = $fixture . '/process-' . $id . '.err';
    $process = proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', $output, 'w'],
        2 => ['file', $error, 'w'],
    ], $pipes, $fixture);
    check(is_resource($process), 'Could not start subprocess');
    $processes[$id] = [$process, $output, $error];
    return $id;
}

function finishProcess(int $id): array
{
    global $processes;
    [$process, $output, $error] = $processes[$id];
    $deadline = microtime(true) + 5;
    do {
        $status = proc_get_status($process);
        if (!$status['running']) {
            $exitCode = $status['exitcode'];
            $closedCode = proc_close($process);
            unset($processes[$id]);
            return [$exitCode >= 0 ? $exitCode : $closedCode, file_get_contents($output), file_get_contents($error)];
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Subprocess timed out: ' . file_get_contents($error));
}

function startCron(): int
{
    global $fixture;
    return startProcess([PHP_BINARY, $fixture . '/cron.php']);
}

function runCron(): array
{
    return finishProcess(startCron());
}

function waitForProbe(int $id): void
{
    global $processes;
    $deadline = microtime(true) + 3;
    do {
        if (str_contains(events(), "probe\n")) {
            return;
        }
        check(proc_get_status($processes[$id][0])['running'], 'Cron exited before reaching the probe');
        usleep(10000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Cron did not reach the probe');
}

function requestHttp(string $url, int $expectedStatus = 200): void
{
    $body = file_get_contents($url, false, stream_context_create([
        'http' => ['ignore_errors' => true, 'timeout' => 3],
    ]));
    check($body !== false, 'HTTP request failed');
    check(isset($http_response_header[0]) && preg_match('/^HTTP\/\S+ ' . $expectedStatus . '\b/', $http_response_header[0]), 'Expected HTTP ' . $expectedStatus);
    check($body === '', 'HTTP cron exposed its output');
}

function checkSkipped(?string $url = null): void
{
    global $fixture;
    $before = events();
    $timestamp = file_get_contents($fixture . '/cron.lock');
    if ($url === null) {
        [$exitCode, , $error] = runCron();
        check($exitCode === 0, 'Skipped CLI cron failed: ' . $error);
    } else {
        requestHttp($url);
    }
    check(events() === $before, 'Skipped cron entered application code');
    check(file_get_contents($fixture . '/cron.lock') === $timestamp, 'Skipped cron changed the timestamp');
}

function test(string $name, callable $run): void
{
    global $fixture;
    foreach (['cron.lock', 'events', 'hold', 'critical-fail', 'health-error', 'monitor-error'] as $file) {
        $path = $fixture . '/' . $file;
        if (is_dir($path)) {
            rmdir($path);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }
    setTime(1000);
    $run();
    echo 'PASS ' . $name . "\n";
}

function removeFixture(string $directory): void
{
    foreach (new FilesystemIterator($directory) as $entry) {
        if ($entry->isDir()) {
            removeFixture($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
}

try {
    mkdir($fixture . '/config', 0700, true);
    copy(__FILE__, $fixture . '/test-runner.php');
    $source = file_get_contents(dirname(__DIR__) . '/cron.php');
    $clock = <<<'PHP'
<?php
namespace CronFixture;
use Throwable;
function microtime($asFloat = false) {
    return (float) file_get_contents(__DIR__ . '/clock');
}
PHP;
    file_put_contents($fixture . '/cron.php', preg_replace('/^<\?php/', $clock, $source, 1));
    foreach (['config', 'database'] as $file) {
        file_put_contents($fixture . '/config/' . $file . '.php', '<?php file_put_contents(dirname(__DIR__) . "/events", "' . $file . '\n", FILE_APPEND);');
    }
    file_put_contents($fixture . '/config/autoload.php', <<<'PHP'
<?php
namespace CronFixture;
function record($event) {
    file_put_contents(dirname(__DIR__) . '/events', $event . "\n", FILE_APPEND);
}
record('autoload');
class DatabaseHealthCheck {}
class NetworkHealthCheck {}
class HealthChecker {
    public function __construct() { record('health'); }
    public function addCheck($check) {}
    public function runCriticalChecks() {
        record('checks');
        if (file_exists(dirname(__DIR__) . '/health-error')) {
            throw new \RuntimeException('Injected health failure');
        }
        return !file_exists(dirname(__DIR__) . '/critical-fail');
    }
    public function getLastResults() { return []; }
    public function logResults() { record('health-log'); }
}
class UptimeMonitor {
    public function __construct() { record('monitor'); }
    public function checkDueMonitors() {
        record('probe');
        if (file_exists(dirname(__DIR__) . '/monitor-error')) {
            throw new \RuntimeException('Injected monitor failure');
        }
        while (file_exists(dirname(__DIR__) . '/hold')) {
            clearstatcache();
            usleep(10000);
        }
    }
}
PHP);

    test('First run and exact 29-/30-second boundary', function () use ($fixture) {
        check(runCron()[0] === 0, 'First run failed');
        check(substr_count(events(), "probe\n") === 1, 'First run did not execute once');
        check(file_get_contents($fixture . '/cron.lock') === "1000.000000\n", 'Wrong persisted start');
        setTime(1029);
        checkSkipped();
        setTime(1030);
        check(runCron()[0] === 0, 'Boundary run failed');
        check(substr_count(events(), "probe\n") === 2, 'Run was not admitted at exactly 30 seconds');
    });

    test('Fractional start times preserve the full interval', function () {
        setTime(1000.9);
        check(runCron()[0] === 0, 'First run failed');
        setTime(1030);
        checkSkipped();
        setTime(1030.9);
        check(runCron()[0] === 0, 'Fractional boundary run failed');
        check(substr_count(events(), "probe\n") === 2, 'Fractional boundary was not admitted');
    });

    test('Empty lock file admits a first run', function () use ($fixture) {
        touch($fixture . '/cron.lock');
        check(runCron()[0] === 0 && str_contains(events(), "probe\n"), 'Empty lock prevented execution');
    });

    test('Concurrent requests skip, including after 30 seconds', function () use ($fixture) {
        touch($fixture . '/hold');
        $worker = startCron();
        waitForProbe($worker);
        $before = events();
        $requests = [];
        for ($i = 0; $i < 6; $i++) {
            $requests[] = startCron();
        }
        foreach ($requests as $request) {
            check(finishProcess($request)[0] === 0, 'Concurrent request failed');
        }
        check(events() === $before, 'Concurrent request entered application code');
        setTime(1031);
        checkSkipped();
        unlink($fixture . '/hold');
        check(finishProcess($worker)[0] === 0, 'Held worker failed');
        check(runCron()[0] === 0, 'Completed worker kept its lock');
        check(substr_count(events(), "probe\n") === 2, 'Expected one run after releasing the lock');
    });

    foreach (['critical-fail', 'health-error', 'monitor-error'] as $failure) {
        test('Failed run consumes its interval: ' . $failure, function () use ($fixture, $failure) {
            touch($fixture . '/' . $failure);
            check(runCron()[0] !== 0, 'Injected failure was not reached');
            unlink($fixture . '/' . $failure);
            setTime(1029);
            checkSkipped();
            setTime(1030);
            check(runCron()[0] === 0, 'Failed run prevented a later admission');
        });
    }

    test('Missing configuration consumes its interval before application work', function () use ($fixture) {
        rename($fixture . '/config/database.php', $fixture . '/config/database.saved');
        try {
            check(runCron()[0] !== 0, 'Missing configuration unexpectedly succeeded');
            check(events() === '', 'Application was loaded without configuration');
        } finally {
            rename($fixture . '/config/database.saved', $fixture . '/config/database.php');
        }
        setTime(1029);
        checkSkipped();
    });

    test('Interrupted run retains cooldown and releases its process lock', function () use ($fixture) {
        global $processes;
        touch($fixture . '/hold');
        $worker = startCron();
        waitForProbe($worker);
        proc_terminate($processes[$worker][0]);
        finishProcess($worker);
        unlink($fixture . '/hold');
        setTime(1029);
        checkSkipped();
        setTime(1030);
        check(runCron()[0] === 0, 'Interrupted worker retained its lock');
        check(substr_count(events(), "probe\n") === 2, 'Expected a run after the interrupted interval');
    });

    test('Malformed and partial timestamps fail closed', function () use ($fixture) {
        foreach (["invalid\n", '1000.000000', "1000.12345\n", "1000.000000\nextra", "1000\n"] as $timestamp) {
            file_put_contents($fixture . '/cron.lock', $timestamp);
            check(runCron()[0] !== 0, 'Malformed timestamp was accepted');
            check(events() === '', 'Malformed timestamp allowed application work');
            check(file_get_contents($fixture . '/cron.lock') === $timestamp, 'Malformed timestamp was overwritten');
        }
    });

    test('Future timestamps do not admit an early run', function () use ($fixture) {
        file_put_contents($fixture . '/cron.lock', "1100.000000\n");
        checkSkipped();
    });

    test('Unusable lock storage fails closed', function () use ($fixture) {
        mkdir($fixture . '/cron.lock');
        check(runCron()[0] !== 0, 'Unusable lock storage unexpectedly succeeded');
        check(events() === '', 'Unusable lock storage allowed application work');
    });

    test('Read-only lock storage fails closed', function () use ($fixture) {
        file_put_contents($fixture . '/cron.lock', "900.000000\n");
        chmod($fixture . '/cron.lock', 0400);
        clearstatcache();
        try {
            if (is_writable($fixture . '/cron.lock')) {
                echo "SKIP permission check: process can write read-only files\n";
                return;
            }
            check(runCron()[0] !== 0, 'Read-only lock storage unexpectedly succeeded');
            check(events() === '', 'Read-only lock storage allowed application work');
        } finally {
            chmod($fixture . '/cron.lock', 0600);
        }
    });

    test('HTTP and CLI share cooldown in both directions', function () use ($fixture) {
        global $processes;
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        check($socket !== false, 'Cannot allocate HTTP test port: ' . $errorMessage);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $server = startProcess([PHP_BINARY, '-S', $address, '-t', $fixture]);
        $deadline = microtime(true) + 3;
        do {
            $connection = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.1);
            if ($connection !== false) {
                fclose($connection);
                break;
            }
            check(proc_get_status($processes[$server][0])['running'], 'HTTP test server failed to start');
            usleep(10000);
        } while (microtime(true) < $deadline);
        check($connection !== false, 'HTTP test server did not become ready');
        requestHttp('http://' . $address . '/test-runner.php', 404);
        $url = 'http://' . $address . '/cron.php';
        requestHttp($url);
        check(substr_count(events(), "probe\n") === 1, 'HTTP did not execute cron');
        setTime(1029);
        checkSkipped();
        setTime(1030);
        check(runCron()[0] === 0, 'CLI boundary run failed');
        check(substr_count(events(), "probe\n") === 2, 'CLI did not execute after HTTP cooldown');
        setTime(1059);
        checkSkipped($url);
        setTime(1060);
        requestHttp($url);
        check(substr_count(events(), "probe\n") === 3, 'HTTP did not execute after CLI cooldown');
        proc_terminate($processes[$server][0]);
        finishProcess($server);
    });
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach ($processes as [$process]) {
        proc_terminate($process, 9);
        proc_close($process);
    }
    if (is_dir($fixture)) {
        removeFixture($fixture);
    }
}

exit($exitCode);
