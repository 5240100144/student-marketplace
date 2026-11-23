<?php
// CampusMarket Add Product Listing

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

require_seller();
$user = current_user();
$sellerId = $user['id'];

$errors = [];
$productName   = trim($_POST['product_name'] ?? '');
$category      = trim($_POST['category'] ?? '');
$price         = trim($_POST['price'] ?? '');
$conditionType = trim($_POST['condition_type'] ?? 'Second-hand');
$description   = trim($_POST['description'] ?? '');
$whatsappPhone = trim($_POST['whatsapp_phone'] ?? $user['phone']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Input validation
    if (empty($productName)) {
        $errors[] = "Product name is required.";
    } elseif (strlen($productName) < 3) {
        $errors[] = "Product name must be at least 3 characters.";
    }

    if (empty($category) || !array_key_exists($category, PRODUCT_CATEGORIES)) {
        $errors[] = "Please select a valid category.";
    }

    if ($price === '' || !is_numeric($price) || (float)$price <= 0) {
        $errors[] = "Please enter a valid positive price in Ghana Cedis (GH₵).";
    }

    if (!in_array($conditionType, PRODUCT_CONDITIONS)) {
        $errors[] = "Please select a valid product condition (New or Second-hand).";
    }

    if (empty($description)) {
        $errors[] = "Please enter a description for your listing.";
    }

    if (empty($whatsappPhone)) {
        $errors[] = "A phone number is required for buyer contact.";
    }

    // Process uploaded photos
    $uploadedImagePaths = [];
    if (!empty($_FILES['photos']['name'][0])) {
        $fileCount = count($_FILES['photos']['name']);
        if ($fileCount > MAX_IMAGES_PER_PRODUCT) {
            $errors[] = "You can upload a maximum of " . MAX_IMAGES_PER_PRODUCT . " photos per product.";
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);

            for ($i = 0; $i < $fileCount; $i++) {
                $tmpName = $_FILES['photos']['tmp_name'][$i];
                $origName = $_FILES['photos']['name'][$i];
                $error   = $_FILES['photos']['error'][$i];
                $size    = $_FILES['photos']['size'][$i];

                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($error !== UPLOAD_ERR_OK) {
                    $errors[] = "Upload failed for file '{$origName}'. Error code: {$error}.";
                    continue;
                }

                if ($size > MAX_FILE_SIZE) {
                    $errors[] = "File '{$origName}' exceeds the maximum allowed size of 5MB.";
                    continue;
                }

                // Verify genuine MIME type on the server (do not trust extension only)
                $mimeType = $finfo->file($tmpName);
                if (!array_key_exists($mimeType, ALLOWED_MIME_TYPES)) {
                    $errors[] = "File '{$origName}' is not an accepted image format (JPEG, PNG, WebP). Detected type: {$mimeType}.";
                    continue;
                }

                // Generate random safe server-side filename
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
                } else {
                    $errors[] = "Could not save uploaded image '{$origName}' to disk.";
                }
            }
        }
    }

    // Save product details and image paths to database
    if (empty($errors)) {
        try {
            db_begin_transaction();

            db_query("
                INSERT INTO products (seller_id, product_name, category, price, condition_type, description, whatsapp_phone, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'available', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ", [
                $sellerId,
                $productName,
                $category,
                (float)$price,
                $conditionType,
                $description,
                $whatsappPhone
            ]);
            $newProductId = db_last_insert_id();

            // Insert image records
            if (!empty($uploadedImagePaths)) {
                foreach ($uploadedImagePaths as $idx => $path) {
                    db_query("
                        INSERT INTO product_images (product_id, image_path, is_primary, created_at)
                        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                    ", [
                        $newProductId,
                        $path,
                        $idx === 0 ? 1 : 0
                    ]);
                }
            } else {
                // If no images uploaded, insert placeholder indicator
                db_query("
                    INSERT INTO product_images (product_id, image_path, is_primary, created_at)
                    VALUES (?, '', 1, CURRENT_TIMESTAMP)
                ", [$newProductId]);
            }

            db_commit();

            set_flash('success', "Your listing &ldquo;{$productName}&rdquo; has been published successfully and is now active in the marketplace!");
            redirect(BASE_URL . '/my-listings.php');
        } catch (Throwable $e) {
            db_rollback();
            $errors[] = "Failed to save product listing: " . $e->getMessage();
        }
    }
}

$pageTitle = "Post an Advertisement";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="form-card" style="max-width: 720px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
            <h1 class="form-title">Post a Product for Sale</h1>
            <a href="<?= BASE_URL ?>/my-listings.php" class="btn btn-outline btn-sm">Back to My Listings</a>
        </div>
        <p class="form-subtitle">Fill in the item specifications below. Once submitted, your listing will be immediately visible to campus buyers.</p>

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

        <form action="<?= BASE_URL ?>/add-product.php" method="POST" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>

            <!-- Product Title -->
            <div class="form-group">
                <label for="product_name" class="form-label">Product Name / Title <span class="required">*</span></label>
                <input type="text" id="product_name" name="product_name" class="form-control" value="<?= e($productName) ?>" placeholder="e.g. Stewart Calculus 8th Edition (Hardcover)" required>
                <div class="form-help">Include brand, model, edition, or edition year for clarity.</div>
            </div>

            <!-- Category and Condition -->
            <div class="form-row">
                <div class="form-group">
                    <label for="category" class="form-label">Category <span class="required">*</span></label>
                    <select id="category" name="category" class="form-control" required>
                        <option value="">-- Select Category --</option>
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
                    <input type="number" id="price" name="price" step="0.50" min="1" class="form-control" value="<?= e($price) ?>" placeholder="e.g. 150.00" required>
                    <div class="form-help">Enter amount in Cedis. Must be greater than 0.</div>
                </div>

                <div class="form-group">
                    <label for="whatsapp_phone" class="form-label">Contact / WhatsApp Number <span class="required">*</span></label>
                    <input type="tel" id="whatsapp_phone" name="whatsapp_phone" class="form-control" value="<?= e($whatsappPhone) ?>" placeholder="e.g. 0244123456" required>
                    <div class="form-help">Defaults to your seller profile phone number.</div>
                </div>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="description" class="form-label">Detailed Description <span class="required">*</span></label>
                <textarea id="description" name="description" rows="4" class="form-control" placeholder="Provide useful details such as item condition, size, defects (if any), warranty, reason for selling, or suggested campus pickup spots." required><?= e($description) ?></textarea>
            </div>

            <!-- 4.2 & 4.3 Photographs Upload with Live Preview & Removal -->
            <div class="form-group">
                <label class="form-label">Product Photographs (Up to 5 images)</label>
                
                <input type="file" id="productPhotosInput" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" style="display: none;">
                
                <div class="image-upload-dropzone" id="uploadDropzone">
                    <svg class="upload-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                    </svg>
                    <div style="font-weight: 600; color: var(--primary); margin-bottom: 0.25rem;">
                        Click here or drag &amp; drop photos to upload
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                        Supported formats: JPEG, PNG, WebP &bull; Max 5MB per image &bull; Up to 5 photos
                    </div>
                </div>

                <!-- Preview Grid with dynamic remove buttons -->
                <div class="preview-grid" id="imagePreviewGrid"></div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-accent btn-lg btn-block">
                    Publish Listing
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

