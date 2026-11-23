<?php
/**
 * Test Suite: InfinityFree Compatibility, Environment Config & Notification Center
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

class InfinityFreeTestRunner {
    private int $passed = 0;
    private int $failed = 0;

    public function assert(string $title, bool $condition, string $details = '') {
        if ($condition) {
            $this->passed++;
            echo "[PASS] " . $title . PHP_EOL;
        } else {
            $this->failed++;
            echo "[FAIL] " . $title . " - " . $details . PHP_EOL;
        }
    }

    public function summary() {
        echo PHP_EOL . "==========================================" . PHP_EOL;
        echo "INFINITYFREE & NOTIFICATION SUITE: {$this->passed} Passed, {$this->failed} Failed." . PHP_EOL;
        echo "==========================================" . PHP_EOL;
        return $this->failed === 0;
    }
}

$t = new InfinityFreeTestRunner();
echo "Starting InfinityFree & Notification Center Verification Tests..." . PHP_EOL . PHP_EOL;

// -------------------------------------------------------------
// Section 1: Environment & .env Configuration
// -------------------------------------------------------------
$t->assert(".env.example template exists", file_exists(ROOT_PATH . '/.env.example'));
$t->assert(".gitignore exists", file_exists(ROOT_PATH . '/.gitignore'));

$envExampleContent = (string)file_get_contents(ROOT_PATH . '/.env.example');
$t->assert(".env.example defines DB_HOST", str_contains($envExampleContent, 'DB_HOST='));
$t->assert(".env.example defines DB_NAME", str_contains($envExampleContent, 'DB_NAME='));
$t->assert(".env.example defines DB_USER", str_contains($envExampleContent, 'DB_USER='));
$t->assert(".env.example defines DB_PASSWORD", str_contains($envExampleContent, 'DB_PASSWORD='));
$t->assert(".env.example uses placeholders for InfinityFree credentials", str_contains($envExampleContent, 'your_infinityfree_'));

$gitignoreContent = (string)file_get_contents(ROOT_PATH . '/.gitignore');
$t->assert(".gitignore ignores .env", preg_match('/^\.env(\b|\*)/m', $gitignoreContent) === 1);
$t->assert(".gitignore preserves .env.example", str_contains($gitignoreContent, '!.env.example'));

// Verify env() helper function
$t->assert("env() helper function returns DB_HOST or fallback", !empty(env('DB_HOST', '127.0.0.1')));
$t->assert("env() helper function supports fallbacks", env('NON_EXISTENT_KEY_XYZ', 'default_val') === 'default_val');

// -------------------------------------------------------------
// Section 2: Database Connection & Schema
// -------------------------------------------------------------
$db = getDB();
$t->assert("Database connection initialized successfully", $db instanceof PDO);
$t->assert("Active database type defined", defined('ACTIVE_DB_TYPE'));

// Check notifications table existence
$tableCheck = db_fetch_one("SELECT name FROM sqlite_master WHERE type='table' AND name='notifications'");
$t->assert("Notifications table exists in database schema", !empty($tableCheck));

// -------------------------------------------------------------
// Section 3: Notification Center Operations
// -------------------------------------------------------------
// Create a test user for notifications
$testEmail = 'notif.test.' . time() . '@st.ug.edu.gh';
db_query("INSERT INTO users (full_name, student_id, school_email, phone, password_hash, role) VALUES ('Notif Tester', 'NT-" . time() . "', ?, '0240001122', 'hash', 'seller')", [$testEmail]);
$testUser = db_fetch_one("SELECT * FROM users WHERE school_email = ?", [$testEmail]);
$testUserId = (int)$testUser['id'];

// Test 3.1: Create notification
$notifId1 = create_notification($testUserId, "Buyer Inquiry: Test Product", "Buyer Kofi (0551234567) is interested", "inquiry", "/inquiries.php?id=1");
$t->assert("create_notification creates an inquiry notification", $notifId1 > 0);

$notifId2 = create_notification($testUserId, "Listing Marked as Sold", "Your listing was marked sold", "product", "/my-listings.php");
$t->assert("create_notification creates a product status notification", $notifId2 > 0);

$notifId3 = create_notification($testUserId, "Welcome to CampusMarket", "Your seller account is ready", "account", "/add-product.php");
$t->assert("create_notification creates an account notification", $notifId3 > 0);

// Test 3.2: Unread count
$unreadCount = get_unread_notifications_count($testUserId);
$t->assert("get_unread_notifications_count returns 3 unread", $unreadCount === 3);

// Test 3.3: Mark single notification as read
$marked = mark_notification_as_read($notifId1, $testUserId);
$t->assert("mark_notification_as_read returns true", $marked === true);
$t->assert("Unread count decreased to 2", get_unread_notifications_count($testUserId) === 2);

$notif1Record = db_fetch_one("SELECT * FROM notifications WHERE id = ?", [$notifId1]);
$t->assert("Notification is_read flag updated to 1", (int)$notif1Record['is_read'] === 1);
$t->assert("Notification read_at timestamp populated", !empty($notif1Record['read_at']));

// Test 3.4: Fetch notifications with filters
$allUserNotifs = get_user_notifications($testUserId, 10, 0, 'all');
$t->assert("get_user_notifications('all') returns all 3 notifications", count($allUserNotifs) === 3);

$unreadUserNotifs = get_user_notifications($testUserId, 10, 0, 'unread');
$t->assert("get_user_notifications('unread') returns 2 notifications", count($unreadUserNotifs) === 2);

$productNotifs = get_user_notifications($testUserId, 10, 0, 'product');
$t->assert("get_user_notifications('product') returns 1 notification", count($productNotifs) === 1);

// Test 3.5: Mark all as read
$allMarkedCount = mark_all_notifications_as_read($testUserId);
$t->assert("mark_all_notifications_as_read updated remaining 2 notifications", $allMarkedCount === 2);
$t->assert("Unread count is now 0", get_unread_notifications_count($testUserId) === 0);

// Test 3.6: Broadcast notification
$broadcastCount = broadcast_notification("Campus Maintenance Alert", "Platform undergoing scheduled maintenance tonight.", "/browse.php", "all");
$t->assert("broadcast_notification delivered to all registered users", $broadcastCount > 0);

// Verify test user received the broadcast
$testBroadcast = db_fetch_one("SELECT * FROM notifications WHERE user_id = ? AND type = 'broadcast'", [$testUserId]);
$t->assert("Test user received the broadcast notification", !empty($testBroadcast) && $testBroadcast['title'] === 'Campus Maintenance Alert');

// Test 3.7: Admin stats & history
$adminStats = admin_get_notification_stats();
$t->assert("admin_get_notification_stats returns total count", $adminStats['total'] > 0);
$t->assert("admin_get_notification_stats returns broadcast count", $adminStats['broadcasts'] > 0);

$adminHistory = admin_get_notification_history(10);
$t->assert("admin_get_notification_history returns recent delivery log", count($adminHistory) > 0);

// Test 3.8: Delete single notification
$deleted = delete_notification($notifId3, $testUserId);
$t->assert("delete_notification deletes specified notification", $deleted === true);
$checkDeleted = db_fetch_one("SELECT * FROM notifications WHERE id = ?", [$notifId3]);
$t->assert("Deleted notification no longer in database", empty($checkDeleted));

// Test 3.9: Clear read notifications
$cleared = clear_read_notifications($testUserId);
$t->assert("clear_read_notifications removes read notifications", $cleared >= 2);

// -------------------------------------------------------------
// Section 4: InfinityFree Compatibility & Security Rules
// -------------------------------------------------------------
$schemaSql = (string)file_get_contents(ROOT_PATH . '/database/schema.sql');
$t->assert("schema.sql does NOT execute CREATE DATABASE (which fails on InfinityFree)", !str_contains($schemaSql, 'CREATE DATABASE IF NOT EXISTS'));
$t->assert("schema.sql does NOT execute USE database", !str_contains($schemaSql, 'USE `student_marketplace`'));

$rootHtaccess = (string)file_get_contents(ROOT_PATH . '/.htaccess');
$t->assert("Root .htaccess does NOT have hardcoded /marktplace/ RewriteBase", !str_contains($rootHtaccess, 'RewriteBase /marktplace/'));
$t->assert("Root .htaccess does NOT use forbidden Options -ExecCGI", !str_contains($rootHtaccess, 'Options -ExecCGI'));
$t->assert("Root .htaccess denies access to .env and dotfiles", str_contains($rootHtaccess, '^\.'));
$t->assert("Root .htaccess denies access to .sql, .sqlite, and .log files", str_contains($rootHtaccess, '\.(env|sql|sqlite'));
$t->assert("Root .htaccess routes /notifications", str_contains($rootHtaccess, 'notifications.php'));

$uploadsHtaccess = (string)file_get_contents(ROOT_PATH . '/uploads/.htaccess');
$t->assert("uploads/.htaccess does NOT contain forbidden Options -ExecCGI (InfinityFree fix)", !str_contains($uploadsHtaccess, 'Options -ExecCGI'));
$t->assert("uploads/.htaccess prevents PHP execution via FilesMatch", str_contains($uploadsHtaccess, 'FilesMatch'));

$t->assert("database/.htaccess exists to block direct HTTP access", file_exists(ROOT_PATH . '/database/.htaccess'));
$t->assert("config/.htaccess exists to block direct HTTP access", file_exists(ROOT_PATH . '/config/.htaccess'));
$t->assert("tests/.htaccess exists to block direct HTTP access", file_exists(ROOT_PATH . '/tests/.htaccess'));

// Cleanup test user
db_query("DELETE FROM users WHERE id = ?", [$testUserId]);
$t->assert("Test cleanup completed successfully", true);

$success = $t->summary();
exit($success ? 0 : 1);

