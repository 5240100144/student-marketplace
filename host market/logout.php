<?php
// CampusMarket Logout

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();

set_flash('info', 'You have been logged out successfully.');
redirect(BASE_URL . '/index.php');

