<?php
declare(strict_types=1);

require_once __DIR__ . '/ReportService.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AuthService.php';

header('Content-Type: application/json');

try {
    Auth::requireTechOrAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Use POST to generate a report.']);
        exit;
    }

    if (!isset($_FILES['currentCsv']) || !is_uploaded_file($_FILES['currentCsv']['tmp_name'])) {
        throw new RuntimeException('Upload a current terminal CSV first.');
    }

    $pdo = Database::fromConfig();
    if (!$pdo) {
        throw new RuntimeException('Database configuration is required for CSV uploads.');
    }
    $result = ReportService::generate($_FILES['currentCsv'], null, dirname(__DIR__), $pdo);
    Database::saveReport($pdo, $result);
    $result['databaseSaved'] = true;
    echo json_encode(['ok' => true] + $result);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
