<?php
declare(strict_types=1);

require_once __DIR__ . '/../WebApp/api/SecurityService.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function expectFailure(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException($message);
}

SecurityService::validatePassword('SecurePassword123');
expectFailure(
    static fn() => SecurityService::validatePassword('short1A'),
    'Short passwords must be rejected.'
);
expectFailure(
    static fn() => SecurityService::validatePassword('lowercaseonly123'),
    'Passwords without uppercase letters must be rejected.'
);
expectFailure(
    static fn() => SecurityService::validatePassword('NOLOWERCASE123'),
    'Passwords without lowercase letters must be rejected.'
);
expectFailure(
    static fn() => SecurityService::validatePassword('NoNumbersHere'),
    'Passwords without numbers must be rejected.'
);

$token = bin2hex(random_bytes(32));
$hash = SecurityService::hashResetToken($token);
expect(strlen($hash) === 64, 'Reset tokens must use a SHA-256 hash.');
expect($hash !== $token, 'Raw reset tokens must not be stored.');
expect(hash_equals($hash, SecurityService::hashResetToken($token)), 'Reset-token hashing must be deterministic.');
expect(!hash_equals($hash, SecurityService::hashResetToken($token . 'x')), 'Different reset tokens must not share a hash.');

expect(SecurityService::normalizeRecoveryCode('ABCD-1234-ef56') === 'ABCD1234EF56', 'Recovery codes must normalize case and separators.');
expect(SecurityService::normalizeRecoveryCode(' ABCD 1234 EF56 ') === 'ABCD1234EF56', 'Recovery-code whitespace must be removed.');

echo "Security service tests passed.\n";
