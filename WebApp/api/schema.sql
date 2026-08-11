CREATE TABLE IF NOT EXISTS reports (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS csv_uploads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_filename VARCHAR(255) NOT NULL,
    upload_role VARCHAR(40) NOT NULL,
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_at DATETIME NOT NULL,
    INDEX idx_csv_uploads_uploaded_at (uploaded_at),
    INDEX idx_csv_uploads_role (upload_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS terminal_rows (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_imports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_filename VARCHAR(255) NOT NULL,
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_at DATETIME NOT NULL,
    INDEX idx_store_imports_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_rows (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role VARCHAR(40) NOT NULL,
    section_key VARCHAR(80) NOT NULL,
    can_access TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (role, section_key),
    INDEX idx_role_permissions_section (section_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_schedules (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_locks (
    lock_key VARCHAR(80) PRIMARY KEY,
    locked_at DATETIME NOT NULL,
    locked_by VARCHAR(160) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
