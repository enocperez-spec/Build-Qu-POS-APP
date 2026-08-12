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
        $actingUser = Auth::requireAdmin();
        $id = (int)($input['id'] ?? 0);
        $uploads = Database::listCsvUploads($pdo);
        $target = current(array_filter($uploads, static fn(array $upload): bool => (int)$upload['id'] === $id)) ?: null;
        Database::deleteCsvUpload($pdo, $id);
        SecurityService::audit($pdo, $actingUser, 'deletion', 'Terminal CSV history deleted', 'csv_upload', (string)$id, $target['filename'] ?? null, 'successful', [
            'rowCount' => $target['rowCount'] ?? null,
        ]);
        echo json_encode(['ok' => true]);
        exit;
    }

    throw new RuntimeException('Unknown uploads action.');
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
