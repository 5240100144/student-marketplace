<?php
// Authentication & Access Code Management

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// Check if seller is logged in
function is_logged_in() {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['seller_status']) && $_SESSION['seller_status'] === 'active';
}

/**
 * Check if current user is an authorized project team administrator
 */
function is_admin() {
    if (!is_logged_in()) {
        return false;
    }
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}

/**
 * Get currently logged-in user profile
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'           => (int)$_SESSION['user_id'],
        'full_name'    => $_SESSION['full_name'] ?? '',
        'student_id'   => $_SESSION['student_id'] ?? '',
        'school_email' => $_SESSION['school_email'] ?? '',
        'phone'        => $_SESSION['phone'] ?? '',
        'seller_status'=> $_SESSION['seller_status'] ?? 'active',
        'role'         => $_SESSION['role'] ?? 'seller'
    ];
}

/**
 * Enforce that the user must be an authenticated seller
 */
function require_seller() {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in with your seller account to access this page.');
        $currentUrl = $_SERVER['REQUEST_URI'] ?? (BASE_URL . '/dashboard.php');
        redirect(BASE_URL . '/login.php?redirect=' . urlencode($currentUrl));
    }
}

/**
 * Enforce role-based dashboard access: only authorized project team members
 */
function require_admin() {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in with an authorized project team account to access this private dashboard.');
        $currentUrl = $_SERVER['REQUEST_URI'] ?? (BASE_URL . '/admin-codes.php');
        redirect(BASE_URL . '/login.php?redirect=' . urlencode($currentUrl));
    }

    if (!is_admin()) {
        http_response_code(403);
        $pageTitle = "Access Restricted (403 Forbidden)";
        require_once __DIR__ . '/header.php';
        ?>
        <div class="container" style="max-width: 620px; margin: 4.5rem auto; text-align: center;">
            <div style="background: #ffffff; padding: 3rem 2.25rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
                <div style="width: 68px; height: 68px; background: rgba(239, 68, 68, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <h1 style="color: var(--primary); font-size: 1.85rem; font-weight: 850; margin-bottom: 0.65rem; letter-spacing: -0.015em;">
                    Access Restricted (403 Forbidden)
                </h1>
                <p style="color: var(--text-secondary); line-height: 1.6; font-size: 0.95rem; margin-bottom: 2rem;">
                    The <strong>Seller Access Code Management Suite</strong> is private and restricted strictly to authorized CampusMarket project team administrators. Your current account does not have team privileges.
                </p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary">Go to Seller Dashboard</a>
                    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline">Switch Account</a>
                </div>
            </div>
        </div>
        <?php
        require_once __DIR__ . '/footer.php';
        exit;
    }
}

/**
 * Authenticate and log in a seller
 */
function login_user($user) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id']       = (int)$user['id'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['student_id']    = $user['student_id'];
    $_SESSION['school_email']  = $user['school_email'];
    $_SESSION['phone']         = $user['phone'];
    $_SESSION['seller_status'] = $user['seller_status'] ?? 'active';
    $_SESSION['role']          = $user['role'] ?? 'seller';
    $_SESSION['login_time']    = time();
}

/**
 * Log out user safely
 */
function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Compute uniform cryptographic hash for access codes (SHA-256 with project salt)
 */
function hash_access_code($plainCode) {
    $clean = strtoupper(trim((string)$plainCode));
    // Remove dashes or spaces for normalized comparison
    $normalized = str_replace(['-', ' '], '', $clean);
    return hash('sha256', 'CAMPUS_MARKET_SALT_' . $normalized);
}

/**
 * Mask code for safe admin display (e.g. CMP-7K9A-••••-9B8F)
 */
function mask_access_code($plainCode) {
    $clean = strtoupper(trim((string)$plainCode));
    if (strlen($clean) >= 14) {
        return substr($clean, 0, 8) . '-••••-' . substr($clean, -4);
    } elseif (strlen($clean) >= 8) {
        return substr($clean, 0, 4) . '-••••-' . substr($clean, -4);
    }
    return substr($clean, 0, 3) . '••••' . substr($clean, -2);
}

/**
 * Generate cryptographically secure activation code
 * Format: CMP-XXXX-XXXX-XXXX
 */
