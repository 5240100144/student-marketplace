<?php
// CampusMarket Seller Dashboard

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// Handle login directly from the Seller Dashboard Gateway
$loginError = '';
$loginIdentifier = '';
if (!is_logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dashboard_login') {
    csrf_verify();
    $loginIdentifier = trim($_POST['login_identifier'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (empty($loginIdentifier) || empty($password)) {
        $loginError = "Please enter your university email or Student ID and password.";
    } else {
        $user = db_fetch_one("
            SELECT * FROM users 
            WHERE school_email = ? OR student_id = ?
        ", [$loginIdentifier, $loginIdentifier]);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['seller_status'] !== 'active') {
                $loginError = "Your seller account is currently suspended. Please contact campus administration.";
            } else {
                login_user($user);
                set_flash('success', "Welcome back, {$user['full_name']}!");
                if (is_admin()) {
                    redirect(BASE_URL . '/admin-codes.php');
                } else {
                    redirect(BASE_URL . '/dashboard.php');
                }
            }
        } else {
            $loginError = "Invalid credentials. Please verify your email / student ID and password.";
        }
    }
}

// If the visitor is not logged in, display the Seller Gateway
if (!is_logged_in()) {
    $pageTitle = "Seller Dashboard - Become a Seller or Log In";
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="container" style="max-width: 960px; margin-top: 2.25rem; margin-bottom: 4rem;">
        
        <!-- Prominent Code Notice Banner -->
        <div style="background: linear-gradient(135deg, rgba(234, 88, 12, 0.08) 0%, rgba(249, 115, 22, 0.12) 100%); border: 1.5px solid var(--coral); border-radius: var(--radius-xl); padding: 1.75rem 2rem; margin-bottom: 2.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.25rem; box-shadow: var(--shadow-sm);">
            <div>
                <div style="display: inline-flex; align-items: center; background: var(--coral); color: #ffffff; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                    Seller Access Notice
                </div>
                <h2 style="font-size: 1.35rem; font-weight: 850; color: var(--purple-dark); margin: 0 0 0.35rem 0; letter-spacing: -0.01em;">
                    Kindly reach <a href="tel:0240449137" style="color: var(--coral); text-decoration: underline;">0240449137</a> for a unique code to be a seller
                </h2>
                <p style="color: var(--text-secondary); margin: 0; font-size: 0.95rem; line-height: 1.5;">
                    To protect students and ensure authentic campus listings, an access code is required before creating a seller account.
                </p>
            </div>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <a href="https://wa.me/233240449137?text=Hello%20CampusMarket,%20I%20would%20like%20to%20request%20a%20unique%20seller%20access%20code" target="_blank" rel="noopener" class="btn btn-accent btn-sm" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2z"/></svg>
                    WhatsApp 0240449137
                </a>
                <a href="tel:0240449137" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    Call 0240449137
                </a>
            </div>
        </div>

        <!-- 2-Column Gateway: Become a Seller vs Log In -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem; align-items: stretch;">
            
            <!-- Left Card: Become a Seller -->
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-xl); padding: 2.25rem 2rem; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(46, 16, 101, 0.08); color: var(--purple-dark); display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                    </div>
                    <h2 style="font-size: 1.5rem; font-weight: 850; color: var(--purple-dark); margin-bottom: 0.5rem;">
                        Become a Seller
                    </h2>
                    <p style="color: var(--text-secondary); line-height: 1.6; font-size: 0.95rem; margin-bottom: 1.5rem;">
                        Have you received your activation code from the project team? Register your student seller account to start posting your items to campus buyers immediately.
                    </p>

                    <div style="background: var(--bg-muted); border-radius: var(--radius-md); padding: 1.15rem; margin-bottom: 1.75rem;">
                        <div style="font-size: 0.825rem; font-weight: 700; color: var(--purple-dark); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.5rem;">
                            Seller Benefits
                        </div>
                        <ul style="padding-left: 1.15rem; margin: 0; color: var(--text-secondary); font-size: 0.885rem; line-height: 1.7;">
                            <li>Zero commissions or transaction fees</li>
                            <li>Direct WhatsApp and phone call inquiries from fellow students</li>
                            <li>Full control over your active and sold listings</li>
                            <li>Permanent access once verified</li>
                        </ul>
                    </div>
                </div>

                <div>
                    <a href="<?= BASE_URL ?>/register.php" class="btn btn-accent btn-lg btn-block" style="text-align: center;">
                        Become a Seller (Enter Code)
                    </a>
                    <div style="font-size: 0.825rem; color: var(--text-muted); text-align: center; margin-top: 0.75rem;">
                        Need a code first? Call/WhatsApp <strong>0240449137</strong>
                    </div>
                </div>
            </div>

            <!-- Right Card: Log In for Users Who Have Already Created Account -->
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-xl); padding: 2.25rem 2rem; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(234, 88, 12, 0.1); color: var(--coral); display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                    </div>
                    <h2 style="font-size: 1.5rem; font-weight: 850; color: var(--purple-dark); margin-bottom: 0.5rem;">
                        Already Have an Account?
                    </h2>
                    <p style="color: var(--text-secondary); line-height: 1.6; font-size: 0.95rem; margin-bottom: 1.5rem;">
                        Log in below to access your seller workspace, track inquiries, and update your listings.
                    </p>

                    <?php if (!empty($loginError)): ?>
                        <div class="alert alert-error" style="margin-bottom: 1.25rem;">
                            <div class="alert-content"><?= e($loginError) ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>/dashboard.php" method="POST" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="dashboard_login">

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="dash_login_identifier" class="form-label">Username, School Email or Student ID</label>
                            <input type="text" id="dash_login_identifier" name="login_identifier" class="form-control" value="<?= e($loginIdentifier) ?>" placeholder="e.g. marketplace or your student email" required>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label for="dash_password" class="form-label">Password</label>
                            <input type="password" id="dash_password" name="password" class="form-control" placeholder="Enter your password" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            Log In to Dashboard
                        </button>
                    </form>
                </div>

                <div style="font-size: 0.85rem; color: var(--text-muted); text-align: center; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    Forgot credentials? Contact team at <strong>0240449137</strong>
                </div>
            </div>

        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// User is Authenticated: Render Full Live Seller Dashboard
