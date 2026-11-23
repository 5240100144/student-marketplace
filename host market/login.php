<?php
// CampusMarket Seller Login

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (is_logged_in()) {
    redirect(BASE_URL . '/dashboard.php');
}

$error = '';
$loginIdentifier = trim($_POST['identifier'] ?? '');
$redirectTarget = trim($_GET['redirect'] ?? ($_POST['redirect'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $password = $_POST['password'] ?? '';

    if (empty($loginIdentifier) || empty($password)) {
        $error = "Please enter your school email (or Student ID) and password.";
    } else {
        // Query user by email or student ID
        $user = db_fetch_one("
            SELECT * FROM users 
            WHERE school_email = ? OR student_id = ?
        ", [$loginIdentifier, $loginIdentifier]);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['seller_status'] !== 'active') {
                $error = "Your seller account is currently suspended. Please contact campus administration.";
            } else {
                login_user($user);
                set_flash('success', "Welcome back, {$user['full_name']}!");

                if (!empty($redirectTarget) && str_starts_with($redirectTarget, BASE_URL)) {
                    redirect($redirectTarget);
                } elseif (is_admin()) {
                    redirect(BASE_URL . '/admin-codes.php');
                } else {
                    redirect(BASE_URL . '/dashboard.php');
                }
            }
        } else {
            $error = "Invalid credentials. Please verify your login details.";
        }
    }
}

$pageTitle = "Seller Login";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="form-card" style="max-width: 480px;">
        <h1 class="form-title">Seller Login</h1>
        <p class="form-subtitle">Access your personal dashboard to manage your campus advertisements.</p>

        <!-- Access Code Notice for New Sellers -->
        <div style="background: linear-gradient(135deg, rgba(234, 88, 12, 0.08) 0%, rgba(249, 115, 22, 0.12) 100%); border: 1.5px solid var(--coral); border-radius: var(--radius-lg); padding: 0.9rem 1.15rem; margin-bottom: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--purple-dark);">
            Want to start selling? <strong>Become a Seller</strong>.<br>
            Kindly reach <strong><a href="tel:0240449137" style="color: var(--coral); text-decoration: underline;">0240449137</a></strong> for a unique code to be a seller.
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                <div class="alert-content"><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/login.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="<?= e($redirectTarget) ?>">

            <div class="form-group">
                <label for="identifier" class="form-label">Username, School Email or Student ID</label>
                <input type="text" id="identifier" name="identifier" class="form-control" value="<?= e($loginIdentifier) ?>" placeholder="e.g. marketplace or your student email" required autofocus>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Your password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-top: 1.5rem;">
                Log In to Dashboard
            </button>
        </form>

        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); text-align: center; font-size: 0.9rem; color: var(--text-secondary);">
            New student seller? <a href="<?= BASE_URL ?>/register.php" style="font-weight: 700; color: var(--coral);">Register with an activation code</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

