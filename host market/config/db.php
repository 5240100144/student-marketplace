<?php
// Database Connection & Query Utilities

require_once __DIR__ . '/config.php';

if (!defined('DB_HOST')) define('DB_HOST', env('DB_HOST', '127.0.0.1'));
if (!defined('DB_PORT')) define('DB_PORT', env('DB_PORT', '3306'));
if (!defined('DB_NAME')) define('DB_NAME', env('DB_NAME', 'student_marketplace'));
if (!defined('DB_USER')) define('DB_USER', env('DB_USER', 'root'));

$rawPass = env('DB_PASSWORD', env('DB_PASS', ''));
if (!defined('DB_PASS')) define('DB_PASS', $rawPass);
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', $rawPass);

// SQLite fallback path
if (!defined('SQLITE_FILE')) define('SQLITE_FILE', ROOT_PATH . '/database/marketplace.sqlite');

function getDB($forceFresh = false) {
    static $pdo = null;

    if ($pdo !== null && !$forceFresh) {
        return $pdo;
    }

    $errors = [];

    // Check if placeholder values are configured in .env
    $isPlaceholder = empty(DB_HOST)
        || str_contains(DB_HOST, 'your_')
        || str_contains(DB_HOST, 'placeholder')
        || str_contains(DB_NAME, 'your_')
        || str_contains(DB_USER, 'your_');

    // Attempt MySQL connection if not placeholder
    if (!$isPlaceholder) {
        try {
            $dsnWithDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 4,
            ];

            try {
                // Connect directly to the user's database (Required for InfinityFree and shared hosting)
                $pdo = new PDO($dsnWithDb, DB_USER, DB_PASSWORD, $options);
            } catch (PDOException $e) {
                // If database does not exist on local root development, attempt auto-create
                $isLocalRoot = (DB_HOST === '127.0.0.1' || DB_HOST === 'localhost') && DB_USER === 'root';
                if ($isLocalRoot && ($e->getCode() == 1049 || str_contains($e->getMessage(), 'Unknown database'))) {
                    $dsnWithoutDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
                    $rootPdo = new PDO($dsnWithoutDb, DB_USER, DB_PASSWORD, $options);
                    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    unset($rootPdo);
                    $pdo = new PDO($dsnWithDb, DB_USER, DB_PASSWORD, $options);
                } else {
                    throw $e;
                }
            }

            if (!defined('ACTIVE_DB_TYPE')) {
                define('ACTIVE_DB_TYPE', 'mysql');
            }

            // Check if tables are installed
            $check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
            if (!$check) {
                $schemaFile = ROOT_PATH . '/database/schema.sql';
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $pdo->exec($sql);
                }
            }

            // Auto-seed codes if table is empty
            $codeCheck = $pdo->query("SELECT COUNT(*) as cnt FROM seller_access_codes")->fetch();
            if ($codeCheck && (int)$codeCheck['cnt'] === 0) {
                require_once ROOT_PATH . '/database/seed.php';
                seed_marketplace_data();
            }

            ensure_schema_updates($pdo, 'mysql');

            return $pdo;
        } catch (Throwable $e) {
            $errors[] = "MySQL: " . $e->getMessage();
        }
    } else {
        $errors[] = "MySQL: Placeholder credentials in .env (" . DB_HOST . ")";
    }

    // Fallback to SQLite (Portable mode for CLI sandbox / offline environments)
    try {
        $dsnSqlite = "sqlite:" . SQLITE_FILE;
        $pdo = new PDO($dsnSqlite, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");

        if (!defined('ACTIVE_DB_TYPE')) {
            define('ACTIVE_DB_TYPE', 'sqlite');
        }

        // Check if tables exist
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
        if (!$check) {
            $schemaFile = ROOT_PATH . '/database/schema_sqlite.sql';
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                $pdo->exec($sql);
            }
        }

        // Auto-seed codes if table is empty
        $codeCheck = $pdo->query("SELECT COUNT(*) as cnt FROM seller_access_codes")->fetch();
        if ($codeCheck && (int)$codeCheck['cnt'] === 0) {
            require_once ROOT_PATH . '/database/seed.php';
            seed_marketplace_data();
        }

        ensure_schema_updates($pdo, 'sqlite');

        return $pdo;
    } catch (Throwable $e) {
        $errors[] = "SQLite: " . $e->getMessage();
        die("Critical Database Connection Failure: " . implode(" | ", $errors));
    }
}

