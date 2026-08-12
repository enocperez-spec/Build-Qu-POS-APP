<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/CsvReader.php';
require_once __DIR__ . '/AuthService.php';

header('Content-Type: application/json');

try {
    $started = microtime(true);
    $logId = null;
    $lockAcquired = false;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Use POST to import a store CSV.']);
        exit;
    }

    if (!isset($_FILES['storeCsv']) || !is_uploaded_file($_FILES['storeCsv']['tmp_name'])) {
        throw new RuntimeException('Upload a Store Information CSV first.');
    }

    $pdo = Database::fromConfig();
    if (!$pdo) {
        throw new RuntimeException('Database configuration is required for store imports.');
    }

    $actingUser = Auth::requireSection($pdo, Database::SECTION_UPLOAD);
    Auth::requireTechOrAdmin();

    if (!Database::acquireApiLock($pdo, Database::JOB_STORES, 'manual-store-import')) {
        throw new RuntimeException('A QU EI store data retrieval is already running.');
    }
    $lockAcquired = true;
    $logId = Database::startApiLog($pdo, 'Manual', $actingUser, 1, Database::JOB_STORES);

    $rows = CsvReader::read($_FILES['storeCsv']['tmp_name']);
    if (count($rows) === 0) {
        throw new RuntimeException('The Store Information CSV does not contain any rows.');
    }

    $importId = Database::saveStoreImport($pdo, (string)$_FILES['storeCsv']['name'], $rows);
    Database::finishApiLog($pdo, $logId, 'Successful', [
        'attempts' => 1,
        'recordsReceived' => count($rows),
        'recordsAdded' => count($rows),
        'recordsUpdated' => 0,
        'recordsSkipped' => 0,
        'durationMs' => (int)((microtime(true) - $started) * 1000),
    ], Database::JOB_STORES);
    Database::releaseApiLock($pdo, Database::JOB_STORES);
    $lockAcquired = false;
    SecurityService::audit($pdo, $actingUser, 'file_upload', 'Store Information CSV uploaded', 'store_import', (string)$importId, (string)$_FILES['storeCsv']['name'], 'successful', [
        'rowCount' => count($rows),
    ]);

    echo json_encode([
        'ok' => true,
        'importId' => $importId,
        'rowCount' => count($rows),
        'health' => Database::dashboardHealth($pdo),
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $logId) {
        Database::finishApiLog($pdo, $logId, 'Failed', [
            'attempts' => 1,
            'durationMs' => (int)((microtime(true) - ($started ?? microtime(true))) * 1000),
            'errorMessage' => $exception->getMessage(),
        ], Database::JOB_STORES);
    }
    if (isset($pdo) && $pdo instanceof PDO && !empty($lockAcquired)) {
        Database::releaseApiLock($pdo, Database::JOB_STORES);
    }
    if (isset($pdo, $actingUser) && $pdo instanceof PDO) {
        SecurityService::audit($pdo, $actingUser, 'file_upload', 'Store Information CSV upload', 'csv_file', null, $_FILES['storeCsv']['name'] ?? null, 'failed', [], $exception->getMessage());
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
