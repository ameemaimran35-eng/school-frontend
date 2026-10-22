<?php
/**
 * admin/sessions.php
 * Academic Sessions Management Module
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Academic Sessions';
$current_page = 'sessions';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$action = $_GET['action'] ?? 'list';
$session_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ─── POST HANDLERS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token). Please try again.';
        header('Location: sessions.php');
        exit();
    }

    $post_action = $_POST['action'] ?? '';

    // CREATE SESSION
    if ($post_action === 'create') {
        $session_name = trim($_POST['session_name'] ?? '');
        $start_date   = trim($_POST['start_date'] ?? '');
        $end_date     = trim($_POST['end_date'] ?? '');
        $is_current   = !empty($_POST['is_current']) ? 1 : 0;

        if ($session_name === '') {
            $errors[] = 'Session name is required (e.g. 2026-2027).';
        } else {
            $chk = mysqli_prepare($conn, "SELECT id FROM academic_sessions WHERE session_name = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 's', $session_name);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) {
                $errors[] = "An academic session named '{$session_name}' already exists.";
            }
            mysqli_stmt_close($chk);
        }

        if ($start_date === '' || $end_date === '') {
            $errors[] = 'Both start date and end date are required.';
        } elseif ($end_date <= $start_date) {
            $errors[] = 'Session end date must be after the start date.';
        }

        if (empty($errors)) {
            mysqli_begin_transaction($conn);
            try {
                if ($is_current === 1) {
                    mysqli_query($conn, "UPDATE academic_sessions SET is_current = 0");
                }
                $stmt = mysqli_prepare($conn, "INSERT INTO academic_sessions (session_name, start_date, end_date, is_current) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'sssi', $session_name, $start_date, $end_date, $is_current);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                mysqli_commit($conn);
                $_SESSION['flash_success'] = "Academic session '{$session_name}' created successfully.";
                header('Location: sessions.php');
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }

    // UPDATE SESSION
    if ($post_action === 'update') {
        $id           = (int)($_POST['id'] ?? 0);
        $session_name = trim($_POST['session_name'] ?? '');
        $start_date   = trim($_POST['start_date'] ?? '');
        $end_date     = trim($_POST['end_date'] ?? '');
        $is_current   = !empty($_POST['is_current']) ? 1 : 0;

        if ($id <= 0) {
            $errors[] = 'Invalid session record.';
        }
        if ($session_name === '') {
            $errors[] = 'Session name is required.';
        } else {
            $chk = mysqli_prepare($conn, "SELECT id FROM academic_sessions WHERE session_name = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'si', $session_name, $id);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) {
                $errors[] = "Session name '{$session_name}' is already used by another session.";
            }
            mysqli_stmt_close($chk);
        }

        if ($start_date === '' || $end_date === '') {
            $errors[] = 'Both start date and end date are required.';
        } elseif ($end_date <= $start_date) {
            $errors[] = 'End date must be after the start date.';
        }

        if (empty($errors)) {
            mysqli_begin_transaction($conn);
            try {
                if ($is_current === 1) {
                    mysqli_query($conn, "UPDATE academic_sessions SET is_current = 0 WHERE id != $id");
                }
                $stmt = mysqli_prepare($conn, "UPDATE academic_sessions SET session_name = ?, start_date = ?, end_date = ?, is_current = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'sssii', $session_name, $start_date, $end_date, $is_current, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                mysqli_commit($conn);
                $_SESSION['flash_success'] = "Academic session '{$session_name}' updated successfully.";
                header('Location: sessions.php');
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }

    // SET AS CURRENT
    if ($post_action === 'set_current') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            mysqli_begin_transaction($conn);
            try {
                mysqli_query($conn, "UPDATE academic_sessions SET is_current = 0");
                $stmt = mysqli_prepare($conn, "UPDATE academic_sessions SET is_current = 1 WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                mysqli_commit($conn);
                $_SESSION['flash_success'] = "Current active session updated successfully.";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $_SESSION['flash_error'] = "Could not activate session: " . $e->getMessage();
            }
        }
        header('Location: sessions.php');
        exit();
    }

    // SAFE DELETE
    if ($post_action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Check linked students
            $chk = mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE session_id = $id");
            $st_cnt = mysqli_fetch_assoc($chk)['c'] ?? 0;

            // Check linked fee structures
            $chk2 = mysqli_query($conn, "SELECT COUNT(*) as c FROM fee_structures WHERE session_id = $id");
            $fee_cnt = mysqli_fetch_assoc($chk2)['c'] ?? 0;

            // Check linked exams
            $chk3 = mysqli_query($conn, "SELECT COUNT(*) as c FROM exams WHERE session_id = $id");
            $ex_cnt = mysqli_fetch_assoc($chk3)['c'] ?? 0;

            if ($st_cnt > 0 || $fee_cnt > 0 || $ex_cnt > 0) {
                $_SESSION['flash_error'] = "Cannot delete this session because historical records exist: {$st_cnt} student(s), {$fee_cnt} fee plan(s), and {$ex_cnt} exam(s) are linked to it.";
            } else {
                $stmt = mysqli_prepare($conn, "DELETE FROM academic_sessions WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['flash_success'] = "Session deleted successfully.";
                } else {
                    $_SESSION['flash_error'] = "Delete failed: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        }
        header('Location: sessions.php');
        exit();
    }
}

// ─── LOAD DATA FOR EDIT ──────────────────────────────────────────
$edit_session = null;
if ($action === 'edit' && $session_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM academic_sessions WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $session_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $edit_session = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$edit_session) {
        $_SESSION['flash_error'] = 'Session not found.';
        header('Location: sessions.php');
        exit();
    }
}

// Fetch all sessions with student count
$sessions_list = [];
$res = mysqli_query($conn, "SELECT s.*, 
    COUNT(st.id) AS student_count
    FROM academic_sessions s
    LEFT JOIN students st ON s.id = st.session_id
    GROUP BY s.id
    ORDER BY s.start_date DESC");
while ($r = mysqli_fetch_assoc($res)) {
    $sessions_list[] = $r;
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Academic Sessions</h1>
        <p class="page-header__desc">Define school calendar terms, date ranges, and the current active academic period.</p>
    </div>
    <div class="page-header__actions">
        <?php if ($action === 'edit'): ?>
            <a href="sessions.php" class="btn-admin btn-admin--secondary">← Cancel Edit</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert--success">
        <div class="alert__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span><?php echo htmlspecialchars($success_msg); ?></span>
        </div>
    </div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert alert--danger">
        <div class="alert__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert--danger">
        <div>
            <strong>Please resolve the following:</strong>
            <ul style="margin-left: 20px; margin-top: 6px;">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="dash-grid" style="grid-template-columns: 360px 1fr; align-items: start;">
    <!-- Add or Edit Session Form -->
    <div class="form-card animate-in">
        <div class="form-card__header">
            <h2 class="form-card__title"><?php echo $edit_session ? 'Edit Session' : 'Create New Session'; ?></h2>
        </div>

        <form method="POST" action="sessions.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="<?php echo $edit_session ? 'update' : 'create'; ?>">
            <?php if ($edit_session): ?>
                <input type="hidden" name="id" value="<?php echo $edit_session['id']; ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="session_name">Session Name <span class="required">*</span></label>
                <input type="text" id="session_name" name="session_name" class="form-control" 
                       value="<?php echo htmlspecialchars($edit_session['session_name'] ?? ($_POST['session_name'] ?? '')); ?>" 
                       placeholder="e.g. 2026-2027" required>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="start_date">Start Date <span class="required">*</span></label>
                <input type="date" id="start_date" name="start_date" class="form-control" 
                       value="<?php echo htmlspecialchars($edit_session['start_date'] ?? ($_POST['start_date'] ?? date('Y-08-01'))); ?>" required>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="end_date">End Date <span class="required">*</span></label>
                <input type="date" id="end_date" name="end_date" class="form-control" 
                       value="<?php echo htmlspecialchars($edit_session['end_date'] ?? ($_POST['end_date'] ?? date('Y-05-31', strtotime('+1 year')))); ?>" required>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;color:var(--admin-text);cursor:pointer;">
                    <input type="checkbox" name="is_current" value="1" 
                           <?php echo (!empty($edit_session['is_current']) || !empty($_POST['is_current'])) ? 'checked' : ''; ?>>
                    <span>Set as Current Active Session</span>
                </label>
                <small style="color:var(--admin-text-muted);font-size:0.75rem;">Only one session can be marked as current at any time.</small>
            </div>

            <div class="form-actions" style="margin-top:0;padding-top:16px;">
                <?php if ($edit_session): ?>
                    <a href="sessions.php" class="btn-admin btn-admin--secondary">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="btn-admin btn-admin--primary">
                    <?php echo $edit_session ? 'Update Session' : 'Save Session'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Sessions List Table Panel -->
    <div class="panel animate-in">
        <div class="panel__header">
            <h3 class="panel__title">Existing Academic Sessions (<?php echo count($sessions_list); ?>)</h3>
        </div>

        <?php if (!empty($sessions_list)): ?>
            <div class="table-responsive">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Session Name</th>
                            <th>Date Range</th>
                            <th>Enrolled</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions_list as $ses): ?>
                        <tr>
                            <td>
                                <strong style="font-size:0.95rem;color:var(--admin-heading);"><?php echo htmlspecialchars($ses['session_name']); ?></strong>
                            </td>
                            <td style="color:var(--admin-text-muted);font-size:0.85rem;">
                                <?php echo date('M j, Y', strtotime($ses['start_date'])); ?> – <?php echo date('M j, Y', strtotime($ses['end_date'])); ?>
                            </td>
                            <td>
                                <span style="font-weight:600;color:var(--admin-heading);"><?php echo number_format($ses['student_count']); ?></span>
                                <small style="color:var(--admin-text-muted);">students</small>
                            </td>
                            <td>
                                <?php if ($ses['is_current']): ?>
                                    <span class="badge badge--active">★ Active Current</span>
                                <?php else: ?>
                                    <span class="badge badge--inactive">Archived</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <div class="table-actions" style="justify-content:flex-end;">
                                    <?php if (!$ses['is_current']): ?>
                                        <form method="POST" action="sessions.php" style="display:inline;" onsubmit="return confirm('Activate <?php echo htmlspecialchars($ses['session_name']); ?> as the current school session?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="action" value="set_current">
                                            <input type="hidden" name="id" value="<?php echo $ses['id']; ?>">
                                            <button type="submit" class="action-btn action-btn--success" title="Set as Current">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <a href="sessions.php?action=edit&id=<?php echo $ses['id']; ?>" class="action-btn action-btn--edit" title="Edit Session">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>

                                    <?php if ($ses['student_count'] == 0): ?>
                                        <form method="POST" action="sessions.php" style="display:inline;" onsubmit="return confirm('Delete this empty session?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $ses['id']; ?>">
                                            <button type="submit" class="action-btn action-btn--delete" title="Delete Session">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="action-btn action-btn--disabled" disabled title="Cannot delete — has <?php echo $ses['student_count']; ?> student(s)">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No academic sessions found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
