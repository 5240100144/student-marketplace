<?php
// CampusMarket Edit Product Listing

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

require_seller();
$user = current_user();
$sellerId = $user['id'];

$productId = (int)($_GET['id'] ?? ($_POST['product_id'] ?? 0));
if ($productId <= 0) {
    set_flash('error', 'Invalid product specified.');
    redirect(BASE_URL . '/my-listings.php');
}

// Ownership verification
$product = get_seller_product($productId, $sellerId);
if (!$product) {
    http_response_code(403);
    set_flash('error', 'Access Denied: You do not have permission to edit this listing.');
    redirect(BASE_URL . '/my-listings.php');
}

$errors = [];
$productName   = trim($_POST['product_name'] ?? $product['product_name']);
$category      = trim($_POST['category'] ?? $product['category']);
$price         = trim($_POST['price'] ?? $product['price']);
$conditionType = trim($_POST['condition_type'] ?? $product['condition_type']);
$description   = trim($_POST['description'] ?? $product['description']);
$whatsappPhone = trim($_POST['whatsapp_phone'] ?? ($product['whatsapp_phone'] ?: $user['phone']));
$status        = trim($_POST['status'] ?? $product['status']);

// Handle photo removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_image') {
    csrf_verify();
    $imageId = (int)($_POST['image_id'] ?? 0);
    $imgRecord = db_fetch_one("SELECT * FROM product_images WHERE id = ? AND product_id = ?", [$imageId, $productId]);
    if ($imgRecord) {
        if (!empty($imgRecord['image_path'])) {
            $fullPath = ROOT_PATH . '/' . ltrim($imgRecord['image_path'], '/');
            if (file_exists($fullPath) && is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
        db_query("DELETE FROM product_images WHERE id = ?", [$imageId]);
        set_flash('info', 'Image removed.');
    }
    redirect(BASE_URL . '/edit-product.php?id=' . $productId);
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'update_product')) {
    csrf_verify();

    if (empty($productName)) {
        $errors[] = "Product name is required.";
    }

    if (empty($category) || !array_key_exists($category, PRODUCT_CATEGORIES)) {
        $errors[] = "Please select a valid category.";
    }

    if ($price === '' || !is_numeric($price) || (float)$price <= 0) {
        $errors[] = "Please enter a valid positive price in Ghana Cedis (GH₵).";
    }

    if (!in_array($conditionType, PRODUCT_CONDITIONS)) {
        $errors[] = "Please select a valid condition.";
    }

    if (empty($description)) {
        $errors[] = "Description is required.";
    }

    if (empty($whatsappPhone)) {
        $errors[] = "Phone number is required for buyer contact.";
    }

    if (!in_array($status, ['available', 'sold'])) {
        $status = 'available';
    }

    // Process new uploaded photographs
    $uploadedImagePaths = [];
    if (!empty($_FILES['photos']['name'][0])) {
        $fileCount = count($_FILES['photos']['name']);
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        for ($i = 0; $i < $fileCount; $i++) {
            $tmpName = $_FILES['photos']['tmp_name'][$i];
            $origName = $_FILES['photos']['name'][$i];
            $error   = $_FILES['photos']['error'][$i];
            $size    = $_FILES['photos']['size'][$i];

            if ($error === UPLOAD_ERR_NO_FILE) continue;
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = "Failed uploading '{$origName}'. Error: {$error}";
                continue;
            }
            if ($size > MAX_FILE_SIZE) {
                $errors[] = "File '{$origName}' exceeds 5MB limit.";
                continue;
            }

            $mimeType = $finfo->file($tmpName);
            if (!array_key_exists($mimeType, ALLOWED_MIME_TYPES)) {
                $errors[] = "File '{$origName}' has invalid format. Only JPEG, PNG, and WebP are allowed.";
                continue;
            }

            $ext = ALLOWED_MIME_TYPES[$mimeType];
            $safeFilename = 'prod_' . bin2hex(random_bytes(12)) . '.' . $ext;
            $destination = UPLOAD_DIR . DIRECTORY_SEPARATOR . $safeFilename;

            if (!is_dir(UPLOAD_DIR)) {
                @mkdir(UPLOAD_DIR, 0755, true);
                @chmod(UPLOAD_DIR, 0755);
            }

            if (move_uploaded_file($tmpName, $destination)) {
                @chmod($destination, 0644); // Ensure readable by Apache web server on Linux hosting
                $uploadedImagePaths[] = 'uploads/products/' . $safeFilename;
            }
        }
    }

    if (empty($errors)) {
        try {
            db_begin_transaction();

            db_query("
                UPDATE products 
                SET product_name = ?, category = ?, price = ?, condition_type = ?, description = ?, whatsapp_phone = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND seller_id = ?
            ", [
                $productName,
                $category,
                (float)$price,
                $conditionType,
                $description,
                $whatsappPhone,
                $status,
                $productId,
                $sellerId
            ]);

            // Save new images
            if (!empty($uploadedImagePaths)) {
                // Delete empty placeholder rows if any
                db_query("DELETE FROM product_images WHERE product_id = ? AND image_path = ''", [$productId]);

                foreach ($uploadedImagePaths as $path) {
                    db_query("
                        INSERT INTO product_images (product_id, image_path, is_primary, created_at)
                        VALUES (?, ?, 0, CURRENT_TIMESTAMP)
                    ", [$productId, $path]);
                }
            }

            db_commit();

            set_flash('success', "Listing &ldquo;{$productName}&rdquo; updated successfully!");
            redirect(BASE_URL . '/my-listings.php');
        } catch (Throwable $e) {
            db_rollback();
            $errors[] = "Database error updating product: " . $e->getMessage();
        }
    }
}

