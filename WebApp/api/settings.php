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
        $role = (string)($input['role'] ?? '');
        $permissions = (array)($input['permissions'] ?? []);
        Database::setRolePermissions($pdo, $role, $permissions);
        SecurityService::audit($pdo, $actingUser, 'permissions', 'Role permissions updated', 'role', Database::normalizeRole($role), Database::roleLabel($role), 'successful', ['permissions' => $permissions]);
        echo json_encode(['ok' => true, 'permissions' => Database::listRolePermissions($pdo)]);
        exit;
    }

    if ($action === 'schedules') {
        echo json_encode(['ok' => true, 'schedules' => Database::listApiSchedules($pdo)]);
        exit;
    }

    if ($action === 'add-schedule') {
        $jobKey = (string)($input['jobKey'] ?? Database::JOB_TERMINALS);
        $scheduledTime = (string)($input['scheduledTime'] ?? '');
        Database::addApiSchedule($pdo, $scheduledTime, $jobKey);
        SecurityService::audit($pdo, $actingUser, 'automation', 'Automation schedule added', 'automation_job', $jobKey, $jobKey, 'successful', ['scheduledTime' => $scheduledTime]);
        echo json_encode(['ok' => true, 'schedules' => Database::listApiSchedules($pdo)]);
        exit;
    }

    if ($action === 'update-schedule') {
        $id = (int)($input['id'] ?? 0);
        $scheduledTime = (string)($input['scheduledTime'] ?? '');
        Database::updateApiSchedule($pdo, $id, $scheduledTime);
        SecurityService::audit($pdo, $actingUser, 'automation', 'Automation schedule updated', 'api_schedule', (string)$id, "Schedule $id", 'successful', ['scheduledTime' => $scheduledTime]);
        echo json_encode(['ok' => true, 'schedules' => Database::listApiSchedules($pdo)]);
        exit;
    }

    if ($action === 'api-logs') {
        echo json_encode(['ok' => true, 'logs' => Database::listApiLogs($pdo)]);
        exit;
    }

    if ($action === 'retrieve-data') {
        $jobKey = (string)($input['jobKey'] ?? Database::JOB_TERMINALS);
        $jobKey = $jobKey === Database::JOB_STORES ? Database::JOB_STORES : Database::JOB_TERMINALS;
        if (!Database::acquireApiLock($pdo, $jobKey, (string)$actingUser['displayName'])) {
            throw new RuntimeException('A QU EI data retrieval is already running. Try again after it finishes.');
        }
        $started = microtime(true);
        $logId = Database::startApiLog($pdo, 'Manual', $actingUser, 1, $jobKey);
        try {
            Database::finishApiLog($pdo, $logId, 'Successful', [
                'attempts' => 1,
                'durationMs' => (int)((microtime(true) - $started) * 1000),
            ], $jobKey);
            SecurityService::audit($pdo, $actingUser, 'process_run', 'Manual QU EI retrieval run', 'automation_job', $jobKey, $jobKey, 'successful', ['durationMs' => (int)((microtime(true) - $started) * 1000)]);
            echo json_encode(['ok' => true, 'message' => 'Manual retrieval request logged.', 'logs' => Database::listApiLogs($pdo)]);
            exit;
        } finally {
            Database::releaseApiLock($pdo, $jobKey);
        }
    }

    throw new RuntimeException('Unknown settings action.');
} catch (Throwable $exception) {
    if (isset($pdo, $actingUser, $action) && $pdo instanceof PDO && !in_array($action, ['overview', 'permissions', 'schedules', 'api-logs'], true)) {
        SecurityService::audit($pdo, $actingUser, 'settings', 'Settings action failed', 'settings_action', (string)$action, (string)$action, 'failed', [], $exception->getMessage());
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
