<?php
// CampusMarket Notification Center

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

require_seller();
$currentUser = current_user();
$userId = $currentUser['id'];
$isAdmin = is_admin();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        if ($notifId > 0) {
            mark_notification_as_read($notifId, $userId);
            set_flash('success', 'Notification marked as read.');
        }
        redirect(BASE_URL . '/notifications.php');
    }

    if ($action === 'mark_all_read') {
        $count = mark_all_notifications_as_read($userId);
        set_flash('success', "Marked {$count} notification(s) as read.");
        redirect(BASE_URL . '/notifications.php');
    }

    if ($action === 'delete') {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        if ($notifId > 0) {
            delete_notification($notifId, $userId);
            set_flash('success', 'Notification removed.');
        }
        redirect(BASE_URL . '/notifications.php');
    }

    if ($action === 'clear_read') {
        $count = clear_read_notifications($userId);
        set_flash('success', "Cleared {$count} read notification(s).");
        redirect(BASE_URL . '/notifications.php');
    }

    // Admin Controls: Broadcast Notification
    if ($action === 'admin_broadcast' && $isAdmin) {
        $title = trim($_POST['broadcast_title'] ?? '');
        $message = trim($_POST['broadcast_message'] ?? '');
        $link = trim($_POST['broadcast_link'] ?? '');
        $targetRole = trim($_POST['target_role'] ?? 'all');

        if (empty($title) || empty($message)) {
            set_flash('error', 'Please provide both an announcement title and message.');
        } else {
            $sentCount = broadcast_notification($title, $message, $link, $targetRole);
            set_flash('success', "Broadcast announcement successfully delivered to {$sentCount} recipient(s).");
        }
        redirect(BASE_URL . '/notifications.php?tab=admin');
    }

    // Admin Controls: Purge Old History
    if ($action === 'admin_purge' && $isAdmin) {
        $days = max(7, (int)($_POST['purge_days'] ?? 30));
        $purgedCount = admin_purge_old_notifications($days);
        set_flash('warning', "Purged {$purgedCount} historical notification(s) older than {$days} days.");
        redirect(BASE_URL . '/notifications.php?tab=admin');
    }
}

// Active Filter
$filter = $_GET['filter'] ?? 'all';
$allowedFilters = ['all', 'unread', 'inquiry', 'product', 'broadcast', 'account'];
if (!in_array($filter, $allowedFilters)) {
    $filter = 'all';
}

$activeTab = $_GET['tab'] ?? 'inbox'; // 'inbox' or 'admin'
if ($activeTab === 'admin' && !$isAdmin) {
    $activeTab = 'inbox';
}

// Counts
$totalNotifs = (int)db_fetch_one("SELECT COUNT(*) as c FROM notifications WHERE user_id = ?", [$userId])['c'];
$unreadCount = get_unread_notifications_count($userId);
$inquiryCount = (int)db_fetch_one("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND type = 'inquiry'", [$userId])['c'];
$systemCount = (int)db_fetch_one("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND (type = 'broadcast' OR type = 'system')", [$userId])['c'];

// User Notifications
$notifications = get_user_notifications($userId, 50, 0, $filter);

// Admin Data (if admin tab)
$adminStats = $isAdmin ? admin_get_notification_stats() : [];
$adminHistory = ($isAdmin && $activeTab === 'admin') ? admin_get_notification_history(50) : [];

