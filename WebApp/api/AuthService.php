<?php
declare(strict_types=1);

require_once __DIR__ . '/SecurityService.php';

final class Auth
{
    private const ISSUER = 'QU POS App Tool';
    private const PENDING_USER_KEY = 'pending_2fa_user';
    private const PENDING_SECRET_KEY = 'pending_2fa_secret';
    private const ROLE_ADMIN = 'admin';
    private const ROLE_TECH = 'tech';
    private const ROLE_READ_ONLY = 'read_only';
    private const DUMMY_PASSWORD_HASH = '$2y$12$XeX1tl3PZdNAbqq7EEQi4eVoMBRACE6r9kOwAgLKo..Pf/lPmgci.';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            ]);
            session_start();
        }
    }

    public static function currentUser(): ?array
    {
        self::start();
        if (!isset($_SESSION['user'])) {
            return null;
        }
        $pdo = Database::fromConfig();
        if ($pdo) {
            try {
                $statement = $pdo->prepare("SELECT email, display_name, role, is_active, auth_version FROM users WHERE id = :id LIMIT 1");
                $statement->execute([':id' => (int)$_SESSION['user']['id']]);
                $current = $statement->fetch();
                $sessionVersion = (int)($_SESSION['user']['authVersion'] ?? 0);
                if (!$current || !(bool)$current['is_active'] || ($sessionVersion > 0 && $sessionVersion !== (int)$current['auth_version'])) {
                    unset($_SESSION['user']);
                    return null;
                }
                $_SESSION['user']['email'] = $current['email'];
                $_SESSION['user']['displayName'] = $current['display_name'];
                $_SESSION['user']['role'] = Database::normalizeRole((string)$current['role']);
                $_SESSION['user']['authVersion'] = (int)$current['auth_version'];
            } catch (PDOException $exception) {
                if ($exception->getCode() === '42S22') {
                    $_SESSION['user']['role'] = Database::normalizeRole((string)($_SESSION['user']['role'] ?? ''));
                } else {
                    unset($_SESSION['user']);
                    return null;
                }
            }
        } else {
            $_SESSION['user']['role'] = Database::normalizeRole((string)($_SESSION['user']['role'] ?? ''));
        }
        return $_SESSION['user'];
    }

    public static function requireLogin(): array
    {
        $user = self::currentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Login required.']);
            exit;
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if (($user['role'] ?? '') !== self::ROLE_ADMIN) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Admin access required.']);
            exit;
        }
        return $user;
    }

    public static function requireSection(PDO $pdo, string $section): array
    {
        $user = self::requireLogin();
        if (!Database::userCanAccessSection($pdo, $user, $section)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'You do not have access to this section.']);
            exit;
        }
        return $user;
    }

    public static function requireTechOrAdmin(): array
    {
        $user = self::requireLogin();
        $role = $user['role'] ?? '';
        if (!in_array($role, [self::ROLE_ADMIN, self::ROLE_TECH], true)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Tech or admin access required.']);
            exit;
        }
        return $user;
    }

    public static function login(PDO $pdo, string $email, string $password): array
    {
        Database::initialize($pdo);
        SecurityService::assertIpLoginAllowed($pdo);
        $email = strtolower(trim($email));
        $statement = $pdo->prepare(
            "SELECT id, email, display_name, role, password_hash, is_active, two_factor_secret, two_factor_enabled,
                    failed_login_attempts, locked_until
             FROM users
             WHERE email = :email
             LIMIT 1"
        );
        $statement->execute([':email' => $email]);
        $user = $statement->fetch();
        if (!$user) {
            password_verify($password, self::DUMMY_PASSWORD_HASH);
            SecurityService::recordIpLoginResult($pdo, false);
            SecurityService::audit($pdo, null, 'authentication', 'Login attempt', 'email', null, $email, 'failed', [], 'Invalid credentials.');
            throw new RuntimeException('Invalid email or password.');
        }
        if (!(bool)$user['is_active']) {
            SecurityService::recordIpLoginResult($pdo, false);
            SecurityService::audit($pdo, $user, 'authentication', 'Login attempt', 'user', (string)$user['id'], $user['email'], 'blocked', [], 'Account is disabled.');
            throw new RuntimeException('Invalid email or password.');
        }
        if (SecurityService::isAccountLocked($user)) {
            SecurityService::recordIpLoginResult($pdo, false);
            SecurityService::audit($pdo, $user, 'authentication', 'Login attempt', 'user', (string)$user['id'], $user['email'], 'blocked', [
                'lockedUntil' => date('c', strtotime((string)$user['locked_until'])),
            ]);
            throw new RuntimeException('Sign-in is temporarily unavailable. Try again later.');
        }
        if (!password_verify($password, (string)$user['password_hash'])) {
            $locked = SecurityService::recordAccountFailure($pdo, $user);
            SecurityService::recordIpLoginResult($pdo, false);
            SecurityService::audit($pdo, $user, 'authentication', 'Login attempt', 'user', (string)$user['id'], $user['email'], $locked ? 'blocked' : 'failed', [
                'failedAttempts' => (int)$user['failed_login_attempts'] + 1,
            ], 'Invalid credentials.');
            throw new RuntimeException($locked ? 'Sign-in is temporarily unavailable. Try again later.' : 'Invalid email or password.');
        }

        self::start();
        $_SESSION[self::PENDING_USER_KEY] = (int)$user['id'];
        unset($_SESSION[self::PENDING_SECRET_KEY], $_SESSION['user']);

        if (!(bool)$user['two_factor_enabled'] || empty($user['two_factor_secret'])) {
            $secret = self::generateTotpSecret();
            $_SESSION[self::PENDING_SECRET_KEY] = $secret;
            return [
                'requiresTwoFactorSetup' => true,
                'setup' => self::totpSetupPayload($user['email'], $secret),
            ];
        }

        return ['requiresTwoFactor' => true];
    }

    public static function verifyTwoFactor(PDO $pdo, string $code): array
    {
        SecurityService::assertIpLoginAllowed($pdo);
        self::start();
        $user = self::pendingUser($pdo);
        if (empty($user['two_factor_secret']) || !(bool)$user['two_factor_enabled']) {
            throw new RuntimeException('Two-factor setup is required.');
        }
        $method = self::verifySecondFactorForUser($pdo, $user, $code);
        if ($method === null) {
            SecurityService::recordIpLoginResult($pdo, false);
            SecurityService::audit($pdo, $user, 'authentication', 'Two-factor login attempt', 'user', (string)$user['id'], $user['email'], 'failed', [], 'Invalid second factor.');
            throw new RuntimeException('Invalid two-factor code.');
        }
        return self::completeLogin($pdo, $user, $method);
    }

    public static function confirmTwoFactorSetup(PDO $pdo, string $code): array
    {
        SecurityService::assertIpLoginAllowed($pdo);
        self::start();
        $user = self::pendingUser($pdo);
        $secret = $_SESSION[self::PENDING_SECRET_KEY] ?? '';
        if ($secret === '') {
            throw new RuntimeException('Two-factor setup was not started. Please sign in again.');
        }
        if (!self::verifyTotpCode($secret, $code)) {
            SecurityService::recordIpLoginResult($pdo, false);
            SecurityService::audit($pdo, $user, 'two_factor', 'Two-factor setup confirmation', 'user', (string)$user['id'], $user['email'], 'failed', [], 'Invalid authenticator code.');
            throw new RuntimeException('Invalid two-factor code.');
        }
        Database::setUserTwoFactor($pdo, (int)$user['id'], $secret, true);
        $user['two_factor_secret'] = $secret;
        $user['two_factor_enabled'] = 1;
        $recoveryCodes = SecurityService::generateRecoveryCodes($pdo, (int)$user['id']);
        SecurityService::audit($pdo, $user, 'two_factor', 'Two-factor authentication enabled', 'user', (string)$user['id'], $user['email'], 'successful', [
            'recoveryCodesGenerated' => count($recoveryCodes),
        ]);
        return [
            'user' => self::completeLogin($pdo, $user, 'totp_setup'),
            'recoveryCodes' => $recoveryCodes,
        ];
    }

    public static function resetTwoFactor(PDO $pdo, int $userId): void
    {
        Database::setUserTwoFactor($pdo, $userId, null, false);
        $pdo->prepare("DELETE FROM two_factor_recovery_codes WHERE user_id = :user_id")->execute([':user_id' => $userId]);
    }

    public static function verifyIdentity(PDO $pdo, int $userId, string $password, string $secondFactor): bool
    {
        $statement = $pdo->prepare(
            "SELECT id, email, display_name, password_hash, two_factor_secret, two_factor_enabled
             FROM users WHERE id = :id AND is_active = 1 LIMIT 1"
        );
        $statement->execute([':id' => $userId]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, (string)$user['password_hash'])) return false;
        return self::verifySecondFactorForUser($pdo, $user, $secondFactor) !== null;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }

    public static function updateCurrentUserIdentity(array $updatedUser): void
    {
        self::start();
        if (!isset($_SESSION['user']) || (int)$_SESSION['user']['id'] !== (int)$updatedUser['id']) return;
        $_SESSION['user']['email'] = $updatedUser['email'];
        $_SESSION['user']['displayName'] = $updatedUser['displayName'];
    }

    public static function refreshCurrentSession(PDO $pdo): void
    {
        self::start();
        if (!isset($_SESSION['user']['id'])) return;
        $statement = $pdo->prepare("SELECT email, display_name, role, is_active, auth_version FROM users WHERE id = :id LIMIT 1");
        $statement->execute([':id' => (int)$_SESSION['user']['id']]);
        $current = $statement->fetch();
        if (!$current || !(bool)$current['is_active']) {
            unset($_SESSION['user']);
            return;
        }
        $_SESSION['user']['email'] = $current['email'];
        $_SESSION['user']['displayName'] = $current['display_name'];
        $_SESSION['user']['role'] = Database::normalizeRole((string)$current['role']);
        $_SESSION['user']['authVersion'] = (int)$current['auth_version'];
    }

    private static function pendingUser(PDO $pdo): array
    {
        $userId = (int)($_SESSION[self::PENDING_USER_KEY] ?? 0);
        if ($userId <= 0) {
            throw new RuntimeException('Please sign in again.');
        }
        $statement = $pdo->prepare(
            "SELECT id, email, display_name, role, is_active, two_factor_secret, two_factor_enabled,
                    failed_login_attempts, locked_until
             FROM users
             WHERE id = :id
             LIMIT 1"
        );
        $statement->execute([':id' => $userId]);
        $user = $statement->fetch();
        if (!$user || !(bool)$user['is_active']) {
            throw new RuntimeException('User is not active.');
        }
        return $user;
    }

    private static function completeLogin(PDO $pdo, array $user, string $method): array
    {
        SecurityService::recordAccountSuccess($pdo, $user);
        SecurityService::audit($pdo, $user, 'authentication', 'Login successful', 'user', (string)$user['id'], $user['email'], 'successful', [
            'secondFactorMethod' => $method,
        ]);
        self::start();
        session_regenerate_id(true);
        unset($_SESSION[self::PENDING_USER_KEY], $_SESSION[self::PENDING_SECRET_KEY]);
        $versionStatement = $pdo->prepare("SELECT auth_version FROM users WHERE id = :id LIMIT 1");
        $versionStatement->execute([':id' => (int)$user['id']]);
        $authVersion = (int)$versionStatement->fetchColumn();
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'displayName' => $user['display_name'],
            'role' => Database::normalizeRole((string)$user['role']),
            'authVersion' => $authVersion,
        ];
        return $_SESSION['user'];
    }

    private static function verifySecondFactorForUser(PDO $pdo, array $user, string $code): ?string
    {
        if (!empty($user['two_factor_secret']) && self::verifyTotpCode((string)$user['two_factor_secret'], $code)) {
            return 'totp';
        }
        if (SecurityService::consumeRecoveryCode($pdo, (int)$user['id'], $code)) {
            SecurityService::audit($pdo, $user, 'two_factor', 'Recovery code used', 'user', (string)$user['id'], $user['email'], 'successful', [
                'remainingCodes' => SecurityService::recoveryCodeCount($pdo, (int)$user['id']),
            ]);
            return 'recovery_code';
        }
        return null;
    }

    private static function generateTotpSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    private static function totpSetupPayload(string $email, string $secret): array
    {
        $label = self::ISSUER . ':' . strtolower(trim($email));
        $uri = 'otpauth://totp/' . rawurlencode($label)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode(self::ISSUER)
            . '&algorithm=SHA1&digits=6&period=30';
        return [
            'secret' => $secret,
            'otpauthUri' => $uri,
            'issuer' => self::ISSUER,
        ];
    }

    private static function verifyTotpCode(string $secret, string $code): bool
    {
        $cleanCode = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($cleanCode) !== 6) {
            return false;
        }
        $counter = (int)floor(time() / 30);
        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals(self::totpCode($secret, $counter + $offset), $cleanCode)) {
                return true;
            }
        }
        return false;
    }

    private static function totpCode(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        $binaryCounter = pack('N2', intdiv($counter, 0x100000000), $counter & 0xffffffff);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $encoded = '';
        for ($i = 0; $i < strlen($bytes); $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $encoded .= $alphabet[bindec($chunk)];
        }
        return $encoded;
    }

    private static function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';
        $decoded = '';
        for ($i = 0; $i < strlen($clean); $i++) {
            $index = strpos($alphabet, $clean[$i]);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }
        return $decoded;
    }
}
