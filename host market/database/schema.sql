-- Student Marketplace Database Schema (MySQL)

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `student_id` VARCHAR(50) NOT NULL UNIQUE,
    `school_email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(30) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `seller_status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `role` VARCHAR(20) NOT NULL DEFAULT 'seller',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_users_student_id` (`student_id`),
    INDEX `idx_users_school_email` (`school_email`),
    INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- seller_access_codes table
CREATE TABLE IF NOT EXISTS `seller_access_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code_hash` VARCHAR(255) NOT NULL UNIQUE,
    `code_plain` VARCHAR(50) NULL,
    `code_prefix` VARCHAR(50) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'unused',
    `recipient_note` TEXT NULL,
    `distributed_at` DATETIME NULL,
    `distributed_by` INT NULL,
    `revoked_at` DATETIME NULL,
    `revoked_by` INT NULL,
    `revoked_reason` TEXT NULL,
    `redeemed_by_user_id` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `redeemed_at` DATETIME NULL,
    INDEX `idx_codes_hash` (`code_hash`),
    INDEX `idx_codes_status` (`status`),
    CONSTRAINT `fk_codes_user` FOREIGN KEY (`redeemed_by_user_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_codes_dist_user` FOREIGN KEY (`distributed_by`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_codes_rev_user` FOREIGN KEY (`revoked_by`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- products table
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `seller_id` INT NOT NULL,
    `product_name` VARCHAR(200) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    `condition_type` VARCHAR(20) NOT NULL DEFAULT 'Second-hand',
    `description` TEXT NOT NULL,
    `whatsapp_phone` VARCHAR(30) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'available',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_products_seller` (`seller_id`),
    INDEX `idx_products_category` (`category`),
    INDEX `idx_products_status` (`status`),
    INDEX `idx_products_price` (`price`),
    CONSTRAINT `fk_products_seller` FOREIGN KEY (`seller_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- product_images table
CREATE TABLE IF NOT EXISTS `product_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_images_product` (`product_id`),
    CONSTRAINT `fk_images_product` FOREIGN KEY (`product_id`) 
        REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- buyer_interest table
CREATE TABLE IF NOT EXISTS `buyer_interest` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `seller_id` INT NOT NULL,
    `buyer_name` VARCHAR(150) NOT NULL,
    `buyer_contact` VARCHAR(150) NOT NULL,
    `buyer_message` TEXT NULL,
    `notification_status` VARCHAR(20) NOT NULL DEFAULT 'logged',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_interest_seller` (`seller_id`),
    INDEX `idx_interest_product` (`product_id`),
    CONSTRAINT `fk_interest_product` FOREIGN KEY (`product_id`) 
        REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_interest_seller` FOREIGN KEY (`seller_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'system',
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notif_user` (`user_id`),
    INDEX `idx_notif_read` (`is_read`),
    INDEX `idx_notif_type` (`type`),
    INDEX `idx_notif_created` (`created_at`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
