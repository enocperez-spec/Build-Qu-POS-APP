<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class SecurityService
{
    private const DEFAULT_POLICY = [
        'failedAttemptThreshold' => 5,
        'lockoutDurationMinutes' => 15,
        'loginRateLimitAttempts' => 20,
        'loginRateLimitWindowMinutes' => 15,
        'passwordResetExpiryMinutes' => 60,
    ];

    public static function policy(PDO $pdo): array
    {
        Database::initialize($pdo);
        $row = $pdo->query(
            "SELECT failed_attempt_threshold, lockout_duration_minutes, login_rate_limit_attempts,
                    login_rate_limit_window_minutes, password_reset_expiry_minutes, updated_at
             FROM security_settings WHERE id = 1"
        )->fetch();
        if (!$row) {
            return self::DEFAULT_POLICY + ['updatedAt' => null];
        }
        return [
            'failedAttemptThreshold' => (int)$row['failed_attempt_threshold'],
            'lockoutDurationMinutes' => (int)$row['lockout_duration_minutes'],
            'loginRateLimitAttempts' => (int)$row['login_rate_limit_attempts'],
            'loginRateLimitWindowMinutes' => (int)$row['login_rate_limit_window_minutes'],
            'passwordResetExpiryMinutes' => (int)$row['password_reset_expiry_minutes'],
            'updatedAt' => $row['updated_at'] ? date('c', strtotime($row['updated_at'])) : null,
        ];
    }

    public static function updatePolicy(PDO $pdo, array $input, array $actingUser): array
    {
        $values = [
            'failedAttemptThreshold' => self::boundedInt($input['failedAttemptThreshold'] ?? null, 3, 20, 'Failed-attempt threshold'),
            'lockoutDurationMinutes' => self::boundedInt($input['lockoutDurationMinutes'] ?? null, 1, 1440, 'Lockout duration'),
            'loginRateLimitAttempts' => self::boundedInt($input['loginRateLimitAttempts'] ?? null, 5, 500, 'Login rate limit'),
            'loginRateLimitWindowMinutes' => self::boundedInt($input['loginRateLimitWindowMinutes'] ?? null, 1, 1440, 'Rate-limit window'),
            'passwordResetExpiryMinutes' => self::boundedInt($input['passwordResetExpiryMinutes'] ?? null, 10, 1440, 'Reset-link expiry'),
        ];
        $statement = $pdo->prepare(
            "UPDATE security_settings SET failed_attempt_threshold = :threshold,
                    lockout_duration_minutes = :lockout_minutes,
                    login_rate_limit_attempts = :rate_attempts,
                    login_rate_limit_window_minutes = :rate_window,
                    password_reset_expiry_minutes = :reset_expiry,
                    updated_by_user_id = :user_id, updated_at = :updated_at
             WHERE id = 1"
        );
        $statement->execute([
            ':threshold' => $values['failedAttemptThreshold'],
            ':lockout_minutes' => $values['lockoutDurationMinutes'],
            ':rate_attempts' => $values['loginRateLimitAttempts'],
            ':rate_window' => $values['loginRateLimitWindowMinutes'],
            ':reset_expiry' => $values['passwordResetExpiryMinutes'],
            ':user_id' => (int)$actingUser['id'],
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
        self::audit($pdo, $actingUser, 'security', 'Security policy updated', 'security_policy', '1', 'Account security policy', 'successful', $values);
        return self::policy($pdo);
    }

    public static function clientIp(): string
    {
        return substr(trim((string)($_SERVER['REMOTE_ADDR'] ?? 'Unknown')), 0, 80);
    }

    public static function userAgent(): string
    {
        return substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown')), 0, 500);
    }

    public static function audit(
        PDO $pdo,
        ?array $user,
        string $actionType,
        string $action,
        ?string $targetType,
        ?string $targetId,
        ?string $targetLabel,
        string $result,
        array $details = [],
        ?string $error = null
    ): void {
        Database::initialize($pdo);
        $result = in_array($result, ['successful', 'failed', 'blocked'], true) ? $result : 'failed';
        $statement = $pdo->prepare(
            "INSERT INTO audit_logs (
                occurred_at, user_id, user_name, user_email, action_type, action,
                target_type, target_id, target_label, result_status, ip_address,
                user_agent, details_json, error_message
             ) VALUES (
                :occurred_at, :user_id, :user_name, :user_email, :action_type, :action,
                :target_type, :target_id, :target_label, :result_status, :ip_address,
                :user_agent, :details_json, :error_message
             )"
        );
        $statement->execute([
            ':occurred_at' => date('Y-m-d H:i:s'),
            ':user_id' => isset($user['id']) ? (int)$user['id'] : null,
            ':user_name' => $user['displayName'] ?? $user['display_name'] ?? null,
            ':user_email' => $user['email'] ?? null,
            ':action_type' => substr(trim($actionType), 0, 80),
            ':action' => substr(trim($action), 0, 160),
            ':target_type' => $targetType ? substr($targetType, 0, 80) : null,
            ':target_id' => $targetId ? substr($targetId, 0, 120) : null,
            ':target_label' => $targetLabel ? substr($targetLabel, 0, 255) : null,
            ':result_status' => $result,
            ':ip_address' => self::clientIp(),
            ':user_agent' => self::userAgent(),
            ':details_json' => $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null,
            ':error_message' => $error ? substr($error, 0, 4000) : null,
        ]);
    }

    public static function assertIpLoginAllowed(PDO $pdo): void
    {
        $policy = self::policy($pdo);
        $keyHash = hash('sha256', self::clientIp());
        $statement = $pdo->prepare(
            "SELECT blocked_until FROM login_rate_limits WHERE scope = 'login_ip' AND key_hash = :key_hash LIMIT 1"
        );
        $statement->execute([':key_hash' => $keyHash]);
        $blockedUntil = $statement->fetchColumn();
        if ($blockedUntil && strtotime((string)$blockedUntil) > time()) {
            self::audit($pdo, null, 'authentication', 'Login rate limited', 'ip_address', self::clientIp(), self::clientIp(), 'blocked', [
                'windowMinutes' => $policy['loginRateLimitWindowMinutes'],
            ]);
            throw new RuntimeException('Sign-in is temporarily unavailable. Try again later.');
        }
    }

    public static function recordIpLoginResult(PDO $pdo, bool $successful): void
    {
        $keyHash = hash('sha256', self::clientIp());
        if ($successful) {
            $statement = $pdo->prepare("DELETE FROM login_rate_limits WHERE scope = 'login_ip' AND key_hash = :key_hash");
            $statement->execute([':key_hash' => $keyHash]);
            return;
        }
        $policy = self::policy($pdo);
        self::incrementRateLimitCounter(
            $pdo,
            'login_ip',
            $keyHash,
            $policy['loginRateLimitAttempts'],
            $policy['loginRateLimitWindowMinutes']
        );
    }

    public static function isAccountLocked(array $user): bool
    {
        return !empty($user['locked_until']) && strtotime((string)$user['locked_until']) > time();
    }

    public static function recordAccountFailure(PDO $pdo, array $user): bool
    {
        $policy = self::policy($pdo);
        $ownsTransaction = !$pdo->inTransaction();
        try {
            if ($ownsTransaction) $pdo->beginTransaction();
            $statement = $pdo->prepare("SELECT failed_login_attempts FROM users WHERE id = :id LIMIT 1 FOR UPDATE");
            $statement->execute([':id' => (int)$user['id']]);
            $persistedAttempts = $statement->fetchColumn();
            if ($persistedAttempts === false) throw new RuntimeException('User account was not found.');

            $attempts = (int)$persistedAttempts + 1;
            $locked = $attempts >= $policy['failedAttemptThreshold'];
            $now = date('Y-m-d H:i:s');
            $statement = $pdo->prepare(
                "UPDATE users SET failed_login_attempts = :attempts, last_failed_login_at = :failed_at,
                        locked_until = :locked_until, updated_at = :updated_at WHERE id = :id"
            );
            $statement->execute([
                ':attempts' => $attempts,
                ':failed_at' => $now,
                ':locked_until' => $locked ? date('Y-m-d H:i:s', time() + ($policy['lockoutDurationMinutes'] * 60)) : null,
                ':updated_at' => $now,
                ':id' => (int)$user['id'],
            ]);
            if ($ownsTransaction) $pdo->commit();
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        if ($locked) {
            self::audit($pdo, $user, 'authentication', 'Account locked', 'user', (string)$user['id'], $user['email'], 'blocked', [
                'failedAttempts' => $attempts,
                'lockoutMinutes' => $policy['lockoutDurationMinutes'],
            ]);
        }
        return $locked;
    }

    public static function recordAccountSuccess(PDO $pdo, array $user): void
    {
        $statement = $pdo->prepare(
            "UPDATE users SET failed_login_attempts = 0, last_failed_login_at = NULL, locked_until = NULL,
                    last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id"
        );
        $now = date('Y-m-d H:i:s');
        $statement->execute([':last_login_at' => $now, ':updated_at' => $now, ':id' => (int)$user['id']]);
        self::recordIpLoginResult($pdo, true);
    }

    public static function generateRecoveryCodes(PDO $pdo, int $userId, int $count = 10): array
    {
        $count = max(5, min(20, $count));
        $codes = [];
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM two_factor_recovery_codes WHERE user_id = :user_id")->execute([':user_id' => $userId]);
            $statement = $pdo->prepare(
                "INSERT INTO two_factor_recovery_codes (user_id, code_hash, created_at) VALUES (:user_id, :code_hash, :created_at)"
            );
            for ($i = 0; $i < $count; $i++) {
                $raw = strtoupper(bin2hex(random_bytes(6)));
                $code = implode('-', str_split($raw, 4));
                $statement->execute([
                    ':user_id' => $userId,
                    ':code_hash' => password_hash(self::normalizeRecoveryCode($code), PASSWORD_DEFAULT),
                    ':created_at' => date('Y-m-d H:i:s'),
                ]);
                $codes[] = $code;
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        return $codes;
    }

    public static function recoveryCodeCount(PDO $pdo, int $userId): int
    {
        $statement = $pdo->prepare("SELECT COUNT(*) FROM two_factor_recovery_codes WHERE user_id = :user_id AND used_at IS NULL");
        $statement->execute([':user_id' => $userId]);
        return (int)$statement->fetchColumn();
    }

    public static function consumeRecoveryCode(PDO $pdo, int $userId, string $code): bool
    {
        $normalized = self::normalizeRecoveryCode($code);
        if (strlen($normalized) !== 12) return false;
        $statement = $pdo->prepare(
            "SELECT id, code_hash FROM two_factor_recovery_codes WHERE user_id = :user_id AND used_at IS NULL ORDER BY id ASC"
        );
        $statement->execute([':user_id' => $userId]);
        foreach ($statement->fetchAll() as $row) {
            if (password_verify($normalized, (string)$row['code_hash'])) {
                $update = $pdo->prepare("UPDATE two_factor_recovery_codes SET used_at = :used_at WHERE id = :id AND used_at IS NULL");
                $update->execute([':used_at' => date('Y-m-d H:i:s'), ':id' => (int)$row['id']]);
                return $update->rowCount() === 1;
            }
        }
        return false;
    }

    public static function requestPasswordReset(PDO $pdo, string $email): void
    {
        $email = strtolower(trim($email));
        $ipAllowed = self::consumeRequestLimit($pdo, 'password_reset_ip', self::clientIp(), 5, 15);
        $emailAllowed = self::consumeRequestLimit($pdo, 'password_reset_email', $email, 3, 30);
        if (!$ipAllowed || !$emailAllowed) {
            self::audit($pdo, null, 'password_reset', 'Password reset request rate limited', 'email', null, $email, 'blocked');
            return;
        }
        $statement = $pdo->prepare("SELECT id, email, display_name, is_active FROM users WHERE email = :email LIMIT 1");
        $statement->execute([':email' => $email]);
        $user = $statement->fetch();
        if (!$user || !(bool)$user['is_active']) {
            self::audit($pdo, null, 'password_reset', 'Password reset requested', 'email', null, $email, 'failed', [], 'No active account matched.');
            return;
        }

        $policy = self::policy($pdo);
        $token = bin2hex(random_bytes(32));
        $tokenHash = self::hashResetToken($token);
        $pdo->prepare("UPDATE password_reset_tokens SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL")
            ->execute([':used_at' => date('Y-m-d H:i:s'), ':user_id' => (int)$user['id']]);
        $insert = $pdo->prepare(
            "INSERT INTO password_reset_tokens (user_id, token_hash, requested_ip, expires_at, created_at)
             VALUES (:user_id, :token_hash, :requested_ip, :expires_at, :created_at)"
        );
        $insert->execute([
            ':user_id' => (int)$user['id'],
            ':token_hash' => $tokenHash,
            ':requested_ip' => self::clientIp(),
            ':expires_at' => date('Y-m-d H:i:s', time() + ($policy['passwordResetExpiryMinutes'] * 60)),
            ':created_at' => date('Y-m-d H:i:s'),
        ]);
        $url = rtrim(self::mailConfig()['baseUrl'], '/') . '/?reset=' . rawurlencode($token);
        $sent = self::sendMail(
            (string)$user['email'],
            'Reset your QU POS Application password',
            "Hello {$user['display_name']},\n\nUse this single-use link to reset your password. It expires in {$policy['passwordResetExpiryMinutes']} minutes:\n\n$url\n\nIf you did not request this, no action is required."
        );
        self::audit($pdo, $user, 'password_reset', 'Password reset requested', 'user', (string)$user['id'], $user['email'], $sent ? 'successful' : 'failed', [
            'expiresInMinutes' => $policy['passwordResetExpiryMinutes'],
            'notificationSent' => $sent,
        ], $sent ? null : 'Password reset email could not be sent.');
    }

    public static function resetPassword(PDO $pdo, string $token, string $newPassword): array
    {
        self::validatePassword($newPassword);
        $tokenHash = self::hashResetToken($token);
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                "SELECT pr.id AS token_id, pr.user_id, pr.expires_at, pr.used_at,
                        u.email, u.display_name, u.is_active
                 FROM password_reset_tokens pr JOIN users u ON u.id = pr.user_id
                 WHERE pr.token_hash = :token_hash LIMIT 1 FOR UPDATE"
            );
            $statement->execute([':token_hash' => $tokenHash]);
            $row = $statement->fetch();
            if (!$row || $row['used_at'] || strtotime((string)$row['expires_at']) < time() || !(bool)$row['is_active']) {
                throw new RuntimeException('This password-reset link is invalid or has expired.');
            }
            $now = date('Y-m-d H:i:s');
            $pdo->prepare("UPDATE password_reset_tokens SET used_at = :used_at WHERE id = :id AND used_at IS NULL")
                ->execute([':used_at' => $now, ':id' => (int)$row['token_id']]);
            $pdo->prepare(
                "UPDATE users SET password_hash = :password_hash, password_changed_at = :changed_at, auth_version = auth_version + 1,
                        failed_login_attempts = 0, last_failed_login_at = NULL, locked_until = NULL, updated_at = :updated_at
                 WHERE id = :id"
            )->execute([
                ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':changed_at' => $now,
                ':updated_at' => $now,
                ':id' => (int)$row['user_id'],
            ]);
            $pdo->prepare("UPDATE password_reset_tokens SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL")
                ->execute([':used_at' => $now, ':user_id' => (int)$row['user_id']]);
            $pdo->commit();
            $user = ['id' => (int)$row['user_id'], 'email' => $row['email'], 'displayName' => $row['display_name']];
            $notified = self::sendSecurityNotification((string)$row['email'], (string)$row['display_name'], 'Your password was reset', 'Your QU POS Application password was reset. If you did not make this change, contact an administrator immediately.');
            self::audit($pdo, $user, 'password_reset', 'Password reset completed', 'user', (string)$row['user_id'], $row['email'], 'successful', ['notificationSent' => $notified]);
            return $user;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            self::audit($pdo, null, 'password_reset', 'Password reset failed', 'reset_token', null, null, 'failed', [], $exception->getMessage());
            throw $exception;
        }
    }

    public static function changePassword(PDO $pdo, array $user, string $currentPassword, string $newPassword): void
    {
        self::validatePassword($newPassword);
        $statement = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $statement->execute([':id' => (int)$user['id']]);
        $hash = $statement->fetchColumn();
        if (!$hash || !password_verify($currentPassword, (string)$hash)) {
            self::audit($pdo, $user, 'account_security', 'Password change', 'user', (string)$user['id'], $user['email'], 'failed', [], 'Current password verification failed.');
            throw new RuntimeException('Current password is incorrect.');
        }
        if (password_verify($newPassword, (string)$hash)) {
            throw new RuntimeException('New password must be different from the current password.');
        }
        $now = date('Y-m-d H:i:s');
        $pdo->prepare("UPDATE users SET password_hash = :hash, password_changed_at = :changed_at, auth_version = auth_version + 1, updated_at = :updated_at WHERE id = :id")
            ->execute([':hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':changed_at' => $now, ':updated_at' => $now, ':id' => (int)$user['id']]);
        $notified = self::sendSecurityNotification((string)$user['email'], (string)$user['displayName'], 'Your password was changed', 'Your QU POS Application password was changed. If you did not make this change, contact an administrator immediately.');
        self::audit($pdo, $user, 'account_security', 'Password changed', 'user', (string)$user['id'], $user['email'], 'successful', ['notificationSent' => $notified]);
    }

    public static function notifyIdentityChange(array $before, array $after): array
    {
        $subject = 'Your QU POS Application account was updated';
        $body = "Your account name or email address was updated by an administrator.\n\nNew name: {$after['displayName']}\nNew email: {$after['email']}\n\nIf you did not expect this change, contact an administrator immediately.";
        $oldSent = self::sendSecurityNotification((string)$before['email'], (string)$before['displayName'], $subject, $body);
        $newSent = strtolower((string)$after['email']) === strtolower((string)$before['email'])
            ? $oldSent
            : self::sendSecurityNotification((string)$after['email'], (string)$after['displayName'], $subject, $body);
        return ['previousAddressNotified' => $oldSent, 'newAddressNotified' => $newSent];
    }

    public static function auditLogs(PDO $pdo, array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $pageSize = max(10, min(5000, (int)($filters['pageSize'] ?? 25)));
        $sortMap = [
            'date' => 'occurred_at', 'user' => 'user_name', 'actionType' => 'action_type',
            'action' => 'action', 'target' => 'target_label', 'status' => 'result_status',
        ];
        $sort = $sortMap[(string)($filters['sort'] ?? 'date')] ?? 'occurred_at';
        $direction = strtolower((string)($filters['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $where = [];
        $params = [];
        if (($q = trim((string)($filters['q'] ?? ''))) !== '') {
            $where[] = "(user_name LIKE :q OR user_email LIKE :q OR action LIKE :q OR target_label LIKE :q OR error_message LIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }
        if (($userId = (int)($filters['userId'] ?? 0)) > 0) {
            $where[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }
        if (($type = trim((string)($filters['actionType'] ?? ''))) !== '') {
            $where[] = 'action_type = :action_type';
            $params[':action_type'] = $type;
        }
        if (in_array(($status = (string)($filters['status'] ?? '')), ['successful', 'failed', 'blocked'], true)) {
            $where[] = 'result_status = :result_status';
            $params[':result_status'] = $status;
        }
        if (($from = self::validDate((string)($filters['dateFrom'] ?? ''))) !== null) {
            $where[] = 'occurred_at >= :date_from';
            $params[':date_from'] = $from . ' 00:00:00';
        }
        if (($to = self::validDate((string)($filters['dateTo'] ?? ''))) !== null) {
            $where[] = 'occurred_at <= :date_to';
            $params[':date_to'] = $to . ' 23:59:59';
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $pdo->prepare("SELECT COUNT(*) FROM audit_logs$whereSql");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $offset = ($page - 1) * $pageSize;
        $statement = $pdo->prepare(
            "SELECT id, occurred_at, user_id, user_name, user_email, action_type, action,
                    target_type, target_id, target_label, result_status, ip_address,
                    user_agent, details_json, error_message
             FROM audit_logs$whereSql ORDER BY $sort $direction, id $direction LIMIT $pageSize OFFSET $offset"
        );
        $statement->execute($params);
        $logs = array_map(static function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'occurredAt' => date('c', strtotime($row['occurred_at'])),
                'userId' => $row['user_id'] !== null ? (int)$row['user_id'] : null,
                'userName' => $row['user_name'] ?: 'System',
                'userEmail' => $row['user_email'] ?: '',
                'actionType' => $row['action_type'],
                'action' => $row['action'],
                'targetType' => $row['target_type'] ?: '',
                'targetId' => $row['target_id'] ?: '',
                'targetLabel' => $row['target_label'] ?: '',
                'status' => $row['result_status'],
                'ipAddress' => $row['ip_address'] ?: '',
                'userAgent' => $row['user_agent'] ?: '',
                'details' => $row['details_json'] ? (json_decode($row['details_json'], true) ?: []) : [],
                'errorMessage' => $row['error_message'] ?: '',
            ];
        }, $statement->fetchAll());
        return ['logs' => $logs, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int)ceil($total / $pageSize))];
    }

    public static function auditFilters(PDO $pdo): array
    {
        return [
            'users' => $pdo->query(
                "SELECT al.user_id AS id, COALESCE(u.display_name, MAX(al.user_name)) AS name,
                        COALESCE(u.email, MAX(al.user_email)) AS email
                 FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
                 WHERE al.user_id IS NOT NULL
                 GROUP BY al.user_id, u.display_name, u.email ORDER BY name"
            )->fetchAll(),
            'actionTypes' => array_column($pdo->query("SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type")->fetchAll(), 'action_type'),
        ];
    }

    public static function validatePassword(string $password): void
    {
        if (strlen($password) < 12) throw new RuntimeException('Password must be at least 12 characters.');
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new RuntimeException('Password must include uppercase, lowercase, and a number.');
        }
    }

    public static function hashResetToken(string $token): string
    {
        return hash('sha256', trim($token));
    }

    public static function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
    }

    public static function mailStatus(): array
    {
        $config = self::mailConfig();
        return ['enabled' => $config['enabled'], 'functionAvailable' => function_exists('mail'), 'fromAddress' => $config['fromAddress']];
    }

    private static function sendSecurityNotification(string $email, string $name, string $subject, string $message): bool
    {
        return self::sendMail($email, $subject, "Hello $name,\n\n$message");
    }

    private static function sendMail(string $to, string $subject, string $message): bool
    {
        $config = self::mailConfig();
        if (!$config['enabled'] || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
        $headers = [
            'From: ' . $config['fromName'] . ' <' . $config['fromAddress'] . '>',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: QU-POS-Application',
        ];
        return @mail($to, $subject, $message, implode("\r\n", $headers));
    }

    private static function mailConfig(): array
    {
        $configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.local.php';
        $config = is_file($configPath) ? (require $configPath) : [];
        $mail = $config['mail'] ?? [];
        return [
            'enabled' => !empty($mail['enabled']),
            'fromAddress' => (string)($mail['from_address'] ?? 'no-reply@qupostech.com'),
            'fromName' => str_replace(["\r", "\n"], '', (string)($mail['from_name'] ?? 'QU POS Application Version Tools')),
            'baseUrl' => (string)($mail['base_url'] ?? 'https://quposapp.qupostech.com'),
        ];
    }

    private static function boundedInt(mixed $value, int $minimum, int $maximum, string $label): int
    {
        if (!is_numeric($value)) throw new RuntimeException("$label must be a number.");
        $number = (int)$value;
        if ($number < $minimum || $number > $maximum) throw new RuntimeException("$label must be between $minimum and $maximum.");
        return $number;
    }

    private static function consumeRequestLimit(PDO $pdo, string $scope, string $key, int $maximum, int $windowMinutes): bool
    {
        $keyHash = hash('sha256', strtolower(trim($key)));
        return self::incrementRateLimitCounter($pdo, $scope, $keyHash, $maximum, $windowMinutes)['allowed'];
    }

    private static function incrementRateLimitCounter(
        PDO $pdo,
        string $scope,
        string $keyHash,
        int $maximum,
        int $windowMinutes
    ): array {
        $ownsTransaction = !$pdo->inTransaction();
        try {
            if ($ownsTransaction) $pdo->beginTransaction();
            $now = date('Y-m-d H:i:s');
            $statement = $pdo->prepare(
                "INSERT IGNORE INTO login_rate_limits
                    (scope, key_hash, attempts, window_started_at, blocked_until, updated_at)
                 VALUES (:scope, :key_hash, 0, :window_started_at, NULL, :updated_at)"
            );
            $statement->execute([
                ':scope' => $scope,
                ':key_hash' => $keyHash,
                ':window_started_at' => $now,
                ':updated_at' => $now,
            ]);

            $statement = $pdo->prepare(
                "SELECT attempts, window_started_at, blocked_until
                 FROM login_rate_limits WHERE scope = :scope AND key_hash = :key_hash LIMIT 1 FOR UPDATE"
            );
            $statement->execute([':scope' => $scope, ':key_hash' => $keyHash]);
            $row = $statement->fetch();
            if (!$row) throw new RuntimeException('Rate-limit counter could not be initialized.');

            if (!empty($row['blocked_until']) && strtotime((string)$row['blocked_until']) > time()) {
                if ($ownsTransaction) $pdo->commit();
                return ['allowed' => false, 'attempts' => (int)$row['attempts'], 'blockedUntil' => $row['blocked_until']];
            }

            $windowExpired = strtotime((string)$row['window_started_at']) <= time() - ($windowMinutes * 60);
            $attempts = $windowExpired ? 1 : ((int)$row['attempts'] + 1);
            $windowStartedAt = $windowExpired ? $now : (string)$row['window_started_at'];
            $blockedUntil = $attempts >= $maximum ? date('Y-m-d H:i:s', time() + ($windowMinutes * 60)) : null;
            $statement = $pdo->prepare(
                "UPDATE login_rate_limits SET attempts = :attempts, window_started_at = :window_started_at,
                        blocked_until = :blocked_until, updated_at = :updated_at
                 WHERE scope = :scope AND key_hash = :key_hash"
            );
            $statement->execute([
                ':attempts' => $attempts,
                ':window_started_at' => $windowStartedAt,
                ':blocked_until' => $blockedUntil,
                ':updated_at' => $now,
                ':scope' => $scope,
                ':key_hash' => $keyHash,
            ]);
            if ($ownsTransaction) $pdo->commit();
            return ['allowed' => true, 'attempts' => $attempts, 'blockedUntil' => $blockedUntil];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    private static function validDate(string $value): ?string
    {
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new RuntimeException('Use YYYY-MM-DD for audit-log dates.');
        return $value;
    }
}
