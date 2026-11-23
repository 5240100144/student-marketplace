<?php
// Legacy setup route - redirects to admin codes or homepage

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (is_admin()) {
    redirect(BASE_URL . '/admin-codes.php');
}

// Redirect public visitors safely to the homepage
redirect(BASE_URL . '/index.php');
exit;
