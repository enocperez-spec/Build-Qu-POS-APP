<?php
declare(strict_types=1);

require_once __DIR__ . '/AuthService.php';

header('Content-Type: application/json');

try {
    $pdo = Database::fromConfig();
    if (!$pdo) {
        throw new RuntimeException('Database configuration is required for CSV history.');
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';
    $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];

    if ($action === 'list') {
        Auth::requireLogin();
        echo json_encode(['ok' => true, 'uploads' => Database::listCsvUploads($pdo)]);
        exit;
    }

    if ($action === 'list-store-imports') {
        Auth::requireLogin();
        echo json_encode(['ok' => true, 'imports' => Database::listStoreImports($pdo)]);
        exit;
    }

    if ($action === 'delete') {
        Auth::requireAdmin();
        Database::deleteCsvUpload($pdo, (int)($input['id'] ?? 0));
        echo json_encode(['ok' => true]);
        exit;
    }

    throw new RuntimeException('Unknown uploads action.');
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
