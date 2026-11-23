<?php
// CampusMarket Seller Registration

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// If already logged in as seller, redirect to dashboard
if (is_logged_in()) {
    redirect(BASE_URL . '/dashboard.php');
}

$errors = [];
$fullName    = trim($_POST['full_name'] ?? '');
$studentId   = trim($_POST['student_id'] ?? '');
$schoolEmail = trim($_POST['school_email'] ?? '');
$phone       = trim($_POST['phone'] ?? '');
$accessCode  = trim($_POST['seller_access_code'] ?? ($_GET['code'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Check rate limiting
    if (!check_registration_rate_limit()) {
        $errors[] = "Too many registration attempts. Please wait 5 minutes before trying again.";
    }

    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($fullName)) {
        $errors[] = "Full name is required.";
    }

    if (empty($studentId)) {
        $errors[] = "Student ID is required.";
    }

    if (empty($schoolEmail) || !filter_var($schoolEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid school email address is required.";
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required for buyer contact.";
    }

    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($accessCode)) {
        $errors[] = "A valid seller activation code is required.";
    }

    if (empty($errors)) {
        increment_registration_attempt();

        // Check if student ID already registered
        $existingStudent = db_fetch_one("SELECT id FROM users WHERE student_id = ?", [$studentId]);
        if ($existingStudent) {
            $errors[] = "An account with this Student ID already exists. Each student can have only one seller account.";
        }

        // Check if school email already registered
        $existingEmail = db_fetch_one("SELECT id FROM users WHERE school_email = ?", [$schoolEmail]);
        if ($existingEmail) {
            $errors[] = "An account with this School Email is already registered.";
        }

        // Verify seller access code
        $codeRecord = get_access_code_record($accessCode);

        if (!$codeRecord) {
            $errors[] = "The activation code entered is invalid. Please check your code and try again.";
        } elseif ($codeRecord['status'] === 'redeemed') {
            $errors[] = "This activation code has already been redeemed and cannot be used again.";
        } elseif ($codeRecord['status'] === 'revoked') {
            $errors[] = "This activation code has been revoked and is no longer valid. Please obtain a valid code from the project team.";
        } elseif ($codeRecord['status'] !== 'unused' && $codeRecord['status'] !== 'distributed') {
            $errors[] = "This activation code is not eligible for seller registration.";
        }

        if (empty($errors) && $codeRecord && ($codeRecord['status'] === 'unused' || $codeRecord['status'] === 'distributed')) {
            try {
                db_begin_transaction();

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // 2. Insert new seller account
                db_query("
                    INSERT INTO users (full_name, student_id, school_email, phone, password_hash, seller_status, role, created_at)
                    VALUES (?, ?, ?, ?, ?, 'active', 'seller', CURRENT_TIMESTAMP)
                ", [
                    $fullName,
                    $studentId,
                    $schoolEmail,
                    $phone,
                    $passwordHash
                ]);
                $newUserId = db_last_insert_id();

                // 3. Mark code as redeemed in the exact same transaction with atomic guard
                $db = getDB();
                $updateStmt = $db->prepare("
                    UPDATE seller_access_codes
                    SET status = 'redeemed',
                        redeemed_by_user_id = ?,
                        redeemed_at = CURRENT_TIMESTAMP,
                        code_plain = NULL
                    WHERE id = ? AND (status = 'unused' OR status = 'distributed')
                ");
                $updateStmt->execute([$newUserId, $codeRecord['id']]);

                if ($updateStmt->rowCount() !== 1) {
                    // Code was redeemed simultaneously by another request!
                    db_rollback();
                    $errors[] = "This activation code was just redeemed by another session. Please check your code or request a new code.";
                } else {
                    db_commit();

                    // Create welcome notification in Notification Center
                    create_notification(
                        $newUserId,
                        "Welcome to CampusMarket!",
                        "Your seller account is verified and ready. You can now post textbooks, electronics, and essentials to campus buyers.",
                        'account',
                        BASE_URL . '/add-product.php'
                    );

                    // Log in seller automatically
                    $newUser = db_fetch_one("SELECT * FROM users WHERE id = ?", [$newUserId]);
                    login_user($newUser);

                    set_flash('success', "Welcome to CampusMarket, {$fullName}! Your seller account is now activated. You can now post your first listing.");
                    redirect(BASE_URL . '/dashboard.php');
                }
            } catch (Throwable $e) {
                db_rollback();
                $errors[] = "Registration failed due to a database error: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Become a Seller - Registration";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="form-card">
        <h1 class="form-title">Become a Campus Seller</h1>
        <p class="form-subtitle">
            Enter your student details and the one-time activation code provided by the project team.
        </p>

        <!-- Access Code Notice -->
        <div style="background: linear-gradient(135deg, rgba(234, 88, 12, 0.08) 0%, rgba(249, 115, 22, 0.12) 100%); border: 1.5px solid var(--coral); border-radius: var(--radius-lg); padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <div style="font-size: 0.95rem; color: var(--purple-dark); font-weight: 700;">
                Kindly reach <a href="tel:0240449137" style="color: var(--coral); text-decoration: underline;">0240449137</a> for a unique code to be a seller
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="https://wa.me/233240449137?text=Hello%20CampusMarket,%20I%20would%20like%20to%20request%20a%20unique%20seller%20access%20code" target="_blank" rel="noopener" class="btn btn-accent btn-sm">
                    WhatsApp 0240449137
                </a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                <div class="alert-content">
                    <ul style="padding-left: 1.25rem; margin: 0;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/register.php" method="POST" novalidate>
            <?= csrf_field() ?>

            <!-- 3.1 Registration Fields -->
            <div class="form-group">
                <label for="full_name" class="form-label">Full Name <span class="required">*</span></label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?= e($fullName) ?>" placeholder="e.g. Kwame Mensah" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="student_id" class="form-label">Student ID Number <span class="required">*</span></label>
                    <input type="text" id="student_id" name="student_id" class="form-control" value="<?= e($studentId) ?>" placeholder="e.g. 10982341" required>
                    <div class="form-help">Enforced unique in database; kept strictly private.</div>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number (WhatsApp) <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($phone) ?>" placeholder="e.g. 0244123456" required>
                    <div class="form-help">Used for buyer WhatsApp and calls.</div>
                </div>
            </div>

            <div class="form-group">
                <label for="school_email" class="form-label">University School Email <span class="required">*</span></label>
                <input type="email" id="school_email" name="school_email" class="form-control" value="<?= e($schoolEmail) ?>" placeholder="e.g. kmensah@st.ug.edu.gh" required>
                <div class="form-help">Must be your unique university email address.</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password" class="form-label">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                </div>
            </div>

            <!-- Activation Code Field (Private & Secure - No Codes Exposed) -->
            <div class="form-group" style="margin-top: 0.5rem; background: var(--bg-muted); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.4rem;">
                    <label for="seller_access_code" class="form-label" style="font-size: 1rem; color: var(--purple-dark); margin: 0;">
                        Seller Activation Code <span class="required">*</span>
                    </label>
                    <span style="font-size: 0.8rem; font-weight: 700; color: var(--coral);">
                        Need a code? Call / WhatsApp 0240449137
                    </span>
                </div>
                <input type="text" id="seller_access_code" name="seller_access_code" class="form-control" value="<?= e($accessCode) ?>" placeholder="e.g. CMP-XXXX-XXXX-XXXX" style="font-family: monospace; font-size: 1.05rem; letter-spacing: 0.05em; text-transform: uppercase;" required autocomplete="off">
                <div class="form-help">Single-use authorization code required. <strong>Kindly reach 0240449137 for a unique code to be a seller.</strong></div>
            </div>

            <button type="submit" class="btn btn-accent btn-lg btn-block" style="margin-top: 1.5rem;">
                Activate Seller Account
            </button>
        </form>

        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); text-align: center; font-size: 0.9rem; color: var(--text-secondary);">
            Already have an active seller account? <a href="<?= BASE_URL ?>/login.php" style="font-weight: 600;">Log in here</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

