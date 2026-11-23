<?php
/**
 * Automated Verification for Private Seller Access Code Manager
 * Tests Generation, Distribution, Revocation, RBAC, and Registration
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/seed.php';

// Seed fresh data
seed_marketplace_data();

class CodeManagerTestRunner {
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
        echo "CODE MANAGER SUITE: {$this->passed} Passed, {$this->failed} Failed." . PHP_EOL;
        echo "==========================================" . PHP_EOL;
        return $this->failed === 0;
    }
}

$t = new CodeManagerTestRunner();
echo "Starting Seller Access Code Management & RBAC Tests..." . PHP_EOL . PHP_EOL;

// -------------------------------------------------------------
// Test 1: Role-Based User Access Check (Single Admin Rule: marketplace)
// -------------------------------------------------------------
$adminUser = db_fetch_one("SELECT * FROM users WHERE student_id = 'marketplace' OR school_email = 'marketplace@st.ug.edu.gh'");
$kwameUser = db_fetch_one("SELECT * FROM users WHERE school_email = 'kwame.mensah@st.ug.edu.gh'");
$amaUser   = db_fetch_one("SELECT * FROM users WHERE school_email = 'ama.serwaa@st.ug.edu.gh'");
$adminCount = (int)db_fetch_one("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'")['cnt'];

$t->assert("Single admin user role is 'admin'", $adminUser && $adminUser['role'] === 'admin');
$t->assert("Admin student_id is 'marketplace'", $adminUser && $adminUser['student_id'] === 'marketplace');
$t->assert("Admin password verifies with 'marketplace123'", $adminUser && password_verify('marketplace123', $adminUser['password_hash']));
$t->assert("Kwame Mensah is a standard seller (not admin)", $kwameUser && $kwameUser['role'] === 'seller');
$t->assert("Ama Serwaa is a standard seller", $amaUser && $amaUser['role'] === 'seller');
$t->assert("Exactly ONE admin account exists in entire database", $adminCount === 1);

// Test session is_admin() logic
$_SESSION = [];
$t->assert("Guest is not admin", !is_admin());

login_user($amaUser);
$t->assert("Seller Ama Serwaa is not admin", !is_admin());

login_user($kwameUser);
$t->assert("Seller Kwame Mensah is not admin", !is_admin());

login_user($adminUser);
$t->assert("Sole Admin login is_admin() returns true", is_admin());

// -------------------------------------------------------------
// Test 2: Cryptographic Code Generation
// -------------------------------------------------------------
$codeData = create_access_code("Test Student Recipient", 'unused', $adminUser['id']);
$t->assert("Generated code starts with 'CMP-'", str_starts_with($codeData['code'], 'CMP-'));
$t->assert("Generated code matches CMP-XXXX-XXXX-XXXX pattern", (bool)preg_match('/^CMP-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $codeData['code']));

// Check database record
$dbRecord = db_fetch_one("SELECT * FROM seller_access_codes WHERE id = ?", [$codeData['id']]);
$expectedHash = hash_access_code($codeData['code']);
$t->assert("Code hash is stored correctly in DB", $dbRecord['code_hash'] === $expectedHash);
$t->assert("Code prefix is masked", str_contains($dbRecord['code_prefix'], '••••'));
$t->assert("Code status is 'unused'", $dbRecord['status'] === 'unused');

// -------------------------------------------------------------
// Test 3: Batch Code Generation
// -------------------------------------------------------------
$batch = batch_generate_codes(3, "Batch Test Allocation", false, $adminUser['id']);
$t->assert("Batch generated 3 codes", count($batch) === 3);
$allUnique = count(array_unique(array_column($batch, 'code'))) === 3;
$t->assert("All batch generated codes are unique", $allUnique);

// -------------------------------------------------------------
// Test 4: Code Distribution Transition
// -------------------------------------------------------------
$distCode = create_access_code("", 'unused', $adminUser['id']);
$distSuccess = distribute_access_code($distCode['id'], "Kofi Boateng (UG-10998877)", $adminUser['id']);
$t->assert("distribute_access_code returns true", $distSuccess);

$afterDistRecord = db_fetch_one("SELECT * FROM seller_access_codes WHERE id = ?", [$distCode['id']]);
$t->assert("Code status changed to 'distributed'", $afterDistRecord['status'] === 'distributed');
$t->assert("Recipient note recorded", $afterDistRecord['recipient_note'] === "Kofi Boateng (UG-10998877)");
$t->assert("Distributed timestamp recorded", !empty($afterDistRecord['distributed_at']));

// Distributed code is valid for registration
$verifyDist = verify_access_code($distCode['code']);
$t->assert("Distributed code is valid for registration", $verifyDist !== false && $verifyDist['id'] == $distCode['id']);

// -------------------------------------------------------------
// Test 5: Code Revocation
// -------------------------------------------------------------
$revokeTarget = create_access_code("Student Changed Mind", 'unused', $adminUser['id']);
$revokeSuccess = revoke_access_code($revokeTarget['id'], "Suspected compromised code", $adminUser['id']);
$t->assert("revoke_access_code returns true", $revokeSuccess);

$revokedRecord = db_fetch_one("SELECT * FROM seller_access_codes WHERE id = ?", [$revokeTarget['id']]);
$t->assert("Revoked status is 'revoked'", $revokedRecord['status'] === 'revoked');
$t->assert("Revocation reason stored", $revokedRecord['revoked_reason'] === "Suspected compromised code");
$t->assert("Plaintext code purged on revocation", empty($revokedRecord['code_plain']));

// Revoked code rejected by verify_access_code
$verifyRevoked = verify_access_code($revokeTarget['code']);
$t->assert("Revoked code is strictly rejected", $verifyRevoked === false);

// -------------------------------------------------------------
// Test 6: Registration with Unused Code
// -------------------------------------------------------------
$regCode = create_access_code("For John Mensah", 'unused', $adminUser['id']);
$tCodePlain = $regCode['code'];

$studentEmail = 'john.mensah.' . time() . '@st.ug.edu.gh';
$studentId = 'ST-' . random_int(1000000, 9999999);

// Execute atomic redemption simulation
db_begin_transaction();
db_query("INSERT INTO users (full_name, student_id, school_email, phone, password_hash, seller_status, role) VALUES (?, ?, ?, ?, ?, 'active', 'seller')", [
    'John Mensah',
    $studentId,
    $studentEmail,
    '0244112233',
    password_hash('Password123!', PASSWORD_DEFAULT)
]);
$newSellerId = db_last_insert_id();

$stmt = getDB()->prepare("
    UPDATE seller_access_codes 
    SET status = 'redeemed', redeemed_by_user_id = ?, redeemed_at = CURRENT_TIMESTAMP, code_plain = NULL
    WHERE id = ? AND (status = 'unused' OR status = 'distributed')
");
$stmt->execute([$newSellerId, $regCode['id']]);
$updatedRows = $stmt->rowCount();
db_commit();

$t->assert("Atomic redemption updated 1 row", $updatedRows === 1);

// Code is now redeemed
$postRedeemRecord = db_fetch_one("SELECT * FROM seller_access_codes WHERE id = ?", [$regCode['id']]);
$t->assert("Status is now 'redeemed'", $postRedeemRecord['status'] === 'redeemed');
$t->assert("Redeemed by user ID matches", $postRedeemRecord['redeemed_by_user_id'] == $newSellerId);
$t->assert("Plain code purged on redemption", empty($postRedeemRecord['code_plain']));

// Attempting to verify redeemed code again fails
$reVerify = verify_access_code($tCodePlain);
$t->assert("Redeemed code cannot be redeemed again", $reVerify === false);

// -------------------------------------------------------------
// Test 7: Simultaneous Redemption Protection (Race Condition)
// -------------------------------------------------------------
$raceCode = create_access_code("Race Condition Target", 'unused', $adminUser['id']);

// Simulate Thread 1 successful redemption using existing valid user ID
$stmt1 = getDB()->prepare("
    UPDATE seller_access_codes 
    SET status = 'redeemed', redeemed_by_user_id = ?, redeemed_at = CURRENT_TIMESTAMP, code_plain = NULL
    WHERE id = ? AND (status = 'unused' OR status = 'distributed')
");
$stmt1->execute([$adminUser['id'], $raceCode['id']]);
$thread1Success = ($stmt1->rowCount() === 1);

// Simulate Thread 2 simultaneous redemption on the SAME code
$stmt2 = getDB()->prepare("
    UPDATE seller_access_codes 
    SET status = 'redeemed', redeemed_by_user_id = ?, redeemed_at = CURRENT_TIMESTAMP, code_plain = NULL
    WHERE id = ? AND (status = 'unused' OR status = 'distributed')
");
$stmt2->execute([$amaUser['id'], $raceCode['id']]);
$thread2Blocked = ($stmt2->rowCount() === 0);

$t->assert("Thread 1 redeems code successfully", $thread1Success);
$t->assert("Simultaneous Thread 2 is blocked (0 rows affected)", $thread2Blocked);

// -------------------------------------------------------------
// Test 8: Public Zero-Exposure Verification
// -------------------------------------------------------------
$t->assert("codes.txt file does NOT exist on disk", !file_exists(ROOT_PATH . '/codes.txt'));

$regHtml = file_get_contents(ROOT_PATH . '/register.php');
$t->assert("register.php does not link to codes.txt", !str_contains($regHtml, 'codes.txt'));
$t->assert("register.php does not have public auto-fill codes", !str_contains($regHtml, 'availableCodes'));
$t->assert("register.php does not link to setup.php", !str_contains($regHtml, 'setup.php'));

$indexHtml = file_get_contents(ROOT_PATH . '/index.php');
$t->assert("index.php does not link to setup.php", !str_contains($indexHtml, 'setup.php'));

$headerHtml = file_get_contents(ROOT_PATH . '/includes/header.php');
$t->assert("Public header.php does not contain Code Manager link", !str_contains($headerHtml, 'Code Manager'));
$t->assert("Public header.php does not link to admin-codes.php", !str_contains($headerHtml, 'admin-codes.php'));

// Cleanup test session
$_SESSION = [];

// Output results summary
$success = $t->summary();
exit($success ? 0 : 1);
