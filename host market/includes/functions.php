<?php
/**
 * Global Helper Functions & Utilities
 */

require_once __DIR__ . '/../config/config.php';

/**
 * HTML Escaper
 */
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format price in Ghana Cedis
 */
function format_price($amount) {
    return APP_CURRENCY . ' ' . number_format((float)$amount, 2);
}

/**
 * Format date nicely
 */
function format_date($datetime) {
    if (!$datetime) return 'N/A';
    $time = strtotime($datetime);
    if (!$time) return 'N/A';
    
    $diff = time() - $time;
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hrs = floor($diff / 3600);
        return $hrs . ' hour' . ($hrs > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    return date('M j, Y', $time);
}

/**
 * Set session flash message
 * Types: success, error, info, warning
 */
function set_flash($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash messages
 */
function get_flash() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Render flash messages as styled HTML alert banners
 */
function render_flash() {
    $messages = get_flash();
    if (empty($messages)) {
        return '';
    }
    $html = '<div class="flash-container">';
    foreach ($messages as $msg) {
        $type = e($msg['type']);
        $text = e($msg['message']);
        $icon = match($type) {
            'success' => '<svg width="20" height="20" fill="#7c3aed" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>',
            'error'   => '<svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>',
            'warning' => '<svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>',
            default   => '<svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'
        };
        $html .= '<div class="alert alert-' . $type . '" role="alert">';
        $html .= '<span class="alert-icon">' . $icon . '</span>';
        $html .= '<div class="alert-content">' . $text . '</div>';
        $html .= '<button type="button" class="alert-close" onclick="this.parentElement.remove();">&times;</button>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Safe HTTP Redirect
 */
function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
    } else {
        echo "<script>window.location.href = '" . addslashes($url) . "';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url) . "'></noscript>";
    }
    exit;
}

/**
 * Format phone number for WhatsApp Click-to-Chat (wa.me)
 * E.g., Ghana local 0244123456 -> 233244123456
 */
function normalize_phone_for_whatsapp($phone) {
    $digits = preg_replace('/[^\d]/', '', $phone);
    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        // Assume Ghana country code (+233)
        $digits = '233' . substr($digits, 1);
    }
    return $digits;
}

/**
 * Build WhatsApp click-to-chat URL with URL-encoded message
 */
function build_whatsapp_url($phone, $productName, $price) {
    $cleanPhone = normalize_phone_for_whatsapp($phone);
    $priceStr = format_price($price);
    $text = "Hello! I saw your listing for \"{$productName}\" ({$priceStr}) on the Student Marketplace and I am interested in purchasing it.";
    return "https://wa.me/" . $cleanPhone . "?text=" . rawurlencode($text);
}

/**
 * Log or send seller email notification for buyer interest
 */
function send_interest_notification($sellerEmail, $sellerName, $productName, $buyerName, $buyerContact, $buyerMessage) {
    $subject = "New buyer interest in your listing: " . $productName;
    $time = date('Y-m-d H:i:s');
    
    $body = "Hi " . $sellerName . ",\n\n"
          . "Great news! A student is interested in your listing on the Student Marketplace.\n\n"
          . "Product: " . $productName . "\n"
          . "Interested Buyer: " . $buyerName . "\n"
          . "Buyer Contact: " . $buyerContact . "\n"
          . "Message: " . ($buyerMessage ?: "(No additional message provided)") . "\n"
          . "Received At: " . $time . "\n\n"
          . "Please reach out to the buyer directly to discuss details and schedule a safe campus exchange.\n\n"
          . "-- " . APP_NAME . " Team";

    // Write to notifications log file
    $logEntry = "[" . $time . "] SELLER: " . $sellerEmail . " | PROD: " . $productName . " | BUYER: " . $buyerName . " (" . $buyerContact . ") | MSG: " . $buyerMessage . PHP_EOL;
    @file_put_contents(NOTIFICATION_LOG_FILE, $logEntry, FILE_APPEND);

    // If real email sending is enabled
    if (ENABLE_REAL_EMAIL && filter_var($sellerEmail, FILTER_VALIDATE_EMAIL)) {
        $headers = 'From: ' . NOTIFY_EMAIL_FROM . "\r\n" .
                   'Reply-To: ' . NOTIFY_EMAIL_FROM . "\r\n" .
                   'X-Mailer: PHP/' . phpversion();
        @mail($sellerEmail, $subject, $body, $headers);
        return 'sent';
    }

    return 'logged';
}

/**
 * Get primary image or placeholder for a product
 */
function get_product_image_url($imagePath) {
    if (!empty($imagePath)) {
        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }
        $cleanPath = str_replace('\\', '/', $imagePath);
        $fullPath = ROOT_PATH . '/' . ltrim($cleanPath, '/');
        if (file_exists($fullPath)) {
            if (!is_readable($fullPath)) {
                @chmod($fullPath, 0644);
            }
            return (defined('BASE_URL') ? BASE_URL : '') . '/' . ltrim($cleanPath, '/');
        }
    }
    return (defined('BASE_URL') ? BASE_URL : '') . '/assets/images/placeholder.svg';
}

/**
 * Check if a product belongs to a seller
 */
function get_seller_product($productId, $sellerId) {
    return db_fetch_one("SELECT * FROM products WHERE id = ? AND seller_id = ?", [(int)$productId, (int)$sellerId]);
}

// Notification Center Utilities

function create_notification($userId, $title, $message, $type = 'system', $link = null) {
    try {
        db_query("
            INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
        ", [
            (int)$userId,
            trim($type),
            trim($title),
            trim($message),
            $link ? trim($link) : null
        ]);
        return (int)db_last_insert_id();
    } catch (Throwable $e) {
        error_log('Failed to create notification: ' . $e->getMessage());
        return false;
    }
}

