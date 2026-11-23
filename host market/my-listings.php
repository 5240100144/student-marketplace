<?php
// CampusMarket My Listings

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

require_seller();
$user = current_user();
$sellerId = $user['id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);

    // Verify ownership
    $existing = get_seller_product($productId, $sellerId);

    if (!$existing) {
        http_response_code(403);
        set_flash('error', 'Security violation: You do not have permission to manage this listing.');
        redirect(BASE_URL . '/my-listings.php');
    }

    if ($action === 'mark_sold') {
        db_query("UPDATE products SET status = 'sold', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND seller_id = ?", [$productId, $sellerId]);
        create_notification(
            $sellerId,
            "Listing Marked as Sold",
            "Your listing \"{$existing['product_name']}\" was marked as SOLD and safely removed from active marketplace search.",
            'product',
            BASE_URL . '/my-listings.php'
        );
        set_flash('success', "Listing &ldquo;{$existing['product_name']}&rdquo; has been marked as SOLD and removed from active public search.");
        redirect(BASE_URL . '/my-listings.php');
    } elseif ($action === 'mark_available') {
        db_query("UPDATE products SET status = 'available', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND seller_id = ?", [$productId, $sellerId]);
        create_notification(
            $sellerId,
            "Listing Relisted Active",
            "Your listing \"{$existing['product_name']}\" is active and visible in the public campus marketplace.",
            'product',
            BASE_URL . '/product.php?id=' . $productId
        );
        set_flash('success', "Listing &ldquo;{$existing['product_name']}&rdquo; is now active and publicly visible again.");
        redirect(BASE_URL . '/my-listings.php');
    } elseif ($action === 'delete') {
        // Fetch image paths to clean up files on disk
        $imgs = db_fetch_all("SELECT image_path FROM product_images WHERE product_id = ?", [$productId]);
        foreach ($imgs as $img) {
            if (!empty($img['image_path'])) {
                $fullPath = ROOT_PATH . '/' . ltrim($img['image_path'], '/');
                if (file_exists($fullPath) && is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }
        db_query("DELETE FROM products WHERE id = ? AND seller_id = ?", [$productId, $sellerId]);
        set_flash('success', "Listing &ldquo;{$existing['product_name']}&rdquo; has been completely deleted.");
        redirect(BASE_URL . '/my-listings.php');
    }
}

// Fetch all listings belonging ONLY to the logged-in seller
$listings = db_fetch_all("
    SELECT p.*,
           (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, id ASC LIMIT 1) as primary_image,
           (SELECT COUNT(*) FROM buyer_interest WHERE product_id = p.id) as inquiry_count
    FROM products p
    WHERE p.seller_id = ?
    ORDER BY p.id DESC
", [$sellerId]);

$pageTitle = "My Listings";
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
                    <?php $myUnread = get_unread_notifications_count($sellerId); if ($myUnread > 0): ?>
                        <span style="margin-left: auto; background: var(--coral); color: #fff; font-size: 0.725rem; font-weight: 800; padding: 0.1rem 0.5rem; border-radius: 9999px;"><?= $myUnread ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>/my-listings.php" class="dashboard-nav-link active">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    My Listings (<?= count($listings) ?>)
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

        <!-- Listings Content -->
        <main>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
                <div>
                    <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--primary);">My Product Listings</h1>
                    <p style="color: var(--text-muted); font-size: 0.95rem;">Review, edit, mark as sold, or remove your campus advertisements.</p>
                </div>
                <a href="<?= BASE_URL ?>/add-product.php" class="btn btn-accent">
                    + Post Another Item
                </a>
            </div>

            <?php if (empty($listings)): ?>
                <div class="empty-state">
                    <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <h3 class="empty-title">You have not created any listings yet</h3>
                    <p class="empty-desc">Upload photos and specify details to put your textbook or gadget in front of students today.</p>
                    <a href="<?= BASE_URL ?>/add-product.php" class="btn btn-accent">Add Product Now</a>
                </div>
            <?php else: ?>
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Listing Details</th>
                                    <th>Category & Condition</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Inquiries</th>
                                    <th>Listing Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listings as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="table-product-cell">
                                                <img src="<?= e(get_product_image_url($item['primary_image'])) ?>" alt="" class="table-thumb">
                                                <div>
                                                    <a href="<?= BASE_URL ?>/product.php?id=<?= $item['id'] ?>" style="font-weight: 700; color: var(--primary);">
                                                        <?= e($item['product_name']) ?>
                                                    </a>
                                                    <div style="font-size: 0.775rem; color: var(--text-muted);">
                                                        Posted <?= format_date($item['created_at']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                                <span class="badge badge-category"><?= e($item['category']) ?></span>
                                                <span class="badge <?= $item['condition_type'] === 'New' ? 'badge-new' : 'badge-secondhand' ?>" style="font-size: 0.7rem;">
                                                    <?= e($item['condition_type']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td><strong><?= format_price($item['price']) ?></strong></td>
                                        <td>
                                            <?php if ($item['status'] === 'available'): ?>
                                                <span class="badge badge-available">Available</span>
                                            <?php else: ?>
                                                <span class="badge badge-sold">Sold</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-weight: 700; color: var(--primary);">
                                                <?= (int)$item['inquiry_count'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                                <!-- View Public -->
                                                <a href="<?= BASE_URL ?>/product.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm" title="View Public Listing">
                                                    View
                                                </a>

                                                <!-- Edit Listing -->
                                                <a href="<?= BASE_URL ?>/edit-product.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm" title="Edit Product Information">
                                                    Edit
                                                </a>

                                                <!-- Status toggle -->
                                                <?php if ($item['status'] === 'available'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="mark_sold">
                                                        <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                                        <button type="submit" class="btn btn-outline btn-sm" title="Mark this item as sold">
                                                            Mark Sold
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="mark_available">
                                                        <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                                        <button type="submit" class="btn btn-outline btn-sm" title="Reactivate this listing">
                                                            Relist
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Delete Listing -->
                                                <form method="POST" style="display: inline;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm btn-confirm-delete" title="Delete listing permanently">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

