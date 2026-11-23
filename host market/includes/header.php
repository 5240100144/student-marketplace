<?php
/**
 * Global Header Component
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$currentUser = current_user();
$isLoggedIn = is_logged_in();
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$unreadNotifCount = $isLoggedIn ? get_unread_notifications_count($currentUser['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' . APP_NAME : APP_NAME . ' - Student Marketplace' ?></title>
    <meta name="description" content="<?= e(APP_TAGLINE) ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/logo.svg">
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <!-- Logo -->
        <a href="<?= BASE_URL ?>/index.php" class="brand-link" aria-label="CampusMarket Homepage">
            <img src="<?= BASE_URL ?>/assets/images/logo.svg" alt="CampusMarket Logo" style="height: 42px; width: auto;">
        </a>

        <!-- Desktop Navigation -->
        <nav class="nav-desktop" aria-label="Main Navigation">
            <a href="<?= BASE_URL ?>/index.php" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">Home</a>
            <a href="<?= BASE_URL ?>/browse.php" class="nav-link <?= $currentPage === 'browse.php' ? 'active' : '' ?>">Browse Products</a>
            <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">Seller Dashboard</a>
            <?php if (!$isLoggedIn): ?>
                <a href="<?= BASE_URL ?>/register.php" class="nav-link <?= $currentPage === 'register.php' ? 'active' : '' ?>">Become a Seller</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/my-listings.php" class="nav-link <?= $currentPage === 'my-listings.php' ? 'active' : '' ?>">My Listings</a>
            <?php endif; ?>
        </nav>

        <!-- Right Action Buttons -->
        <div class="nav-actions">
            <?php if ($isLoggedIn): ?>
                <a href="<?= BASE_URL ?>/notifications.php" class="btn btn-outline btn-sm" title="Notification Center" aria-label="Notifications" style="position: relative; display: inline-flex; align-items: center; justify-content: center; padding: 0.45rem 0.65rem;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <?php if ($unreadNotifCount > 0): ?>
                        <span style="position: absolute; top: -5px; right: -5px; background: var(--coral); color: #ffffff; font-size: 0.7rem; font-weight: 800; min-width: 18px; height: 18px; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; padding: 0 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.15); line-height: 1;">
                            <?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>/add-product.php" class="btn btn-accent btn-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Sell an Item
                </a>
                <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline btn-sm">Log Out</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline btn-sm">Seller Login</a>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-accent btn-sm">Sell on Campus</a>
            <?php endif; ?>
        </div>

        <!-- Mobile Navigation Toggle -->
        <button type="button" class="mobile-nav-toggle" id="mobileNavToggle" aria-label="Toggle mobile menu">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
    </div>

    <!-- Mobile Dropdown Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <a href="<?= BASE_URL ?>/index.php" class="nav-link">Home</a>
        <a href="<?= BASE_URL ?>/browse.php" class="nav-link">Browse Products</a>
        <?php if ($isLoggedIn): ?>
            <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link">Seller Dashboard</a>
            <a href="<?= BASE_URL ?>/notifications.php" class="nav-link" style="display: flex; align-items: center; justify-content: space-between;">
                <span>Notifications</span>
                <?php if ($unreadNotifCount > 0): ?>
                    <span style="background: var(--coral); color: #ffffff; font-size: 0.75rem; font-weight: 700; padding: 0.15rem 0.55rem; border-radius: 9999px;">
                        <?= $unreadNotifCount ?> new
                    </span>
                <?php endif; ?>
            </a>
            <a href="<?= BASE_URL ?>/my-listings.php" class="nav-link">My Listings</a>
            <div class="mobile-actions">
                <a href="<?= BASE_URL ?>/add-product.php" class="btn btn-accent btn-block">Sell an Item</a>
                <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline btn-block">Log Out (<?= e($currentUser['full_name']) ?>)</a>
            </div>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/register.php" class="nav-link">Become a Seller</a>
            <div class="mobile-actions">
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline btn-block">Seller Login</a>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-accent btn-block">Register with Activation Code</a>
            </div>
        <?php endif; ?>
    </div>
</header>

<?= render_flash() ?>

<main class="main-content">

