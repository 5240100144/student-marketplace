<?php
// CampusMarket Browse Products

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$q         = trim($_GET['q'] ?? '');
$category  = trim($_GET['category'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$minPrice  = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$maxPrice  = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$sort      = trim($_GET['sort'] ?? 'newest');

// Only available products
$where = ["p.status = 'available'"];
$params = [];

if ($q !== '') {
    $where[] = "(p.product_name LIKE ? OR p.description LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($category !== '' && array_key_exists($category, PRODUCT_CATEGORIES)) {
    $where[] = "p.category = ?";
    $params[] = $category;
}

if ($condition !== '' && in_array($condition, PRODUCT_CONDITIONS)) {
    $where[] = "p.condition_type = ?";
    $params[] = $condition;
}

if ($minPrice !== null && $minPrice >= 0) {
    $where[] = "p.price >= ?";
    $params[] = $minPrice;
}

if ($maxPrice !== null && $maxPrice > 0) {
    $where[] = "p.price <= ?";
    $params[] = $maxPrice;
}

// Sorting logic
$orderBy = match($sort) {
    'price_asc'  => 'p.price ASC, p.id DESC',
    'price_desc' => 'p.price DESC, p.id DESC',
    'oldest'     => 'p.id ASC',
    default      => 'p.id DESC'
};

$sql = "
    SELECT p.*, u.full_name as seller_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, id ASC LIMIT 1) as primary_image
    FROM products p
    JOIN users u ON p.seller_id = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY {$orderBy}
";

$products = db_fetch_all($sql, $params);
$totalCount = count($products);

$pageTitle = "Browse Campus Listings";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <!-- Breadcrumb / Header -->
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 2rem; font-weight: 800; color: var(--primary);">Explore Student Listings</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem;">
            <?= $totalCount ?> item<?= $totalCount === 1 ? '' : 's' ?> currently available on campus
            <?php if ($category): ?> &bull; Category: <strong><?= e($category) ?></strong><?php endif; ?>
            <?php if ($q): ?> &bull; Search: &ldquo;<strong><?= e($q) ?></strong>&rdquo;<?php endif; ?>
        </p>
    </div>

    <div class="browse-layout">
        <!-- Filter Sidebar -->
        <?php $hasActiveFilters = ($category || $q || $condition || $minPrice !== null || $maxPrice !== null); ?>
        <aside class="filter-sidebar" id="filterSidebar">
            <div class="filter-header <?= $hasActiveFilters ? 'open' : '' ?>" id="filterHeader">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color: var(--purple-accent);"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    <span class="filter-title">Filter &amp; Sort</span>
                    <?php if ($hasActiveFilters): ?>
                        <span class="badge badge-secondhand" style="font-size: 0.65rem; padding: 2px 7px;">Active</span>
                    <?php endif; ?>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <?php if ($hasActiveFilters): ?>
                        <a href="<?= BASE_URL ?>/browse.php" style="font-size: 0.8rem; color: var(--accent); font-weight: 700;">Reset</a>
                    <?php endif; ?>
                    <button type="button" class="filter-mobile-toggle" id="filterMobileToggle" aria-label="Toggle filters on mobile">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </button>
                </div>
            </div>

            <form action="<?= BASE_URL ?>/browse.php" method="GET" id="filterForm" class="filter-form <?= $hasActiveFilters ? 'active' : '' ?>">
                <!-- Keyword search -->
                <div class="filter-group">
                    <label for="filter_q" class="filter-label">Keyword Search</label>
                    <input type="text" id="filter_q" name="q" value="<?= e($q) ?>" placeholder="Keywords..." class="filter-input">
                </div>

                <!-- Category filter -->
                <div class="filter-group">
                    <label for="filter_cat" class="filter-label">Category</label>
                    <select id="filter_cat" name="category" class="filter-input">
                        <option value="">All Categories</option>
                        <?php foreach (PRODUCT_CATEGORIES as $catName => $catDesc): ?>
                            <option value="<?= e($catName) ?>" <?= $category === $catName ? 'selected' : '' ?>>
                                <?= e($catName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Condition filter -->
                <div class="filter-group">
                    <label for="filter_cond" class="filter-label">Condition</label>
                    <select id="filter_cond" name="condition" class="filter-input">
                        <option value="">All Conditions</option>
                        <option value="New" <?= $condition === 'New' ? 'selected' : '' ?>>New</option>
                        <option value="Second-hand" <?= $condition === 'Second-hand' ? 'selected' : '' ?>>Second-hand</option>
                    </select>
                </div>

                <!-- Price Range Filter -->
                <div class="filter-group">
                    <label class="filter-label">Price Range (GH₵)</label>
                    <div class="price-range-inputs">
                        <input type="number" name="min_price" value="<?= $minPrice !== null ? e($minPrice) : '' ?>" placeholder="Min" min="0" step="1" class="filter-input">
                        <span class="price-divider">&ndash;</span>
                        <input type="number" name="max_price" value="<?= $maxPrice !== null ? e($maxPrice) : '' ?>" placeholder="Max" min="0" step="1" class="filter-input">
                    </div>
                </div>

                <!-- Sort Order -->
                <div class="filter-group">
                    <label for="filter_sort" class="filter-label">Sort By</label>
                    <select id="filter_sort" name="sort" class="filter-input">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-accent btn-block">Apply Filters</button>
                    <a href="<?= BASE_URL ?>/browse.php" class="btn btn-outline btn-block btn-sm">Clear Filters</a>
                </div>
            </form>
        </aside>

        <!-- Product Results Area -->
        <main>
            <?php if (empty($products)): ?>
                <!-- 5.3 Useful No-results Message -->
                <div class="empty-state">
                    <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <h3 class="empty-title">No products match your criteria</h3>
                    <p class="empty-desc">
                        We couldn't find any active listings matching your filters. Try clearing your search keyword or adjusting price limits.
                    </p>
                    <div style="display: flex; gap: 0.75rem; justify-content: center;">
                        <a href="<?= BASE_URL ?>/browse.php" class="btn btn-outline">Reset All Filters</a>
                        <a href="<?= BASE_URL ?>/add-product.php" class="btn btn-accent">Sell This Item Instead</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="product-grid" style="margin-bottom: 2rem;">
                    <?php foreach ($products as $item): ?>
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
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

