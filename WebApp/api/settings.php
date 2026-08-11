<?php
declare(strict_types=1);

require_once __DIR__ . '/AuthService.php';

header('Content-Type: application/json');

try {
    $pdo = Database::fromConfig();
    if (!$pdo) {
        throw new RuntimeException('Database configuration is required for settings.');
    }
    Database::initialize($pdo);
    $actingUser = Auth::requireAdmin();

    $action = $_GET['action'] ?? $_POST['action'] ?? 'overview';
    $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];

    if ($action === 'permissions') {
        echo json_encode([
            'ok' => true,
            'roles' => array_map(static fn(string $role): array => [
                'key' => $role,
                'label' => Database::roleLabel($role),
            ], Database::roles()),
            'sections' => Database::navigationSections(),
            'permissions' => Database::listRolePermissions($pdo),
        ]);
        exit;
    }

    if ($action === 'save-permissions') {
        Database::setRolePermissions($pdo, (string)($input['role'] ?? ''), (array)($input['permissions'] ?? []));
        echo json_encode(['ok' => true, 'permissions' => Database::listRolePermissions($pdo)]);
        exit;
    }

    if ($action === 'schedules') {
        echo json_encode(['ok' => true, 'schedules' => Database::listApiSchedules($pdo)]);
        exit;
    }

    if ($action === 'add-schedule') {
        Database::addApiSchedule($pdo, (string)($input['scheduledTime'] ?? ''));
        echo json_encode(['ok' => true, 'schedules' => Database::listApiSchedules($pdo)]);
        exit;
    }

    if ($action === 'update-schedule') {
        Database::updateApiSchedule($pdo, (int)($input['id'] ?? 0), (string)($input['scheduledTime'] ?? ''));
        echo json_encode(['ok' => true, 'schedules' => Database::listApiSchedules($pdo)]);
        exit;
    }

    if ($action === 'api-logs') {
        echo json_encode(['ok' => true, 'logs' => Database::listApiLogs($pdo)]);
        exit;
    }

    if ($action === 'retrieve-data') {
        if (!Database::acquireApiLock($pdo, 'qu_ei_terminals_csv', (string)$actingUser['displayName'])) {
            throw new RuntimeException('A QU EI data retrieval is already running. Try again after it finishes.');
        }
        $started = microtime(true);
        $logId = Database::startApiLog($pdo, 'Manual', $actingUser, 1);
        try {
            Database::finishApiLog($pdo, $logId, 'Successful', [
                'attempts' => 1,
                'durationMs' => (int)((microtime(true) - $started) * 1000),
            ]);
            echo json_encode(['ok' => true, 'message' => 'Manual retrieval request logged.', 'logs' => Database::listApiLogs($pdo)]);
            exit;
        } finally {
            Database::releaseApiLock($pdo, 'qu_ei_terminals_csv');
        }
    }

    throw new RuntimeException('Unknown settings action.');
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
