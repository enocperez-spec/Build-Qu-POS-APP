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
        $user = Database::createUser(
            $pdo,
            (string)($input['email'] ?? ''),
            (string)($input['displayName'] ?? ''),
            (string)($input['password'] ?? ''),
            (string)($input['role'] ?? 'user')
        );
        echo json_encode(['ok' => true, 'user' => $user]);
        exit;
    }

    if ($action === 'set-active') {
        Database::setUserActive($pdo, (int)($input['id'] ?? 0), (bool)($input['isActive'] ?? false), (int)$actingUser['id']);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'set-role') {
        Database::setUserRole($pdo, (int)($input['id'] ?? 0), (string)($input['role'] ?? ''), (int)$actingUser['id']);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete') {
        Database::deleteUser($pdo, (int)($input['id'] ?? 0), (int)$actingUser['id']);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'reset-2fa') {
        Auth::resetTwoFactor($pdo, (int)($input['id'] ?? 0));
        echo json_encode(['ok' => true]);
        exit;
    }

    throw new RuntimeException('Unknown users action.');
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
