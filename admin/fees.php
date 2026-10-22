<?php
/**
 * admin/fees.php
 * Fees & Finance Management Module
 * Handles fee structures and student fee assignments.
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();

$page_title = 'Fees Structure';
$current_page = 'fees';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$action = $_GET['action'] ?? 'list';

// ─── POST HANDLERS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token). Please try again.';
        header('Location: fees.php');
        exit();
    }
    $post_action = $_POST['action'] ?? '';

    // ─── CREATE / UPDATE FEE STRUCTURE ─────────────────────────
    if (in_array($post_action, ['create_fee', 'update_fee'])) {
        $session_id = (int)($_POST['session_id'] ?? 0);
        $class_id   = (int)($_POST['class_id'] ?? 0);
        $fee_title  = trim($_POST['fee_title'] ?? '');
        $amount     = floatval($_POST['amount'] ?? 0);
        $due_date   = trim($_POST['due_date'] ?? '');
        $fee_type   = trim($_POST['fee_type'] ?? 'monthly');

        if ($session_id <= 0) $errors[] = 'Academic session is required.';
        if ($class_id   <= 0) $errors[] = 'Class is required.';
        if ($fee_title  === '') $errors[] = 'Fee title is required.';
        if ($amount <= 0)      $errors[] = 'Amount must be greater than zero.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) $errors[] = 'A valid due date is required (YYYY-MM-DD).';

        if (empty($errors)) {
            if ($post_action === 'create_fee') {
                $stmt = mysqli_prepare($conn, "INSERT INTO fee_structures (session_id, class_id, fee_title, amount, due_date) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'iisds', $session_id, $class_id, $fee_title, $amount, $due_date);
                if (mysqli_stmt_execute($stmt)) {
                    logActivity('fee_created', 'fee_structures', mysqli_insert_id($conn), "Created fee structure: $fee_title");
                    $_SESSION['flash_success'] = "Fee structure &ldquo;$fee_title&rdquo; created successfully.";
                } else {
                    $errors[] = 'Failed to add fee structure: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) $errors[] = 'Invalid fee ID.';
                if (empty($errors)) {
                    $stmt = mysqli_prepare($conn, "UPDATE fee_structures SET session_id=?, class_id=?, fee_title=?, amount=?, due_date=? WHERE id=?");
                    mysqli_stmt_bind_param($stmt, 'iisdsi', $session_id, $class_id, $fee_title, $amount, $due_date, $id);
                    if (mysqli_stmt_execute($stmt)) {
                        logActivity('fee_updated', 'fee_structures', $id, "Updated fee structure: $fee_title");
                        $_SESSION['flash_success'] = "Fee structure updated successfully.";
                    } else {
                        $errors[] = 'Failed to update fee structure: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
        }
        header('Location: fees.php');
        exit();
    }

    // ─── DELETE FEE STRUCTURE ────────────────────────────────
    if ($post_action === 'delete_fee') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Check for linked fee assignments
            $chk = mysqli_prepare($conn, "SELECT COUNT(*) FROM student_fee_assignments WHERE fee_structure_id = ?");
            mysqli_stmt_bind_param($chk, 'i', $id);
            mysqli_stmt_execute($chk);
            mysqli_stmt_bind_result($chk, $linked_count);
            mysqli_stmt_fetch($chk);
            mysqli_stmt_close($chk);
            if ($linked_count > 0) {
                $_SESSION['flash_error'] = "Cannot delete: this fee structure is linked to $linked_count student assignment(s). Remove linked assignments first.";
            } else {
                $stmt = mysqli_prepare($conn, "DELETE FROM fee_structures WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                if (mysqli_stmt_execute($stmt)) {
                    logActivity('fee_deleted', 'fee_structures', $id, "Deleted fee structure #$id");
                    $_SESSION['flash_success'] = 'Fee structure deleted successfully.';
                } else {
                    $_SESSION['flash_error'] = 'Failed to delete: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        }
        header('Location: fees.php');
        exit();
    }

    // ─── ASSIGN FEE TO STUDENT ───────────────────────────────
    if ($post_action === 'assign_fee') {
        $student_id       = (int)($_POST['student_id'] ?? 0);
        $fee_structure_id = (int)($_POST['fee_structure_id'] ?? 0);
        $discount_amount  = floatval($_POST['discount_amount'] ?? 0);

        if ($student_id <= 0)       $errors[] = 'Student is required.';
        if ($fee_structure_id <= 0) $errors[] = 'Fee structure is required.';

        $fee_row = null;
        if (empty($errors)) {
            $chk2 = mysqli_prepare($conn, "SELECT amount FROM fee_structures WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($chk2, 'i', $fee_structure_id);
            mysqli_stmt_execute($chk2);
            $res2 = mysqli_stmt_get_result($chk2);
            $fee_row = mysqli_fetch_assoc($res2);
            mysqli_stmt_close($chk2);
            if (!$fee_row) $errors[] = 'Fee structure not found.';
        }

        if (empty($errors)) {
            $dup = mysqli_prepare($conn, "SELECT id FROM student_fee_assignments WHERE student_id=? AND fee_structure_id=? LIMIT 1");
            mysqli_stmt_bind_param($dup, 'ii', $student_id, $fee_structure_id);
            mysqli_stmt_execute($dup);
            mysqli_stmt_store_result($dup);
            if (mysqli_stmt_num_rows($dup) > 0) $errors[] = 'This fee is already assigned to the selected student.';
            mysqli_stmt_close($dup);
        }

        if (empty($errors)) {
            $assigned_amount = $fee_row['amount'];
            $net_amount = max(0, $assigned_amount - $discount_amount);
            $stmt = mysqli_prepare($conn, "INSERT INTO student_fee_assignments (student_id, fee_structure_id, assigned_amount, discount_amount, net_amount) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iiddd', $student_id, $fee_structure_id, $assigned_amount, $discount_amount, $net_amount);
            if (mysqli_stmt_execute($stmt)) {
                logActivity('fee_assigned', 'student_fee_assignments', mysqli_insert_id($conn), "Assigned fee structure #$fee_structure_id to student #$student_id");
                $_SESSION['flash_success'] = 'Fee assigned to student successfully.';
            } else {
                $errors[] = 'Failed to assign fee: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        if (!empty($errors)) $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: fees.php');
        exit();
    }
}

// ─── LOAD DATA FOR FORMS ────────────────────────────────────────
$classes_for_form  = [];
$resc = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY numeric_grade ASC");
while ($c = mysqli_fetch_assoc($resc)) $classes_for_form[] = $c;

$sessions_for_form = [];
$ressess = mysqli_query($conn, "SELECT id, session_name FROM academic_sessions ORDER BY is_current DESC, id DESC");
while ($ss = mysqli_fetch_assoc($ressess)) $sessions_for_form[] = $ss;

// ─── SEARCH / FILTER ────────────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$filter_sess = (int)($_GET['filter_session'] ?? 0);
$filter_cls  = (int)($_GET['filter_class'] ?? 0);

$where   = "WHERE 1=1";
$params  = [];
$types   = '';
if ($search !== '') {
    $like = "%$search%";
    $where  .= " AND fs.fee_title LIKE ?";
    $params[] = &$like;
    $types   .= 's';
}
if ($filter_sess > 0) {
    $where  .= " AND fs.session_id = ?";
    $params[] = &$filter_sess;
    $types   .= 'i';
}
if ($filter_cls > 0) {
    $where  .= " AND fs.class_id = ?";
    $params[] = &$filter_cls;
    $types   .= 'i';
}

$query = "SELECT fs.*, c.class_name, s.session_name,
          (SELECT COUNT(*) FROM student_fee_assignments sfa WHERE sfa.fee_structure_id = fs.id) AS assignment_count
          FROM fee_structures fs
          JOIN classes c ON fs.class_id = c.id
          JOIN academic_sessions s ON fs.session_id = s.id
          $where
          ORDER BY fs.due_date ASC";

$fee_structures = [];
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $fee_structures[] = $row;
    mysqli_stmt_close($stmt);
} else {
    $res = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($res)) $fee_structures[] = $row;
}

// If editing a fee structure
$edit_fee = null;
if ($action === 'edit_fee' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM fee_structures WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $edit_fee = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$edit_fee) {
        $_SESSION['flash_error'] = 'Fee structure not found.';
        header('Location: fees.php');
        exit();
    }
}

// Students for assignment dropdown (if assign_fee action)
$assign_fee_info = null;
$students_for_assign = [];
if ($action === 'assign_fee' && isset($_GET['fee_id'])) {
    $assign_fee_id = (int)$_GET['fee_id'];
    $stmt = mysqli_prepare($conn, "SELECT fs.fee_title, fs.amount, c.class_name, s.session_name FROM fee_structures fs JOIN classes c ON fs.class_id=c.id JOIN academic_sessions s ON fs.session_id=s.id WHERE fs.id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $assign_fee_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $assign_fee_info = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    $stu_res = mysqli_query($conn, "SELECT id, admission_no, first_name, last_name FROM students WHERE status='active' ORDER BY first_name ASC");
    while ($s = mysqli_fetch_assoc($stu_res)) $students_for_assign[] = $s;
}

$currency = getSetting('currency_symbol', 'Rs');

include __DIR__ . '/header.php';
?>
<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Fees Structure</h1>
        <p class="page-header__desc">Create fee structures, assign to students, and track payments.</p>
    </div>
    <div class="page-header__actions">
        <a href="fee_payments.php" class="btn-admin btn-admin--ghost btn-admin--sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            Fee Payments
        </a>
        <a href="fee_dues.php" class="btn-admin btn-admin--ghost btn-admin--sm">Fee Dues</a>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert--success"><div class="alert__content"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><span><?php echo $success_msg; ?></span></div></div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert alert--danger"><div class="alert__content"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span><?php echo htmlspecialchars($error_msg); ?></span></div></div>
<?php endif; ?>

<!-- Assign Fee Panel (shown at top when action=assign_fee) -->
<?php if ($action === 'assign_fee' && $assign_fee_info): ?>
<div class="panel animate-in" style="margin-bottom:24px;">
    <div class="panel__header">
        <h3 class="panel__title">
            Assign &ldquo;<?php echo htmlspecialchars($assign_fee_info['fee_title']); ?>&rdquo; to a Student
            <span class="badge badge--info" style="margin-left:8px;"><?php echo htmlspecialchars($assign_fee_info['class_name']); ?> &bull; <?php echo htmlspecialchars($assign_fee_info['session_name']); ?></span>
        </h3>
        <a href="fees.php" class="btn-admin btn-admin--ghost btn-admin--sm">Cancel</a>
    </div>
    <div style="padding:24px;">
        <form method="POST" action="fees.php" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="assign_fee">
            <input type="hidden" name="fee_structure_id" value="<?php echo $assign_fee_id; ?>">
            <div class="form-grid form-grid--2" style="gap:16px;">
                <div class="form-group">
                    <label class="form-label" for="student_id">Student <span class="required">*</span></label>
                    <select id="student_id" name="student_id" class="form-control" required>
                        <option value="">— Select Active Student —</option>
                        <?php foreach ($students_for_assign as $stu): ?>
                            <option value="<?php echo $stu['id']; ?>"><?php echo htmlspecialchars($stu['admission_no'] . ' — ' . $stu['first_name'] . ' ' . $stu['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="discount_amount">Discount (<?php echo htmlspecialchars($currency); ?>) <small style="font-weight:400;">(optional)</small></label>
                    <input type="number" step="0.01" min="0" id="discount_amount" name="discount_amount" class="form-control" value="0" placeholder="0.00">
                    <span class="form-hint">Full fee: <?php echo htmlspecialchars($currency); ?> <?php echo number_format($assign_fee_info['amount'], 2); ?></span>
                </div>
            </div>
            <div class="form-actions" style="justify-content:flex-start;border-top:1px solid var(--admin-border);padding-top:16px;margin-top:8px;">
                <button type="submit" class="btn-admin btn-admin--primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    Assign Fee
                </button>
                <a href="fees.php" class="btn-admin btn-admin--ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="fees-layout">
    <!-- ── LEFT: Form Card ── -->
    <div class="form-card animate-in">
        <?php if ($action === 'edit_fee' && $edit_fee): ?>
            <div class="form-card__header">
                <h2 class="form-card__title">Edit Fee Structure</h2>
                <a href="fees.php" class="btn-admin btn-admin--ghost btn-admin--sm" title="Cancel edit">Cancel</a>
            </div>
        <?php else: ?>
            <div class="form-card__header">
                <h2 class="form-card__title">Add New Fee Structure</h2>
            </div>
        <?php endif; ?>
        <form method="POST" action="fees.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <?php if ($action === 'edit_fee' && $edit_fee): ?>
                <input type="hidden" name="action" value="update_fee">
                <input type="hidden" name="id" value="<?php echo $edit_fee['id']; ?>">
            <?php else: ?>
                <input type="hidden" name="action" value="create_fee">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="session_id">Academic Session <span class="required">*</span></label>
                <select id="session_id" name="session_id" class="form-control" required>
                    <option value="">Select Session</option>
                    <?php $sid = $edit_fee['session_id'] ?? 0; ?>
                    <?php foreach ($sessions_for_form as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $sid == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['session_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="class_id">Class <span class="required">*</span></label>
                <select id="class_id" name="class_id" class="form-control" required>
                    <option value="">Select Class</option>
                    <?php $cid = $edit_fee['class_id'] ?? 0; ?>
                    <?php foreach ($classes_for_form as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $cid == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="fee_title">Fee Title <span class="required">*</span></label>
                <input type="text" id="fee_title" name="fee_title" class="form-control" placeholder="e.g. Monthly Tuition Fee" value="<?php echo htmlspecialchars($edit_fee['fee_title'] ?? ''); ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="amount">Amount (<?php echo htmlspecialchars($currency); ?>) <span class="required">*</span></label>
                <input type="number" step="0.01" min="0.01" id="amount" name="amount" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($edit_fee['amount'] ?? ''); ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="due_date">Due Date <span class="required">*</span></label>
                <input type="date" id="due_date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($edit_fee['due_date'] ?? ''); ?>" required>
            </div>
            <div class="form-actions" style="margin-top:0;padding-top:16px;">
                <button type="submit" class="btn-admin btn-admin--primary">
                    <?php if ($action === 'edit_fee' && $edit_fee): ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Changes
                    <?php else: ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Create Fee
                    <?php endif; ?>
                </button>
                <?php if ($action === 'edit_fee' && $edit_fee): ?>
                    <a href="fees.php" class="btn-admin btn-admin--ghost">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ── RIGHT: List & Filter ── -->
    <div style="display:flex;flex-direction:column;gap:16px;min-width:0;">
        <!-- Search / Filter Bar -->
        <div class="filter-card" style="margin-bottom:0;">
            <form method="GET" action="fees.php" class="filter-form">
                <div class="search-input-wrap">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" class="form-control" placeholder="Search fee title…" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <select name="filter_session" class="form-control">
                        <option value="">All Sessions</option>
                        <?php foreach ($sessions_for_form as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $filter_sess == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['session_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="filter_class" class="form-control">
                        <option value="">All Classes</option>
                        <?php foreach ($classes_for_form as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $filter_cls == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-admin btn-admin--primary btn-admin--sm">Filter</button>
                <?php if ($search || $filter_sess || $filter_cls): ?>
                    <a href="fees.php" class="btn-admin btn-admin--ghost btn-admin--sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Fee Structures Table -->
        <div class="panel animate-in">
            <div class="panel__header">
                <h3 class="panel__title">Fee Structures (<?php echo count($fee_structures); ?>)</h3>
                <?php if (count($fee_structures) > 0): ?>
                    <span class="badge badge--info"><?php echo count($fee_structures); ?> record<?php echo count($fee_structures) !== 1 ? 's' : ''; ?></span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Session</th>
                            <th>Class</th>
                            <th>Fee Title</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Assigned</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($fee_structures)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:32px 20px;color:var(--admin-text-muted);">
                            <?php echo $search || $filter_sess || $filter_cls ? 'No fee structures match your filters.' : 'No fee structures yet. Create one using the form.'; ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($fee_structures as $fs): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fs['session_name']); ?></td>
                            <td><span class="badge badge--info"><?php echo htmlspecialchars($fs['class_name']); ?></span></td>
                            <td style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($fs['fee_title']); ?></td>
                            <td style="font-weight:700;color:var(--admin-accent);"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($fs['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($fs['due_date']); ?></td>
                            <td>
                                <?php if ($fs['assignment_count'] > 0): ?>
                                    <span class="badge badge--active"><?php echo $fs['assignment_count']; ?> student<?php echo $fs['assignment_count'] != 1 ? 's' : ''; ?></span>
                                <?php else: ?>
                                    <span class="badge" style="background:rgba(255,255,255,0.06);color:var(--admin-text-muted);">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions" style="justify-content:flex-end;">
                                    <a href="fees.php?action=assign_fee&fee_id=<?php echo $fs['id']; ?>" class="action-btn action-btn--success" title="Assign to Student">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                    </a>
                                    <a href="fees.php?action=edit_fee&id=<?php echo $fs['id']; ?>" class="action-btn action-btn--edit" title="Edit Fee Structure">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>
                                    <?php if ($fs['assignment_count'] == 0): ?>
                                    <form method="POST" action="fees.php" style="display:inline;" onsubmit="return confirm('Delete this fee structure? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="delete_fee">
                                        <input type="hidden" name="id" value="<?php echo $fs['id']; ?>">
                                        <button type="submit" class="action-btn action-btn--delete" title="Delete Fee Structure">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <button class="action-btn" title="Cannot delete: has student assignments" disabled style="opacity:0.35;cursor:not-allowed;">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* ── Fees module two-column layout ── */
.fees-layout {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 24px;
    align-items: start;
}
@media (max-width: 900px) {
    .fees-layout {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include __DIR__ . '/footer.php'; ?>
