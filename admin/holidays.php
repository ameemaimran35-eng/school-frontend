<?php
/**
 * admin/holidays.php
 * School Holidays Management Module
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Holidays Calendar';
$current_page = 'holidays';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ─── POST HANDLERS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
        header('Location: holidays.php');
        exit();
    }

    $op = $_POST['op'] ?? '';

    if ($op === 'create') {
        $title = trim($_POST['title'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '') $errors[] = 'Holiday title is required.';
        if ($start_date === '' || $end_date === '') $errors[] = 'Both start and end dates are required.';
        elseif ($end_date < $start_date) $errors[] = 'End date cannot be earlier than start date.';

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO holidays (title, start_date, end_date, description) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssss', $title, $start_date, $end_date, $description);
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                logActivity('holiday_created', 'holidays', $new_id, "Created holiday '{$title}'");
                $_SESSION['flash_success'] = "Holiday '{$title}' added successfully.";
            } else {
                $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
            header('Location: holidays.php');
            exit();
        }
    } elseif ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM holidays WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (mysqli_stmt_execute($stmt)) {
            logActivity('holiday_deleted', 'holidays', $id, "Deleted holiday #{$id}");
            $_SESSION['flash_success'] = 'Holiday record deleted.';
        } else {
            $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
        header('Location: holidays.php');
        exit();
    }
}

// Fetch holidays
$holidays = [];
$res = mysqli_query($conn, "SELECT * FROM holidays ORDER BY start_date ASC");
while ($r = mysqli_fetch_assoc($res)) {
    $holidays[] = $r;
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">School Holidays & Observances</h1>
        <p class="page-header__desc">Manage official school breaks, gazetted holidays, and scheduled closures.</p>
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

<div class="grid-2col" style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">
    <!-- Holidays List Table -->
    <div class="panel animate-in">
        <div class="panel__header">
            <h3 class="panel__title">Official Holidays Calendar (<?php echo count($holidays); ?>)</h3>
        </div>
        <?php if (!empty($holidays)): ?>
            <div class="table-responsive">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Holiday Name</th>
                            <th>Date Range</th>
                            <th>Description</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($holidays as $h): ?>
                        <tr>
                            <td style="font-weight:700;color:var(--admin-heading);">
                                <?php echo htmlspecialchars($h['title']); ?>
                            </td>
                            <td style="font-size:0.85rem;color:var(--admin-accent);font-weight:600;">
                                <?php echo date('M j, Y', strtotime($h['start_date'])); ?>
                                <?php if ($h['start_date'] !== $h['end_date']): ?>
                                    – <?php echo date('M j, Y', strtotime($h['end_date'])); ?>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--admin-text-muted);font-size:0.85rem;">
                                <?php echo htmlspecialchars($h['description'] ?? '-'); ?>
                            </td>
                            <td style="text-align:right;">
                                <form method="POST" action="holidays.php" style="display:inline;" onsubmit="return confirm('Delete this holiday?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="op" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $h['id']; ?>">
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
            <div class="empty-state"><p>No holidays registered.</p></div>
        <?php endif; ?>
    </div>

    <!-- Add Holiday Form -->
    <div class="card animate-in">
        <div class="form-card__header">
            <h3 class="form-card__title" style="margin:0;padding:0;border:none;">Add Holiday</h3>
        </div>
        <form method="POST" action="holidays.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="op" value="create">

            <div class="form-group">
                <label class="form-label">Title / Occasion *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Eid-ul-Fitr Break" required>
            </div>

            <div class="form-group">
                <label class="form-label">Start Date *</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">End Date *</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Description / Remarks</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Optional details..."></textarea>
            </div>

            <button type="submit" class="btn-admin btn-admin--primary" style="width:100%;margin-top:6px;">
                Save Holiday
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
