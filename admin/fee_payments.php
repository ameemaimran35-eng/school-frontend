<?php
/**
 * admin/fee_payments.php
 * Manage fee payments and printable receipts.
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();

$current_page = 'fee_payments';
$page_title   = 'Fee Payments';

$errors      = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$currency = getSetting('currency_symbol', 'Rs');

// ─── Load students for dropdowns ────────────────────────────────
$students = [];
$res_stu = mysqli_query($conn, "SELECT id, admission_no, first_name, last_name FROM students WHERE status='active' ORDER BY first_name ASC");
while ($row = mysqli_fetch_assoc($res_stu)) $students[] = $row;

// Load unpaid/partial fee assignments for payment form
$fee_assignments = [];
$res_fa = mysqli_query($conn, "
    SELECT sfa.id, sfa.net_amount, sfa.status, sfa.student_id, sfa.assigned_amount, sfa.discount_amount,
           fs.fee_title, fs.due_date,
           CONCAT(s.first_name,' ',s.last_name) AS student_name,
           s.admission_no
    FROM student_fee_assignments sfa
    JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
    JOIN students s ON sfa.student_id = s.id
    WHERE sfa.status IN ('unpaid','partial')
    ORDER BY fs.due_date ASC, s.first_name ASC
");
if ($res_fa) {
    while ($row = mysqli_fetch_assoc($res_fa)) $fee_assignments[] = $row;
}

// ─── POST HANDLER ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid CSRF token. Please try again.';
        header('Location: fee_payments.php');
        exit();
    }

    $post_action = $_POST['action'] ?? '';

    // ── ADD PAYMENT ──────────────────────────────────────────
    if ($post_action === 'add_payment') {
        $student_fee_id = (int)($_POST['student_fee_id'] ?? 0);
        $student_id     = (int)($_POST['student_id']     ?? 0);
        $amount_paid    = (float)($_POST['amount_paid']   ?? 0);
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $payment_date   = $_POST['payment_date']   ?? date('Y-m-d');
        $reference_no   = trim($_POST['reference_no'] ?? '');
        $remarks        = trim($_POST['remarks']      ?? '');

        if ($student_fee_id <= 0) $errors[] = 'Please select a fee assignment.';
        if ($student_id     <= 0) $errors[] = 'Please select a student.';
        if ($amount_paid    <= 0) $errors[] = 'Amount must be greater than zero.';

        // Verify assignment belongs to student
        if (empty($errors)) {
            $chk = mysqli_prepare($conn, "SELECT net_amount, status FROM student_fee_assignments WHERE id=? AND student_id=? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'ii', $student_fee_id, $student_id);
            mysqli_stmt_execute($chk);
            $res = mysqli_stmt_get_result($chk);
            $assign_row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($chk);
            if (!$assign_row) {
                $errors[] = 'Fee assignment not found or does not belong to the selected student.';
            } elseif ($assign_row['status'] === 'paid') {
                $errors[] = 'This fee assignment is already fully paid.';
            } elseif ($amount_paid > $assign_row['net_amount'] + 0.01) {
                $errors[] = 'Amount paid (' . number_format($amount_paid, 2) . ') exceeds the outstanding balance (' . number_format($assign_row['net_amount'], 2) . ').';
            }
        }

        // Duplicate check: same assignment + same date + same amount
        if (empty($errors)) {
            $dupStmt = mysqli_prepare($conn, "SELECT id FROM fee_payments WHERE student_fee_id=? AND amount_paid=? AND payment_date=? LIMIT 1");
            mysqli_stmt_bind_param($dupStmt, 'ids', $student_fee_id, $amount_paid, $payment_date);
            mysqli_stmt_execute($dupStmt);
            mysqli_stmt_store_result($dupStmt);
            if (mysqli_stmt_num_rows($dupStmt) > 0) {
                $errors[] = 'A duplicate payment with the same amount and date already exists for this fee assignment.';
            }
            mysqli_stmt_close($dupStmt);
        }

        if (empty($errors)) {
            $receipt_no = 'RCPT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

            $stmt = mysqli_prepare($conn, "INSERT INTO fee_payments (receipt_no, student_fee_id, student_id, amount_paid, payment_method, payment_date, reference_no, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'siidssss', $receipt_no, $student_fee_id, $student_id, $amount_paid, $payment_method, $payment_date, $reference_no, $remarks);

            if (mysqli_stmt_execute($stmt)) {
                $payment_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                // Update fee assignment balance
                $assignStmt = mysqli_prepare($conn, "SELECT net_amount FROM student_fee_assignments WHERE id=?");
                mysqli_stmt_bind_param($assignStmt, 'i', $student_fee_id);
                mysqli_stmt_execute($assignStmt);
                mysqli_stmt_bind_result($assignStmt, $current_net);
                mysqli_stmt_fetch($assignStmt);
                mysqli_stmt_close($assignStmt);

                $new_net    = max(0, $current_net - $amount_paid);
                $new_status = ($new_net <= 0.009) ? 'paid' : 'partial';

                $updStmt = mysqli_prepare($conn, "UPDATE student_fee_assignments SET net_amount=?, status=? WHERE id=?");
                mysqli_stmt_bind_param($updStmt, 'dsi', $new_net, $new_status, $student_fee_id);
                mysqli_stmt_execute($updStmt);
                mysqli_stmt_close($updStmt);

                // Record in financial ledger (reference_id is integer payment_id)
                $desc  = "Fee payment – receipt $receipt_no";
                $stmt2 = mysqli_prepare($conn, "INSERT IGNORE INTO financial_transactions (trans_date, type, category, amount, title, description, payment_method, reference_type, reference_id, receipt_invoice_no) VALUES (?, 'income', 'fee_payment', ?, 'Fee Payment', ?, ?, 'fee_payment', ?, ?)");
                mysqli_stmt_bind_param($stmt2, 'sdssis', $payment_date, $amount_paid, $desc, $payment_method, $payment_id, $receipt_no);
                mysqli_stmt_execute($stmt2);
                mysqli_stmt_close($stmt2);

                logActivity('fee_payment_recorded', 'fee_payments', null, "Payment $receipt_no – $currency $amount_paid – Assign #$student_fee_id");
                $_SESSION['flash_success'] = "Payment recorded successfully! Receipt No: <strong>$receipt_no</strong> &nbsp;<a href='receipt.php?receipt_no=" . urlencode($receipt_no) . "' target='_blank' style='color:var(--admin-accent);'>View Receipt →</a>";
            } else {
                $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
                if (isset($stmt)) mysqli_stmt_close($stmt);
            }
            header('Location: fee_payments.php');
            exit();
        }

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            header('Location: fee_payments.php');
            exit();
        }
    }

    // ── DELETE PAYMENT ───────────────────────────────────────
    if ($post_action === 'delete_payment') {
        $pay_id = (int)($_POST['pay_id'] ?? 0);
        if ($pay_id > 0) {
            // Get payment details before deleting so we can reverse the balance
            $get = mysqli_prepare($conn, "SELECT student_fee_id, amount_paid, receipt_no FROM fee_payments WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($get, 'i', $pay_id);
            mysqli_stmt_execute($get);
            $res = mysqli_stmt_get_result($get);
            $pay_row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($get);

            if ($pay_row) {
                // Reverse balance on assignment
                $rev = mysqli_prepare($conn, "UPDATE student_fee_assignments SET net_amount = net_amount + ?, status = CASE WHEN (net_amount + ?) <= 0.009 THEN 'paid' WHEN (net_amount + ?) < assigned_amount THEN 'partial' ELSE 'unpaid' END WHERE id=?");
                mysqli_stmt_bind_param($rev, 'dddi', $pay_row['amount_paid'], $pay_row['amount_paid'], $pay_row['amount_paid'], $pay_row['student_fee_id']);
                mysqli_stmt_execute($rev);
                mysqli_stmt_close($rev);

                // Delete the payment record
                $del = mysqli_prepare($conn, "DELETE FROM fee_payments WHERE id=?");
                mysqli_stmt_bind_param($del, 'i', $pay_id);
                if (mysqli_stmt_execute($del)) {
                    // Remove from ledger
                    mysqli_query($conn, "DELETE FROM financial_transactions WHERE reference_type='fee_payment' AND receipt_invoice_no='" . mysqli_real_escape_string($conn, $pay_row['receipt_no']) . "' LIMIT 1");
                    logActivity('fee_payment_deleted', 'fee_payments', $pay_id, "Deleted payment " . $pay_row['receipt_no']);
                    $_SESSION['flash_success'] = 'Payment deleted and balance reversed successfully.';
                } else {
                    $_SESSION['flash_error'] = 'Failed to delete payment: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($del);
            } else {
                $_SESSION['flash_error'] = 'Payment record not found.';
            }
        }
        header('Location: fee_payments.php');
        exit();
    }
}

// ─── SEARCH / FILTER ─────────────────────────────────────────────
$search_pay = trim($_GET['search'] ?? '');
$filter_method = trim($_GET['method'] ?? '');
$filter_from = trim($_GET['from'] ?? '');
$filter_to   = trim($_GET['to'] ?? '');

$pay_where = "WHERE 1=1";
$pay_params = [];
$pay_types  = '';

if ($search_pay !== '') {
    $like = "%$search_pay%";
    $pay_where  .= " AND (fp.receipt_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ? OR fs.fee_title LIKE ?)";
    $pay_params = array_merge($pay_params, [$like, $like, $like, $like, $like]);
    $pay_types  .= 'sssss';
}
if ($filter_method !== '') {
    $pay_where  .= " AND fp.payment_method = ?";
    $pay_params[] = $filter_method;
    $pay_types  .= 's';
}
if ($filter_from !== '') {
    $pay_where  .= " AND fp.payment_date >= ?";
    $pay_params[] = $filter_from;
    $pay_types  .= 's';
}
if ($filter_to !== '') {
    $pay_where  .= " AND fp.payment_date <= ?";
    $pay_params[] = $filter_to;
    $pay_types  .= 's';
}

$pay_query = "
    SELECT fp.id, fp.receipt_no, fp.amount_paid, fp.payment_method, fp.payment_date,
           fp.reference_no, fp.remarks, fp.student_fee_id,
           s.first_name, s.last_name, s.admission_no,
           fs.fee_title
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    JOIN student_fee_assignments sfa ON fp.student_fee_id = sfa.id
    JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
    $pay_where
    ORDER BY fp.payment_date DESC, fp.id DESC
    LIMIT 200
";

$payments = [];
if (!empty($pay_params)) {
    $stmt = mysqli_prepare($conn, $pay_query);
    mysqli_stmt_bind_param($stmt, $pay_types, ...$pay_params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $payments[] = $row;
    mysqli_stmt_close($stmt);
} else {
    $res = mysqli_query($conn, $pay_query);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) $payments[] = $row;
    }
}

// Summary stats
$stats_res = mysqli_query($conn, "SELECT COUNT(*) AS total_payments, COALESCE(SUM(amount_paid),0) AS total_collected FROM fee_payments");
$stats = mysqli_fetch_assoc($stats_res);

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Fee Payments</h1>
        <p class="page-header__desc">Record student fee payments and view receipt history.</p>
    </div>
    <div class="page-header__actions">
        <a href="fees.php" class="btn-admin btn-admin--ghost btn-admin--sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1" ry="1"/></svg>
            Fee Structure
        </a>
        <a href="fee_dues.php" class="btn-admin btn-admin--ghost btn-admin--sm">Fee Dues</a>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--accent">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Total Collected</div>
            <div class="stat-card__value"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($stats['total_collected'], 0); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--blue">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Total Transactions</div>
            <div class="stat-card__value"><?php echo number_format($stats['total_payments']); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--amber">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Pending Assignments</div>
            <div class="stat-card__value"><?php echo count($fee_assignments); ?></div>
        </div>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert--success"><div class="alert__content"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><span><?php echo $success_msg; ?></span></div></div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert alert--danger"><div class="alert__content"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span><?php echo htmlspecialchars($error_msg); ?></span></div></div>
<?php endif; ?>

<div class="payments-layout">
    <!-- ── LEFT: Record Payment Form ── -->
    <div class="form-card animate-in">
        <div class="form-card__header">
            <h2 class="form-card__title">Record New Payment</h2>
        </div>
        <form method="POST" action="fee_payments.php" id="paymentForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <input type="hidden" name="action" value="add_payment">

            <div style="display:flex;flex-direction:column;gap:14px;margin-top:4px;">
                <div class="form-group">
                    <label class="form-label" for="student_fee_id">Fee Assignment <span class="required">*</span></label>
                    <select id="student_fee_id" name="student_fee_id" class="form-control" required onchange="autoFillStudent(this)">
                        <option value="">— Select Fee Assignment —</option>
                        <?php foreach ($fee_assignments as $fa): ?>
                            <option value="<?php echo $fa['id']; ?>" data-student-id="<?php echo $fa['student_id']; ?>" data-balance="<?php echo $fa['net_amount']; ?>">
                                <?php echo htmlspecialchars($fa['student_name'] . ' (' . $fa['admission_no'] . ') — ' . $fa['fee_title']); ?>
                                <?php echo ' | Balance: ' . htmlspecialchars($currency) . ' ' . number_format($fa['net_amount'], 0); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($fee_assignments)): ?>
                            <option disabled>No pending fee assignments found</option>
                        <?php endif; ?>
                    </select>
                    <span class="form-hint">Only unpaid / partial assignments shown</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="student_id">Student <span class="required">*</span></label>
                    <select id="student_id" name="student_id" class="form-control" required>
                        <option value="">— Select Student —</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['admission_no'] . ' — ' . $s['first_name'] . ' ' . $s['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="amount_paid">Amount Paid (<?php echo htmlspecialchars($currency); ?>) <span class="required">*</span></label>
                    <input type="number" step="0.01" min="0.01" id="amount_paid" name="amount_paid" class="form-control" placeholder="0.00" required>
                    <span class="form-hint" id="balanceHint"></span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method" class="form-control">
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="online">Online</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="payment_date">Payment Date <span class="required">*</span></label>
                    <input type="date" id="payment_date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reference_no">Reference / Cheque No <small style="font-weight:400;">(optional)</small></label>
                    <input type="text" id="reference_no" name="reference_no" class="form-control" placeholder="e.g. CHQ-00123">
                </div>

                <div class="form-group">
                    <label class="form-label" for="remarks">Remarks <small style="font-weight:400;">(optional)</small></label>
                    <textarea id="remarks" name="remarks" class="form-control" rows="2" placeholder="Any additional notes…"></textarea>
                </div>

                <div class="form-actions" style="margin-top:0;padding-top:12px;justify-content:stretch;">
                    <button type="submit" class="btn-admin btn-admin--primary" style="width:100%;justify-content:center;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                        Record Payment
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ── RIGHT: Payments History ── -->
    <div style="display:flex;flex-direction:column;gap:16px;min-width:0;">
        <!-- Search & Filter -->
        <div class="filter-card" style="margin-bottom:0;">
            <form method="GET" action="fee_payments.php" class="filter-form">
                <div class="search-input-wrap">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" class="form-control" placeholder="Receipt #, student, fee title…" value="<?php echo htmlspecialchars($search_pay); ?>">
                </div>
                <div class="filter-group" style="min-width:140px;">
                    <select name="method" class="form-control">
                        <option value="">All Methods</option>
                        <option value="cash" <?php echo $filter_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="bank_transfer" <?php echo $filter_method === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="cheque" <?php echo $filter_method === 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                        <option value="online" <?php echo $filter_method === 'online' ? 'selected' : ''; ?>>Online</option>
                    </select>
                </div>
                <div class="filter-group" style="min-width:130px;">
                    <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($filter_from); ?>" title="From date">
                </div>
                <div class="filter-group" style="min-width:130px;">
                    <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($filter_to); ?>" title="To date">
                </div>
                <button type="submit" class="btn-admin btn-admin--primary btn-admin--sm">Filter</button>
                <?php if ($search_pay || $filter_method || $filter_from || $filter_to): ?>
                    <a href="fee_payments.php" class="btn-admin btn-admin--ghost btn-admin--sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="panel animate-in">
            <div class="panel__header">
                <h3 class="panel__title">Payment History (<?php echo count($payments); ?>)</h3>
            </div>
            <?php if (!empty($payments)): ?>
            <div class="table-responsive">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Student</th>
                            <th>Fee Title</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Ref</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td>
                                <a href="receipt.php?receipt_no=<?php echo urlencode($p['receipt_no']); ?>"
                                   class="badge badge--info" title="View Receipt" target="_blank">
                                    <?php echo htmlspecialchars($p['receipt_no']); ?>
                                </a>
                            </td>
                            <td>
                                <div class="student-cell">
                                    <div class="student-avatar" style="background:linear-gradient(135deg,var(--admin-blue),var(--admin-purple));">
                                        <?php echo strtoupper(substr($p['first_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="student-name"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></div>
                                        <div style="font-size:0.72rem;color:var(--admin-text-muted);"><?php echo htmlspecialchars($p['admission_no']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:0.83rem;"><?php echo htmlspecialchars($p['fee_title']); ?></td>
                            <td style="font-weight:700;color:var(--admin-accent);white-space:nowrap;">
                                <?php echo htmlspecialchars($currency); ?> <?php echo number_format($p['amount_paid'], 2); ?>
                            </td>
                            <td>
                                <span class="badge badge--blue"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $p['payment_method']))); ?></span>
                            </td>
                            <td style="white-space:nowrap;"><?php echo htmlspecialchars($p['payment_date']); ?></td>
                            <td style="font-size:0.78rem;color:var(--admin-text-muted);"><?php echo htmlspecialchars($p['reference_no'] ?: '—'); ?></td>
                            <td>
                                <div class="table-actions" style="justify-content:flex-end;">
                                    <a href="receipt.php?receipt_no=<?php echo urlencode($p['receipt_no']); ?>" class="action-btn action-btn--view" title="View / Print Receipt" target="_blank">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <form method="POST" action="fee_payments.php" style="display:inline;" onsubmit="return confirm('Delete this payment? The student\'s balance will be reversed.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="delete_payment">
                                        <input type="hidden" name="pay_id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="action-btn action-btn--delete" title="Delete Payment (reverses balance)">
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
            <div class="empty-state" style="padding:40px 20px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <p style="margin-top:12px;"><?php echo $search_pay || $filter_method || $filter_from || $filter_to ? 'No payments match your filters.' : 'No payments recorded yet.'; ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.payments-layout {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 24px;
    align-items: start;
}
@media (max-width: 900px) {
    .payments-layout {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function autoFillStudent(sel) {
    var opt = sel.options[sel.selectedIndex];
    var sid = opt.getAttribute('data-student-id');
    var balance = opt.getAttribute('data-balance');
    var studentSel = document.getElementById('student_id');
    var balHint    = document.getElementById('balanceHint');
    var amtInput   = document.getElementById('amount_paid');

    if (sid && studentSel) {
        studentSel.value = sid;
    }
    if (balance !== null && balance !== '') {
        var bal = parseFloat(balance).toFixed(2);
        balHint.textContent = 'Outstanding balance: <?php echo addslashes(htmlspecialchars($currency)); ?> ' + bal;
        if (amtInput && !amtInput.value) {
            amtInput.value = bal;
        }
        amtInput.max = bal;
    } else {
        balHint.textContent = '';
        if (amtInput) amtInput.removeAttribute('max');
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
