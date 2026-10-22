<?php
/**
 * admin/contact_inquiries.php
 * Website Contact Inquiries Management Module
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Contact Inquiries';
$current_page = 'contact_inquiries';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ─── POST HANDLERS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
        header('Location: contact_inquiries.php');
        exit();
    }

    $op = $_POST['op'] ?? '';

    if ($op === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'read';
        if (in_array($status, ['unread', 'read', 'in_progress', 'resolved'], true)) {
            $stmt = mysqli_prepare($conn, "UPDATE contact_inquiries SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $status, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            logActivity('inquiry_status_updated', 'contact_inquiries', $id, "Updated inquiry #{$id} status to {$status}");
            $_SESSION['flash_success'] = 'Inquiry status updated.';
        }
        header('Location: contact_inquiries.php');
        exit();
    }

    if ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM contact_inquiries WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        logActivity('inquiry_deleted', 'contact_inquiries', $id, "Deleted inquiry #{$id}");
        $_SESSION['flash_success'] = 'Inquiry deleted.';
        header('Location: contact_inquiries.php');
        exit();
    }
}

// Fetch inquiries
$status_filter = $_GET['status'] ?? '';
$where = "WHERE 1=1";
$params = [];
$types = '';
if ($status_filter && in_array($status_filter, ['unread', 'read', 'in_progress', 'resolved'], true)) {
    $where .= " AND status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$query = "SELECT * FROM contact_inquiries $where ORDER BY id DESC";
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$inquiries = [];
while ($r = mysqli_fetch_assoc($res)) {
    $inquiries[] = $r;
}
mysqli_stmt_close($stmt);

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Contact Inquiries</h1>
        <p class="page-header__desc">Review messages and inquiries submitted through the public website contact form.</p>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert--success"><div class="alert__content"><span><?php echo htmlspecialchars($success_msg); ?></span></div></div>
<?php endif; ?>

<!-- Status Filter Tabs -->
<div class="filter-card animate-in">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <span style="font-weight:600;font-size:0.85rem;color:var(--admin-text-muted);">Status:</span>
        <a href="contact_inquiries.php" class="btn-admin btn-admin--sm <?php echo empty($status_filter) ? 'btn-admin--primary' : 'btn-admin--ghost'; ?>">All</a>
        <a href="contact_inquiries.php?status=unread" class="btn-admin btn-admin--sm <?php echo $status_filter === 'unread' ? 'btn-admin--primary' : 'btn-admin--ghost'; ?>">Unread</a>
        <a href="contact_inquiries.php?status=read" class="btn-admin btn-admin--sm <?php echo $status_filter === 'read' ? 'btn-admin--primary' : 'btn-admin--ghost'; ?>">Read</a>
        <a href="contact_inquiries.php?status=in_progress" class="btn-admin btn-admin--sm <?php echo $status_filter === 'in_progress' ? 'btn-admin--primary' : 'btn-admin--ghost'; ?>">In Progress</a>
        <a href="contact_inquiries.php?status=resolved" class="btn-admin btn-admin--sm <?php echo $status_filter === 'resolved' ? 'btn-admin--primary' : 'btn-admin--ghost'; ?>">Resolved</a>
    </div>
</div>

<!-- Inquiries Panel -->
<div class="panel animate-in">
    <div class="panel__header">
        <h3 class="panel__title">Messages (<?php echo count($inquiries); ?>)</h3>
    </div>
    <?php if (!empty($inquiries)): ?>
        <div class="table-responsive">
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Sender Name</th>
                        <th>Contact</th>
                        <th>Topic</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inquiries as $inq): ?>
                    <tr>
                        <td style="font-size:0.85rem;color:var(--admin-text-muted);white-space:nowrap;">
                            <?php echo date('M j, Y', strtotime($inq['created_at'])); ?>
                        </td>
                        <td style="font-weight:700;color:var(--admin-heading);">
                            <?php echo htmlspecialchars($inq['name']); ?>
                        </td>
                        <td style="font-size:0.85rem;">
                            <div><a href="mailto:<?php echo htmlspecialchars($inq['email']); ?>" style="color:var(--admin-accent);"><?php echo htmlspecialchars($inq['email']); ?></a></div>
                            <?php if ($inq['phone']): ?><small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($inq['phone']); ?></small><?php endif; ?>
                        </td>
                        <td><span class="badge badge--info"><?php echo htmlspecialchars($inq['topic']); ?></span></td>
                        <td style="max-width:320px;">
                            <div style="font-size:0.85rem;color:var(--admin-text);line-height:1.4;">
                                <?php echo nl2br(htmlspecialchars($inq['message'])); ?>
                            </div>
                        </td>
                        <td>
                            <form method="POST" action="contact_inquiries.php" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="op" value="update_status">
                                <input type="hidden" name="id" value="<?php echo $inq['id']; ?>">
                                <select name="status" class="form-control" style="padding:3px 8px;font-size:0.8rem;width:auto;" onchange="this.form.submit()">
                                    <option value="unread" <?php echo $inq['status'] === 'unread' ? 'selected' : ''; ?>>Unread</option>
                                    <option value="read" <?php echo $inq['status'] === 'read' ? 'selected' : ''; ?>>Read</option>
                                    <option value="in_progress" <?php echo $inq['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo $inq['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                </select>
                            </form>
                        </td>
                        <td style="text-align:right;">
                            <form method="POST" action="contact_inquiries.php" onsubmit="return confirm('Delete this inquiry?');" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="op" value="delete">
                                <input type="hidden" name="id" value="<?php echo $inq['id']; ?>">
                                <button type="submit" class="action-btn action-btn--delete" style="background:none;border:none;cursor:pointer;" title="Delete">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state"><p>No inquiries found.</p></div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
