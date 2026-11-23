<?php
// Admin directory notifications redirect
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();
redirect(BASE_URL . '/notifications.php?tab=admin');

