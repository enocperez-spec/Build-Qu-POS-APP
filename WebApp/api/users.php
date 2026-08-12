<?php
declare(strict_types=1);

require_once __DIR__ . '/AuthService.php';

header('Content-Type: application/json');

try {
    $pdo = Database::fromConfig();
    if (!$pdo) {
        throw new RuntimeException('Database configuration is required for users.');
    }
    $actingUser = Auth::requireAdmin();

    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';
    $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];

    if ($action === 'list') {
        echo json_encode(['ok' => true, 'users' => Database::listUsers($pdo)]);
        exit;
    }

    if ($action === 'create') {
        SecurityService::validatePassword((string)($input['password'] ?? ''));
        $user = Database::createUser(
            $pdo,
            (string)($input['email'] ?? ''),
            (string)($input['displayName'] ?? ''),
            (string)($input['password'] ?? ''),
            (string)($input['role'] ?? 'user')
        );
        SecurityService::audit($pdo, $actingUser, 'user_management', 'User created', 'user', (string)$user['id'], $user['email'], 'successful', [
            'displayName' => $user['displayName'], 'role' => $user['role'],
        ]);
        echo json_encode(['ok' => true, 'user' => $user]);
        exit;
    }

    if ($action === 'update-identity') {
        $id = (int)($input['id'] ?? 0);
        $before = Database::getUser($pdo, $id);
        if (!$before) throw new RuntimeException('User not found.');
        $after = Database::updateUserIdentity($pdo, $id, (string)($input['displayName'] ?? ''), (string)($input['email'] ?? ''));
        $notifications = SecurityService::notifyIdentityChange($before, $after);
        SecurityService::audit($pdo, $actingUser, 'user_management', 'User identity updated', 'user', (string)$id, $after['email'], 'successful', [
            'before' => ['displayName' => $before['displayName'], 'email' => $before['email']],
            'after' => ['displayName' => $after['displayName'], 'email' => $after['email']],
            'notifications' => $notifications,
        ]);
        if ($id === (int)$actingUser['id']) Auth::refreshCurrentSession($pdo);
        echo json_encode(['ok' => true, 'user' => $after]);
        exit;
    }

    if ($action === 'set-active') {
        $id = (int)($input['id'] ?? 0);
        $target = Database::getUser($pdo, $id);
        Database::setUserActive($pdo, $id, (bool)($input['isActive'] ?? false), (int)$actingUser['id']);
        if ($id === (int)$actingUser['id']) Auth::refreshCurrentSession($pdo);
        SecurityService::audit($pdo, $actingUser, 'user_management', !empty($input['isActive']) ? 'User activated' : 'User deactivated', 'user', (string)$id, $target['email'] ?? null, 'successful');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'set-role') {
        $id = (int)($input['id'] ?? 0);
        $target = Database::getUser($pdo, $id);
        $newRole = (string)($input['role'] ?? '');
        Database::setUserRole($pdo, $id, $newRole, (int)$actingUser['id']);
        if ($id === (int)$actingUser['id']) Auth::refreshCurrentSession($pdo);
        SecurityService::audit($pdo, $actingUser, 'permissions', 'User role changed', 'user', (string)$id, $target['email'] ?? null, 'successful', [
            'previousRole' => $target['role'] ?? null, 'newRole' => Database::normalizeRole($newRole),
        ]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        $target = Database::getUser($pdo, $id);
        Database::deleteUser($pdo, $id, (int)$actingUser['id']);
        SecurityService::audit($pdo, $actingUser, 'deletion', 'User deleted', 'user', (string)$id, $target['email'] ?? null, 'successful', [
            'deletedUserName' => $target['displayName'] ?? null, 'deletedUserRole' => $target['role'] ?? null,
        ]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'reset-2fa') {
        $id = (int)($input['id'] ?? 0);
        $target = Database::getUser($pdo, $id);
        Auth::resetTwoFactor($pdo, $id);
        SecurityService::audit($pdo, $actingUser, 'two_factor', 'Two-factor authentication reset', 'user', (string)$id, $target['email'] ?? null, 'successful');
        echo json_encode(['ok' => true]);
        exit;
    }

    throw new RuntimeException('Unknown users action.');
} catch (Throwable $exception) {
    if (isset($pdo, $actingUser, $action) && $pdo instanceof PDO) {
        SecurityService::audit($pdo, $actingUser, 'user_management', 'User management action failed', 'user', isset($input['id']) ? (string)$input['id'] : null, $input['email'] ?? null, 'failed', ['action' => $action], $exception->getMessage());
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
