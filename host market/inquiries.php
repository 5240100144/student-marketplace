<?php
// CampusMarket Buyer Inquiries

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

require_seller();
$user = current_user();
$sellerId = $user['id'];

// Filter by product if specified
$filterProductId = (int)($_GET['product_id'] ?? 0);
$where = ["bi.seller_id = ?"];
$params = [$sellerId];

if ($filterProductId > 0) {
    $where[] = "bi.product_id = ?";
    $params[] = $filterProductId;
}

// Fetch all inquiries
$inquiries = db_fetch_all("
    SELECT bi.*, p.product_name, p.price,
           (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, id ASC LIMIT 1) as primary_image
    FROM buyer_interest bi
    JOIN products p ON bi.product_id = p.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY bi.id DESC
", $params);

// Fetch seller's products for filter dropdown
$sellerProducts = db_fetch_all("SELECT id, product_name FROM products WHERE seller_id = ? ORDER BY product_name ASC", [$sellerId]);

$pageTitle = "Buyer Inquiries & Leads";
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
            </div>

            <nav>
                <a href="<?= BASE_URL ?>/dashboard.php" class="dashboard-nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Overview
                </a>
                <a href="<?= BASE_URL ?>/notifications.php" class="dashboard-nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    Notifications
                    <?php $inqUnread = get_unread_notifications_count($sellerId); if ($inqUnread > 0): ?>
                        <span style="margin-left: auto; background: var(--coral); color: #fff; font-size: 0.725rem; font-weight: 800; padding: 0.1rem 0.5rem; border-radius: 9999px;"><?= $inqUnread ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>/my-listings.php" class="dashboard-nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    My Listings
                </a>
                <a href="<?= BASE_URL ?>/inquiries.php" class="dashboard-nav-link active">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    Buyer Messages (<?= count($inquiries) ?>)
                </a>
                <a href="<?= BASE_URL ?>/add-product.php" class="dashboard-nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                    Add Product
                </a>
                <a href="<?= BASE_URL ?>/browse.php" class="dashboard-nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Public Marketplace
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--primary);">Buyer Messages &amp; Inquiries</h1>
                    <p style="color: var(--text-muted); font-size: 0.95rem;">Students who expressed interest in purchasing your listed items.</p>
                </div>

                <!-- Filter by Product Dropdown -->
                <?php if (!empty($sellerProducts)): ?>
                    <form action="<?= BASE_URL ?>/inquiries.php" method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
                        <select name="product_id" class="form-control" style="padding: 0.45rem 0.75rem; font-size: 0.85rem;" onchange="this.form.submit()">
                            <option value="0">All Items (<?= count($inquiries) ?>)</option>
                            <?php foreach ($sellerProducts as $sp): ?>
                                <option value="<?= $sp['id'] ?>" <?= $filterProductId === (int)$sp['id'] ? 'selected' : '' ?>>
                                    <?= e($sp['product_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($filterProductId > 0): ?>
                            <a href="<?= BASE_URL ?>/inquiries.php" class="btn btn-outline btn-sm">Reset</a>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($inquiries)): ?>
                <div class="empty-state">
                    <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    <h3 class="empty-title">No inquiries received yet</h3>
                    <p class="empty-desc">When prospective buyers click &ldquo;I'm Interested&rdquo; on your product listings, their contact details and questions will appear here.</p>
                    <a href="<?= BASE_URL ?>/my-listings.php" class="btn btn-outline">Review Your Active Listings</a>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($inquiries as $inq): 
                        $contact = $inq['buyer_contact'];
                        $isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL);
                        $isPhone = preg_match('/^[0-9+\-\s()]{7,18}$/', $contact);
                    ?>
                        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 1rem;">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                                <!-- Left: Product & Buyer Info -->
                                <div style="display: flex; gap: 1rem; align-items: center;">
                                    <img src="<?= e(get_product_image_url($inq['primary_image'])) ?>" alt="" style="width: 56px; height: 56px; border-radius: var(--radius-sm); object-fit: cover; border: 1px solid var(--border-color);">
                                    <div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.15rem;">Interested in:</div>
                                        <a href="<?= BASE_URL ?>/product.php?id=<?= $inq['product_id'] ?>" style="font-weight: 700; color: var(--primary); font-size: 1.05rem;">
                                            <?= e($inq['product_name']) ?>
                                        </a>
                                        <span style="font-size: 0.9rem; font-weight: 700; color: var(--accent-dark); margin-left: 0.5rem;">
                                            <?= format_price($inq['price']) ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Timestamp -->
                                <div style="font-size: 0.8rem; color: var(--text-muted); background: var(--bg-muted); padding: 0.25rem 0.65rem; border-radius: 9999px;">
                                    Received <?= format_date($inq['created_at']) ?>
                                </div>
                            </div>

                            <!-- Buyer Details Box -->
                            <div style="background: var(--bg-muted); border-radius: var(--radius-md); padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-weight: 700; color: var(--primary);"><?= e($inq['buyer_name']) ?></span>
                                    <span style="color: var(--text-muted);">&bull;</span>
                                    <code style="font-weight: 600; color: var(--text-secondary);"><?= e($contact) ?></code>
                                </div>

                                <?php if (!empty($inq['buyer_message'])): ?>
                                    <div style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5; font-style: italic;">
                                        &ldquo;<?= e($inq['buyer_message']) ?>&rdquo;
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Actions Row -->
                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                <?php if ($isPhone): 
                                    $waLink = build_whatsapp_url($contact, $inq['product_name'], $inq['price']);
                                ?>
                                    <a href="<?= e($waLink) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.592 2.654-.697c.969.57 1.961.872 2.806.872 3.182 0 5.768-2.587 5.768-5.766.001-3.187-2.575-5.754-5.768-5.754zm7.654 5.766c.002 4.223-3.437 7.662-7.66 7.662-1.258 0-2.496-.309-3.593-.896l-4.432 1.162 1.182-4.321c-.692-1.167-1.057-2.511-1.056-3.887.002-4.224 3.442-7.662 7.665-7.662 4.225 0 7.664 3.438 7.664 7.662z"/></svg>
                                        WhatsApp Buyer
                                    </a>
                                    <a href="tel:<?= e($contact) ?>" class="btn btn-outline btn-sm">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                        Call Buyer
                                    </a>
                                <?php endif; ?>

                                <?php if ($isEmail): ?>
                                    <a href="mailto:<?= e($contact) ?>?subject=Regarding your inquiry on <?= rawurlencode($inq['product_name']) ?>" class="btn btn-outline btn-sm">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                        Send Email
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

