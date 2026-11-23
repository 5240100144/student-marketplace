<?php
// CampusMarket Product Details & Buyer Interest

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$productId = (int)($_GET['id'] ?? 0);
if ($productId <= 0) {
    set_flash('error', 'Invalid product requested.');
    redirect(BASE_URL . '/browse.php');
}

// Fetch product details and seller public profile
$product = db_fetch_one("
    SELECT p.*, u.full_name as seller_name, u.school_email as seller_email, u.phone as seller_default_phone
    FROM products p
    JOIN users u ON p.seller_id = u.id
    WHERE p.id = ?
", [$productId]);

if (!$product) {
    set_flash('error', 'The requested listing does not exist or has been removed.');
    redirect(BASE_URL . '/browse.php');
}

// Determine contact phone
$contactPhone = !empty($product['whatsapp_phone']) ? $product['whatsapp_phone'] : $product['seller_default_phone'];

// Fetch product images
$images = db_fetch_all("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC", [$productId]);

// Handle buyer interest form submission
$interestSubmitted = false;
$interestError = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_interest') {
    csrf_verify();

    // Honeypot anti-spam check
    $honeypot = trim($_POST['website_hp'] ?? '');
    if (!empty($honeypot)) {
        // Silently terminate bot submissions
        redirect(BASE_URL . '/product.php?id=' . $productId);
    }

    $buyerName    = trim($_POST['buyer_name'] ?? '');
    $buyerContact = trim($_POST['buyer_contact'] ?? '');
    $buyerMessage = trim($_POST['buyer_message'] ?? '');

    if (empty($buyerName) || empty($buyerContact)) {
        $interestError = 'Please provide both your name and a contact phone number or email so the seller can respond.';
    } else {
        // Record interest against product and seller in buyer_interest table
        try {
            $notificationStatus = send_interest_notification(
                $product['seller_email'],
                $product['seller_name'],
                $product['product_name'],
                $buyerName,
                $buyerContact,
                $buyerMessage
            );

            db_query("
                INSERT INTO buyer_interest (product_id, seller_id, buyer_name, buyer_contact, buyer_message, notification_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ", [
                $productId,
                $product['seller_id'],
                $buyerName,
                $buyerContact,
                $buyerMessage,
                $notificationStatus
            ]);

            // Create in-app system notification for the seller (Notification Center)
            create_notification(
                $product['seller_id'],
                "New Buyer Inquiry: " . $product['product_name'],
                "{$buyerName} ({$buyerContact}) expressed interest: " . ($buyerMessage ? "\"{$buyerMessage}\"" : "Interested in purchasing {$product['product_name']}."),
                'inquiry',
                BASE_URL . '/inquiries.php?product_id=' . $productId
            );

            $interestSubmitted = true;
            set_flash('success', "Your message has been sent to {$product['seller_name']}! You can also chat with them directly on WhatsApp below.");
        } catch (Throwable $e) {
            $interestError = 'A database error occurred while recording your interest. Please try again.';
        }
    }
}

// Prepare WhatsApp URL
$whatsappUrl = build_whatsapp_url($contactPhone, $product['product_name'], $product['price']);

