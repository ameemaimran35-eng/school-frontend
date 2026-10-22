<?php
/**
 * admin/announcements.php
 * School Announcements Management Module
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Announcements Management';
$current_page = 'announcements';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$action = $_GET['action'] ?? 'list';
$edit_id = (int)($_GET['id'] ?? 0);

// ─── POST HANDLERS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
        header('Location: announcements.php');
        exit();
    }

    $op = $_POST['op'] ?? '';

    if ($op === 'create' || $op === 'update') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $target_audience = $_POST['target_audience'] ?? 'public';
        $published_date = trim($_POST['published_date'] ?? date('Y-m-d'));
        $is_published = isset($_POST['is_published']) ? 1 : 0;

        if ($title === '') $errors[] = 'Announcement title is required.';
        if ($content === '') $errors[] = 'Content is required.';
        if (!in_array($target_audience, ['public', 'internal', 'all'], true)) $target_audience = 'public';

        if (empty($errors)) {
            if ($op === 'create') {
                $stmt = mysqli_prepare($conn, "INSERT INTO announcements (title, content, target_audience, is_published, published_date) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'sssis', $title, $content, $target_audience, $is_published, $published_date);
                if (mysqli_stmt_execute($stmt)) {
                    $new_id = mysqli_insert_id($conn);
                    logActivity('announcement_created', 'announcements', $new_id, "Created announcement '{$title}'");
                    $_SESSION['flash_success'] = 'Announcement published successfully.';
                } else {
                    $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = mysqli_prepare($conn, "UPDATE announcements SET title=?, content=?, target_audience=?, is_published=?, published_date=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'sssisi', $title, $content, $target_audience, $is_published, $published_date, $id);
                if (mysqli_stmt_execute($stmt)) {
                    logActivity('announcement_updated', 'announcements', $id, "Updated announcement '{$title}'");
                    $_SESSION['flash_success'] = 'Announcement updated successfully.';
                } else {
                    $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
            header('Location: announcements.php');
            exit();
        }
    } elseif ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM announcements WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (mysqli_stmt_execute($stmt)) {
            logActivity('announcement_deleted', 'announcements', $id, "Deleted announcement #{$id}");
            $_SESSION['flash_success'] = 'Announcement deleted.';
        } else {
            $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
        header('Location: announcements.php');
        exit();
    }
}

// Fetch announcements
$announcements = [];
$res = mysqli_query($conn, "SELECT * FROM announcements ORDER BY published_date DESC, id DESC");
while ($r = mysqli_fetch_assoc($res)) {
    $announcements[] = $r;
}

// Edit item lookup
$edit_item = null;
if ($action === 'edit' && $edit_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM announcements WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $edit_id);
    mysqli_stmt_execute($stmt);
    $edit_item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Announcements Management</h1>
        <p class="page-header__desc">Post circulars, notices, and updates for the public website and internal staff.</p>
    </div>
    <div class="page-header__actions">
        <a href="announcements.php?action=add" class="btn-admin btn-admin--primary">+ New Announcement</a>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert--success"><div class="alert__content"><span><?php echo htmlspecialchars($success_msg); ?></span></div></div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert alert--danger"><div class="alert__content"><span><?php echo htmlspecialchars($error_msg); ?></span></div></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert--danger">
        <ul><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card animate-in" style="padding:28px;max-width:700px;margin-bottom:28px;">
        <h3 style="font-size:1.15rem;font-weight:700;color:var(--admin-heading);margin-bottom:16px;">
            <?php echo $action === 'edit' ? 'Edit Announcement' : 'Create New Announcement'; ?>
        </h3>
        <form method="POST" action="announcements.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="op" value="<?php echo $action === 'edit' ? 'update' : 'create'; ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo (int)$edit_item['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Annual Sports Day Scheduled" value="<?php echo htmlspecialchars($edit_item['title'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Full Content *</label>
                <textarea name="content" class="form-control" rows="4" placeholder="Announcement details..." required><?php echo htmlspecialchars($edit_item['content'] ?? ''); ?></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label">Target Audience</label>
                    <select name="target_audience" class="form-control">
                        <option value="public" <?php echo ($edit_item['target_audience'] ?? '') === 'public' ? 'selected' : ''; ?>>Public (Website)</option>
                        <option value="internal" <?php echo ($edit_item['target_audience'] ?? '') === 'internal' ? 'selected' : ''; ?>>Internal Staff</option>
                        <option value="all" <?php echo ($edit_item['target_audience'] ?? '') === 'all' ? 'selected' : ''; ?>>All</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Published Date</label>
                    <input type="date" name="published_date" class="form-control" value="<?php echo htmlspecialchars($edit_item['published_date'] ?? date('Y-m-d')); ?>" required>
                </div>
            </div>

            <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" id="is_published" name="is_published" value="1" <?php echo (!isset($edit_item) || $edit_item['is_published'] == 1) ? 'checked' : ''; ?>>
                <label for="is_published" style="font-size:0.9rem;cursor:pointer;">Active / Published</label>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <a href="announcements.php" class="btn-admin btn-admin--ghost">Cancel</a>
                <button type="submit" class="btn-admin btn-admin--primary">
                    <?php echo $action === 'edit' ? 'Update Announcement' : 'Publish Announcement'; ?>
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Announcements List Table -->
<div class="panel animate-in">
    <div class="panel__header">
        <h3 class="panel__title">All Announcements (<?php echo count($announcements); ?>)</h3>
    </div>
    <?php if (!empty($announcements)): ?>
        <div class="table-responsive">
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Audience</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($announcements as $a): ?>
                    <tr>
                        <td style="font-size:0.85rem;color:var(--admin-text-muted);"><?php echo date('M j, Y', strtotime($a['published_date'])); ?></td>
                        <td>
                            <div style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($a['title']); ?></div>
                            <small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars(substr($a['content'], 0, 90)) . (strlen($a['content']) > 90 ? '...' : ''); ?></small>
                        </td>
                        <td><span class="badge badge--info"><?php echo ucfirst($a['target_audience']); ?></span></td>
                        <td>
                            <span class="badge badge--<?php echo $a['is_published'] ? 'active' : 'danger'; ?>">
                                <?php echo $a['is_published'] ? 'Published' : 'Draft'; ?>
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <div class="table-actions" style="justify-content:flex-end;">
                                <a href="announcements.php?action=edit&id=<?php echo $a['id']; ?>" class="action-btn action-btn--edit" title="Edit">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>
                                <form method="POST" action="announcements.php" style="display:inline;" onsubmit="return confirm('Delete this announcement?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="op" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" class="action-btn action-btn--delete" style="background:none;border:none;cursor:pointer;" title="Delete">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>No announcements found.</p>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
