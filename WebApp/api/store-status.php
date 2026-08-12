<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AuthService.php';

header('Content-Type: application/json');

try {
    $pdo = Database::fromConfig();
    if (!$pdo) {
        throw new RuntimeException('Database configuration is required.');
    }

    $config = require dirname(__DIR__) . '/config.local.php';
    $expectedToken = (string)($config['automation']['import_token'] ?? '');
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $providedToken = preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches) ? trim($matches[1]) : '';
    if ($providedToken === '') {
        $providedToken = trim((string)($_SERVER['HTTP_X_QU_IMPORT_TOKEN'] ?? ''));
    }

    if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
        Auth::requireLogin();
    }

    $query = trim((string)($_GET['q'] ?? ''));
    if ($query === '') {
        throw new RuntimeException('Provide q with a store name or store ID.');
    }

    $latestStatement = $pdo->query(
        "SELECT id, original_filename, uploaded_at
         FROM store_imports
         ORDER BY uploaded_at DESC, id DESC
         LIMIT 1"
    );
    $latest = $latestStatement->fetch();
    if (!$latest) {
        echo json_encode(['ok' => true, 'latestImport' => null, 'matches' => []]);
        exit;
    }

    $statement = $pdo->prepare(
        "SELECT store_id, store_name, brand, status, city, state
         FROM store_rows
         WHERE import_id = :import_id
           AND (store_id = :exact OR store_name LIKE :needle)
         ORDER BY store_name
         LIMIT 25"
    );
    $statement->execute([
        ':import_id' => (int)$latest['id'],
        ':exact' => $query,
        ':needle' => '%' . $query . '%',
    ]);

    $matches = array_map(static fn(array $row): array => [
            'storeId' => $row['store_id'],
            'storeName' => $row['store_name'],
            'brand' => $row['brand'],
            'status' => $row['status'],
            'city' => $row['city'],
            'state' => $row['state'],
            'source' => 'store_import',
        ], $statement->fetchAll());

    if (count($matches) === 0) {
        $latestUploadStatement = $pdo->query(
            "SELECT id, original_filename, uploaded_at
             FROM csv_uploads
             ORDER BY uploaded_at DESC, id DESC
             LIMIT 1"
        );
        $latestUpload = $latestUploadStatement->fetch();
        if ($latestUpload) {
            $terminalStatement = $pdo->prepare(
                "SELECT store_id, store_name, COUNT(*) AS terminal_count, MAX(last_seen_online) AS latest_seen
                 FROM terminal_rows
                 WHERE upload_id = :upload_id
                   AND (store_id = :exact OR store_name LIKE :needle)
                 GROUP BY store_id, store_name
                 ORDER BY store_name
                 LIMIT 25"
            );
            $terminalStatement->execute([
                ':upload_id' => (int)$latestUpload['id'],
                ':exact' => $query,
                ':needle' => '%' . $query . '%',
            ]);

            $terminalStores = $terminalStatement->fetchAll();
            $storeIds = array_values(array_unique(array_filter(array_map(
                static fn(array $row): string => trim((string)($row['store_id'] ?? '')),
                $terminalStores
            ))));

            $statusRows = [];
            if (count($storeIds) > 0) {
                $placeholders = implode(', ', array_fill(0, count($storeIds), '?'));
                $statusStatement = $pdo->prepare(
                    "SELECT store_id, brand, status, city, state
                     FROM store_rows
                     WHERE import_id = ?
                       AND store_id IN ($placeholders)"
                );
                $statusStatement->execute(array_merge([(int)$latest['id']], $storeIds));
                foreach ($statusStatement->fetchAll() as $statusRow) {
                    $statusRows[(string)$statusRow['store_id']] = $statusRow;
                }
            }

            $matches = array_map(static function (array $row) use ($statusRows): array {
                $storeId = trim((string)($row['store_id'] ?? ''));
                $statusRow = $statusRows[$storeId] ?? [];
                return [
                    'storeId' => $storeId,
                    'storeName' => $row['store_name'],
                    'brand' => $statusRow['brand'] ?? null,
                    'status' => $statusRow['status'] ?? 'No Store Data',
                    'city' => $statusRow['city'] ?? null,
                    'state' => $statusRow['state'] ?? null,
                    'terminalCount' => (int)$row['terminal_count'],
                    'latestSeen' => $row['latest_seen'],
                    'source' => 'terminal_upload',
                ];
            }, $terminalStores);
        }
    }

    echo json_encode([
        'ok' => true,
        'latestImport' => [
            'id' => (int)$latest['id'],
            'filename' => $latest['original_filename'],
            'uploadedAt' => date('c', strtotime((string)$latest['uploaded_at'])),
        ],
        'matches' => $matches,
    ]);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