$pageTitle = "Notification Center";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width: 1040px; margin-top: 1.75rem; margin-bottom: 4rem;">

    <!-- Top Banner -->
    <div style="background: linear-gradient(135deg, #1e1b4b 0%, #2e1065 100%); color: #ffffff; padding: 2rem 2.25rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-md); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(234, 88, 12, 0.25); color: #fdba74; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                Campus Alerts &amp; Updates
            </div>
            <h1 style="font-size: 1.85rem; font-weight: 850; margin: 0 0 0.35rem 0; letter-spacing: -0.015em;">
                Notification Center
            </h1>
            <p style="color: #cbd5e1; margin: 0; font-size: 0.95rem;">
                Stay updated on buyer inquiries, listing changes, and campus marketplace announcements.
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <?php if ($unreadCount > 0): ?>
                <form method="POST" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-accent btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Mark All Read (<?= $unreadCount ?>)
                    </button>
                </form>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline btn-sm" style="color: #ffffff; border-color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.08);">
                Seller Dashboard
            </a>
        </div>
    </div>

    <!-- Mode Toggle (If Admin) -->
    <?php if ($isAdmin): ?>
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; background: var(--bg-muted); padding: 0.4rem; border-radius: var(--radius-lg); width: fit-content;">
            <a href="?tab=inbox" class="btn btn-sm <?= $activeTab === 'inbox' ? 'btn-primary' : 'btn-outline' ?>" style="border: none; font-weight: 700;">
                Personal Notifications (<?= $unreadCount ?> unread)
            </a>
            <a href="?tab=admin" class="btn btn-sm <?= $activeTab === 'admin' ? 'btn-primary' : 'btn-outline' ?>" style="border: none; font-weight: 700;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 0.25rem;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                Admin Control &amp; Broadcasts
            </a>
        </div>
    <?php endif; ?>

    <?php if ($activeTab === 'admin' && $isAdmin): ?>
        <!-- ======================================================== -->
        <!-- ADMIN CONTROLS VIEW -->
        <!-- ======================================================== -->

        <!-- Admin Notification Stats -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem; box-shadow: var(--shadow-sm);">
                <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Total Notifications Dispatched</div>
                <div style="font-size: 2rem; font-weight: 850; color: var(--primary); margin-top: 0.35rem;"><?= number_format($adminStats['total']) ?></div>
            </div>
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem; box-shadow: var(--shadow-sm); border-left: 4px solid var(--coral);">
                <div style="font-size: 0.8rem; font-weight: 700; color: var(--coral); text-transform: uppercase;">Pending Unread System-Wide</div>
                <div style="font-size: 2rem; font-weight: 850; color: var(--coral); margin-top: 0.35rem;"><?= number_format($adminStats['unread']) ?></div>
            </div>
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem; box-shadow: var(--shadow-sm); border-left: 4px solid #8b5cf6;">
                <div style="font-size: 0.8rem; font-weight: 700; color: #7c3aed; text-transform: uppercase;">System Broadcasts Sent</div>
                <div style="font-size: 2rem; font-weight: 850; color: #7c3aed; margin-top: 0.35rem;"><?= number_format($adminStats['broadcasts']) ?></div>
            </div>
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem; box-shadow: var(--shadow-sm); border-left: 4px solid #10b981;">
                <div style="font-size: 0.8rem; font-weight: 700; color: #059669; text-transform: uppercase;">Buyer Inquiries Logged</div>
                <div style="font-size: 2rem; font-weight: 850; color: #059669; margin-top: 0.35rem;"><?= number_format($adminStats['inquiries']) ?></div>
            </div>
        </div>

        <!-- Broadcast Tool Card -->
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-sm); margin-bottom: 2.5rem;">
            <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.5rem;">
                <div style="width: 38px; height: 38px; background: rgba(234, 88, 12, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--coral);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                </div>
                <div>
                    <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--purple-dark); margin: 0;">Broadcast Platform Announcement</h2>
                    <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">Send instant campus announcements or security notices directly to student sellers' notification centers.</p>
                </div>
            </div>

            <form method="POST" style="margin-top: 1.5rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_broadcast">

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" for="broadcast_title">Announcement Title <span class="required">*</span></label>
                    <input type="text" id="broadcast_title" name="broadcast_title" class="form-control" placeholder="e.g. Campus Safety: Safe Trade Zones Activated at Central Library" required>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" for="broadcast_message">Announcement Body <span class="required">*</span></label>
                    <textarea id="broadcast_message" name="broadcast_message" rows="3" class="form-control" placeholder="Write message details for sellers..." required></textarea>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="broadcast_link">Action Link (Optional)</label>
                        <input type="text" id="broadcast_link" name="broadcast_link" class="form-control" placeholder="e.g. /browse.php or https://...">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="target_role">Audience</label>
                        <select id="target_role" name="target_role" class="form-control">
                            <option value="all">All Registered Campus Sellers</option>
                            <option value="seller">Standard Sellers Only</option>
                            <option value="admin">Administrators Only</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-accent">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="display: inline; vertical-align: middle; margin-right: 0.25rem;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    Send Broadcast Now
                </button>
            </form>
        </div>

        <!-- System Notification Log History -->
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-xl); padding: 1.75rem; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--purple-dark); margin: 0;">System Notification History &amp; Delivery Log</h2>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">Audit log of recent system notifications delivered across the marketplace.</p>
                </div>
                <!-- Purge Form -->
                <form method="POST" onsubmit="return confirm('Purge notifications older than 30 days?');" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="admin_purge">
                    <input type="hidden" name="purge_days" value="30">
                    <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: rgba(239, 68, 68, 0.3);">
                        Purge History Older Than 30 Days
                    </button>
                </form>
            </div>

            <?php if (empty($adminHistory)): ?>
                <div style="text-align: center; padding: 2.5rem; color: var(--text-muted); font-size: 0.9rem;">
                    No notifications in platform log yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="custom-table" style="font-size: 0.885rem;">
                        <thead>
                            <tr>
                                <th>Recipient</th>
                                <th>Type</th>
                                <th>Notification Title</th>
                                <th>Status</th>
                                <th>Sent Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adminHistory as $nh): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary);"><?= e($nh['full_name'] ?: 'System Broadcast') ?></div>
                                        <div style="font-size: 0.775rem; color: var(--text-muted);"><?= e($nh['student_id'] ?: $nh['school_email']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: var(--bg-muted); color: var(--primary); font-size: 0.75rem; text-transform: uppercase;">
                                            <?= e($nh['type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--purple-dark);"><?= e($nh['title']) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-secondary); max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= e($nh['message']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($nh['is_read']): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; color: #059669; font-weight: 700; font-size: 0.775rem;">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                Read
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--coral); font-weight: 700; font-size: 0.775rem;">
                                                Unread
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                        <?= format_date($nh['created_at']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- ======================================================== -->
        <!-- USER NOTIFICATION INBOX VIEW -->
        <!-- ======================================================== -->

        <!-- Filter Navigation Tabs -->
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-xl); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?>" style="font-weight: 600;">
                    All (<?= $totalNotifs ?>)
                </a>
                <a href="?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline' ?>" style="font-weight: 600;">
                    Unread <?= $unreadCount > 0 ? "({$unreadCount})" : "" ?>
                </a>
                <a href="?filter=inquiry" class="btn btn-sm <?= $filter === 'inquiry' ? 'btn-primary' : 'btn-outline' ?>" style="font-weight: 600;">
                    Buyer Inquiries (<?= $inquiryCount ?>)
                </a>
                <a href="?filter=broadcast" class="btn btn-sm <?= $filter === 'broadcast' ? 'btn-primary' : 'btn-outline' ?>" style="font-weight: 600;">
                    Announcements (<?= $systemCount ?>)
                </a>
            </div>

            <?php if ($totalNotifs > 0): ?>
                <form method="POST" onsubmit="return confirm('Clear all read notifications from your history?');" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_read">
                    <button type="submit" class="btn btn-outline btn-sm" style="font-size: 0.8rem; color: var(--text-muted);">
                        Clear Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Notifications List -->
        <?php if (empty($notifications)): ?>
            <div class="empty-state" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-xl); padding: 3.5rem 2rem;">
                <div style="width: 64px; height: 64px; background: rgba(46, 16, 101, 0.08); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; color: var(--purple-dark);">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                </div>
                <h3 class="empty-title" style="font-size: 1.35rem; color: var(--purple-dark);">No notifications to display</h3>
                <p class="empty-desc">
                    <?= $filter === 'unread' ? "You're all caught up! No unread notifications right now." : "When buyers submit inquiries on your products or campus announcements are posted, they will appear here." ?>
                </p>
                <div style="display: flex; gap: 0.75rem; justify-content: center; margin-top: 1.5rem;">
                    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary btn-sm">Go to Dashboard</a>
                    <a href="<?= BASE_URL ?>/browse.php" class="btn btn-outline btn-sm">Browse Marketplace</a>
                </div>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.9rem;">
                <?php foreach ($notifications as $n): 
                    $isUnread = !(bool)$n['is_read'];
                    $type = $n['type'];
                    $iconColor = match($type) {
                        'inquiry'   => '#059669', // emerald
                        'product'   => '#ea580c', // coral
                        'broadcast' => '#7c3aed', // purple
                        'account'   => '#2563eb', // blue
                        default     => '#64748b'
                    };
                    $typeLabel = match($type) {
                        'inquiry'   => 'Buyer Inquiry',
                        'product'   => 'Listing Update',
                        'broadcast' => 'Campus Announcement',
                        'account'   => 'Account Notice',
                        default     => 'Notification'
                    };
                ?>
                    <div style="background: <?= $isUnread ? '#faf5ff' : '#ffffff' ?>; border: 1.5px solid <?= $isUnread ? 'var(--lavender-border)' : 'var(--border-color)' ?>; border-left: 4px solid <?= $isUnread ? 'var(--coral)' : $iconColor ?>; border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: flex-start; gap: 1.25rem; transition: transform 0.15s ease;">
                        
                        <div style="display: flex; gap: 1rem; align-items: flex-start; flex: 1;">
                            <!-- Type Icon -->
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: center; color: <?= $iconColor ?>; flex-shrink: 0; margin-top: 0.15rem;">
                                <?php if ($type === 'inquiry'): ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                <?php elseif ($type === 'product'): ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                <?php elseif ($type === 'broadcast'): ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                                <?php else: ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                <?php endif; ?>
                            </div>

                            <!-- Body -->
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.35rem;">
                                    <span style="font-size: 0.725rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: <?= $iconColor ?>;">
                                        <?= $typeLabel ?>
                                    </span>
                                    <span style="color: var(--text-muted); font-size: 0.75rem;">&bull;</span>
                                    <span style="font-size: 0.775rem; color: var(--text-muted);">
                                        <?= format_date($n['created_at']) ?>
                                    </span>
                                    <?php if ($isUnread): ?>
                                        <span style="background: var(--coral); color: #ffffff; font-size: 0.685rem; font-weight: 800; padding: 0.1rem 0.45rem; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.04em;">
                                            New
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div style="font-size: 1.05rem; font-weight: <?= $isUnread ? '800' : '700' ?>; color: var(--purple-dark); margin-bottom: 0.35rem;">
                                    <?= e($n['title']) ?>
                                </div>

                                <div style="color: var(--text-secondary); font-size: 0.925rem; line-height: 1.5; margin-bottom: 0.65rem;">
                                    <?= nl2br(e($n['message'])) ?>
                                </div>

                                <!-- Action Link if present -->
                                <?php if (!empty($n['link'])): ?>
                                    <div style="margin-top: 0.5rem;">
                                        <a href="<?= e($n['link']) ?>" class="btn btn-sm btn-outline" style="font-size: 0.825rem; font-weight: 700;">
                                            View Related Details &rarr;
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Card Action Buttons -->
                        <div style="display: flex; gap: 0.4rem; align-items: center;">
                            <?php if ($isUnread): ?>
                                <form method="POST" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" title="Mark as Read" style="padding: 0.35rem 0.65rem; font-size: 0.775rem;">
                                        Mark Read
                                    </button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this notification?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm" title="Delete" style="padding: 0.35rem 0.55rem; color: var(--text-muted); border-color: var(--border-color);">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
                            </form>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

