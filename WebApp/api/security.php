<?php
declare(strict_types=1);

require_once __DIR__ . '/AuthService.php';

header('Content-Type: application/json');

function securityAutomationToken(): string
{
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.local.php';
    if (!is_file($path)) return '';
    $config = require $path;
    return (string)($config['automation']['import_token'] ?? '');
}

function securityRequestToken(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) return trim($matches[1]);
    return trim((string)($_SERVER['HTTP_X_QU_IMPORT_TOKEN'] ?? ''));
}

try {
    $pdo = Database::fromConfig();
    if (!$pdo) throw new RuntimeException('Database configuration is required for security settings.');
    Database::initialize($pdo);
    $action = $_GET['action'] ?? $_POST['action'] ?? 'overview';
    $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];

    if ($action === 'validate') {
        $expected = securityAutomationToken();
        $provided = securityRequestToken();
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            SecurityService::audit($pdo, null, 'security', 'Security validation authentication', 'automation_endpoint', 'security-validate', 'security-validate', 'blocked', [], 'Invalid automation token.');
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
            exit;
        }
        $requiredTables = ['security_settings', 'login_rate_limits', 'password_reset_tokens', 'two_factor_recovery_codes', 'audit_logs'];
        $statement = $pdo->prepare(
            "SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('security_settings','login_rate_limits','password_reset_tokens','two_factor_recovery_codes','audit_logs')"
        );
        $statement->execute();
        $tables = array_map('strtolower', array_column($statement->fetchAll(), 'name'));
        $missing = array_values(array_diff($requiredTables, $tables));
        $policy = SecurityService::policy($pdo);
        $policyWithinBounds = $policy['failedAttemptThreshold'] >= 3
            && $policy['lockoutDurationMinutes'] >= 1
            && $policy['loginRateLimitAttempts'] >= 5
            && $policy['passwordResetExpiryMinutes'] >= 10;
        $mail = SecurityService::mailStatus();
        $mailReady = $mail['enabled'] && $mail['functionAvailable'];
        if (count($missing) === 0 && $policyWithinBounds && $mailReady) {
            SecurityService::audit($pdo, null, 'deployment', 'Security controls validation', 'application_release', 'v007.04', 'v007.04', 'successful');
        }
        echo json_encode([
            'ok' => count($missing) === 0 && $policyWithinBounds && $mailReady,
            'version' => 'v007.04',
            'checks' => [
                'requiredTablesPresent' => count($missing) === 0,
                'missingTables' => $missing,
                'policyWithinBounds' => $policyWithinBounds,
                'auditLogWritable' => true,
                'mailConfigured' => $mail['enabled'],
                'mailFunctionAvailable' => $mail['functionAvailable'],
            ],
            'policy' => $policy,
        ]);
        exit;
    }

    $user = Auth::requireLogin();
    if ($action === 'overview') {
        $statement = $pdo->prepare("SELECT two_factor_enabled, last_login_at, password_changed_at FROM users WHERE id = :id LIMIT 1");
        $statement->execute([':id' => (int)$user['id']]);
        $account = $statement->fetch() ?: [];
        echo json_encode([
            'ok' => true,
            'account' => [
                'twoFactorEnabled' => !empty($account['two_factor_enabled']),
                'recoveryCodesRemaining' => SecurityService::recoveryCodeCount($pdo, (int)$user['id']),
                'lastLoginAt' => !empty($account['last_login_at']) ? date('c', strtotime($account['last_login_at'])) : null,
                'passwordChangedAt' => !empty($account['password_changed_at']) ? date('c', strtotime($account['password_changed_at'])) : null,
            ],
            'policy' => $user['role'] === 'admin' ? SecurityService::policy($pdo) : null,
            'mail' => SecurityService::mailStatus(),
        ]);
        exit;
    }

    if ($action === 'change-password') {
        SecurityService::changePassword($pdo, $user, (string)($input['currentPassword'] ?? ''), (string)($input['newPassword'] ?? ''));
        Auth::refreshCurrentSession($pdo);
        echo json_encode(['ok' => true, 'message' => 'Password changed successfully.']);
        exit;
    }

    if ($action === 'regenerate-recovery-codes') {
        if (!Auth::verifyIdentity($pdo, (int)$user['id'], (string)($input['password'] ?? ''), (string)($input['secondFactor'] ?? ''))) {
            SecurityService::audit($pdo, $user, 'two_factor', 'Recovery code regeneration', 'user', (string)$user['id'], $user['email'], 'blocked', [], 'Identity verification failed.');
            throw new RuntimeException('Identity verification failed.');
        }
        $codes = SecurityService::generateRecoveryCodes($pdo, (int)$user['id']);
        SecurityService::audit($pdo, $user, 'two_factor', 'Recovery codes regenerated', 'user', (string)$user['id'], $user['email'], 'successful', ['recoveryCodesGenerated' => count($codes)]);
        echo json_encode(['ok' => true, 'recoveryCodes' => $codes]);
        exit;
    }

    $admin = Auth::requireAdmin();
    if ($action === 'save-policy') {
        echo json_encode(['ok' => true, 'policy' => SecurityService::updatePolicy($pdo, $input, $admin)]);
        exit;
    }
    if ($action === 'audit-filters') {
        echo json_encode(['ok' => true, 'filters' => SecurityService::auditFilters($pdo)]);
        exit;
    }
    if ($action === 'audit-logs') {
        echo json_encode(['ok' => true] + SecurityService::auditLogs($pdo, $_GET));
        exit;
    }

    throw new RuntimeException('Unknown security action.');
} catch (Throwable $exception) {
    if (isset($pdo, $user, $action) && $pdo instanceof PDO && in_array($action, ['change-password', 'regenerate-recovery-codes', 'save-policy'], true)) {
        SecurityService::audit($pdo, $user, 'account_security', 'Security settings action failed', 'settings_action', $action, $action, 'failed', [], $exception->getMessage());
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
