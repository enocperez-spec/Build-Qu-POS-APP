<?php
declare(strict_types=1);

require_once __DIR__ . '/ReportService.php';
require_once __DIR__ . '/Database.php';

header('Content-Type: application/json');

function automationToken(): string
{
    $configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.local.php';
    if (!is_file($configPath)) {
        return '';
    }
    $config = require $configPath;
    return (string)($config['automation']['import_token'] ?? '');
}

function requestBearerToken(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return trim($matches[1]);
    }
    return trim((string)($_SERVER['HTTP_X_QU_IMPORT_TOKEN'] ?? ''));
}

try {
    $started = microtime(true);
    $logId = null;
    $pdo = null;
    $lockAcquired = false;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Use POST to import a CSV.']);
        exit;
    }

    $expectedToken = automationToken();
    $providedToken = requestBearerToken();
    if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
        exit;
    }

    if (!isset($_FILES['currentCsv']) || !is_uploaded_file($_FILES['currentCsv']['tmp_name'])) {
        throw new RuntimeException('Upload a currentCsv file.');
    }

    $pdo = Database::fromConfig();
    if (!$pdo) {
        throw new RuntimeException('Database configuration is required for cloud CSV imports.');
    }
    if (!Database::acquireApiLock($pdo, 'qu_ei_terminals_csv', 'cloud-import')) {
        throw new RuntimeException('A QU EI data retrieval is already running.');
    }
    $lockAcquired = true;
    $triggerType = (string)($_POST['triggerType'] ?? $_SERVER['HTTP_X_QU_TRIGGER_TYPE'] ?? 'Scheduled');
    $attempts = (int)($_POST['attempts'] ?? $_SERVER['HTTP_X_QU_ATTEMPTS'] ?? 1);
    $logId = Database::startApiLog($pdo, $triggerType === 'Manual' ? 'Manual' : 'Scheduled', null, max(1, $attempts));

    $result = ReportService::generate($_FILES['currentCsv'], null, dirname(__DIR__), $pdo);
    Database::saveReport($pdo, $result);
    $records = (int)($result['report']['summary']['posAppTerminals'] ?? 0);
    Database::finishApiLog($pdo, $logId, 'Successful', [
        'attempts' => max(1, $attempts),
        'recordsReceived' => $records,
        'recordsAdded' => $records,
        'recordsUpdated' => 0,
        'recordsSkipped' => 0,
        'durationMs' => (int)((microtime(true) - $started) * 1000),
    ]);
    Database::releaseApiLock($pdo, 'qu_ei_terminals_csv');
    $lockAcquired = false;
    $result['databaseSaved'] = true;
    echo json_encode(['ok' => true] + $result);
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $logId) {
        Database::finishApiLog($pdo, $logId, 'Failed', [
            'attempts' => $attempts ?? 1,
            'durationMs' => (int)((microtime(true) - ($started ?? microtime(true))) * 1000),
            'errorMessage' => $exception->getMessage(),
        ]);
    }
    if ($pdo instanceof PDO && !empty($lockAcquired)) {
        Database::releaseApiLock($pdo, 'qu_ei_terminals_csv');
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
