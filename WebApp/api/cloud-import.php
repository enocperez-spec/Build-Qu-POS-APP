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

    $result = ReportService::generate($_FILES['currentCsv'], null, dirname(__DIR__), $pdo);
    Database::saveReport($pdo, $result);
    $result['databaseSaved'] = true;
    echo json_encode(['ok' => true] + $result);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
