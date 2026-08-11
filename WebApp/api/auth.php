<?php
declare(strict_types=1);

require_once __DIR__ . '/AuthService.php';

header('Content-Type: application/json');

try {
    $pdo = Database::fromConfig();
    if (!$pdo) {
        throw new RuntimeException('Database configuration is required for login.');
    }
    Database::initialize($pdo);

    $action = $_GET['action'] ?? $_POST['action'] ?? 'status';
    $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];

    if ($action === 'status') {
        $user = Auth::currentUser();
        if ($user) {
            $allPermissions = Database::listRolePermissions($pdo);
            $user['permissions'] = $allPermissions[$user['role']] ?? [];
        }
        echo json_encode([
            'ok' => true,
            'user' => $user,
            'needsSetup' => Database::userCount($pdo) === 0,
        ]);
        exit;
    }

    if ($action === 'setup') {
        if (Database::userCount($pdo) > 0) {
            throw new RuntimeException('Initial setup is already complete.');
        }
        $email = (string)($input['email'] ?? '');
        $name = (string)($input['displayName'] ?? '');
        $password = (string)($input['password'] ?? '');
        $user = Database::createUser($pdo, $email, $name, $password, 'admin');
        $login = Auth::login($pdo, $email, $password);
        echo json_encode(['ok' => true, 'created' => $user] + $login);
        exit;
    }

    if ($action === 'login') {
        $login = Auth::login($pdo, (string)($input['email'] ?? ''), (string)($input['password'] ?? ''));
        echo json_encode(['ok' => true] + $login);
        exit;
    }

    if ($action === 'verify-2fa') {
        $user = Auth::verifyTwoFactor($pdo, (string)($input['code'] ?? ''));
        echo json_encode(['ok' => true, 'user' => $user]);
        exit;
    }

    if ($action === 'confirm-2fa-setup') {
        $user = Auth::confirmTwoFactorSetup($pdo, (string)($input['code'] ?? ''));
        echo json_encode(['ok' => true, 'user' => $user]);
        exit;
    }

    if ($action === 'logout') {
        Auth::logout();
        echo json_encode(['ok' => true]);
        exit;
    }

    throw new RuntimeException('Unknown auth action.');
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
        'action' => $action ?? 'status',
    ]);
}
