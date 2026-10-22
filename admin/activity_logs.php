<?php
/**
 * admin/activity_logs.php
 * Administrative & Financial Audit Logs
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Security & Activity Logs';
$current_page = 'activity_logs';

$action_filter = trim($_GET['action_filter'] ?? '');
$role_filter   = trim($_GET['role_filter'] ?? '');

$where = "WHERE 1=1";
$params = [];
$types = '';

if ($action_filter !== '') {
    $where .= " AND action LIKE ?";
    $params[] = "%{$action_filter}%";
    $types .= 's';
}
if ($role_filter !== '') {
    $where .= " AND user_role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

$count_sql = "SELECT COUNT(*) AS total FROM activity_logs $where";
$stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$total_logs = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0;
mysqli_stmt_close($stmt);

// Fetch latest 100 logs
$query = "SELECT * FROM activity_logs $where ORDER BY id DESC LIMIT 100";
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$logs = [];
while ($r = mysqli_fetch_assoc($res)) {
    $logs[] = $r;
}
mysqli_stmt_close($stmt);

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Audit Trail & Activity Logs</h1>
        <p class="page-header__desc">Review chronological security, academic, and financial transactions recorded by the system.</p>
    </div>
</div>

<!-- Filters -->
<div class="filter-card animate-in">
    <form method="GET" action="activity_logs.php" class="filter-form" style="display:flex;gap:12px;align-items:flex-end;">
        <div class="form-group" style="margin-bottom:0;min-width:200px;">
            <label class="form-label" style="font-size:0.8rem;">Filter by Action</label>
            <input type="text" name="action_filter" class="form-control" placeholder="e.g. login, fee, attendance..." value="<?php echo htmlspecialchars($action_filter); ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;min-width:160px;">
            <label class="form-label" style="font-size:0.8rem;">Filter by Role</label>
            <select name="role_filter" class="form-control">
                <option value="">All Roles</option>
                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="accountant" <?php echo $role_filter === 'accountant' ? 'selected' : ''; ?>>Accountant</option>
                <option value="receptionist" <?php echo $role_filter === 'receptionist' ? 'selected' : ''; ?>>Admission Officer</option>
            </select>
        </div>
        <button type="submit" class="btn-admin btn-admin--secondary btn-admin--sm">Filter Logs</button>
        <?php if ($action_filter !== '' || $role_filter !== ''): ?>
            <a href="activity_logs.php" class="btn-admin btn-admin--ghost btn-admin--sm">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Logs Panel -->
<div class="panel animate-in">
    <div class="panel__header">
        <h3 class="panel__title">Audit Trail Records (Showing <?php echo count($logs); ?> of <?php echo $total_logs; ?>)</h3>
        <span style="color:var(--admin-text-muted);font-size:0.85rem;">Immutable activity log</span>
    </div>

    <?php if (!empty($logs)): ?>
        <div class="table-responsive">
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td style="font-size:0.8rem;color:var(--admin-text-muted);white-space:nowrap;">
                            <?php echo date('M j, Y H:i:s', strtotime($l['created_at'])); ?>
                        </td>
                        <td style="font-weight:700;color:var(--admin-heading);">
                            <?php echo htmlspecialchars($l['user_name']); ?>
                        </td>
                        <td>
                            <span class="badge badge--info" style="font-size:0.75rem;">
                                <?php echo htmlspecialchars($l['user_role']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge--active" style="font-size:0.75rem;font-family:monospace;">
                                <?php echo htmlspecialchars($l['action']); ?>
                            </span>
                        </td>
                        <td style="font-size:0.85rem;color:var(--admin-accent);font-weight:600;">
                            <?php echo htmlspecialchars($l['entity_type']); ?><?php if ($l['entity_id']) echo " #" . $l['entity_id']; ?>
                        </td>
                        <td style="font-size:0.85rem;max-width:320px;line-height:1.4;">
                            <?php echo htmlspecialchars($l['details']); ?>
                        </td>
                        <td style="font-size:0.8rem;color:var(--admin-text-muted);font-family:monospace;">
                            <?php echo htmlspecialchars($l['ip_address']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state"><p>No activity logs recorded yet.</p></div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