$user = current_user();
$sellerId = $user['id'];

// Listing counts
$totalListings = (int)db_fetch_one("SELECT COUNT(*) as count FROM products WHERE seller_id = ?", [$sellerId])['count'];
$activeListings = (int)db_fetch_one("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'available'", [$sellerId])['count'];
$soldListings = (int)db_fetch_one("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'sold'", [$sellerId])['count'];
$totalInquiries = (int)db_fetch_one("SELECT COUNT(*) as count FROM buyer_interest WHERE seller_id = ?", [$sellerId])['count'];

// Recent listings
$recentListings = db_fetch_all("
    SELECT p.*, 
           (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, id ASC LIMIT 1) as primary_image
    FROM products p
    WHERE p.seller_id = ?
    ORDER BY p.id DESC
    LIMIT 5
", [$sellerId]);

// Recent buyer inquiries
$recentInquiries = db_fetch_all("
    SELECT bi.*, p.product_name
    FROM buyer_interest bi
    JOIN products p ON bi.product_id = p.id
    WHERE bi.seller_id = ?
    ORDER BY bi.id DESC
    LIMIT 5
", [$sellerId]);

$pageTitle = "Seller Dashboard";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="dashboard-grid">
        <!-- Sidebar Navigation -->
        <aside class="dashboard-nav">
            <div class="dashboard-user-box">
                <div class="user-name-title"><?= e($user['full_name']) ?></div>
                <div class="user-id-sub">Student ID: <?= e($user['student_id']) ?></div>
                <div class="user-id-sub"><?= e($user['school_email']) ?></div>
                <div style="margin-top: 0.5rem;">
                    <span class="badge badge-available">Active Seller</span>
                </div>
            </div>

            <nav>
                <a href="<?= BASE_URL ?>/dashboard.php" class="dashboard-nav-link active">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Overview
                </a>
                <a href="<?= BASE_URL ?>/notifications.php" class="dashboard-nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    Notifications
                    <?php $dUnread = get_unread_notifications_count($sellerId); if ($dUnread > 0): ?>
                        <span style="margin-left: auto; background: var(--coral); color: #fff; font-size: 0.725rem; font-weight: 800; padding: 0.1rem 0.5rem; border-radius: 9999px;"><?= $dUnread ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>/my-listings.php" class="dashboard-nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    My Listings (<?= $totalListings ?>)
                </a>
                <a href="<?= BASE_URL ?>/add-product.php" class="dashboard-nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                    Add Product
                </a>
                <?php if (is_admin()): ?>
                    <a href="<?= BASE_URL ?>/admin-codes.php" class="dashboard-nav-link" style="color: var(--coral); font-weight: 700; background: rgba(234, 88, 12, 0.08);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        Code Manager
                    </a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/browse.php" class="dashboard-nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Public Marketplace
                </a>
                <a href="<?= BASE_URL ?>/logout.php" class="dashboard-nav-link" style="color: var(--danger);">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Log Out
                </a>
            </nav>
        </aside>

        <!-- Main Dashboard View -->
        <main>
            <?php if (is_admin()): ?>
                <div style="background: linear-gradient(135deg, #1e1b4b 0%, #2e1065 100%); color: #ffffff; padding: 1.15rem 1.5rem; border-radius: var(--radius-lg); margin-bottom: 1.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(255,255,255,0.1);">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: #fdba74; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.2rem;">
                            Administrator Privileges
                        </div>
                        <div style="font-size: 1.05rem; font-weight: 800;">
                            Access Code Management
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/admin-codes.php" class="btn btn-accent btn-sm">
                        Open Code Manager
                    </a>
                </div>
            <?php endif; ?>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
                <div>
                    <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--purple-dark);">Seller Workspace</h1>
                    <p style="color: var(--text-muted); font-size: 0.95rem;">Manage your advertisements and respond to campus buyers.</p>
                </div>
                <a href="<?= BASE_URL ?>/add-product.php" class="btn btn-accent">
                    Add New Listing
                </a>
            </div>

            <!-- Overview Metrics -->
            <div class="metric-cards-grid">
                <div class="metric-card" style="border-top-color: var(--coral);">
                    <div class="metric-val" style="color: var(--coral);"><?= $activeListings ?></div>
                    <div class="metric-title">Active Public Listings</div>
                </div>
                <div class="metric-card" style="border-top-color: #64748b;">
                    <div class="metric-val" style="color: #64748b;"><?= $soldListings ?></div>
                    <div class="metric-title">Sold Items</div>
                </div>
                <div class="metric-card" style="border-top-color: var(--purple-accent);">
                    <div class="metric-val" style="color: var(--purple-dark);"><?= $totalInquiries ?></div>
                    <div class="metric-title">Buyer Inquiries Received</div>
                </div>
            </div>

            <!-- Recent Listings Section -->
            <div style="margin-bottom: 2.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--purple-dark);">Your Recent Listings</h2>
                    <a href="<?= BASE_URL ?>/my-listings.php" style="font-size: 0.875rem; color: var(--coral); font-weight: 700;">View All Listings</a>
                </div>

                <?php if (empty($recentListings)): ?>
                    <div class="empty-state" style="padding: 2.5rem 1.5rem;">
                        <h3 class="empty-title">No listings posted yet</h3>
                        <p class="empty-desc">Your active and sold items will appear here once you post them.</p>
                        <a href="<?= BASE_URL ?>/add-product.php" class="btn btn-accent">Add Listing</a>
                    </div>
                <?php else: ?>
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentListings as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="table-product-cell">
                                                    <img src="<?= e(get_product_image_url($item['primary_image'])) ?>" alt="" class="table-thumb">
                                                    <div>
                                                        <div style="font-weight: 700; color: var(--primary);"><?= e($item['product_name']) ?></div>
                                                        <div style="font-size: 0.775rem; color: var(--text-muted);"><?= format_date($item['created_at']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge badge-category"><?= e($item['category']) ?></span></td>
                                            <td><strong><?= format_price($item['price']) ?></strong></td>
                                            <td>
                                                <?php if ($item['status'] === 'available'): ?>
                                                    <span class="badge badge-available">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-sold">Sold</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <a href="<?= BASE_URL ?>/product.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm" title="View Public Page">View</a>
                                                    <a href="<?= BASE_URL ?>/edit-product.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm" title="Edit Listing">Edit</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Inquiries Section -->
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 1rem;">Recent Buyer Messages</h2>
                <?php if (empty($recentInquiries)): ?>
                    <div style="background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: var(--radius-lg); padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                        No buyer inquiries received yet. When students click &ldquo;I'm Interested&rdquo; on your items, their contact info and messages will show here.
                    </div>
                <?php else: ?>
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Buyer Name</th>
                                        <th>Contact Details</th>
                                        <th>Message</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentInquiries as $inq): ?>
                                        <tr>
                                            <td style="font-weight: 600; color: var(--primary);"><?= e($inq['product_name']) ?></td>
                                            <td><?= e($inq['buyer_name']) ?></td>
                                            <td><code><?= e($inq['buyer_contact']) ?></code></td>
                                            <td style="max-width: 280px; font-size: 0.85rem; color: var(--text-secondary);"><?= e($inq['buyer_message'] ?: '(No message)') ?></td>
                                            <td style="font-size: 0.8rem; color: var(--text-muted);"><?= format_date($inq['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

