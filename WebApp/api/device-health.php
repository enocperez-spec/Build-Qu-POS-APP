<?php
declare(strict_types=1);

require_once __DIR__ . '/AuthService.php';
require_once __DIR__ . '/DeviceHealthService.php';

header('Content-Type: application/json');

try {
    $pdo = Database::fromConfig();
    if (!$pdo) {
        throw new RuntimeException('Database configuration is required for Device Health.');
    }
    Auth::requireSection($pdo, Database::SECTION_DEVICE_HEALTH);
    $action = (string)($_GET['action'] ?? 'dashboard');
    $days = (int)($_GET['days'] ?? DeviceHealthService::DEFAULT_DAYS);

    if ($action === 'dashboard') {
        $selectedBrands = array_values(array_filter(array_map('trim', explode('|', (string)($_GET['selectedBrands'] ?? '')))));
        $filters = [
            'mode' => (string)($_GET['mode'] ?? 'all'),
            'brand' => (string)($_GET['brand'] ?? ''),
            'combination' => (string)($_GET['combination'] ?? ''),
            'selectedBrands' => $selectedBrands,
            'match' => (string)($_GET['match'] ?? 'any'),
            'query' => (string)($_GET['query'] ?? ''),
        ];
        echo json_encode(['ok' => true, 'dashboard' => DeviceHealthService::dashboard($pdo, $filters, $days)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'store') {
        echo json_encode(['ok' => true, 'scorecard' => DeviceHealthService::store($pdo, (string)($_GET['storeId'] ?? ''), $days)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'search') {
        echo json_encode(['ok' => true, 'stores' => DeviceHealthService::searchStores($pdo, (string)($_GET['query'] ?? ''), $days)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new RuntimeException('Unknown Device Health action.');
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