/**
 * Broadcast an announcement notification to all registered sellers/users
 *
 * @param string $title
 * @param string $message
 * @param string|null $link
 * @param string $role Target user role ('all', 'seller', 'admin')
 * @return int Number of notifications created
 */
function broadcast_notification($title, $message, $link = null, $role = 'all') {
    try {
        if ($role === 'all') {
            $users = db_fetch_all("SELECT id FROM users");
        } else {
            $users = db_fetch_all("SELECT id FROM users WHERE role = ?", [$role]);
        }

        $count = 0;
        foreach ($users as $u) {
            if (create_notification($u['id'], $title, $message, 'broadcast', $link)) {
                $count++;
            }
        }
        return $count;
    } catch (Throwable $e) {
        error_log('Failed to broadcast notification: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get count of unread notifications for a user
 *
 * @param int $userId
 * @return int
 */
function get_unread_notifications_count($userId) {
    if (!$userId) return 0;
    try {
        $row = db_fetch_one("
            SELECT COUNT(*) as cnt 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ", [(int)$userId]);
        return (int)($row['cnt'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Fetch notifications for a user with optional filter
 *
 * @param int $userId
 * @param int $limit
 * @param int $offset
 * @param string $filter 'all', 'unread', 'inquiry', 'product', 'system'
 * @return array
 */
function get_user_notifications($userId, $limit = 50, $offset = 0, $filter = 'all') {
    if (!$userId) return [];
    try {
        $where = ["user_id = ?"];
        $params = [(int)$userId];

        if ($filter === 'unread') {
            $where[] = "is_read = 0";
        } elseif (in_array($filter, ['inquiry', 'product', 'system', 'broadcast', 'account'])) {
            $where[] = "type = ?";
            $params[] = $filter;
        }

        $limit = max(1, min((int)$limit, 100));
        $offset = max(0, (int)$offset);

        $sql = "
            SELECT * FROM notifications
            WHERE " . implode(' AND ', $where) . "
            ORDER BY id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return db_fetch_all($sql, $params);
    } catch (Throwable $e) {
        error_log('Failed to fetch user notifications: ' . $e->getMessage());
        return [];
    }
}

/**
 * Mark a single notification as read
 *
 * @param int $notificationId
 * @param int $userId
 * @return bool
 */
function mark_notification_as_read($notificationId, $userId) {
    try {
        $stmt = getDB()->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = CURRENT_TIMESTAMP
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([(int)$notificationId, (int)$userId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Mark all notifications for a user as read
 *
 * @param int $userId
 * @return int Number of updated rows
 */
function mark_all_notifications_as_read($userId) {
    try {
        $stmt = getDB()->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = CURRENT_TIMESTAMP
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([(int)$userId]);
        return $stmt->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Delete a notification
 *
 * @param int $notificationId
 * @param int $userId
 * @return bool
 */
function delete_notification($notificationId, $userId) {
    try {
        $stmt = getDB()->prepare("
            DELETE FROM notifications
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([(int)$notificationId, (int)$userId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Clear all read notifications for a user
 *
 * @param int $userId
 * @return int Number of deleted notifications
 */
function clear_read_notifications($userId) {
    try {
        $stmt = getDB()->prepare("
            DELETE FROM notifications
            WHERE user_id = ? AND is_read = 1
        ");
        $stmt->execute([(int)$userId]);
        return $stmt->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Admin: Get global notification stats across the marketplace
 */
function admin_get_notification_stats() {
    try {
        $total = (int)db_fetch_one("SELECT COUNT(*) as c FROM notifications")['c'];
        $unread = (int)db_fetch_one("SELECT COUNT(*) as c FROM notifications WHERE is_read = 0")['c'];
        $broadcasts = (int)db_fetch_one("SELECT COUNT(*) as c FROM notifications WHERE type = 'broadcast'")['c'];
        $inquiries = (int)db_fetch_one("SELECT COUNT(*) as c FROM notifications WHERE type = 'inquiry'")['c'];
        return [
            'total'       => $total,
            'unread'      => $unread,
            'broadcasts'  => $broadcasts,
            'inquiries'   => $inquiries
        ];
    } catch (Throwable $e) {
        return ['total' => 0, 'unread' => 0, 'broadcasts' => 0, 'inquiries' => 0];
    }
}

/**
 * Admin: Get recent notifications log across all users
 */
function admin_get_notification_history($limit = 100, $filter = 'all') {
    try {
        $where = [];
        $params = [];
        if ($filter !== 'all') {
            $where[] = "n.type = ?";
            $params[] = $filter;
        }
        $whereSql = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";
        $limit = max(1, min((int)$limit, 200));

        return db_fetch_all("
            SELECT n.*, u.full_name, u.school_email, u.student_id
            FROM notifications n
            LEFT JOIN users u ON n.user_id = u.id
            {$whereSql}
            ORDER BY n.id DESC
            LIMIT {$limit}
        ", $params);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Admin: Purge old notifications older than specified days
 */
function admin_purge_old_notifications($days = 60) {
    try {
        $days = max(7, (int)$days);
        $stmt = getDB()->prepare("
            DELETE FROM notifications
            WHERE created_at < DATE('now', '-' || ? || ' days')
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    } catch (Throwable $e) {
        // Fallback for MySQL DATE_SUB
        try {
            $stmt = getDB()->prepare("
                DELETE FROM notifications
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            return $stmt->rowCount();
        } catch (Throwable $e2) {
            return 0;
        }
    }
}

