-- Student Marketplace Database Schema (SQLite)

PRAGMA foreign_keys = ON;

-- users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    student_id TEXT NOT NULL UNIQUE,
    school_email TEXT NOT NULL UNIQUE,
    phone TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    seller_status TEXT NOT NULL DEFAULT 'active',
    role TEXT NOT NULL DEFAULT 'seller',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_student_id ON users (student_id);
CREATE INDEX IF NOT EXISTS idx_users_school_email ON users (school_email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users (role);

-- seller_access_codes table
CREATE TABLE IF NOT EXISTS seller_access_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code_hash TEXT NOT NULL UNIQUE,
    code_plain TEXT NULL,
    code_prefix TEXT NULL,
    status TEXT NOT NULL DEFAULT 'unused',
    recipient_note TEXT NULL,
    distributed_at DATETIME NULL,
    distributed_by INTEGER NULL,
    revoked_at DATETIME NULL,
    revoked_by INTEGER NULL,
    revoked_reason TEXT NULL,
    redeemed_by_user_id INTEGER NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    redeemed_at DATETIME NULL,
    FOREIGN KEY (redeemed_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    FOREIGN KEY (distributed_by) REFERENCES users (id) ON DELETE SET NULL,
    FOREIGN KEY (revoked_by) REFERENCES users (id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_codes_hash ON seller_access_codes (code_hash);
CREATE INDEX IF NOT EXISTS idx_codes_status ON seller_access_codes (status);

-- products table
CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    seller_id INTEGER NOT NULL,
    product_name TEXT NOT NULL,
    category TEXT NOT NULL,
    price REAL NOT NULL,
    condition_type TEXT NOT NULL DEFAULT 'Second-hand',
    description TEXT NOT NULL,
    whatsapp_phone TEXT NULL,
    status TEXT NOT NULL DEFAULT 'available',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_products_seller ON products (seller_id);
CREATE INDEX IF NOT EXISTS idx_products_category ON products (category);
CREATE INDEX IF NOT EXISTS idx_products_status ON products (status);
CREATE INDEX IF NOT EXISTS idx_products_price ON products (price);

-- product_images table
CREATE TABLE IF NOT EXISTS product_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    image_path TEXT NOT NULL,
    is_primary INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_images_product ON product_images (product_id);

-- buyer_interest table
CREATE TABLE IF NOT EXISTS buyer_interest (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    seller_id INTEGER NOT NULL,
    buyer_name TEXT NOT NULL,
    buyer_contact TEXT NOT NULL,
    buyer_message TEXT NULL,
    notification_status TEXT NOT NULL DEFAULT 'logged',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_interest_seller ON buyer_interest (seller_id);
CREATE INDEX IF NOT EXISTS idx_interest_product ON buyer_interest (product_id);

-- notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    type TEXT NOT NULL DEFAULT 'system',
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    link TEXT NULL,
    is_read INTEGER NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications (user_id);
CREATE INDEX IF NOT EXISTS idx_notif_read ON notifications (is_read);
CREATE INDEX IF NOT EXISTS idx_notif_type ON notifications (type);
CREATE INDEX IF NOT EXISTS idx_notif_created ON notifications (created_at);
