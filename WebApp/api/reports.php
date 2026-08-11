<?php
declare(strict_types=1);

require_once __DIR__ . '/ReportService.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AuthService.php';

header('Content-Type: application/json');

try {
    Auth::requireLogin();

    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';
    $pdo = Database::fromConfig();
    if ($action === 'latest') {
        if ($pdo) {
            $latest = Database::latestReport($pdo);
            $report = $latest ? ReportService::reportFromFileUrl(dirname(__DIR__), (string)$latest['jsonUrl']) : null;
            if ($report && (!array_key_exists('qukdsVersions', $report) || !array_key_exists('quorbVersions', $report) || empty($report['summary']['trends'])) && !empty($latest['currentUploadId'])) {
                $fresh = ReportService::reportFromUpload($pdo, (int)$latest['currentUploadId'], dirname(__DIR__));
                $report = $fresh['report'] ?? $report;
            }
            echo json_encode(['ok' => true, 'report' => $report, 'metadata' => $latest, 'health' => Database::dashboardHealth($pdo)]);
            exit;
        }
        $reports = ReportService::listReports(dirname(__DIR__));
        $latest = $reports[0] ?? null;
        $report = $latest ? ReportService::reportFromFileUrl(dirname(__DIR__), str_replace('.html', '.json', (string)$latest['url'])) : null;
        echo json_encode(['ok' => true, 'report' => $report, 'metadata' => $latest]);
        exit;
    }

    if ($action === 'from-upload') {
        if (!$pdo) {
            throw new RuntimeException('Database configuration is required for upload history reports.');
        }
        $result = ReportService::reportFromUpload($pdo, (int)($_GET['id'] ?? 0), dirname(__DIR__));
        echo json_encode(['ok' => true] + $result);
        exit;
    }

    if ($pdo) {
        echo json_encode(['ok' => true, 'source' => 'database', 'reports' => Database::listReports($pdo)]);
    } else {
        echo json_encode(['ok' => true, 'source' => 'files', 'reports' => ReportService::listReports(dirname(__DIR__))]);
    }
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
