<?php
// Database seeder

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

function seed_marketplace_data() {
    $db = getDB();
    $messages = [];

    // Seed initial codes if empty
    $codeCount = (int)db_fetch_one("SELECT COUNT(*) as c FROM seller_access_codes")['c'];
    if ($codeCount < 5) {
        $initialCodes = batch_generate_codes(5, 'Initial Campus Allocation Pool');
        $messages[] = "Generated " . count($initialCodes) . " initial access codes.";
    }

    // Seed redeemed code for verification
    $usedCode = 'CMP-USED-CODE-1234';
    $usedHash = hash_access_code($usedCode);
    $existsUsed = db_fetch_one("SELECT id FROM seller_access_codes WHERE code_hash = ?", [$usedHash]);
    if (!$existsUsed) {
        db_query("INSERT INTO seller_access_codes (code_hash, code_plain, code_prefix, status, created_at, redeemed_at) VALUES (?, ?, ?, 'redeemed', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)", [
            $usedHash,
            $usedCode,
            mask_access_code($usedCode)
        ]);
        $messages[] = "Added redeemed test code: {$usedCode}";
    }

    // 3. Seed a Revoked Code for Testing
    $revokedCode = 'CMP-REVOKED-5678';
    $revokedHash = hash_access_code($revokedCode);
    $existsRevoked = db_fetch_one("SELECT id FROM seller_access_codes WHERE code_hash = ?", [$revokedHash]);
    if (!$existsRevoked) {
        db_query("INSERT INTO seller_access_codes (code_hash, code_plain, code_prefix, status, revoked_reason, created_at, revoked_at) VALUES (?, NULL, ?, 'revoked', 'Test Revocation', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)", [
            $revokedHash,
            mask_access_code($revokedCode)
        ]);
        $messages[] = "Added revoked test code: {$revokedCode}";
    }

    // Seed default test seller
    $testSellerEmail = 'kwame.mensah@st.ug.edu.gh';
    $seller = db_fetch_one("SELECT id FROM users WHERE school_email = ?", [$testSellerEmail]);
    
    if (!$seller) {
        $hashedPass = password_hash('Password123!', PASSWORD_DEFAULT);
        db_query("INSERT INTO users (full_name, student_id, school_email, phone, password_hash, seller_status, role, created_at) 
                  VALUES (?, ?, ?, ?, ?, 'active', 'seller', CURRENT_TIMESTAMP)", [
            'Kwame Mensah',
            'UG-10892341',
            $testSellerEmail,
            '0244123456',
            $hashedPass
        ]);
        $sellerId = db_last_insert_id();
        $messages[] = "Created demo seller: Kwame Mensah ({$testSellerEmail} / Password123!)";
    } else {
        $sellerId = $seller['id'];
        db_query("UPDATE users SET role = 'seller' WHERE id = ?", [$sellerId]);
    }

    // Seed administrator account
    $adminUsername = 'marketplace';
    $adminEmail = 'marketplace@st.ug.edu.gh';
    $adminUser = db_fetch_one("SELECT id FROM users WHERE student_id = ? OR school_email = ?", [$adminUsername, $adminEmail]);
    $adminPassHash = password_hash('marketplace123', PASSWORD_DEFAULT);

    if (!$adminUser) {
        db_query("INSERT INTO users (full_name, student_id, school_email, phone, password_hash, seller_status, role, created_at) 
                  VALUES (?, ?, ?, ?, ?, 'active', 'admin', CURRENT_TIMESTAMP)", [
            'Marketplace Administrator',
            $adminUsername,
            $adminEmail,
            '0240449137',
            $adminPassHash
        ]);
        $messages[] = "Created sole team admin account: {$adminUsername} / marketplace123";
    } else {
        db_query("UPDATE users SET full_name = 'Marketplace Administrator', student_id = ?, school_email = ?, phone = '0240449137', password_hash = ?, role = 'admin', seller_status = 'active' WHERE id = ?", [
            $adminUsername,
            $adminEmail,
            $adminPassHash,
            $adminUser['id']
        ]);
        $messages[] = "Updated sole team admin account: {$adminUsername} / marketplace123";
    }

    // Seed secondary test seller
    $secondSellerEmail = 'ama.serwaa@st.ug.edu.gh';
    $seller2 = db_fetch_one("SELECT id FROM users WHERE school_email = ?", [$secondSellerEmail]);
    if (!$seller2) {
        $hashedPass = password_hash('Password123!', PASSWORD_DEFAULT);
        db_query("INSERT INTO users (full_name, student_id, school_email, phone, password_hash, seller_status, role, created_at) 
                  VALUES (?, ?, ?, ?, ?, 'active', 'seller', CURRENT_TIMESTAMP)", [
            'Ama Serwaa',
            'UG-10945821',
            $secondSellerEmail,
            '0501987654',
            $hashedPass
        ]);
        $seller2Id = db_last_insert_id();
        $messages[] = "Created demo seller: Ama Serwaa ({$secondSellerEmail} / Password123!)";
    } else {
        $seller2Id = $seller2['id'];
    }

    return $messages;
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'seed.php') {
    echo "=== Running Student Marketplace Seeder ===" . PHP_EOL;
    $res = seed_marketplace_data();
    foreach ($res as $m) {
        echo " - " . $m . PHP_EOL;
    }
    echo "Seeding completed successfully!" . PHP_EOL;
}

