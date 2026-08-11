<?php
declare(strict_types=1);

final class Database
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_TECH = 'tech';
    public const ROLE_READ_ONLY = 'read_only';

    public static function fromConfig(): ?PDO
    {
        $configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.local.php';
        if (!is_file($configPath)) {
            return null;
        }

        $config = require $configPath;
        $database = $config['database'] ?? [];
        if (empty($database['enabled'])) {
            return null;
        }

        $name = (string)($database['name'] ?? '');
        if ($name === '') {
            return null;
        }

        $host = (string)($database['host'] ?? 'localhost');
        $port = (int)($database['port'] ?? 3306);
        $charset = (string)($database['charset'] ?? 'utf8mb4');
        $username = (string)($database['username'] ?? '');
        $password = (string)($database['password'] ?? '');
        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=$charset";

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function initialize(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS reports (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_date DATE NOT NULL,
                report_name VARCHAR(255) NOT NULL,
                source_csv VARCHAR(255) NOT NULL,
                previous_csv VARCHAR(255) NULL,
                html_path VARCHAR(500) NOT NULL,
                json_path VARCHAR(500) NOT NULL,
                pos_terminal_count INT UNSIGNED NOT NULL DEFAULT 0,
                out_of_date_store_count INT UNSIGNED NOT NULL DEFAULT 0,
                out_of_date_terminal_count INT UNSIGNED NOT NULL DEFAULT 0,
                current_stable_version VARCHAR(80) NULL,
                most_current_version VARCHAR(80) NULL,
                current_upload_id INT UNSIGNED NULL,
                previous_upload_id INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_reports_created_at (created_at),
                INDEX idx_reports_report_date (report_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS csv_uploads (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                original_filename VARCHAR(255) NOT NULL,
                upload_role VARCHAR(40) NOT NULL,
                row_count INT UNSIGNED NOT NULL DEFAULT 0,
                uploaded_at DATETIME NOT NULL,
                INDEX idx_csv_uploads_uploaded_at (uploaded_at),
                INDEX idx_csv_uploads_role (upload_role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                display_name VARCHAR(160) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                two_factor_secret VARCHAR(64) NULL,
                two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
                two_factor_confirmed_at DATETIME NULL,
                role VARCHAR(40) NOT NULL DEFAULT 'tech',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_users_role (role),
                INDEX idx_users_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS terminal_rows (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                upload_id INT UNSIGNED NOT NULL,
                `row_number` INT UNSIGNED NOT NULL,
                store_id VARCHAR(80) NULL,
                store_name VARCHAR(255) NULL,
                terminal_id VARCHAR(80) NULL,
                computer_name VARCHAR(120) NULL,
                network_address VARCHAR(120) NULL,
                serial_number VARCHAR(120) NULL,
                terminal_type VARCHAR(80) NULL,
                current_version VARCHAR(120) NULL,
                last_seen_online VARCHAR(120) NULL,
                last_reboot VARCHAR(120) NULL,
                raw_json JSON NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_terminal_rows_upload_id (upload_id),
                INDEX idx_terminal_rows_store_id (store_id),
                INDEX idx_terminal_rows_current_version (current_version),
                INDEX idx_terminal_rows_terminal_type (terminal_type),
                CONSTRAINT fk_terminal_rows_upload
                    FOREIGN KEY (upload_id) REFERENCES csv_uploads(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec("ALTER TABLE reports ADD COLUMN IF NOT EXISTS current_upload_id INT UNSIGNED NULL");
        $pdo->exec("ALTER TABLE reports ADD COLUMN IF NOT EXISTS previous_upload_id INT UNSIGNED NULL");
        self::addColumnIfMissing($pdo, 'users', 'two_factor_secret', 'VARCHAR(64) NULL');
        self::addColumnIfMissing($pdo, 'users', 'two_factor_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
        self::addColumnIfMissing($pdo, 'users', 'two_factor_confirmed_at', 'DATETIME NULL');
    }

    public static function saveCsvUpload(PDO $pdo, string $filename, string $role, array $rows): int
    {
        self::initialize($pdo);
        $pdo->beginTransaction();
        try {
            $uploadStatement = $pdo->prepare(
                "INSERT INTO csv_uploads (original_filename, upload_role, row_count, uploaded_at)
                 VALUES (:original_filename, :upload_role, :row_count, :uploaded_at)"
            );
            $uploadStatement->execute([
                ':original_filename' => $filename,
                ':upload_role' => $role,
                ':row_count' => count($rows),
                ':uploaded_at' => date('Y-m-d H:i:s'),
            ]);
            $uploadId = (int)$pdo->lastInsertId();

            $rowStatement = $pdo->prepare(
                "INSERT INTO terminal_rows (
                    upload_id, `row_number`, store_id, store_name, terminal_id, computer_name,
                    network_address, serial_number, terminal_type, current_version,
                    last_seen_online, last_reboot, raw_json, created_at
                ) VALUES (
                    :upload_id, :row_number, :store_id, :store_name, :terminal_id, :computer_name,
                    :network_address, :serial_number, :terminal_type, :current_version,
                    :last_seen_online, :last_reboot, :raw_json, :created_at
                )"
            );

            foreach ($rows as $index => $row) {
                $rowStatement->execute([
                    ':upload_id' => $uploadId,
                    ':row_number' => $index + 1,
                    ':store_id' => self::field($row, 'Store ID'),
                    ':store_name' => self::field($row, 'Store Name'),
                    ':terminal_id' => self::field($row, 'Terminal ID'),
                    ':computer_name' => self::field($row, 'Computer Name'),
                    ':network_address' => self::field($row, 'Network Address'),
                    ':serial_number' => self::field($row, 'Serial Number'),
                    ':terminal_type' => self::field($row, 'Terminal Type'),
                    ':current_version' => self::field($row, 'Current Version'),
                    ':last_seen_online' => self::field($row, 'Last Seen Online'),
                    ':last_reboot' => self::field($row, 'Last Reboot'),
                    ':raw_json' => json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ':created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $pdo->commit();
            return $uploadId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function latestCsvUploads(PDO $pdo, int $limit = 2): array
    {
        self::initialize($pdo);
        $limit = max(1, min(25, $limit));
        $statement = $pdo->query(
            "SELECT id, original_filename, upload_role, row_count, uploaded_at
             FROM csv_uploads
             ORDER BY uploaded_at DESC, id DESC
             LIMIT $limit"
        );
        return array_map([self::class, 'csvUploadRow'], $statement->fetchAll());
    }

    public static function getCsvUpload(PDO $pdo, int $uploadId): ?array
    {
        self::initialize($pdo);
        if ($uploadId <= 0) {
            throw new RuntimeException('Invalid CSV upload ID.');
        }
        $statement = $pdo->prepare(
            "SELECT id, original_filename, upload_role, row_count, uploaded_at
             FROM csv_uploads
             WHERE id = :id
             LIMIT 1"
        );
        $statement->execute([':id' => $uploadId]);
        $row = $statement->fetch();
        return $row ? self::csvUploadRow($row) : null;
    }

    public static function previousCsvUpload(PDO $pdo, int $uploadId): ?array
    {
        self::initialize($pdo);
        if ($uploadId <= 0) {
            throw new RuntimeException('Invalid CSV upload ID.');
        }
        $current = self::getCsvUpload($pdo, $uploadId);
        if (!$current) {
            return null;
        }
        $statement = $pdo->prepare(
            "SELECT id, original_filename, upload_role, row_count, uploaded_at
             FROM csv_uploads
             WHERE id < :id
             ORDER BY id DESC
             LIMIT 1"
        );
        $statement->execute([':id' => $uploadId]);
        $row = $statement->fetch();
        return $row ? self::csvUploadRow($row) : null;
    }

    public static function listCsvUploads(PDO $pdo): array
    {
        self::initialize($pdo);
        $statement = $pdo->query(
            "SELECT id, original_filename, upload_role, row_count, uploaded_at
             FROM csv_uploads
             ORDER BY uploaded_at DESC, id DESC
             LIMIT 250"
        );
        return array_map([self::class, 'csvUploadRow'], $statement->fetchAll());
    }

    public static function getCsvUploadRows(PDO $pdo, int $uploadId): array
    {
        self::initialize($pdo);
        $statement = $pdo->prepare("SELECT raw_json FROM terminal_rows WHERE upload_id = :upload_id ORDER BY `row_number` ASC, id ASC");
        $statement->execute([':upload_id' => $uploadId]);
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $decoded = json_decode((string)$row['raw_json'], true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }
        return $rows;
    }

    public static function deleteCsvUpload(PDO $pdo, int $uploadId): void
    {
        self::initialize($pdo);
        if ($uploadId <= 0) {
            throw new RuntimeException('Invalid CSV upload ID.');
        }
        $statement = $pdo->prepare("DELETE FROM csv_uploads WHERE id = :id");
        $statement->execute([':id' => $uploadId]);
    }

    public static function saveReport(PDO $pdo, array $result): void
    {
        self::initialize($pdo);
        $report = $result['report'];
        $summary = $report['summary'];
        $statement = $pdo->prepare(
            "INSERT INTO reports (
                report_date, report_name, source_csv, previous_csv, html_path, json_path,
                pos_terminal_count, out_of_date_store_count, out_of_date_terminal_count,
                current_stable_version, most_current_version, current_upload_id, previous_upload_id, created_at
            ) VALUES (
                :report_date, :report_name, :source_csv, :previous_csv, :html_path, :json_path,
                :pos_terminal_count, :out_of_date_store_count, :out_of_date_terminal_count,
                :current_stable_version, :most_current_version, :current_upload_id, :previous_upload_id, :created_at
            )"
        );
        $statement->execute([
            ':report_date' => date('Y-m-d'),
            ':report_name' => $result['htmlFile'],
            ':source_csv' => $report['sourceCsv'],
            ':previous_csv' => $report['previousCsv'],
            ':html_path' => $result['htmlUrl'],
            ':json_path' => $result['jsonUrl'],
            ':pos_terminal_count' => $summary['posAppTerminals'],
            ':out_of_date_store_count' => $summary['outOfDateStores'],
            ':out_of_date_terminal_count' => $summary['outOfDatePosTerminals'],
            ':current_stable_version' => $summary['currentStableVersion'],
            ':most_current_version' => $summary['mostCurrentVersion'],
            ':current_upload_id' => $result['currentUploadId'],
            ':previous_upload_id' => $result['previousUploadId'],
            ':created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function userCount(PDO $pdo): int
    {
        self::initialize($pdo);
        return (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    public static function createUser(PDO $pdo, string $email, string $displayName, string $password, string $role = 'user'): array
    {
        self::initialize($pdo);
        $email = strtolower(trim($email));
        $displayName = trim($displayName);
        $role = self::normalizeRole($role);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }
        if ($displayName === '') {
            throw new RuntimeException('Enter a display name.');
        }
        if (strlen($password) < 10) {
            throw new RuntimeException('Password must be at least 10 characters.');
        }
        if (self::emailExists($pdo, $email)) {
            throw new RuntimeException('A user with this email address already exists.');
        }

        $statement = $pdo->prepare(
            "INSERT INTO users (email, display_name, password_hash, role, is_active, created_at, updated_at)
             VALUES (:email, :display_name, :password_hash, :role, 1, :created_at, :updated_at)"
        );
        $now = date('Y-m-d H:i:s');
        try {
            $statement->execute([
                ':email' => $email,
                ':display_name' => $displayName,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => $role,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new RuntimeException('A user with this email address already exists.');
            }
            throw $exception;
        }

        return [
            'id' => (int)$pdo->lastInsertId(),
            'email' => $email,
            'displayName' => $displayName,
            'role' => $role,
            'isActive' => true,
        ];
    }

    public static function listUsers(PDO $pdo): array
    {
        self::initialize($pdo);
        $statement = $pdo->query("SELECT id, email, display_name, role, is_active, two_factor_enabled, created_at FROM users ORDER BY created_at DESC");
        return array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'email' => $row['email'],
            'displayName' => $row['display_name'],
            'role' => self::normalizeRole((string)$row['role']),
            'roleLabel' => self::roleLabel((string)$row['role']),
            'isActive' => (bool)$row['is_active'],
            'twoFactorEnabled' => (bool)$row['two_factor_enabled'],
            'createdAt' => date('c', strtotime($row['created_at'])),
        ], $statement->fetchAll());
    }

    public static function setUserActive(PDO $pdo, int $id, bool $isActive, ?int $actingUserId = null): void
    {
        self::initialize($pdo);
        if ($id <= 0) {
            throw new RuntimeException('Invalid user ID.');
        }
        if ($actingUserId !== null && $id === $actingUserId && !$isActive) {
            throw new RuntimeException('You cannot deactivate your own account.');
        }
        $user = self::findUserById($pdo, $id);
        if (!$user) {
            throw new RuntimeException('User not found.');
        }
        if (!$isActive && self::normalizeRole((string)$user['role']) === self::ROLE_ADMIN && self::activeAdminCount($pdo) <= 1) {
            throw new RuntimeException('At least one active admin is required.');
        }
        $statement = $pdo->prepare("UPDATE users SET is_active = :is_active, updated_at = :updated_at WHERE id = :id");
        $statement->execute([
            ':id' => $id,
            ':is_active' => $isActive ? 1 : 0,
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function setUserRole(PDO $pdo, int $id, string $role, ?int $actingUserId = null): void
    {
        self::initialize($pdo);
        if ($id <= 0) {
            throw new RuntimeException('Invalid user ID.');
        }
        $role = self::normalizeRole($role);
        $user = self::findUserById($pdo, $id);
        if (!$user) {
            throw new RuntimeException('User not found.');
        }
        $currentRole = self::normalizeRole((string)$user['role']);
        if ($actingUserId !== null && $id === $actingUserId && $role !== self::ROLE_ADMIN) {
            throw new RuntimeException('You cannot remove your own admin role.');
        }
        if ($currentRole === self::ROLE_ADMIN && $role !== self::ROLE_ADMIN && (bool)$user['is_active'] && self::activeAdminCount($pdo) <= 1) {
            throw new RuntimeException('At least one active admin is required.');
        }
        $statement = $pdo->prepare("UPDATE users SET role = :role, updated_at = :updated_at WHERE id = :id");
        $statement->execute([
            ':id' => $id,
            ':role' => $role,
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function deleteUser(PDO $pdo, int $id, ?int $actingUserId = null): void
    {
        self::initialize($pdo);
        if ($id <= 0) {
            throw new RuntimeException('Invalid user ID.');
        }
        if ($actingUserId !== null && $id === $actingUserId) {
            throw new RuntimeException('You cannot delete your own account.');
        }
        $user = self::findUserById($pdo, $id);
        if (!$user) {
            throw new RuntimeException('User not found.');
        }
        if (self::normalizeRole((string)$user['role']) === self::ROLE_ADMIN && (bool)$user['is_active'] && self::activeAdminCount($pdo) <= 1) {
            throw new RuntimeException('At least one active admin is required.');
        }
        $statement = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $statement->execute([':id' => $id]);
    }

    public static function setUserTwoFactor(PDO $pdo, int $id, ?string $secret, bool $enabled): void
    {
        self::initialize($pdo);
        if ($id <= 0) {
            throw new RuntimeException('Invalid user ID.');
        }
        $statement = $pdo->prepare(
            "UPDATE users
             SET two_factor_secret = :secret,
                 two_factor_enabled = :enabled,
                 two_factor_confirmed_at = :confirmed_at,
                 updated_at = :updated_at
             WHERE id = :id"
        );
        $statement->execute([
            ':id' => $id,
            ':secret' => $secret,
            ':enabled' => $enabled ? 1 : 0,
            ':confirmed_at' => $enabled ? date('Y-m-d H:i:s') : null,
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));
        $role = str_replace('-', '_', $role);
        if ($role === 'user') {
            return self::ROLE_TECH;
        }
        if (in_array($role, [self::ROLE_ADMIN, self::ROLE_TECH, self::ROLE_READ_ONLY], true)) {
            return $role;
        }
        return self::ROLE_READ_ONLY;
    }

    public static function roleLabel(string $role): string
    {
        return match (self::normalizeRole($role)) {
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_TECH => 'Tech',
            default => 'Read-Only',
        };
    }

    public static function listReports(PDO $pdo): array
    {
        self::initialize($pdo);
        $statement = $pdo->query(
            "SELECT report_name, report_date, source_csv, previous_csv, html_path, json_path,
                    pos_terminal_count, out_of_date_store_count, out_of_date_terminal_count,
                    current_stable_version, most_current_version, created_at
             FROM reports
             ORDER BY created_at DESC
             LIMIT 250"
        );

        return array_map(static fn(array $row): array => [
            'name' => $row['report_name'],
            'date' => $row['report_date'],
            'sourceCsv' => $row['source_csv'],
            'previousCsv' => $row['previous_csv'],
            'url' => $row['html_path'],
            'jsonUrl' => $row['json_path'],
            'posTerminals' => (int)$row['pos_terminal_count'],
            'outOfDateStores' => (int)$row['out_of_date_store_count'],
            'outOfDateTerminals' => (int)$row['out_of_date_terminal_count'],
            'currentStableVersion' => $row['current_stable_version'],
            'mostCurrentVersion' => $row['most_current_version'],
            'modified' => date('c', strtotime($row['created_at'])),
        ], $statement->fetchAll());
    }

    public static function latestReport(PDO $pdo): ?array
    {
        self::initialize($pdo);
        $statement = $pdo->query(
            "SELECT report_name, report_date, source_csv, previous_csv, html_path, json_path,
                    pos_terminal_count, out_of_date_store_count, out_of_date_terminal_count,
                    current_stable_version, most_current_version, created_at
             FROM reports
             ORDER BY created_at DESC
             LIMIT 1"
        );
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }
        $items = self::listReports($pdo);
        return $items[0] ?? null;
    }

    private static function field(array $row, string $name): ?string
    {
        $value = trim((string)($row[$name] ?? ''));
        return $value === '' ? null : $value;
    }

    private static function csvUploadRow(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'filename' => $row['original_filename'],
            'role' => $row['upload_role'],
            'rowCount' => (int)$row['row_count'],
            'uploadedAt' => date('c', strtotime($row['uploaded_at'])),
        ];
    }

    private static function findUserById(PDO $pdo, int $id): ?array
    {
        $statement = $pdo->prepare("SELECT id, role, is_active FROM users WHERE id = :id LIMIT 1");
        $statement->execute([':id' => $id]);
        $user = $statement->fetch();
        return $user ?: null;
    }

    private static function emailExists(PDO $pdo, string $email): bool
    {
        $statement = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $statement->execute([':email' => $email]);
        return (int)$statement->fetchColumn() > 0;
    }

    private static function activeAdminCount(PDO $pdo): int
    {
        $statement = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = :role AND is_active = 1");
        $statement->execute([':role' => self::ROLE_ADMIN]);
        return (int)$statement->fetchColumn();
    }

    private static function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
    {
        $statement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name"
        );
        $statement->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);
        if ((int)$statement->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }
}
