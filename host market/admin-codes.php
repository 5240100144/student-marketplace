<?php
// CampusMarket Seller Access Code Management

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

require_admin();

$currentUser = current_user();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $count = max(1, min((int)($_POST['count'] ?? 1), 50));
        $recipientNote = trim($_POST['recipient_note'] ?? '');
        $markDistributed = !empty($_POST['mark_distributed']);

        $generated = batch_generate_codes($count, $recipientNote, $markDistributed, $currentUser['id']);
        $_SESSION['newly_generated_codes'] = $generated;

        set_flash('success', "Successfully generated {$count} unique seller access code(s). Copy them below for manual distribution.");
        redirect(BASE_URL . '/admin-codes.php');
    }

    if ($action === 'distribute') {
        $codeId = (int)($_POST['code_id'] ?? 0);
        $recipientNote = trim($_POST['recipient_note'] ?? '');

        if ($codeId > 0 && !empty($recipientNote)) {
            $success = distribute_access_code($codeId, $recipientNote, $currentUser['id']);
            if ($success) {
                set_flash('success', "Code successfully marked as distributed to {$recipientNote}.");
            } else {
                set_flash('error', "Unable to mark code as distributed (it may have already been redeemed or revoked).");
            }
        } else {
            set_flash('error', "Please provide recipient details or student ID when marking as distributed.");
        }
        redirect(BASE_URL . '/admin-codes.php');
    }

    if ($action === 'revoke') {
        $codeId = (int)($_POST['code_id'] ?? 0);
        $reason = trim($_POST['revoke_reason'] ?? 'Revoked by administrator');

        if ($codeId > 0) {
            $success = revoke_access_code($codeId, $reason, $currentUser['id']);
            if ($success) {
                set_flash('warning', "Access code has been revoked and cannot be redeemed.");
            } else {
                set_flash('error', "Unable to revoke code (already redeemed or not found).");
            }
        }
        redirect(BASE_URL . '/admin-codes.php');
    }

    if ($action === 'update_note') {
        $codeId = (int)($_POST['code_id'] ?? 0);
        $recipientNote = trim($_POST['recipient_note'] ?? '');
        if ($codeId > 0) {
            update_code_recipient_note($codeId, $recipientNote);
            set_flash('success', "Recipient note updated.");
        }
        redirect(BASE_URL . '/admin-codes.php');
    }

    if ($action === 'dismiss_new_codes') {
        unset($_SESSION['newly_generated_codes']);
        redirect(BASE_URL . '/admin-codes.php');
    }
}

// Fetch Metrics
$totalCodes       = (int)db_fetch_one("SELECT COUNT(*) as c FROM seller_access_codes")['c'];
$unusedCount      = (int)db_fetch_one("SELECT COUNT(*) as c FROM seller_access_codes WHERE status = 'unused'")['c'];
$distributedCount = (int)db_fetch_one("SELECT COUNT(*) as c FROM seller_access_codes WHERE status = 'distributed'")['c'];
$redeemedCount    = (int)db_fetch_one("SELECT COUNT(*) as c FROM seller_access_codes WHERE status = 'redeemed'")['c'];
$revokedCount     = (int)db_fetch_one("SELECT COUNT(*) as c FROM seller_access_codes WHERE status = 'revoked'")['c'];

// Filter & Search Parameters
$filter = $_GET['filter'] ?? 'all';
$allowedFilters = ['all', 'unused', 'distributed', 'redeemed', 'revoked'];
if (!in_array($filter, $allowedFilters)) {
    $filter = 'all';
}
$search = trim($_GET['q'] ?? '');

$whereClauses = [];
$params = [];

if ($filter !== 'all') {
    $whereClauses[] = "c.status = ?";
    $params[] = $filter;
}