// Fetch current images for this listing
$existingImages = db_fetch_all("SELECT * FROM product_images WHERE product_id = ? AND image_path != '' ORDER BY is_primary DESC, id ASC", [$productId]);

$pageTitle = "Edit Listing: " . $product['product_name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="form-card" style="max-width: 720px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
            <h1 class="form-title">Edit Listing</h1>
            <a href="<?= BASE_URL ?>/my-listings.php" class="btn btn-outline btn-sm">Back to My Listings</a>
        </div>
        <p class="form-subtitle">Update details or photos for your item &ldquo;<strong><?= e($product['product_name']) ?></strong>&rdquo;.</p>

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

        <!-- Manage Existing Images -->
        <?php if (!empty($existingImages)): ?>
            <div style="margin-bottom: 1.5rem; background: var(--bg-muted); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <label class="form-label">Current Photos (Click remove to delete):</label>
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <?php foreach ($existingImages as $img): ?>
                        <div style="position: relative; width: 80px; height: 80px; border-radius: var(--radius-sm); overflow: hidden; border: 1px solid var(--border-color);">
                            <img src="<?= e(get_product_image_url($img['image_path'])) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                            <form method="POST" style="position: absolute; top: 2px; right: 2px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_image">
                                <input type="hidden" name="product_id" value="<?= $productId ?>">
                                <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                <button type="submit" class="preview-remove" title="Delete this image" onclick="return confirm('Remove this photo?');">&times;</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/edit-product.php?id=<?= $productId ?>" method="POST" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_product">
            <input type="hidden" name="product_id" value="<?= $productId ?>">

            <!-- Product Title -->
            <div class="form-group">
                <label for="product_name" class="form-label">Product Name / Title <span class="required">*</span></label>
                <input type="text" id="product_name" name="product_name" class="form-control" value="<?= e($productName) ?>" required>
            </div>

            <!-- Category and Condition -->
            <div class="form-row">
                <div class="form-group">
                    <label for="category" class="form-label">Category <span class="required">*</span></label>
                    <select id="category" name="category" class="form-control" required>
                        <?php foreach (PRODUCT_CATEGORIES as $catName => $catDesc): ?>
                            <option value="<?= e($catName) ?>" <?= $category === $catName ? 'selected' : '' ?>>
                                <?= e($catName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="condition_type" class="form-label">Condition <span class="required">*</span></label>
                    <select id="condition_type" name="condition_type" class="form-control" required>
                        <option value="Second-hand" <?= $conditionType === 'Second-hand' ? 'selected' : '' ?>>Second-hand (Pre-owned)</option>
                        <option value="New" <?= $conditionType === 'New' ? 'selected' : '' ?>>New (Unopened / Brand New)</option>
                    </select>
                </div>
            </div>

            <!-- Price and WhatsApp Contact -->
            <div class="form-row">
                <div class="form-group">
                    <label for="price" class="form-label">Price in Ghana Cedis (GH₵) <span class="required">*</span></label>
                    <input type="number" id="price" name="price" step="0.50" min="1" class="form-control" value="<?= e($price) ?>" required>
                </div>

                <div class="form-group">
                    <label for="whatsapp_phone" class="form-label">Contact / WhatsApp Number <span class="required">*</span></label>
                    <input type="tel" id="whatsapp_phone" name="whatsapp_phone" class="form-control" value="<?= e($whatsappPhone) ?>" required>
                </div>
            </div>

            <!-- Status Setting -->
            <div class="form-group">
                <label for="status" class="form-label">Listing Availability Status <span class="required">*</span></label>
                <select id="status" name="status" class="form-control">
                    <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available (Publicly Visible)</option>
                    <option value="sold" <?= $status === 'sold' ? 'selected' : '' ?>>Sold (Hidden from public browse)</option>
                </select>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="description" class="form-label">Detailed Description <span class="required">*</span></label>
                <textarea id="description" name="description" rows="4" class="form-control" required><?= e($description) ?></textarea>
            </div>

            <!-- Upload Additional Photos -->
            <div class="form-group">
                <label class="form-label">Upload Additional Photos</label>
                <input type="file" id="productPhotosInput" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" style="display: none;">
                
                <div class="image-upload-dropzone" id="uploadDropzone">
                    <svg class="upload-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                    </svg>
                    <div style="font-weight: 600; color: var(--primary);">Add more photos</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">JPEG, PNG, WebP up to 5MB</div>
                </div>

                <div class="preview-grid" id="imagePreviewGrid"></div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-accent btn-lg btn-block">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

