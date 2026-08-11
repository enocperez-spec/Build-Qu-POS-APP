<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Auth
{
    private const ISSUER = 'QU POS App Tool';
    private const PENDING_USER_KEY = 'pending_2fa_user';
    private const PENDING_SECRET_KEY = 'pending_2fa_secret';
    private const ROLE_ADMIN = 'admin';
    private const ROLE_TECH = 'tech';
    private const ROLE_READ_ONLY = 'read_only';

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
        $_SESSION['user']['role'] = Database::normalizeRole((string)($_SESSION['user']['role'] ?? ''));
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
        $statement = $pdo->prepare(
            "SELECT id, email, display_name, role, password_hash, is_active, two_factor_secret, two_factor_enabled
             FROM users
             WHERE email = :email
             LIMIT 1"
        );
        $statement->execute([':email' => strtolower(trim($email))]);
        $user = $statement->fetch();
        if (!$user || !(bool)$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            throw new RuntimeException('Invalid email or password.');
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
        self::start();
        $user = self::pendingUser($pdo);
        if (empty($user['two_factor_secret']) || !(bool)$user['two_factor_enabled']) {
            throw new RuntimeException('Two-factor setup is required.');
        }
        if (!self::verifyTotpCode((string)$user['two_factor_secret'], $code)) {
            throw new RuntimeException('Invalid two-factor code.');
        }
        return self::completeLogin($user);
    }

    public static function confirmTwoFactorSetup(PDO $pdo, string $code): array
    {
        self::start();
        $user = self::pendingUser($pdo);
        $secret = $_SESSION[self::PENDING_SECRET_KEY] ?? '';
        if ($secret === '') {
            throw new RuntimeException('Two-factor setup was not started. Please sign in again.');
        }
        if (!self::verifyTotpCode($secret, $code)) {
            throw new RuntimeException('Invalid two-factor code.');
        }
        Database::setUserTwoFactor($pdo, (int)$user['id'], $secret, true);
        $user['two_factor_secret'] = $secret;
        $user['two_factor_enabled'] = 1;
        return self::completeLogin($user);
    }

    public static function resetTwoFactor(PDO $pdo, int $userId): void
    {
        Database::setUserTwoFactor($pdo, $userId, null, false);
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

    private static function pendingUser(PDO $pdo): array
    {
        $userId = (int)($_SESSION[self::PENDING_USER_KEY] ?? 0);
        if ($userId <= 0) {
            throw new RuntimeException('Please sign in again.');
        }
        $statement = $pdo->prepare(
            "SELECT id, email, display_name, role, is_active, two_factor_secret, two_factor_enabled
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

    private static function completeLogin(array $user): array
    {
        self::start();
        session_regenerate_id(true);
        unset($_SESSION[self::PENDING_USER_KEY], $_SESSION[self::PENDING_SECRET_KEY]);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'displayName' => $user['display_name'],
            'role' => Database::normalizeRole((string)$user['role']),
        ];
        return $_SESSION['user'];
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