function generate_access_code() {
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // exclude 0, 1, I, O to prevent confusion
    $code = 'CMP';
    for ($chunk = 0; $chunk < 3; $chunk++) {
        $code .= '-';
        for ($i = 0; $i < 4; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
    }
    return $code;
}

/**
 * Create a new access code in the database
 * Stores hashed code; retains code_plain for initial copy during distribution.
 *
 * @param string $recipientNote
 * @param string $status 'unused' or 'distributed'
 * @param int|null $adminUserId
 * @return array
 */
function create_access_code($recipientNote = '', $status = 'unused', $adminUserId = null) {
    $plain = generate_access_code();
    $hash = hash_access_code($plain);
    $prefix = mask_access_code($plain);
    $status = in_array($status, ['unused', 'distributed']) ? $status : 'unused';
    $distributedAt = ($status === 'distributed') ? date('Y-m-d H:i:s') : null;
    $distributedBy = ($status === 'distributed') ? $adminUserId : null;

    db_query("
        INSERT INTO seller_access_codes (code_hash, code_plain, code_prefix, status, recipient_note, distributed_at, distributed_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ", [
        $hash,
        $plain,
        $prefix,
        $status,
        !empty($recipientNote) ? trim($recipientNote) : null,
        $distributedAt,
        $distributedBy
    ]);

    $id = db_last_insert_id();

    return [
        'id'             => $id,
        'code'           => $plain,
        'hash'           => $hash,
        'prefix'         => $prefix,
        'status'         => $status,
        'recipient_note' => $recipientNote
    ];
}

/**
 * Batch generate codes for project team
 */
function batch_generate_codes($count = 5, $recipientNote = '', $markDistributed = false, $adminUserId = null) {
    $count = max(1, min((int)$count, 50));
    $status = $markDistributed ? 'distributed' : 'unused';
    $created = [];

    for ($i = 0; $i < $count; $i++) {
        try {
            $created[] = create_access_code($recipientNote, $status, $adminUserId);
        } catch (Throwable $e) {
            // Collision retry
            try {
                $created[] = create_access_code($recipientNote, $status, $adminUserId);
            } catch (Throwable $e2) {}
        }
    }

    return $created;
}

/**
 * Mark a code as distributed to a student
 */
function distribute_access_code($codeId, $recipientNote, $adminUserId = null) {
    $stmt = getDB()->prepare("
        UPDATE seller_access_codes
        SET status = 'distributed',
            recipient_note = ?,
            distributed_at = CURRENT_TIMESTAMP,
            distributed_by = ?
        WHERE id = ? AND (status = 'unused' OR status = 'distributed')
    ");
    $stmt->execute([trim($recipientNote), $adminUserId, (int)$codeId]);
    return $stmt->rowCount() > 0;
}

/**
 * Revoke an access code immediately
 */
function revoke_access_code($codeId, $reason = '', $adminUserId = null) {
    $stmt = getDB()->prepare("
        UPDATE seller_access_codes
        SET status = 'revoked',
            revoked_at = CURRENT_TIMESTAMP,
            revoked_by = ?,
            revoked_reason = ?,
            code_plain = NULL
        WHERE id = ? AND (status = 'unused' OR status = 'distributed')
    ");
    $stmt->execute([$adminUserId, trim($reason), (int)$codeId]);
    return $stmt->rowCount() > 0;
}

/**
 * Update recipient note for tracking
 */
function update_code_recipient_note($codeId, $note) {
    db_query("
        UPDATE seller_access_codes
        SET recipient_note = ?
        WHERE id = ?
    ", [trim($note), (int)$codeId]);
    return true;
}

/**
 * Find access code record in database by SHA-256 hash or plaintext fallback
 */
function get_access_code_record($plainCode) {
    $clean = strtoupper(trim((string)$plainCode));
    if (empty($clean)) {
        return false;
    }

    $codeHash = hash_access_code($clean);

    // Primary search: lookup by deterministic SHA-256 hash
    $row = db_fetch_one("
        SELECT * FROM seller_access_codes 
        WHERE code_hash = ? OR UPPER(code_plain) = ?
    ", [$codeHash, $clean]);

    return $row ?: false;
}

/**
 * Verify if access code is valid and eligible for registration
 * Returns row if status is 'unused' or 'distributed'; returns false if invalid, redeemed, or revoked.
 */
function verify_access_code($plainCode) {
    $row = get_access_code_record($plainCode);
    if ($row && ($row['status'] === 'unused' || $row['status'] === 'distributed')) {
        return $row;
    }
    return false;
}

/**
 * Enhanced rate limiting for registration attempts (session and time window)
 */
function check_registration_rate_limit() {
    $key = 'reg_attempts';
    $timeKey = 'reg_time';
    $now = time();

    if (!isset($_SESSION[$timeKey]) || ($now - $_SESSION[$timeKey]) > 300) {
        $_SESSION[$key] = 0;
        $_SESSION[$timeKey] = $now;
    }

    if ($_SESSION[$key] >= 8) {
        return false;
    }

    return true;
}

function increment_registration_attempt() {
    $key = 'reg_attempts';
    $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
}

/**
 * Legacy compatibility alias for seed script
 */
function seed_access_codes($count = 5) {
    $codes = batch_generate_codes($count);
    return array_column($codes, 'code');
}

/**
 * Legacy sync helper - retained safely as no-op if codes.txt is removed
 */
function sync_codes_with_database() {
    return 0;
}
