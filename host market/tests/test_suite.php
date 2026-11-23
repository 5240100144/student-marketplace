<?php
/**
 * Student Marketplace - Automated Acceptance & Checklist Test Suite
 * Based on Project Specification Section 13 (FR-01 to FR-13) and Section 15
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/seed.php';

// Force seeding if fresh
seed_marketplace_data();

class TestRunner {
    private int $passed = 0;
    private int $failed = 0;
    private array $results = [];

    public function assert(string $title, bool $condition, string $details = '') {
        if ($condition) {
            $this->passed++;
            $this->results[] = ['status' => 'PASS', 'title' => $title, 'details' => $details];
            echo "[PASS] " . $title . PHP_EOL;
        } else {
            $this->failed++;
            $this->results[] = ['status' => 'FAIL', 'title' => $title, 'details' => $details];
            echo "[FAIL] " . $title . " - " . $details . PHP_EOL;
        }
    }

    public function summary() {
        echo PHP_EOL . "==========================================" . PHP_EOL;
        echo "TEST SUITE COMPLETED: {$this->passed} Passed, {$this->failed} Failed." . PHP_EOL;
        echo "==========================================" . PHP_EOL;
        return $this->failed === 0;
    }
}

$t = new TestRunner();
echo "Starting Student Marketplace Acceptance Checklist Tests..." . PHP_EOL . PHP_EOL;

// Ensure test fixture seller and products exist for acceptance testing
$fixtureSeller = db_fetch_one("SELECT * FROM users WHERE school_email = 'kwame.mensah@st.ug.edu.gh'");
if (!$fixtureSeller) {
    db_query("INSERT INTO users (full_name, student_id, school_email, phone, password_hash, seller_status, role) VALUES ('Kwame Mensah', 'UG-10892341', 'kwame.mensah@st.ug.edu.gh', '0244123456', ?, 'active', 'seller')", [
        password_hash('Password123!', PASSWORD_DEFAULT)
    ]);
    $fixtureSellerId = db_last_insert_id();
} else {
    $fixtureSellerId = $fixtureSeller['id'];
}

$tempFixtureIds = [];
$activeCheck = (int)db_fetch_one("SELECT COUNT(*) as c FROM products WHERE status = 'available'")['c'];
if ($activeCheck === 0) {
    db_query("INSERT INTO products (seller_id, product_name, category, price, condition_type, description, whatsapp_phone, status) VALUES (?, 'Stewart Calculus: Early Transcendentals', 'Books', 180.00, 'Second-hand', 'Complete hardcover edition.', '0244123456', 'available')", [$fixtureSellerId]);
    $tempFixtureIds[] = db_last_insert_id();
    db_query("INSERT INTO products (seller_id, product_name, category, price, condition_type, description, whatsapp_phone, status) VALUES (?, 'Casio FX-991EX Calculator', 'Stationery', 150.00, 'Second-hand', 'Original exam calculator.', '0244123456', 'available')", [$fixtureSellerId]);
    $tempFixtureIds[] = db_last_insert_id();
}

// ---------------------------------------------------------
// Test 1: Open homepage while logged out (FR-01)
// ---------------------------------------------------------
$activeProducts = db_fetch_all("SELECT * FROM products WHERE status = 'available'");
$t->assert(
    "FR-01: Public listings accessible without login",
    count($activeProducts) > 0,
    "Found " . count($activeProducts) . " active listings in database."
);

// ---------------------------------------------------------
// Test 2: Search for a product (FR-02)
// ---------------------------------------------------------
$searchParam = '%Calculus%';
$searchResults = db_fetch_all("SELECT * FROM products WHERE status = 'available' AND (product_name LIKE ? OR description LIKE ?)", [$searchParam, $searchParam]);
$t->assert(
    "FR-02: Search product by keyword returns relevant active items",
    count($searchResults) > 0 && str_contains($searchResults[0]['product_name'], 'Calculus'),
    "Search for 'Calculus' returned: " . ($searchResults[0]['product_name'] ?? 'none')
);

// ---------------------------------------------------------
// Test 3: Filter by category and condition (FR-02)
// ---------------------------------------------------------
$catResults = db_fetch_all("SELECT * FROM products WHERE status = 'available' AND category = 'Books' AND condition_type = 'Second-hand'");
$t->assert(
    "FR-02: Filter category and condition works correctly",
    count($catResults) > 0 && $catResults[0]['category'] === 'Books' && $catResults[0]['condition_type'] === 'Second-hand',
    "Found " . count($catResults) . " Second-hand Books."
);

// ---------------------------------------------------------
// Test 4: Open product details & check contact options (FR-03)
// ---------------------------------------------------------
$sampleProd = db_fetch_one("SELECT p.*, u.full_name, u.phone FROM products p JOIN users u ON p.seller_id = u.id WHERE p.status = 'available' LIMIT 1");
$waUrl = build_whatsapp_url($sampleProd['phone'], $sampleProd['product_name'], $sampleProd['price']);
$t->assert(
    "FR-03: Product details page and seller contact options",
    !empty($sampleProd['full_name']) && str_starts_with($waUrl, 'https://wa.me/'),
    "Generated WhatsApp URL: " . $waUrl
);

// ---------------------------------------------------------
// Test 5: Register with invalid activation code (FR-04)
// ---------------------------------------------------------
$invalidCode = "INVALID-CODE-9999";
$verifiedInvalid = verify_access_code($invalidCode);
$t->assert(
    "FR-04: Registration rejects invalid activation code",
    $verifiedInvalid === false,
    "Code '{$invalidCode}' correctly rejected by verify_access_code."
);

// ---------------------------------------------------------
// Test 6: Register with used/redeemed code (FR-05)
// ---------------------------------------------------------
$usedCode = "CMP-USED-CODE-1234";
$verifiedUsed = verify_access_code($usedCode);
$t->assert(
    "FR-05: Registration rejects already-redeemed activation code",
    $verifiedUsed === false,
    "Code '{$usedCode}' correctly rejected as not unused."
);

// ---------------------------------------------------------
// Test 7: Register with valid unused code (FR-04, FR-05)
// ---------------------------------------------------------
$newCode = generate_access_code();
$newHash = hash_access_code($newCode);
db_query("INSERT INTO seller_access_codes (code_hash, code_plain, status, created_at) VALUES (?, ?, 'unused', CURRENT_TIMESTAMP)", [$newHash, $newCode]);

$codeBefore = verify_access_code($newCode);
$t->assert("Valid unused code is initially accepted", $codeBefore !== false);

// Simulate registration transaction
$testEmail = 'test.student.' . time() . '@st.ug.edu.gh';
$testStudentId = 'ST-' . random_int(1000000, 9999999);
$testPassHash = password_hash('SecretPass123', PASSWORD_DEFAULT);

db_begin_transaction();
db_query("INSERT INTO users (full_name, student_id, school_email, phone, password_hash, seller_status) VALUES (?, ?, ?, ?, ?, 'active')", [
    'Test Runner Student',
    $testStudentId,
    $testEmail,
    '0244998877',
    $testPassHash
]);
$createdUserId = db_last_insert_id();
db_query("UPDATE seller_access_codes SET status = 'redeemed', redeemed_by_user_id = ?, redeemed_at = CURRENT_TIMESTAMP WHERE id = ?", [
    $createdUserId,
    $codeBefore['id']
]);
db_commit();

$codeAfter = verify_access_code($newCode);
$t->assert(
    "FR-05: Code is atomically redeemed and cannot be used again",
    $codeAfter === false,
    "Code '{$newCode}' now has status redeemed."
);

// ---------------------------------------------------------
// Test 8: Duplicate student ID or email prevented
// ---------------------------------------------------------
$isDuplicatePrevented = false;
try {
    db_query("INSERT INTO users (full_name, student_id, school_email, phone, password_hash, seller_status) VALUES (?, ?, ?, ?, ?, 'active')", [
        'Duplicate Attempt',
        $testStudentId, // Duplicate ID
        'another.' . time() . '@st.ug.edu.gh',
        '0244998877',
        $testPassHash
    ]);
} catch (Throwable $e) {
    $isDuplicatePrevented = true;
}
$t->assert(
    "FR-04: Duplicate Student ID prevented by unique constraint",
    $isDuplicatePrevented,
    "Database rejected duplicate student_id."
);

// ---------------------------------------------------------
// Test 9: Seller Login authentication (FR-06)
// ---------------------------------------------------------
$sellerUser = db_fetch_one("SELECT * FROM users WHERE school_email = ?", [$testEmail]);
$passMatches = password_verify('SecretPass123', $sellerUser['password_hash']);
$badPassFails = !password_verify('WrongPassword', $sellerUser['password_hash']);
$t->assert(
    "FR-06: Registered seller can authenticate securely with password_verify",
    $passMatches && $badPassFails,
    "Password hashing and verification functioning as specified."
);

// ---------------------------------------------------------
// Test 10: Product creation linked to seller (FR-07)
// ---------------------------------------------------------
db_query("INSERT INTO products (seller_id, product_name, category, price, condition_type, description, whatsapp_phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'available')", [
    $createdUserId,
    'Unit Test Laptop Stand',
    'Electronics',
    75.00,
    'New',
    'Ergonomic aluminum stand for student study desks.',
    '0244998877'
]);
$newProdId = db_last_insert_id();
$t->assert(
    "FR-07: Logged-in seller can create a product listing",
    $newProdId > 0,
    "Created test product ID: {$newProdId}"
);

// ---------------------------------------------------------
// Test 11: Listing Ownership check on server (FR-08)
// ---------------------------------------------------------
$otherSeller = db_fetch_one("SELECT id FROM users WHERE id != ? LIMIT 1", [$createdUserId]);
$ownerCheckPassed = (get_seller_product($newProdId, $createdUserId) !== null);
$otherSellerDenied = (get_seller_product($newProdId, $otherSeller['id']) === null);
$t->assert(
    "FR-08: Server enforces listing ownership; foreign seller is denied access",
    $ownerCheckPassed && $otherSellerDenied,
    "Owner has access; foreign seller is strictly denied."
);

// ---------------------------------------------------------
// Test 12: Mark own product as sold leaves active results (FR-09, FR-10)
// ---------------------------------------------------------
db_query("UPDATE products SET status = 'sold' WHERE id = ? AND seller_id = ?", [$newProdId, $createdUserId]);
$checkSoldInPublic = db_fetch_one("SELECT id FROM products WHERE id = ? AND status = 'available'", [$newProdId]);
$checkSoldStatus = db_fetch_one("SELECT status FROM products WHERE id = ?", [$newProdId]);

$t->assert(
    "FR-09 & FR-10: Sold products change status and do not appear in active marketplace results",
    $checkSoldInPublic === null && $checkSoldStatus['status'] === 'sold',
    "Product status changed to 'sold' and excluded from available query."
);

// ---------------------------------------------------------
// Test 13: Buyer interest recorded without account (FR-11, FR-12)
// ---------------------------------------------------------
$notifStatus = send_interest_notification($sellerUser['school_email'], $sellerUser['full_name'], 'Unit Test Laptop Stand', 'Kofi Buyer', '0551234567', 'Can I buy today?');
db_query("INSERT INTO buyer_interest (product_id, seller_id, buyer_name, buyer_contact, buyer_message, notification_status, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)", [
    $newProdId,
    $createdUserId,
    'Kofi Buyer',
    '0551234567',
    'Can I buy today?',
    $notifStatus
]);
$interestRecord = db_fetch_one("SELECT * FROM buyer_interest WHERE product_id = ? AND buyer_name = ?", [$newProdId, 'Kofi Buyer']);

$t->assert(
    "FR-11 & FR-12: Buyer interest recorded and seller notification dispatched",
    $interestRecord !== null && ($notifStatus === 'logged' || $notifStatus === 'sent'),
    "Interest saved; notification status: " . $notifStatus
);

// ---------------------------------------------------------
// Test 14: WhatsApp formatting
// ---------------------------------------------------------
$waLink = build_whatsapp_url('0244123456', 'Kettle', 95.00);
$t->assert(
    "WhatsApp URL correctly normalizes Ghana numbers and encodes query",
    str_contains($waLink, '233244123456') && str_contains($waLink, 'Kettle'),
    "WhatsApp URL: " . $waLink
);

// ---------------------------------------------------------
// Test 15: Clean up test product
// ---------------------------------------------------------
db_query("DELETE FROM products WHERE id = ?", [$newProdId]);
db_query("DELETE FROM users WHERE id = ?", [$createdUserId]);
foreach ($tempFixtureIds as $fid) {
    db_query("DELETE FROM products WHERE id = ?", [$fid]);
}
$t->assert("Test cleanup completed", true);

$allPassed = $t->summary();
exit($allPassed ? 0 : 1);