if (!empty($search)) {
    $searchWild = '%' . $search . '%';
    $whereClauses[] = "(
        c.code_prefix LIKE ? 
        OR c.recipient_note LIKE ? 
        OR c.code_plain LIKE ?
        OR u.full_name LIKE ? 
        OR u.student_id LIKE ? 
        OR u.school_email LIKE ?
    )";
    $params = array_merge($params, [$searchWild, $searchWild, $searchWild, $searchWild, $searchWild, $searchWild]);
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Fetch codes with user and administrator details
$codes = db_fetch_all("
    SELECT c.*,
           u.full_name as redeemed_by_name,
           u.student_id as redeemed_by_student_id,
           u.school_email as redeemed_by_email,
           dist_u.full_name as distributed_by_name,
           rev_u.full_name as revoked_by_name
    FROM seller_access_codes c
    LEFT JOIN users u ON c.redeemed_by_user_id = u.id
    LEFT JOIN users dist_u ON c.distributed_by = dist_u.id
    LEFT JOIN users rev_u ON c.revoked_by = rev_u.id
    {$whereSql}
    ORDER BY c.id DESC
    LIMIT 200
", $params);

// Newly generated codes in flash session
$newlyGenerated = $_SESSION['newly_generated_codes'] ?? [];

$pageTitle = "Seller Access Codes";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width: 1200px; margin-top: 1.5rem; margin-bottom: 3.5rem;">
    <!-- Top Admin Header -->
    <div style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 60%, #2e1065 100%); color: #ffffff; padding: 2.25rem 2rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-lg); margin-bottom: 2rem; position: relative; overflow: hidden;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem; position: relative; z-index: 2;">
            <div>
                <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(234, 88, 12, 0.2); border: 1px solid rgba(234, 88, 12, 0.5); padding: 0.35rem 0.85rem; border-radius: var(--radius-full); font-size: 0.775rem; font-weight: 700; color: #fdba74; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    Access Control
                </div>
                <h1 style="font-size: 2rem; font-weight: 850; letter-spacing: -0.015em; margin-bottom: 0.35rem;">
                    Seller Access Codes
                </h1>
                <p style="color: #cbd5e1; font-size: 0.95rem; max-width: 680px; line-height: 1.5;">
                    Generate single-use authorization codes, track distribution to campus students, monitor redeemed accounts, and revoke codes when necessary.
                </p>
            </div>
            <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-accent" onclick="openGenerateModal()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Generate New Codes
                </button>
                <a href="<?= BASE_URL ?>/notifications.php?tab=admin" class="btn btn-outline" style="color: #ffffff; border-color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.08);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 0.25rem;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    Notification Center
                </a>
                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline" style="color: #ffffff; border-color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.08);">
                    Seller Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Newly Generated Codes Alert Card -->
    <?php if (!empty($newlyGenerated)): ?>
        <div style="background: #ffffff; border: 2px solid var(--coral); border-radius: var(--radius-xl); padding: 1.85rem; margin-bottom: 2rem; box-shadow: 0 10px 25px -5px rgba(234, 88, 12, 0.25);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--coral); font-weight: 800; font-size: 1.2rem; margin-bottom: 0.25rem;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span><?= count($newlyGenerated) ?> Newly Generated Activation Code(s) Ready for Distribution</span>
                    </div>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0;">
                        Copy these codes now for manual distribution to verified students. For database integrity, hashed values are stored permanently.
                    </p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="copyAllNewCodes()">
                        Copy All Codes
                    </button>
                    <form method="POST" style="display: inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="dismiss_new_codes">
                        <button type="submit" class="btn btn-outline btn-sm" style="color: var(--text-muted);">
                            Dismiss Banner
                        </button>
                    </form>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 0.85rem;">
                <?php foreach ($newlyGenerated as $nc): ?>
                    <div style="background: var(--bg-muted); border: 1.5px solid var(--lavender-border); border-radius: var(--radius-md); padding: 0.85rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                        <div>
                            <div style="font-family: monospace; font-size: 1.1rem; font-weight: 800; color: var(--primary); letter-spacing: 0.05em;" id="code-text-<?= $nc['id'] ?>">
                                <?= e($nc['code']) ?>
                            </div>
                            <?php if (!empty($nc['recipient_note'])): ?>
                                <div style="font-size: 0.775rem; color: var(--text-muted); margin-top: 0.2rem;">
                                    For: <?= e($nc['recipient_note']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-accent btn-sm" style="padding: 0.35rem 0.85rem; font-size: 0.8rem; font-weight: 700;" onclick="copyCode('<?= e($nc['code']) ?>', this)">
                            Copy Code
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Summary Metrics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <!-- Total Codes -->
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.35rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="color: var(--text-muted); font-size: 0.825rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                Total Codes Generated
            </div>
            <div style="font-size: 2.1rem; font-weight: 850; color: var(--primary); line-height: 1;">
                <?= number_format($totalCodes) ?>
            </div>
        </div>

        <!-- Unused Codes -->
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.35rem 1.5rem; box-shadow: var(--shadow-sm); border-left: 4px solid #10b981;">
            <div style="color: #059669; font-size: 0.825rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                Available in Pool
            </div>
            <div style="font-size: 2.1rem; font-weight: 850; color: #10b981; line-height: 1;">
                <?= number_format($unusedCount) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Ready to be assigned</div>
        </div>

        <!-- Distributed Codes -->
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.35rem 1.5rem; box-shadow: var(--shadow-sm); border-left: 4px solid #3b82f6;">
            <div style="color: #2563eb; font-size: 0.825rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                Distributed Pending
            </div>
            <div style="font-size: 2.1rem; font-weight: 850; color: #3b82f6; line-height: 1;">
                <?= number_format($distributedCount) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Given to student, unredeemed</div>
        </div>

        <!-- Redeemed Codes -->
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.35rem 1.5rem; box-shadow: var(--shadow-sm); border-left: 4px solid #8b5cf6;">
            <div style="color: #7c3aed; font-size: 0.825rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                Redeemed &amp; Active
            </div>
            <div style="font-size: 2.1rem; font-weight: 850; color: #8b5cf6; line-height: 1;">
                <?= number_format($redeemedCount) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Seller accounts created</div>
        </div>

        <!-- Revoked Codes -->
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.35rem 1.5rem; box-shadow: var(--shadow-sm); border-left: 4px solid #ef4444;">
            <div style="color: #dc2626; font-size: 0.825rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                Revoked / Voided
            </div>
            <div style="font-size: 2.1rem; font-weight: 850; color: #ef4444; line-height: 1;">
                <?= number_format($revokedCount) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Blocked from registration</div>
        </div>
    </div>

    <!-- Search, Filter Tabs & Action Bar -->
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-sm); margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
            <!-- Filter Tabs -->
            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                <a href="?filter=all&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?>" style="font-weight: 600;">
                    All (<?= $totalCodes ?>)
                </a>
                <a href="?filter=unused&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter === 'unused' ? 'btn-primary' : 'btn-outline' ?>" style="font-weight: 600;">
                    Unused (<?= $unusedCount ?>)
                </a>
                <a href="?filter=distributed&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter === 'distributed' ? 'btn-primary' : 'btn-outline' ?>" style="font-weight: 600;">
                    Distributed (<?= $distributedCount ?>)
                </a>
                <a href="?filter=redeemed&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter === 'redeemed' ? 'btn-primary' : 'btn-outline' ?>" style="font-weight: 600;">
                    Redeemed (<?= $redeemedCount ?>)
                </a>
                <a href="?filter=revoked&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter === 'revoked' ? 'btn-primary' : 'btn-outline' ?>" style="font-weight: 600;">
                    Revoked (<?= $revokedCount ?>)
                </a>
            </div>

            <!-- Search Input Form -->
            <form action="admin-codes.php" method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="hidden" name="filter" value="<?= e($filter) ?>">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search code, student, note..." class="form-control" style="width: 240px; padding: 0.45rem 0.85rem; font-size: 0.875rem;">
                <button type="submit" class="btn btn-outline btn-sm">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="?filter=<?= e($filter) ?>" class="btn btn-outline btn-sm" style="color: var(--text-muted);">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Codes Table -->
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); text-align: left; background: var(--bg-muted);">
                        <th style="padding: 0.85rem 1rem;">ID</th>
                        <th style="padding: 0.85rem 1rem;">Access Code</th>
                        <th style="padding: 0.85rem 1rem;">Status</th>
                        <th style="padding: 0.85rem 1rem;">Recipient / Allocation Note</th>
                        <th style="padding: 0.85rem 1rem;">Redeemed / Revoked By</th>
                        <th style="padding: 0.85rem 1rem;">Timeline</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($codes)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                                No access codes found matching your criteria.
                                <br><br>
                                <button type="button" class="btn btn-accent btn-sm" onclick="openGenerateModal()">Generate Access Codes</button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($codes as $c): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.85rem 1rem; color: var(--text-muted); font-size: 0.8rem;">
                                    #<?= $c['id'] ?>
                                </td>

                                <td style="padding: 0.85rem 1rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <code style="font-family: monospace; font-size: 0.95rem; font-weight: 800; color: var(--primary);">
                                            <?= e(!empty($c['code_plain']) ? $c['code_plain'] : ($c['code_prefix'] ?? mask_access_code($c['code_hash']))) ?>
                                        </code>
                                        <?php if (!empty($c['code_plain'])): ?>
                                            <button type="button" class="btn btn-outline btn-sm" style="padding: 0.15rem 0.5rem; font-size: 0.7rem; min-height: 24px;" onclick="copyCode('<?= e($c['code_plain']) ?>', this)">
                                                Copy
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.725rem; color: var(--text-muted); font-family: monospace; margin-top: 0.2rem;">
                                        Hash: <?= substr($c['code_hash'], 0, 16) ?>...
                                    </div>
                                </td>

                                <td style="padding: 0.85rem 1rem;">
                                    <?php if ($c['status'] === 'unused'): ?>
                                        <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #047857; border: 1px solid rgba(16, 185, 129, 0.3);">
                                            Unused
                                        </span>
                                    <?php elseif ($c['status'] === 'distributed'): ?>
                                        <span class="badge" style="background: rgba(59, 130, 246, 0.12); color: #1d4ed8; border: 1px solid rgba(59, 130, 246, 0.3);">
                                            Distributed
                                        </span>
                                    <?php elseif ($c['status'] === 'redeemed'): ?>
                                        <span class="badge" style="background: rgba(139, 92, 246, 0.12); color: #6d28d9; border: 1px solid rgba(139, 92, 246, 0.3);">
                                            Redeemed
                                        </span>
                                    <?php elseif ($c['status'] === 'revoked'): ?>
                                        <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #b91c1c; border: 1px solid rgba(239, 68, 68, 0.3);">
                                            Revoked
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 0.85rem 1rem;">
                                    <?php if (!empty($c['recipient_note'])): ?>
                                        <div style="font-weight: 600; color: var(--text-primary);"><?= e($c['recipient_note']) ?></div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-style: italic;">Unassigned</span>
                                    <?php endif; ?>

                                    <?php if ($c['status'] === 'unused' || $c['status'] === 'distributed'): ?>
                                        <button type="button" onclick="openNoteModal(<?= $c['id'] ?>, '<?= e(addslashes($c['recipient_note'] ?? '')) ?>')" style="background: none; border: none; color: var(--primary); font-size: 0.75rem; text-decoration: underline; cursor: pointer; padding: 0; margin-top: 0.25rem;">
                                            <?= empty($c['recipient_note']) ? 'Assign Student' : 'Edit Note' ?>
                                        </button>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 0.85rem 1rem;">
                                    <?php if ($c['status'] === 'redeemed' && !empty($c['redeemed_by_name'])): ?>
                                        <div style="font-weight: 700; color: var(--primary);">
                                            <?= e($c['redeemed_by_name']) ?>
                                        </div>
                                        <div style="font-size: 0.775rem; color: var(--text-muted);">
                                            ID: <strong><?= e($c['redeemed_by_student_id']) ?></strong> &bull; <?= e($c['redeemed_by_email']) ?>
                                        </div>
                                    <?php elseif ($c['status'] === 'revoked'): ?>
                                        <div style="color: var(--danger); font-size: 0.8rem; font-weight: 600;">
                                            Reason: <?= e($c['revoked_reason'] ?: 'None specified') ?>
                                        </div>
                                        <?php if (!empty($c['revoked_by_name'])): ?>
                                            <div style="font-size: 0.725rem; color: var(--text-muted);">By: <?= e($c['revoked_by_name']) ?></div>
                                        <?php endif; ?>
                                    <?php elseif ($c['status'] === 'distributed' && !empty($c['distributed_by_name'])): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                            Distributed by <?= e($c['distributed_by_name']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">&mdash;</span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 0.85rem 1rem; font-size: 0.8rem; color: var(--text-secondary);">
                                    <div>Created: <?= date('M j, Y H:i', strtotime($c['created_at'])) ?></div>
                                    <?php if (!empty($c['distributed_at'])): ?>
                                        <div style="color: #2563eb;">Distributed: <?= date('M j, H:i', strtotime($c['distributed_at'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($c['redeemed_at'])): ?>
                                        <div style="color: #7c3aed;">Redeemed: <?= date('M j, H:i', strtotime($c['redeemed_at'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($c['revoked_at'])): ?>
                                        <div style="color: #dc2626;">Revoked: <?= date('M j, H:i', strtotime($c['revoked_at'])) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 0.85rem 1rem; text-align: right; white-space: nowrap;">
                                    <?php if ($c['status'] === 'unused'): ?>
                                        <button type="button" class="btn btn-outline btn-sm" onclick="openDistributeModal(<?= $c['id'] ?>, '<?= e(addslashes($c['code_plain'] ?? '')) ?>')" style="padding: 0.3rem 0.65rem; font-size: 0.775rem;">
                                            Distribute
                                        </button>
                                        <button type="button" class="btn btn-sm" style="padding: 0.3rem 0.65rem; font-size: 0.775rem; background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3);" onclick="openRevokeModal(<?= $c['id'] ?>)">
                                            Revoke
                                        </button>
                                    <?php elseif ($c['status'] === 'distributed'): ?>
                                        <button type="button" class="btn btn-sm" style="padding: 0.3rem 0.65rem; font-size: 0.775rem; background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3);" onclick="openRevokeModal(<?= $c['id'] ?>)">
                                            Revoke
                                        </button>
                                    <?php elseif ($c['status'] === 'redeemed'): ?>
                                        <span style="font-size: 0.775rem; color: #7c3aed; font-weight: 700;">Account Activated</span>
                                    <?php elseif ($c['status'] === 'revoked'): ?>
                                        <span style="font-size: 0.775rem; color: var(--text-muted); font-weight: 600;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==========================================
     MODALS
     ========================================== -->

<!-- Generate Codes Modal -->
<div id="generateModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem;">
    <div style="background: #ffffff; border-radius: var(--radius-xl); max-width: 500px; width: 100%; padding: 2rem; box-shadow: var(--shadow-xl); position: relative;">
        <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">
            Generate Unique Seller Activation Codes
        </h2>
        <p style="color: var(--text-muted); font-size: 0.875rem; line-height: 1.5; margin-bottom: 1.5rem;">
            Generated codes use cryptographically secure entropy and are stored hashed with SHA-256 in the database.
        </p>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="generate">

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label">Number of Codes to Generate</label>
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('codeCountInput').value = 1">1 Code</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('codeCountInput').value = 5">5 Codes</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('codeCountInput').value = 10">10 Codes</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('codeCountInput').value = 25">25 Codes</button>
                </div>
                <input type="number" id="codeCountInput" name="count" value="5" min="1" max="50" class="form-control" required>
                <div class="form-help">Enter any amount from 1 to 50 codes per batch.</div>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="gen_recipient_note" class="form-label">Optional Allocation Note / Recipient</label>
                <input type="text" id="gen_recipient_note" name="recipient_note" class="form-control" placeholder="e.g. Student Union Batch or Kwame Mensah (10982341)">
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; cursor: pointer;">
                    <input type="checkbox" name="mark_distributed" value="1">
                    <span>Mark as <strong>Distributed</strong> immediately (if handing out now)</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-outline" onclick="closeModal('generateModal')">Cancel</button>
                <button type="submit" class="btn btn-accent">Generate Codes</button>
            </div>
        </form>
    </div>
</div>

<!-- Distribute Modal -->
<div id="distributeModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem;">
    <div style="background: #ffffff; border-radius: var(--radius-xl); max-width: 480px; width: 100%; padding: 2rem; box-shadow: var(--shadow-xl);">
        <h2 style="font-size: 1.3rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">
            Mark Code as Distributed
        </h2>
        <p style="color: var(--text-muted); font-size: 0.875rem; line-height: 1.5; margin-bottom: 1.5rem;">
            Record the student or department to whom you are issuing this code.
        </p>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="distribute">
            <input type="hidden" id="distributeCodeId" name="code_id" value="">

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="dist_recipient_note" class="form-label">Recipient Student Details <span class="required">*</span></label>
                <input type="text" id="dist_recipient_note" name="recipient_note" class="form-control" placeholder="e.g. Ama Serwaa (ID: 10945821, WhatsApp: 0501987654)" required>
                <div class="form-help">Enter student name, student ID, department, or contact number.</div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-outline" onclick="closeModal('distributeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save &amp; Mark Distributed</button>
            </div>
        </form>
    </div>
</div>

<!-- Revoke Modal -->
<div id="revokeModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem;">
    <div style="background: #ffffff; border-radius: var(--radius-xl); max-width: 480px; width: 100%; padding: 2rem; box-shadow: var(--shadow-xl);">
        <h2 style="font-size: 1.3rem; font-weight: 800; color: #dc2626; margin-bottom: 0.5rem;">
            Revoke Access Code
        </h2>
        <p style="color: var(--text-muted); font-size: 0.875rem; line-height: 1.5; margin-bottom: 1.5rem;">
            Revoking immediately cancels this code. It cannot be used for registration, and plaintext references are purged.
        </p>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="revoke">
            <input type="hidden" id="revokeCodeId" name="code_id" value="">

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="revoke_reason" class="form-label">Reason for Revocation</label>
                <input type="text" id="revoke_reason" name="revoke_reason" class="form-control" placeholder="e.g. Code compromised, misallocated, or cancelled" required>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-outline" onclick="closeModal('revokeModal')">Keep Code</button>
                <button type="submit" class="btn btn-accent" style="background: #dc2626; border-color: #dc2626;">Confirm Revocation</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Note Modal -->
<div id="noteModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem;">
    <div style="background: #ffffff; border-radius: var(--radius-xl); max-width: 480px; width: 100%; padding: 2rem; box-shadow: var(--shadow-xl);">
        <h2 style="font-size: 1.3rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">
            Edit Allocation Note
        </h2>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_note">
            <input type="hidden" id="editNoteCodeId" name="code_id" value="">

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="edit_recipient_note" class="form-label">Recipient / Allocation Note</label>
                <input type="text" id="edit_recipient_note" name="recipient_note" class="form-control" placeholder="e.g. John Doe (10982341)">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-outline" onclick="closeModal('noteModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Note</button>
            </div>
        </form>
    </div>
</div>

<script>
function copyCode(text, btnElement) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        const originalText = btnElement.innerHTML;
        btnElement.innerHTML = 'Copied';
        btnElement.style.background = '#10b981';
        btnElement.style.color = '#ffffff';
        setTimeout(() => {
            btnElement.innerHTML = originalText;
            btnElement.style.background = '';
            btnElement.style.color = '';
        }, 1800);
    }).catch(err => {
        prompt('Copy code:', text);
    });
}

function copyAllNewCodes() {
    const codes = <?= json_encode(array_column($newlyGenerated, 'code')) ?>;
    if (!codes || !codes.length) return;
    const text = codes.join("\n");
    navigator.clipboard.writeText(text).then(() => {
        alert('Copied all ' + codes.length + ' codes to clipboard!');
    }).catch(err => {
        prompt('Copy all codes:', text);
    });
}

function openGenerateModal() {
    document.getElementById('generateModal').style.display = 'flex';
}

function openDistributeModal(id, code) {
    document.getElementById('distributeCodeId').value = id;
    document.getElementById('distributeModal').style.display = 'flex';
}

function openRevokeModal(id) {
    document.getElementById('revokeCodeId').value = id;
    document.getElementById('revokeModal').style.display = 'flex';
}

function openNoteModal(id, currentNote) {
    document.getElementById('editNoteCodeId').value = id;
    document.getElementById('edit_recipient_note').value = currentNote || '';
    document.getElementById('noteModal').style.display = 'flex';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

// Close modals when clicking backdrop
window.addEventListener('click', (e) => {
    ['generateModal', 'distributeModal', 'revokeModal', 'noteModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (modal && e.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