$pageTitle = $product['product_name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <!-- Breadcrumb Navigation -->
    <nav class="product-breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Home</a>
        <span>&rsaquo;</span>
        <a href="<?= BASE_URL ?>/browse.php">Browse</a>
        <span>&rsaquo;</span>
        <a href="<?= BASE_URL ?>/browse.php?category=<?= urlencode($product['category']) ?>"><?= e($product['category']) ?></a>
        <span>&rsaquo;</span>
        <span style="color: var(--text-primary); font-weight: 500;"><?= e($product['product_name']) ?></span>
    </nav>

    <!-- Product Details Layout -->
    <div class="product-detail-layout">
        <!-- Image Gallery -->
        <div class="gallery-container">
            <?php 
            $primaryImgSrc = !empty($images) ? get_product_image_url($images[0]['image_path']) : BASE_URL . '/assets/images/placeholder.svg';
            ?>
            <div class="main-image-wrap">
                <img id="mainProductImage" src="<?= e($primaryImgSrc) ?>" alt="<?= e($product['product_name']) ?>" class="main-image">
            </div>

            <?php if (count($images) > 1): ?>
                <div class="thumbnails-row">
                    <?php foreach ($images as $idx => $img): 
                        $thumbSrc = get_product_image_url($img['image_path']);
                    ?>
                        <button type="button" class="thumbnail-btn <?= $idx === 0 ? 'active' : '' ?>" data-full-img="<?= e($thumbSrc) ?>" aria-label="View photo <?= $idx + 1 ?>">
                            <img src="<?= e($thumbSrc) ?>" alt="Thumbnail <?= $idx + 1 ?>">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Safe Campus Meet Reminder -->
            <div class="product-safety-box">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                <div class="product-safety-box-text">
                    <strong style="color: var(--purple-accent);">Campus Safety Reminder:</strong> Meet the seller in a public, well-lit university area (such as the main library or student cafeteria). Test and inspect the item before finalizing payment.
                </div>
            </div>
        </div>

        <!-- Right: Information & Contact Panel -->
        <div class="product-info-panel">
            <h1 class="product-detail-title"><?= e($product['product_name']) ?></h1>
            
            <div class="product-detail-price">
                <?= format_price($product['price']) ?>
            </div>

            <div class="product-badges-row">
                <span class="badge <?= $product['condition_type'] === 'New' ? 'badge-new' : 'badge-secondhand' ?>">
                    Condition: <?= e($product['condition_type']) ?>
                </span>
                <span class="badge badge-category">
                    <?= e($product['category']) ?>
                </span>
                <?php if ($product['status'] === 'available'): ?>
                    <span class="badge badge-available">Available</span>
                <?php else: ?>
                    <span class="badge badge-sold">Sold Out</span>
                <?php endif; ?>
            </div>

            <div class="product-description-box">
                <h3 class="description-title">Product Details & Features</h3>
                <div class="description-text"><?= e($product['description']) ?></div>
            </div>

            <!-- Seller Profile Box -->
            <div class="seller-box">
                <div class="seller-header">
                    <div class="seller-avatar">
                        <?= strtoupper(substr($product['seller_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="seller-name"><?= e($product['seller_name']) ?></div>
                        <div class="seller-badge">Verified Student Seller</div>
                    </div>
                </div>
                <div class="seller-note">
                    Active Campus Member
                </div>
            </div>

            <!-- 6.2 Contact Actions -->
            <?php if ($product['status'] === 'available'): ?>
                <div class="contact-actions">
                    <!-- WhatsApp Click-to-Chat Button -->
                    <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-lg">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.592 2.654-.697c.969.57 1.961.872 2.806.872 3.182 0 5.768-2.587 5.768-5.766.001-3.187-2.575-5.754-5.768-5.754zm7.654 5.766c.002 4.223-3.437 7.662-7.66 7.662-1.258 0-2.496-.309-3.593-.896l-4.432 1.162 1.182-4.321c-.692-1.167-1.057-2.511-1.056-3.887.002-4.224 3.442-7.662 7.665-7.662 4.225 0 7.664 3.438 7.664 7.662z"/>
                        </svg>
                        Chat with Seller on WhatsApp
                    </a>

                    <!-- Direct Call Button -->
                    <a href="tel:<?= e($contactPhone) ?>" class="btn btn-primary btn-lg">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                        Call Seller (<?= e($contactPhone) ?>)
                    </a>

                    <!-- I'm Interested Button (Modal Trigger) -->
                    <button type="button" id="openInterestModalBtn" class="btn btn-accent btn-lg">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        I'm Interested / Send Message
                    </button>
                </div>
            <?php else: ?>
                <div style="background-color: var(--bg-muted); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; text-align: center;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">This item has been marked as SOLD</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">The seller has successfully closed this transaction.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Buyer Interest Form Modal -->
<div class="modal-overlay" id="interestModal" role="dialog" aria-modal="true" aria-labelledby="interestModalTitle">
    <div class="modal-card">
        <div class="modal-header">
            <h2 class="modal-title" id="interestModalTitle">Express Interest</h2>
            <button type="button" class="modal-close close-interest-modal" aria-label="Close modal">&times;</button>
        </div>

        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            No account required. Submit your contact details below and <?= e($product['seller_name']) ?> will be notified immediately.
        </p>

        <?php if ($interestError): ?>
            <div class="alert alert-error" style="margin-bottom: 1rem;">
                <div class="alert-content"><?= e($interestError) ?></div>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/product.php?id=<?= $productId ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="submit_interest">
            
            <!-- Honeypot anti-bot field -->
            <input type="text" name="website_hp" style="display:none !important;" tabindex="-1" autocomplete="off">

            <div class="form-group">
                <label for="buyer_name" class="form-label">Your Full Name <span class="required">*</span></label>
                <input type="text" id="buyer_name" name="buyer_name" class="form-control" placeholder="e.g. Kofi Boateng" required>
            </div>

            <div class="form-group">
                <label for="buyer_contact" class="form-label">Phone Number or Email <span class="required">*</span></label>
                <input type="text" id="buyer_contact" name="buyer_contact" class="form-control" placeholder="e.g. 0240000000 or student@ug.edu.gh" required>
                <div class="form-help">The seller will use this to reach you to agree on meeting details.</div>
            </div>

            <div class="form-group">
                <label for="buyer_message" class="form-label">Message / Question (Optional)</label>
                <textarea id="buyer_message" name="buyer_message" rows="3" class="form-control" placeholder="e.g. Is this still available? Can we meet around the main library at 4pm?"></textarea>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-accent btn-block">Send Message to Seller</button>
                <button type="button" class="btn btn-outline close-interest-modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

