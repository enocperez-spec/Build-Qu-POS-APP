<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/CsvReader.php';

header('Content-Type: application/json');

function storeAutomationToken(): string
{
    $configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.local.php';
    if (!is_file($configPath)) {
        return '';
    }
    $config = require $configPath;
    return (string)($config['automation']['import_token'] ?? '');
}

function storeRequestBearerToken(): string
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
        echo json_encode(['ok' => false, 'error' => 'Use POST to import a store CSV.']);
        exit;
    }

    $expectedToken = storeAutomationToken();
    $providedToken = storeRequestBearerToken();
    if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
        exit;
    }

    $uploadedFile = $_FILES['storeCsv'] ?? $_FILES['currentCsv'] ?? null;
    if (!$uploadedFile || !is_uploaded_file($uploadedFile['tmp_name'])) {
        throw new RuntimeException('Upload a storeCsv file.');
    }

    $pdo = Database::fromConfig();
    if (!$pdo) {
        throw new RuntimeException('Database configuration is required for store imports.');
    }
    if (!Database::acquireApiLock($pdo, Database::JOB_STORES, 'cloud-store-import')) {
        throw new RuntimeException('A QU EI store data retrieval is already running.');
    }
    $lockAcquired = true;

    $triggerType = (string)($_POST['triggerType'] ?? $_SERVER['HTTP_X_QU_TRIGGER_TYPE'] ?? 'Scheduled');
    $attempts = (int)($_POST['attempts'] ?? $_SERVER['HTTP_X_QU_ATTEMPTS'] ?? 1);
    $logId = Database::startApiLog($pdo, $triggerType === 'Manual' ? 'Manual' : 'Scheduled', null, max(1, $attempts), Database::JOB_STORES);

    $rows = CsvReader::read($uploadedFile['tmp_name']);
    if (count($rows) === 0) {
        throw new RuntimeException('The store CSV does not contain any rows.');
    }
    $importId = Database::saveStoreImport($pdo, (string)$uploadedFile['name'], $rows);
    Database::finishApiLog($pdo, $logId, 'Successful', [
        'attempts' => max(1, $attempts),
        'recordsReceived' => count($rows),
        'recordsAdded' => count($rows),
        'recordsUpdated' => 0,
        'recordsSkipped' => 0,
        'durationMs' => (int)((microtime(true) - $started) * 1000),
    ], Database::JOB_STORES);
    Database::releaseApiLock($pdo, Database::JOB_STORES);
    $lockAcquired = false;

    echo json_encode(['ok' => true, 'importId' => $importId, 'rowCount' => count($rows)]);
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $logId) {
        Database::finishApiLog($pdo, $logId, 'Failed', [
            'attempts' => $attempts ?? 1,
            'durationMs' => (int)((microtime(true) - ($started ?? microtime(true))) * 1000),
            'errorMessage' => $exception->getMessage(),
        ], Database::JOB_STORES);
    }
    if ($pdo instanceof PDO && !empty($lockAcquired)) {
        Database::releaseApiLock($pdo, Database::JOB_STORES);
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