// Ensure database tables and columns exist
function ensure_schema_updates($pdo, $driver) {
    static $migrated = false;
    if ($migrated) return;
    $migrated = true;

    try {
        if ($driver === 'sqlite') {
            // Check users table columns
            $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
            $names = array_column($cols, 'name');
            if (!in_array('role', $names)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'seller'");
            }

            // Check seller_access_codes columns
            $codeCols = $pdo->query("PRAGMA table_info(seller_access_codes)")->fetchAll(PDO::FETCH_ASSOC);
            $codeNames = array_column($codeCols, 'name');
            if (!in_array('code_prefix', $codeNames)) {
                $pdo->exec("ALTER TABLE seller_access_codes ADD COLUMN code_prefix TEXT NULL");
            }
            if (!in_array('recipient_note', $codeNames)) {
                $pdo->exec("ALTER TABLE seller_access_codes ADD COLUMN recipient_note TEXT NULL");
            }
            if (!in_array('distributed_at', $codeNames)) {
                $pdo->exec("ALTER TABLE seller_access_codes ADD COLUMN distributed_at DATETIME NULL");
            }
            if (!in_array('distributed_by', $codeNames)) {
                $pdo->exec("ALTER TABLE seller_access_codes ADD COLUMN distributed_by INTEGER NULL");
            }
            if (!in_array('revoked_at', $codeNames)) {
                $pdo->exec("ALTER TABLE seller_access_codes ADD COLUMN revoked_at DATETIME NULL");
            }
            if (!in_array('revoked_by', $codeNames)) {
                $pdo->exec("ALTER TABLE seller_access_codes ADD COLUMN revoked_by INTEGER NULL");
            }
            if (!in_array('revoked_reason', $codeNames)) {
                $pdo->exec("ALTER TABLE seller_access_codes ADD COLUMN revoked_reason TEXT NULL");
            }

            // Ensure notifications table exists
            $checkNotif = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notifications'")->fetch();
            if (!$checkNotif) {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS notifications (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NULL,
                        type TEXT NOT NULL DEFAULT 'system',
                        title TEXT NOT NULL,
                        message TEXT NOT NULL,
                        link TEXT NULL,
                        is_read INTEGER NOT NULL DEFAULT 0,
                        read_at DATETIME NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
                    );
                    CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications (user_id);
                    CREATE INDEX IF NOT EXISTS idx_notif_read ON notifications (is_read);
                    CREATE INDEX IF NOT EXISTS idx_notif_type ON notifications (type);
                    CREATE INDEX IF NOT EXISTS idx_notif_created ON notifications (created_at);
                ");
            }
        } else {
            // MySQL
            $checkRole = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch();
            if (!$checkRole) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `role` VARCHAR(20) NOT NULL DEFAULT 'seller'");
            }

            $codeCols = $pdo->query("SHOW COLUMNS FROM `seller_access_codes`")->fetchAll(PDO::FETCH_ASSOC);
            $codeNames = array_column($codeCols, 'Field');
            if (!in_array('code_prefix', $codeNames)) {
                $pdo->exec("ALTER TABLE `seller_access_codes` ADD COLUMN `code_prefix` VARCHAR(50) NULL");
            }
            if (!in_array('recipient_note', $codeNames)) {
                $pdo->exec("ALTER TABLE `seller_access_codes` ADD COLUMN `recipient_note` TEXT NULL");
            }
            if (!in_array('distributed_at', $codeNames)) {
                $pdo->exec("ALTER TABLE `seller_access_codes` ADD COLUMN `distributed_at` DATETIME NULL");
            }
            if (!in_array('distributed_by', $codeNames)) {
                $pdo->exec("ALTER TABLE `seller_access_codes` ADD COLUMN `distributed_by` INT NULL");
            }
            if (!in_array('revoked_at', $codeNames)) {
                $pdo->exec("ALTER TABLE `seller_access_codes` ADD COLUMN `revoked_at` DATETIME NULL");
            }
            if (!in_array('revoked_by', $codeNames)) {
                $pdo->exec("ALTER TABLE `seller_access_codes` ADD COLUMN `revoked_by` INT NULL");
            }
            if (!in_array('revoked_reason', $codeNames)) {
                $pdo->exec("ALTER TABLE `seller_access_codes` ADD COLUMN `revoked_reason` TEXT NULL");
            }

            // Ensure notifications table exists in MySQL
            $checkNotif = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetch();
            if (!$checkNotif) {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `notifications` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT NULL,
                        `type` VARCHAR(50) NOT NULL DEFAULT 'system',
                        `title` VARCHAR(255) NOT NULL,
                        `message` TEXT NOT NULL,
                        `link` VARCHAR(255) NULL,
                        `is_read` TINYINT(1) NOT NULL DEFAULT 0,
                        `read_at` DATETIME NULL,
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX `idx_notif_user` (`user_id`),
                        INDEX `idx_notif_read` (`is_read`),
                        INDEX `idx_notif_type` (`type`),
                        INDEX `idx_notif_created` (`created_at`),
                        CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }
        }

        // Enforce strictly ONE administrator in the system: username 'marketplace'
        $adminPass = password_hash('marketplace123', PASSWORD_DEFAULT);
        
        // Remove legacy admin accounts and ensure no other user has role 'admin'
        $pdo->exec("DELETE FROM users WHERE school_email = 'admin@st.ug.edu.gh' OR student_id = 'ADMIN-001'");
        $pdo->exec("UPDATE users SET role = 'seller' WHERE student_id != 'marketplace' AND school_email != 'marketplace@st.ug.edu.gh'");

        // Ensure the marketplace admin account exists and has role 'admin'
        $adminCheck = $pdo->query("SELECT id FROM users WHERE student_id = 'marketplace' OR school_email = 'marketplace@st.ug.edu.gh'")->fetch();
        if (!$adminCheck) {
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, student_id, school_email, phone, password_hash, seller_status, role, created_at)
                VALUES ('Marketplace Administrator', 'marketplace', 'marketplace@st.ug.edu.gh', '0240449137', ?, 'active', 'admin', CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$adminPass]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = 'Marketplace Administrator',
                    student_id = 'marketplace',
                    school_email = 'marketplace@st.ug.edu.gh',
                    phone = '0240449137',
                    password_hash = ?,
                    seller_status = 'active',
                    role = 'admin'
                WHERE id = ?
            ");
            $stmt->execute([$adminPass, $adminCheck['id']]);
        }

        // Populate code_prefix for any existing codes missing it
        if ($driver === 'sqlite') {
            $pdo->exec("
                UPDATE seller_access_codes 
                SET code_prefix = SUBSTR(code_plain, 1, 8) || '-••••-' || SUBSTR(code_plain, -4)
                WHERE code_prefix IS NULL AND code_plain IS NOT NULL AND LENGTH(code_plain) >= 12
            ");
        } else {
            $pdo->exec("
                UPDATE seller_access_codes 
                SET code_prefix = CONCAT(SUBSTRING(code_plain, 1, 8), '-••••-', SUBSTRING(code_plain, -4))
                WHERE code_prefix IS NULL AND code_plain IS NOT NULL AND LENGTH(code_plain) >= 12
            ");
        }

        // Ensure upload directories and existing product images are world-readable on Linux shared hosting
        $uploadProductDir = ROOT_PATH . '/uploads/products';
        if (is_dir($uploadProductDir)) {
            @chmod($uploadProductDir, 0755);
            $existingImages = @glob($uploadProductDir . '/*');
            if ($existingImages) {
                foreach ($existingImages as $imgFile) {
                    if (is_file($imgFile) && !is_readable($imgFile)) {
                        @chmod($imgFile, 0644);
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Schema update notice: ' . $e->getMessage());
    }
}

/**
 * Execute a query with prepared statements
 */
function db_query($sql, $params = []) {
    $pdo = getDB();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch single row
 */
function db_fetch_one($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetch() ?: null;
}

/**
 * Fetch all rows
 */
function db_fetch_all($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll() ?: [];
}

/**
 * Last insert ID
 */
function db_last_insert_id() {
    return getDB()->lastInsertId();
}

/**
 * Begin Transaction
 */
function db_begin_transaction() {
    return getDB()->beginTransaction();
}

/**
 * Commit Transaction
 */
function db_commit() {
    return getDB()->commit();
}

/**
 * Rollback Transaction
 */
function db_rollback() {
    if (getDB()->inTransaction()) {
        return getDB()->rollBack();
    }
    return false;
}
