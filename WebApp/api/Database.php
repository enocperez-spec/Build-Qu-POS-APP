<?php
declare(strict_types=1);

final class Database
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_TECH = 'tech';
    public const ROLE_READ_ONLY = 'read_only';
    public const SECTION_DASHBOARD = 'dashboard';
    public const SECTION_REPORTS = 'reports';
    public const SECTION_UPLOAD = 'upload';
    public const SECTION_ALERTS = 'alerts';
    public const SECTION_SETTINGS = 'settings';
    public const JOB_TERMINALS = 'qu_ei_terminals_csv';
    public const JOB_STORES = 'qu_ei_stores_csv';

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
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS store_imports (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                original_filename VARCHAR(255) NOT NULL,
                row_count INT UNSIGNED NOT NULL DEFAULT 0,
                uploaded_at DATETIME NOT NULL,
                INDEX idx_store_imports_uploaded_at (uploaded_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS store_rows (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_id INT UNSIGNED NOT NULL,
                `row_number` INT UNSIGNED NOT NULL,
                store_id VARCHAR(80) NULL,
                store_name VARCHAR(255) NULL,
                brand VARCHAR(160) NULL,
                status VARCHAR(120) NULL,
                timezone VARCHAR(120) NULL,
                address VARCHAR(255) NULL,
                city VARCHAR(120) NULL,
                state VARCHAR(80) NULL,
                postal_code VARCHAR(40) NULL,
                phone VARCHAR(80) NULL,
                raw_json JSON NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_store_rows_import_id (import_id),
                INDEX idx_store_rows_store_id (store_id),
                INDEX idx_store_rows_brand (brand),
                CONSTRAINT fk_store_rows_import
                    FOREIGN KEY (import_id) REFERENCES store_imports(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS role_permissions (
                role VARCHAR(40) NOT NULL,
                section_key VARCHAR(80) NOT NULL,
                can_access TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (role, section_key),
                INDEX idx_role_permissions_section (section_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS api_schedules (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                job_key VARCHAR(80) NOT NULL,
                job_name VARCHAR(160) NOT NULL,
                scheduled_time CHAR(5) NOT NULL,
                timezone VARCHAR(80) NOT NULL DEFAULT 'America/New_York',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_run_at DATETIME NULL,
                last_status VARCHAR(40) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_api_schedule_time (job_key, scheduled_time),
                INDEX idx_api_schedules_job (job_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS api_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                job_key VARCHAR(80) NOT NULL,
                source VARCHAR(160) NOT NULL,
                trigger_type VARCHAR(40) NOT NULL,
                initiated_by_user_id INT UNSIGNED NULL,
                initiated_by_name VARCHAR(160) NULL,
                status VARCHAR(40) NOT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                records_received INT UNSIGNED NOT NULL DEFAULT 0,
                records_added INT UNSIGNED NOT NULL DEFAULT 0,
                records_updated INT UNSIGNED NOT NULL DEFAULT 0,
                records_skipped INT UNSIGNED NOT NULL DEFAULT 0,
                duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
                error_message TEXT NULL,
                started_at DATETIME NOT NULL,
                completed_at DATETIME NULL,
                INDEX idx_api_logs_started (started_at),
                INDEX idx_api_logs_job (job_key),
                INDEX idx_api_logs_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS api_locks (
                lock_key VARCHAR(80) PRIMARY KEY,
                locked_at DATETIME NOT NULL,
                locked_by VARCHAR(160) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec("ALTER TABLE reports ADD COLUMN IF NOT EXISTS current_upload_id INT UNSIGNED NULL");
        $pdo->exec("ALTER TABLE reports ADD COLUMN IF NOT EXISTS previous_upload_id INT UNSIGNED NULL");
        self::addColumnIfMissing($pdo, 'users', 'two_factor_secret', 'VARCHAR(64) NULL');
        self::addColumnIfMissing($pdo, 'users', 'two_factor_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
        self::addColumnIfMissing($pdo, 'users', 'two_factor_confirmed_at', 'DATETIME NULL');
        self::seedRolePermissions($pdo);
        self::seedApiSchedules($pdo);
    }

    public static function navigationSections(): array
    {
        return [
            ['key' => self::SECTION_DASHBOARD, 'label' => 'Dashboard'],
            ['key' => self::SECTION_REPORTS, 'label' => 'View Reports'],
            ['key' => self::SECTION_UPLOAD, 'label' => 'Upload CSV'],
            ['key' => self::SECTION_ALERTS, 'label' => 'Alerts'],
            ['key' => self::SECTION_SETTINGS, 'label' => 'Settings'],
        ];
    }

    public static function roles(): array
    {
        return [self::ROLE_ADMIN, self::ROLE_TECH, self::ROLE_READ_ONLY];
    }

    public static function defaultRolePermissions(): array
    {
        return [
            self::ROLE_ADMIN => [
                self::SECTION_DASHBOARD => true,
                self::SECTION_REPORTS => true,
                self::SECTION_UPLOAD => true,
                self::SECTION_ALERTS => true,
                self::SECTION_SETTINGS => true,
            ],
            self::ROLE_TECH => [
                self::SECTION_DASHBOARD => true,
                self::SECTION_REPORTS => true,
                self::SECTION_UPLOAD => true,
                self::SECTION_ALERTS => true,
                self::SECTION_SETTINGS => false,
            ],
            self::ROLE_READ_ONLY => [
                self::SECTION_DASHBOARD => true,
                self::SECTION_REPORTS => true,
                self::SECTION_UPLOAD => false,
                self::SECTION_ALERTS => true,
                self::SECTION_SETTINGS => false,
            ],
        ];
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

    public static function saveStoreImport(PDO $pdo, string $filename, array $rows): int
    {
        self::initialize($pdo);
        $pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $uploadStatement = $pdo->prepare(
                "INSERT INTO store_imports (original_filename, row_count, uploaded_at)
                 VALUES (:original_filename, :row_count, :uploaded_at)"
            );
            $uploadStatement->execute([
                ':original_filename' => $filename,
                ':row_count' => count($rows),
                ':uploaded_at' => $now,
            ]);
            $importId = (int)$pdo->lastInsertId();

            $rowStatement = $pdo->prepare(
                "INSERT INTO store_rows (
                    import_id, `row_number`, store_id, store_name, brand, status, timezone,
                    address, city, state, postal_code, phone, raw_json, created_at
                ) VALUES (
                    :import_id, :row_number, :store_id, :store_name, :brand, :status, :timezone,
                    :address, :city, :state, :postal_code, :phone, :raw_json, :created_at
                )"
            );

            foreach ($rows as $index => $row) {
                $rowStatement->execute([
                    ':import_id' => $importId,
                    ':row_number' => $index + 1,
                    ':store_id' => self::fieldAny($row, ['Store ID', 'Store Id', 'Store Number', 'Number', 'ID']),
                    ':store_name' => self::fieldAny($row, ['Store Name', 'Name', 'Location Name']),
                    ':brand' => self::fieldAny($row, ['Brand', 'Concept', 'Store Brand']),
                    ':status' => self::fieldAny($row, ['Status', 'Store Status']),
                    ':timezone' => self::fieldAny($row, ['Timezone', 'Time Zone']),
                    ':address' => self::fieldAny($row, ['Address', 'Address 1', 'Street Address']),
                    ':city' => self::fieldAny($row, ['City']),
                    ':state' => self::fieldAny($row, ['State', 'Province']),
                    ':postal_code' => self::fieldAny($row, ['Postal Code', 'Zip', 'ZIP Code']),
                    ':phone' => self::fieldAny($row, ['Phone', 'Phone Number']),
                    ':raw_json' => json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ':created_at' => $now,
                ]);
            }

            $pdo->commit();
            return $importId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
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

    public static function listRolePermissions(PDO $pdo): array
    {
        self::initialize($pdo);
        $statement = $pdo->query("SELECT role, section_key, can_access FROM role_permissions");
        $permissions = self::defaultRolePermissions();
        foreach ($statement->fetchAll() as $row) {
            $role = self::normalizeRole((string)$row['role']);
            $section = (string)$row['section_key'];
            if (isset($permissions[$role])) {
                $permissions[$role][$section] = (bool)$row['can_access'];
            }
        }
        return $permissions;
    }

    public static function setRolePermissions(PDO $pdo, string $role, array $permissions): void
    {
        self::initialize($pdo);
        $role = self::normalizeRole($role);
        if ($role === self::ROLE_ADMIN && empty($permissions[self::SECTION_SETTINGS])) {
            throw new RuntimeException('The Admin role must keep Settings access.');
        }
        if ($role === self::ROLE_ADMIN && self::activeAdminCount($pdo) < 1) {
            throw new RuntimeException('At least one active admin is required.');
        }

        $sections = array_column(self::navigationSections(), 'key');
        $statement = $pdo->prepare(
            "INSERT INTO role_permissions (role, section_key, can_access, updated_at)
             VALUES (:role, :section_key, :can_access, :updated_at)
             ON DUPLICATE KEY UPDATE can_access = VALUES(can_access), updated_at = VALUES(updated_at)"
        );
        $now = date('Y-m-d H:i:s');
        foreach ($sections as $section) {
            $canAccess = !empty($permissions[$section]);
            if ($role === self::ROLE_ADMIN && $section === self::SECTION_SETTINGS) {
                $canAccess = true;
            }
            $statement->execute([
                ':role' => $role,
                ':section_key' => $section,
                ':can_access' => $canAccess ? 1 : 0,
                ':updated_at' => $now,
            ]);
        }
    }

    public static function userCanAccessSection(PDO $pdo, array $user, string $section): bool
    {
        self::initialize($pdo);
        $role = self::normalizeRole((string)($user['role'] ?? ''));
        $statement = $pdo->prepare(
            "SELECT can_access FROM role_permissions WHERE role = :role AND section_key = :section_key LIMIT 1"
        );
        $statement->execute([':role' => $role, ':section_key' => $section]);
        $value = $statement->fetchColumn();
        if ($value === false) {
            $defaults = self::defaultRolePermissions();
            return !empty($defaults[$role][$section]);
        }
        return (bool)$value;
    }

    public static function listApiSchedules(PDO $pdo): array
    {
        self::initialize($pdo);
        $statement = $pdo->query(
            "SELECT id, job_key, job_name, scheduled_time, timezone, is_active, last_run_at, last_status
             FROM api_schedules
             ORDER BY job_name ASC, scheduled_time ASC"
        );
        return array_map([self::class, 'apiScheduleRow'], $statement->fetchAll());
    }

    public static function addApiSchedule(PDO $pdo, string $time, string $jobKey = self::JOB_TERMINALS): void
    {
        self::initialize($pdo);
        $time = self::validateScheduleTime($time);
        $job = self::apiJob($jobKey);
        $now = date('Y-m-d H:i:s');
        $statement = $pdo->prepare(
            "INSERT INTO api_schedules (job_key, job_name, scheduled_time, timezone, is_active, created_at, updated_at)
             VALUES (:job_key, :job_name, :scheduled_time, 'America/New_York', 1, :created_at, :updated_at)"
        );
        try {
            $statement->execute([
                ':job_key' => $job['key'],
                ':job_name' => $job['name'],
                ':scheduled_time' => $time,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new RuntimeException('That scheduled time already exists.');
            }
            throw $exception;
        }
    }

    public static function updateApiSchedule(PDO $pdo, int $id, string $time): void
    {
        self::initialize($pdo);
        if ($id <= 0) {
            throw new RuntimeException('Invalid schedule ID.');
        }
        $time = self::validateScheduleTime($time);
        $statement = $pdo->prepare("UPDATE api_schedules SET scheduled_time = :scheduled_time, updated_at = :updated_at WHERE id = :id");
        try {
            $statement->execute([':id' => $id, ':scheduled_time' => $time, ':updated_at' => date('Y-m-d H:i:s')]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new RuntimeException('That scheduled time already exists.');
            }
            throw $exception;
        }
    }

    public static function listApiLogs(PDO $pdo, int $limit = 100): array
    {
        self::initialize($pdo);
        $limit = max(1, min(250, $limit));
        $statement = $pdo->query(
            "SELECT id, job_key, source, trigger_type, initiated_by_name, status, attempts,
                    records_received, records_added, records_updated, records_skipped,
                    duration_ms, error_message, started_at, completed_at
             FROM api_logs
             ORDER BY started_at DESC, id DESC
             LIMIT $limit"
        );
        return array_map([self::class, 'apiLogRow'], $statement->fetchAll());
    }

    public static function startApiLog(PDO $pdo, string $triggerType, ?array $user = null, int $attempts = 1, string $jobKey = self::JOB_TERMINALS): int
    {
        self::initialize($pdo);
        $job = self::apiJob($jobKey);
        $statement = $pdo->prepare(
            "INSERT INTO api_logs (
                job_key, source, trigger_type, initiated_by_user_id, initiated_by_name,
                status, attempts, started_at
             ) VALUES (
                :job_key, :source, :trigger_type, :user_id, :user_name,
                'In Progress', :attempts, :started_at
             )"
        );
        $statement->execute([
            ':job_key' => $job['key'],
            ':source' => $job['source'],
            ':trigger_type' => $triggerType,
            ':user_id' => $user['id'] ?? null,
            ':user_name' => $user['displayName'] ?? null,
            ':attempts' => $attempts,
            ':started_at' => date('Y-m-d H:i:s'),
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function finishApiLog(PDO $pdo, int $id, string $status, array $stats = [], string $jobKey = self::JOB_TERMINALS): void
    {
        self::initialize($pdo);
        $statement = $pdo->prepare(
            "UPDATE api_logs
             SET status = :status,
                 attempts = :attempts,
                 records_received = :records_received,
                 records_added = :records_added,
                 records_updated = :records_updated,
                 records_skipped = :records_skipped,
                 duration_ms = :duration_ms,
                 error_message = :error_message,
                 completed_at = :completed_at
             WHERE id = :id"
        );
        $statement->execute([
            ':id' => $id,
            ':status' => $status,
            ':attempts' => (int)($stats['attempts'] ?? 1),
            ':records_received' => (int)($stats['recordsReceived'] ?? 0),
            ':records_added' => (int)($stats['recordsAdded'] ?? 0),
            ':records_updated' => (int)($stats['recordsUpdated'] ?? 0),
            ':records_skipped' => (int)($stats['recordsSkipped'] ?? 0),
            ':duration_ms' => (int)($stats['durationMs'] ?? 0),
            ':error_message' => $stats['errorMessage'] ?? null,
            ':completed_at' => date('Y-m-d H:i:s'),
        ]);
        $pdo->prepare(
            "UPDATE api_schedules SET last_run_at = :last_run_at, last_status = :last_status, updated_at = :updated_at WHERE job_key = :job_key"
        )->execute([
            ':job_key' => self::apiJob($jobKey)['key'],
            ':last_run_at' => date('Y-m-d H:i:s'),
            ':last_status' => $status,
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function acquireApiLock(PDO $pdo, string $lockKey, string $lockedBy): bool
    {
        self::initialize($pdo);
        $statement = $pdo->prepare("DELETE FROM api_locks WHERE lock_key = :lock_key AND locked_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        $statement->execute([':lock_key' => $lockKey]);
        $statement = $pdo->prepare("INSERT INTO api_locks (lock_key, locked_at, locked_by) VALUES (:lock_key, :locked_at, :locked_by)");
        try {
            $statement->execute([':lock_key' => $lockKey, ':locked_at' => date('Y-m-d H:i:s'), ':locked_by' => $lockedBy]);
            return true;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return false;
            }
            throw $exception;
        }
    }

    public static function releaseApiLock(PDO $pdo, string $lockKey): void
    {
        self::initialize($pdo);
        $statement = $pdo->prepare("DELETE FROM api_locks WHERE lock_key = :lock_key");
        $statement->execute([':lock_key' => $lockKey]);
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
                    current_stable_version, most_current_version, current_upload_id, previous_upload_id, created_at
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
            'currentUploadId' => isset($row['current_upload_id']) ? (int)$row['current_upload_id'] : null,
            'previousUploadId' => isset($row['previous_upload_id']) ? (int)$row['previous_upload_id'] : null,
            'modified' => date('c', strtotime($row['created_at'])),
        ], $statement->fetchAll());
    }

    public static function latestReport(PDO $pdo): ?array
    {
        self::initialize($pdo);
        $statement = $pdo->query(
            "SELECT report_name, report_date, source_csv, previous_csv, html_path, json_path,
                    pos_terminal_count, out_of_date_store_count, out_of_date_terminal_count,
                    current_stable_version, most_current_version, current_upload_id, previous_upload_id, created_at
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

    public static function dashboardHealth(PDO $pdo): array
    {
        self::initialize($pdo);
        $latestTerminalUpload = self::latestCsvUploads($pdo, 1)[0] ?? null;
        $latestStoreImport = self::latestStoreImport($pdo);
        $schedules = self::listApiSchedules($pdo);
        $jobs = [];
        foreach ($schedules as $schedule) {
            $key = $schedule['jobKey'];
            if (!isset($jobs[$key])) {
                $jobs[$key] = [
                    'jobKey' => $key,
                    'jobName' => $schedule['jobName'],
                    'scheduledTimes' => [],
                    'timezone' => $schedule['timezone'],
                    'lastRunAt' => $schedule['lastRunAt'],
                    'nextRunAt' => $schedule['nextRunAt'],
                    'status' => $schedule['lastStatus'],
                ];
            }
            $jobs[$key]['scheduledTimes'][] = $schedule['displayTime'];
            if (!$jobs[$key]['lastRunAt'] && $schedule['lastRunAt']) {
                $jobs[$key]['lastRunAt'] = $schedule['lastRunAt'];
            }
            if (!$jobs[$key]['nextRunAt'] || strtotime((string)$schedule['nextRunAt']) < strtotime((string)$jobs[$key]['nextRunAt'])) {
                $jobs[$key]['nextRunAt'] = $schedule['nextRunAt'];
            }
        }

        return [
            'latestTerminalUpload' => $latestTerminalUpload,
            'latestStoreImport' => $latestStoreImport,
            'apiJobs' => array_values($jobs),
        ];
    }

    private static function latestStoreImport(PDO $pdo): ?array
    {
        $statement = $pdo->query(
            "SELECT id, original_filename, row_count, uploaded_at
             FROM store_imports
             ORDER BY uploaded_at DESC, id DESC
             LIMIT 1"
        );
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'filename' => $row['original_filename'],
            'rowCount' => (int)$row['row_count'],
            'uploadedAt' => date('c', strtotime($row['uploaded_at'])),
        ];
    }

    public static function latestStoreStatusMap(PDO $pdo): array
    {
        self::initialize($pdo);
        $latest = self::latestStoreImport($pdo);
        if (!$latest) {
            return [];
        }

        $statement = $pdo->prepare(
            "SELECT store_id, status
             FROM store_rows
             WHERE import_id = :import_id
               AND store_id IS NOT NULL
               AND store_id <> ''"
        );
        $statement->execute([':import_id' => $latest['id']]);
        $statusMap = [];
        foreach ($statement->fetchAll() as $row) {
            $storeId = trim((string)$row['store_id']);
            if ($storeId === '') {
                continue;
            }
            $statusMap[$storeId] = trim((string)($row['status'] ?? ''));
        }
        return $statusMap;
    }

    private static function field(array $row, string $name): ?string
    {
        if (!array_key_exists($name, $row)) {
            foreach ($row as $key => $candidate) {
                if (strcasecmp(trim((string)$key), $name) === 0) {
                    $value = trim((string)$candidate);
                    return $value === '' ? null : $value;
                }
            }
        }
        $value = trim((string)($row[$name] ?? ''));
        return $value === '' ? null : $value;
    }

    private static function fieldAny(array $row, array $names): ?string
    {
        foreach ($names as $name) {
            $value = self::field($row, $name);
            if ($value !== null) {
                return $value;
            }
        }
        return null;
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

    private static function seedRolePermissions(PDO $pdo): void
    {
        $statement = $pdo->prepare(
            "INSERT IGNORE INTO role_permissions (role, section_key, can_access, updated_at)
             VALUES (:role, :section_key, :can_access, :updated_at)"
        );
        $now = date('Y-m-d H:i:s');
        foreach (self::defaultRolePermissions() as $role => $sections) {
            foreach ($sections as $section => $canAccess) {
                $statement->execute([
                    ':role' => $role,
                    ':section_key' => $section,
                    ':can_access' => $canAccess ? 1 : 0,
                    ':updated_at' => $now,
                ]);
            }
        }
    }

    private static function seedApiSchedules(PDO $pdo): void
    {
        $statement = $pdo->prepare(
            "INSERT IGNORE INTO api_schedules (job_key, job_name, scheduled_time, timezone, is_active, created_at, updated_at)
             VALUES (:job_key, :job_name, :scheduled_time, 'America/New_York', 1, :created_at, :updated_at)"
        );
        $now = date('Y-m-d H:i:s');
        foreach ([self::JOB_TERMINALS, self::JOB_STORES] as $jobKey) {
            $job = self::apiJob($jobKey);
            foreach (['08:00', '14:00'] as $time) {
                $statement->execute([
                    ':job_key' => $job['key'],
                    ':job_name' => $job['name'],
                    ':scheduled_time' => $time,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
            }
        }
    }

    private static function apiJob(string $jobKey): array
    {
        return match ($jobKey) {
            self::JOB_STORES => [
                'key' => self::JOB_STORES,
                'name' => 'QU EI Store Information CSV',
                'source' => 'QU EI Store Information CSV',
            ],
            default => [
                'key' => self::JOB_TERMINALS,
                'name' => 'QU EI Terminals CSV',
                'source' => 'QU EI Admin Terminals CSV',
            ],
        };
    }

    private static function validateScheduleTime(string $time): string
    {
        $time = trim($time);
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
            throw new RuntimeException('Enter a valid time in HH:MM format.');
        }
        return $time;
    }

    private static function apiScheduleRow(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'jobKey' => $row['job_key'],
            'jobName' => $row['job_name'],
            'scheduledTime' => $row['scheduled_time'],
            'displayTime' => self::displayEasternTime((string)$row['scheduled_time']),
            'timezone' => $row['timezone'],
            'isActive' => (bool)$row['is_active'],
            'lastRunAt' => $row['last_run_at'] ? date('c', strtotime($row['last_run_at'])) : null,
            'nextRunAt' => self::nextRunAt((string)$row['scheduled_time'], (string)$row['timezone']),
            'lastStatus' => $row['last_status'] ?: 'Not Run Yet',
        ];
    }

    private static function apiLogRow(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'jobKey' => $row['job_key'],
            'source' => $row['source'],
            'triggerType' => $row['trigger_type'],
            'initiatedBy' => $row['initiated_by_name'],
            'status' => $row['status'],
            'attempts' => (int)$row['attempts'],
            'recordsReceived' => (int)$row['records_received'],
            'recordsAdded' => (int)$row['records_added'],
            'recordsUpdated' => (int)$row['records_updated'],
            'recordsSkipped' => (int)$row['records_skipped'],
            'durationMs' => (int)$row['duration_ms'],
            'errorMessage' => $row['error_message'],
            'startedAt' => date('c', strtotime($row['started_at'])),
            'completedAt' => $row['completed_at'] ? date('c', strtotime($row['completed_at'])) : null,
        ];
    }

    private static function displayEasternTime(string $time): string
    {
        $date = DateTime::createFromFormat('H:i', $time, new DateTimeZone('America/New_York'));
        return $date ? $date->format('g:i A') . ' ET' : $time;
    }

    private static function nextRunAt(string $time, string $timezone): string
    {
        $zone = new DateTimeZone($timezone ?: 'America/New_York');
        $now = new DateTimeImmutable('now', $zone);
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $next = $now->setTime($hour, $minute);
        if ($next <= $now) {
            $next = $next->modify('+1 day');
        }
        return $next->format(DateTimeInterface::ATOM);
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
