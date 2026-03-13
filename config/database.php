<?php
// config/database.php

$dbPath = __DIR__ . '/../database/ba.sqlite';

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON");

    // Initialize Database Tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        full_name TEXT,
        email TEXT,
        email_verified_at DATETIME,
        oauth_provider TEXT,
        oauth_sub TEXT,
        role TEXT DEFAULT 'user',
        is_approved INTEGER DEFAULT 1,
        approved_at DATETIME,
        approved_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        name TEXT NOT NULL,
        sponsor TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS surveys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        department_id INTEGER,
        section_id INTEGER,
        section_name TEXT,
        field_key TEXT,
        field_label TEXT,
        field_value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    )");

    // Table for custom modules per department
    $pdo->exec("CREATE TABLE IF NOT EXISTS department_modules (
        user_id INTEGER,
        department_id INTEGER,
        module_name TEXT,
        PRIMARY KEY (department_id, module_name),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    )");

    // Table for KPIs per department
    $pdo->exec("CREATE TABLE IF NOT EXISTS department_kpis (
        user_id INTEGER,
        department_id INTEGER,
        kpi_name TEXT,
        PRIMARY KEY (department_id, kpi_name),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    )");

    // Table for Processes (Process Mapping)
    $pdo->exec("CREATE TABLE IF NOT EXISTS department_processes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        department_id INTEGER,
        name TEXT,
        description TEXT,
        type TEXT, -- AS-IS or TO-BE
        steps TEXT, -- JSON or comma-separated
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    )");

    // Table for Entities (Data Architecture)
    $pdo->exec("CREATE TABLE IF NOT EXISTS department_entities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        department_id INTEGER,
        name TEXT,
        attributes TEXT,
        relationships TEXT,
        entity_type TEXT DEFAULT 'master',
        description TEXT,
        data_source TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS entity_attributes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        department_id INTEGER,
        entity_id INTEGER,
        name TEXT NOT NULL,
        data_type TEXT DEFAULT 'string',
        is_primary_key INTEGER DEFAULT 0,
        is_foreign_key INTEGER DEFAULT 0,
        is_nullable INTEGER DEFAULT 1,
        reference_entity TEXT,
        description TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
        FOREIGN KEY (entity_id) REFERENCES department_entities(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS entity_relationships (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        department_id INTEGER,
        entity_from_id INTEGER,
        entity_to_id INTEGER,
        relationship_type TEXT DEFAULT 'one-to-many',
        foreign_key TEXT,
        description TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
        FOREIGN KEY (entity_from_id) REFERENCES department_entities(id) ON DELETE CASCADE,
        FOREIGN KEY (entity_to_id) REFERENCES department_entities(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS entity_versions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        department_id INTEGER,
        entity_id INTEGER,
        action_type TEXT,
        snapshot TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
        FOREIGN KEY (entity_id) REFERENCES department_entities(id) ON DELETE CASCADE
    )");

    // Table for Integrations
    $pdo->exec("CREATE TABLE IF NOT EXISTS department_integrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        department_id INTEGER,
        system_name TEXT,
        interface_type TEXT,
        data_flow TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    )");

    // Table for Backlog
    $pdo->exec("CREATE TABLE IF NOT EXISTS department_backlog (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        department_id INTEGER,
        requirement TEXT,
        priority TEXT,
        status TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    )");

    // Table for General Settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        user_id INTEGER,
        key_name TEXT,
        value TEXT,
        PRIMARY KEY (user_id, key_name)
    )");

    // AI Run telemetry
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_runs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        endpoint TEXT,
        model TEXT,
        request_chars INTEGER DEFAULT 0,
        response_chars INTEGER DEFAULT 0,
        latency_ms INTEGER DEFAULT 0,
        status TEXT,
        error_message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // AI jobs queue/history
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_jobs (
        id TEXT PRIMARY KEY,
        user_id INTEGER,
        department_id INTEGER,
        scope TEXT, -- field | section | full | report
        status TEXT, -- pending | completed | failed
        field_key TEXT,
        section_id TEXT,
        source_text TEXT,
        source_payload TEXT,
        normalized_payload TEXT,
        error_message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    )");

    // Versioned report storage
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_versions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        department_id INTEGER,
        report_type TEXT, -- ai_full | manual | summary
        title TEXT,
        content_html TEXT,
        content_text TEXT,
        source_payload TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    )");

    // Section image/file attachments
    $pdo->exec("CREATE TABLE IF NOT EXISTS section_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        department_id INTEGER NOT NULL,
        section_id TEXT NOT NULL,
        storage_disk TEXT NOT NULL DEFAULT 'local',
        storage_path TEXT NOT NULL,
        public_url TEXT,
        original_name TEXT,
        mime_type TEXT,
        file_size INTEGER DEFAULT 0,
        width INTEGER,
        height INTEGER,
        checksum_sha256 TEXT,
        av_scanned INTEGER DEFAULT 0,
        av_status TEXT DEFAULT 'unknown',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS email_verification_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        email TEXT NOT NULL,
        token_hash TEXT NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        email TEXT NOT NULL,
        token_hash TEXT NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Expand surveys schema for raw/normalized split
    $tableInfoStmt = $pdo->query("PRAGMA table_info(surveys)");
    $tableColumns = array_map(
        static fn($row) => $row['name'] ?? '',
        $tableInfoStmt ? $tableInfoStmt->fetchAll() : []
    );

    $ensureColumn = static function (PDO $pdoRef, array $existingColumns, string $tableName, string $columnName, string $columnDef): array {
        if (!in_array($columnName, $existingColumns, true)) {
            $pdoRef->exec("ALTER TABLE {$tableName} ADD COLUMN {$columnName} {$columnDef}");
            $existingColumns[] = $columnName;
        }
        return $existingColumns;
    };

    // Ensure user_id exists in all tables if they were already created
    $tablesToUpdate = [
        'departments', 'surveys', 'department_modules', 'department_kpis', 
        'department_processes', 'department_entities', 'department_integrations', 
        'department_backlog', 'settings', 'ai_runs', 'ai_jobs', 'report_versions', 'section_files'
    ];
    
    foreach ($tablesToUpdate as $table) {
        $info = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
        $cols = array_map(fn($r) => $r['name'], $info);
        if (!in_array('user_id', $cols)) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN user_id INTEGER");
        }
    }

    // Ensure AI runs has action_name and provider
    $aiRunInfo = $pdo->query("PRAGMA table_info(ai_runs)")->fetchAll();
    $aiRunCols = array_map(fn($r) => $r['name'], $aiRunInfo);
    if (!in_array('action_name', $aiRunCols)) {
        $pdo->exec("ALTER TABLE ai_runs ADD COLUMN action_name TEXT");
    }
    if (!in_array('provider', $aiRunCols)) {
        $pdo->exec("ALTER TABLE ai_runs ADD COLUMN provider TEXT");
    }

    // Ensure approval columns exist in users table
    $userInfo = $pdo->query("PRAGMA table_info(users)")->fetchAll();
    $userCols = array_map(fn($r) => $r['name'], $userInfo);
    if (!in_array('is_approved', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_approved INTEGER DEFAULT 1");
    }
    if (!in_array('approved_at', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN approved_at DATETIME");
    }
    if (!in_array('approved_by', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN approved_by INTEGER");
    }
    if (!in_array('email', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT");
    }
    if (!in_array('oauth_provider', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN oauth_provider TEXT");
    }
    if (!in_array('oauth_sub', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN oauth_sub TEXT");
    }
    if (!in_array('email_verified_at', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_verified_at DATETIME");
    }
    $pdo->exec("UPDATE users SET is_approved = 1 WHERE is_approved IS NULL");

    // Ensure latest columns in department_entities for old DBs
    $entityInfo = $pdo->query("PRAGMA table_info(department_entities)")->fetchAll();
    $entityCols = array_map(fn($r) => $r['name'], $entityInfo);
    if (!in_array('entity_type', $entityCols, true)) {
        $pdo->exec("ALTER TABLE department_entities ADD COLUMN entity_type TEXT DEFAULT 'master'");
    }
    if (!in_array('description', $entityCols, true)) {
        $pdo->exec("ALTER TABLE department_entities ADD COLUMN description TEXT");
    }
    if (!in_array('data_source', $entityCols, true)) {
        $pdo->exec("ALTER TABLE department_entities ADD COLUMN data_source TEXT");
    }

    $tableColumns = $ensureColumn($pdo, $tableColumns, 'surveys', 'raw_value', 'TEXT');
    $tableColumns = $ensureColumn($pdo, $tableColumns, 'surveys', 'normalized_value', 'TEXT');
    $tableColumns = $ensureColumn($pdo, $tableColumns, 'surveys', 'normalization_state', "TEXT DEFAULT 'none'");
    $tableColumns = $ensureColumn($pdo, $tableColumns, 'surveys', 'normalized_at', 'DATETIME');

    // Useful indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_surveys_user ON surveys(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_surveys_department ON surveys(department_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_surveys_section_key ON surveys(section_id, field_key)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ai_jobs_department ON ai_jobs(department_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_report_versions_department ON report_versions(department_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_oauth ON users(oauth_provider, oauth_sub)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_verification_user ON email_verification_tokens(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_reset_user ON password_reset_tokens(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entity_attr_entity ON entity_attributes(entity_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entity_rel_dept ON entity_relationships(department_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_section_files_dept_section ON section_files(department_id, section_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_section_files_user ON section_files(user_id)");

    // Initial default settings
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'groq_model', 'llama-3.3-70b-versatile')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'groq_endpoint', 'https://api.groq.com/openai/v1/chat/completions')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'groq_api_key', '')");

    // App/AI defaults
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'app_env', 'local')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'ai_timeout_sec', '90')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'ai_ssl_verify', '0')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'ai_ssl_verify_host', '0')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'ai_report_model', 'llama-3.3-70b-versatile')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'storage_driver', 'local')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'storage_local_root', 'storage/uploads')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'upload_max_mb', '5')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'upload_max_width', '1920')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'upload_max_height', '1920')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'upload_jpeg_quality', '82')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'upload_require_av', '0')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'smtp_host', '')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'smtp_port', '587')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'smtp_username', '')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'smtp_password', '')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'smtp_encryption', 'tls')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'smtp_from_email', '')");
    $pdo->exec("INSERT OR IGNORE INTO settings (user_id, key_name, value) VALUES (0, 'smtp_from_name', 'BA Toolkit')");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
