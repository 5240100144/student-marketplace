<?php
// CampusMarket Homepage

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Fetch recent active listings
$recentProducts = db_fetch_all("
    SELECT p.*, u.full_name as seller_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, id ASC LIMIT 1) as primary_image
    FROM products p
    JOIN users u ON p.seller_id = u.id
    WHERE p.status = 'available'
    ORDER BY p.id DESC
    LIMIT 8
");

$pageTitle = "Campus Student Marketplace";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-pill">
                Campus Verified Marketplace
            </div>
            <h1 class="hero-title">Discover, Buy &amp; Sell <span class="highlight-coral">Among Fellow Students</span></h1>
            <p class="hero-subtitle">
                Buy and sell textbooks, electronics, room essentials, and fashion directly with students on campus.
            </p>
            <form action="<?= BASE_URL ?>/browse.php" method="GET" class="hero-search">
                <svg class="hero-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" name="q" placeholder="Search textbooks, laptops, calculators, room essentials..." aria-label="Search campus products" required>
            </form>
            <div class="hero-actions">
                <a href="<?= BASE_URL ?>/browse.php" class="btn btn-hero-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Browse All Listings
                </a>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-hero-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Start Selling on Campus
                </a>
            </div>
        </div>
    </section>

    <!-- Category Shortcuts -->
    <section class="category-section">
        <div class="section-header">
            <h2 class="section-title">Browse by Category</h2>
            <a href="<?= BASE_URL ?>/browse.php" class="nav-link" style="color: var(--purple-accent); font-weight: 600;">View All</a>
        </div>
        <div class="category-grid">
            <?php 
            $catIcons = [
                'Books' => '<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>',
                'Electronics' => '<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>',
                'Fashion' => '<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.47a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.47a2 2 0 00-1.34-2.23z"></path></svg>',
                'Hostel Essentials' => '<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
                'Stationery' => '<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 19l7-7 3 3-7 7-3-3z"></path><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"></path><path d="M2 2l7.586 7.586"></path><circle cx="11" cy="11" r="2"></circle></svg>',
                'Other' => '<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
            ];
            foreach (PRODUCT_CATEGORIES as $catName => $catDesc): 
            ?>
                <a href="<?= BASE_URL ?>/browse.php?category=<?= urlencode($catName) ?>" class="category-card">
                    <div class="category-icon">
                        <?= $catIcons[$catName] ?? $catIcons['Other'] ?>
                    </div>
                    <div class="category-name"><?= e($catName) ?></div>
                    <div class="category-desc"><?= e($catDesc) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Recently Listed Products -->
    <section style="margin-bottom: 3.5rem;">
        <div class="section-header">
            <div>
                <h2 class="section-title">Recently Listed Items</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Latest items posted by campus sellers</p>
            </div>
            <a href="<?= BASE_URL ?>/browse.php" class="btn btn-outline btn-sm">View All Items</a>
        </div>

        <?php if (empty($recentProducts)): ?>
            <div class="empty-state">
                <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                <h3 class="empty-title">No items listed yet</h3>
                <p class="empty-desc">Check back soon or register as a seller to post your items.</p>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-accent">Become a Seller</a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($recentProducts as $item): ?>
                    <div class="product-card">
                        <a href="<?= BASE_URL ?>/product.php?id=<?= $item['id'] ?>" class="product-card-image-wrap">
                            <img src="<?= e(get_product_image_url($item['primary_image'])) ?>" 
                                 alt="<?= e($item['product_name']) ?>" 
                                 class="product-card-image"
                                 loading="lazy">
                            <div class="product-card-badges">
                                <span class="badge <?= $item['condition_type'] === 'New' ? 'badge-new' : 'badge-secondhand' ?>">
                                    <?= e($item['condition_type']) ?>
                                </span>
                            </div>
                        </a>
                        <div class="product-card-body">
                            <div class="product-card-meta">
                                <span class="badge badge-category"><?= e($item['category']) ?></span>
                            </div>
                            <h3 class="product-card-title">
                                <a href="<?= BASE_URL ?>/product.php?id=<?= $item['id'] ?>">
                                    <?= e($item['product_name']) ?>
                                </a>
                            </h3>
                            <p class="product-card-desc"><?= e($item['description']) ?></p>
                            <div class="product-card-footer">
                                <div class="product-card-price"><?= format_price($item['price']) ?></div>
                                <a href="<?= BASE_URL ?>/product.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm">
                                    View
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>


    <!-- Call to action for sellers -->
    <?php if (!$isLoggedIn): ?>
    <section style="background: linear-gradient(135deg, #2e1065 0%, #1e1b4b 60%, #4c1d95 100%); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: var(--radius-xl); padding: 3.5rem 2rem; color: #ffffff; text-align: center; margin-bottom: 2rem; box-shadow: var(--shadow-lg);">
        <h2 style="font-size: 2.15rem; font-weight: 850; margin-bottom: 0.75rem; letter-spacing: -0.015em;">Sell your textbooks and campus gear</h2>
        <p style="font-size: 1.05rem; opacity: 0.9; max-width: 580px; margin: 0 auto 1.85rem; line-height: 1.6;">
            Connect directly with campus buyers with zero listing fees. Reach out to administration for an access code to set up your seller account.
        </p>
        <div style="display: flex; gap: 1.35rem; justify-content: center; flex-wrap: wrap;">
            <a href="<?= BASE_URL ?>/register.php" class="btn btn-accent btn-lg">Register as a Seller</a>
            <a href="<?= BASE_URL ?>/browse.php" class="btn btn-outline btn-lg" style="color: #ffffff; border-color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.06);">Browse Products</a>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

