<?php
/**
 * accountant/fee_payments.php
 * Fee Collection — Search student, view balance, record full/partial payment.
 * SRS Section 7.2
 */
require_once __DIR__ . '/auth_check.php';

$page_title   = 'Collect Fee Payment';
$current_page = 'fee_payments';

$errors      = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ─── STUDENT SEARCH ───────────────────────────────────────────────
$search_q   = trim($_GET['q'] ?? '');
$student_id = (int)($_GET['student_id'] ?? 0);

// Fetch matching students
$students = [];
if ($search_q !== '') {
    $sq = '%' . $search_q . '%';
    $stmt = mysqli_prepare($conn,
        "SELECT id, admission_no, first_name, last_name, class_id, section_id
         FROM students
         WHERE status='active' AND (first_name LIKE ? OR last_name LIKE ? OR admission_no LIKE ?)
         ORDER BY first_name LIMIT 20");
    mysqli_stmt_bind_param($stmt, 'sss', $sq, $sq, $sq);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($r)) $students[] = $row;
    mysqli_stmt_close($stmt);
}

// Fetch selected student profile + fee assignments
$selected_student   = null;
$fee_assignments     = [];
$payment_history     = [];
if ($student_id > 0) {
    $stmt = mysqli_prepare($conn,
        "SELECT s.*, c.class_name, sec.section_name
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.id
         LEFT JOIN sections sec ON s.section_id = sec.id
         WHERE s.id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $student_id);
    mysqli_stmt_execute($stmt);
    $selected_student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($selected_student) {
        // Outstanding fee assignments
        $stmt2 = mysqli_prepare($conn,
            "SELECT sfa.id AS sfa_id, sfa.assigned_amount, sfa.discount_amount, sfa.net_amount, sfa.status,
                    fs.fee_title, fs.due_date
             FROM student_fee_assignments sfa
             JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
             WHERE sfa.student_id = ?
             ORDER BY sfa.status, fs.due_date ASC");
        mysqli_stmt_bind_param($stmt2, 'i', $student_id);
        mysqli_stmt_execute($stmt2);
        $r2 = mysqli_stmt_get_result($stmt2);
        while ($row = mysqli_fetch_assoc($r2)) $fee_assignments[] = $row;
        mysqli_stmt_close($stmt2);

        // Recent payment history (last 10)
        $stmt3 = mysqli_prepare($conn,
            "SELECT fp.receipt_no, fp.amount_paid, fp.payment_method, fp.payment_date, fp.reference_no,
                    fs.fee_title, fp.remarks
             FROM fee_payments fp
             JOIN student_fee_assignments sfa ON fp.student_fee_id = sfa.id
             JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
             WHERE fp.student_id = ?
             ORDER BY fp.id DESC LIMIT 10");
        mysqli_stmt_bind_param($stmt3, 'i', $student_id);
        mysqli_stmt_execute($stmt3);
        $r3 = mysqli_stmt_get_result($stmt3);
        while ($row = mysqli_fetch_assoc($r3)) $payment_history[] = $row;
        mysqli_stmt_close($stmt3);
    }
}

// ─── POST HANDLER: RECORD PAYMENT ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_payment') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid CSRF token.';
        header('Location: fee_payments.php' . ($student_id ? '?student_id='.$student_id : ''));
        exit();
    }

    $sfa_id         = (int)($_POST['student_fee_id'] ?? 0);
    $post_student   = (int)($_POST['student_id']     ?? 0);
    $amount_paid    = (float)($_POST['amount_paid']   ?? 0);
    $payment_method = trim($_POST['payment_method']   ?? 'cash');
    $payment_date   = trim($_POST['payment_date']     ?? date('Y-m-d'));
    $reference_no   = trim($_POST['reference_no']     ?? '');
    $remarks        = trim($_POST['remarks']          ?? '');

    if ($sfa_id       <= 0) $errors[] = 'Please select a fee assignment.';
    if ($post_student <= 0) $errors[] = 'Student not identified.';
    if ($amount_paid  <= 0) $errors[] = 'Amount must be greater than zero.';
    if (!in_array($payment_method, ['cash','bank_transfer','cheque','online'], true))
        $errors[] = 'Invalid payment method.';

    // Verify assignment belongs to student and get current balance
    $assign_row = null;
    if (empty($errors)) {
        $chk = mysqli_prepare($conn, "SELECT net_amount, status, assigned_amount FROM student_fee_assignments WHERE id=? AND student_id=? LIMIT 1");
        mysqli_stmt_bind_param($chk, 'ii', $sfa_id, $post_student);
        mysqli_stmt_execute($chk);
        $assign_row = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);

        if (!$assign_row) {
            $errors[] = 'Fee assignment not found or does not belong to this student.';
        } elseif ($assign_row['status'] === 'paid') {
            $errors[] = 'This fee is already fully paid.';
        } elseif ($amount_paid > ($assign_row['net_amount'] + 0.01)) {
            $errors[] = sprintf('Payment (%s %.2f) exceeds outstanding balance (%s %.2f).', $currency, $amount_paid, $currency, $assign_row['net_amount']);
        }
    }

    // Duplicate check: same assignment + same date + same amount
    if (empty($errors)) {
        $dup = mysqli_prepare($conn, "SELECT id FROM fee_payments WHERE student_fee_id=? AND amount_paid=? AND payment_date=? LIMIT 1");
        mysqli_stmt_bind_param($dup, 'ids', $sfa_id, $amount_paid, $payment_date);
        mysqli_stmt_execute($dup);
        mysqli_stmt_store_result($dup);
        if (mysqli_stmt_num_rows($dup) > 0)
            $errors[] = 'A duplicate payment with the same amount and date already exists for this fee assignment.';
        mysqli_stmt_close($dup);
    }

    if (empty($errors)) {
        // Wrap in transaction for atomicity
        mysqli_begin_transaction($conn);
        try {
            // Generate unique receipt number
            $receipt_no = 'RCPT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            // Ensure uniqueness
            $uniq = mysqli_prepare($conn, "SELECT id FROM fee_payments WHERE receipt_no=? LIMIT 1");
            mysqli_stmt_bind_param($uniq, 's', $receipt_no);
            mysqli_stmt_execute($uniq);
            mysqli_stmt_store_result($uniq);
            if (mysqli_stmt_num_rows($uniq) > 0)
                $receipt_no = 'RCPT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            mysqli_stmt_close($uniq);

            // Insert payment
            $ins = mysqli_prepare($conn,
                "INSERT INTO fee_payments (receipt_no, student_fee_id, student_id, amount_paid, payment_method, payment_date, reference_no, remarks)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins, 'siidssss', $receipt_no, $sfa_id, $post_student, $amount_paid, $payment_method, $payment_date, $reference_no, $remarks);
            mysqli_stmt_execute($ins);
            $payment_id = mysqli_insert_id($conn);
            mysqli_stmt_close($ins);

            // Update fee assignment balance
            $new_net    = max(0, $assign_row['net_amount'] - $amount_paid);
            $new_status = ($new_net <= 0.009) ? 'paid' : 'partial';
            $upd = mysqli_prepare($conn, "UPDATE student_fee_assignments SET net_amount=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($upd, 'dsi', $new_net, $new_status, $sfa_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            // Record in financial ledger (income) — avoid double-counting
            $ft_desc = "Fee payment – receipt {$receipt_no}";
            $ft = mysqli_prepare($conn,
                "INSERT INTO financial_transactions (trans_date, type, category, amount, title, description, payment_method, reference_type, reference_id, receipt_invoice_no)
                 VALUES (?, 'income', 'Fee Collection', ?, ?, ?, ?, 'fee_payment', ?, ?)");
            mysqli_stmt_bind_param($ft, 'sdsssiss',
                $payment_date, $amount_paid, $ft_desc, $ft_desc, $payment_method, $payment_id, $receipt_no);
            mysqli_stmt_execute($ft);
            mysqli_stmt_close($ft);

            // Log activity
            logActivity('fee_payment_recorded', 'fee_payments', $payment_id,
                "Collected {$currency} {$amount_paid} from student #{$post_student}, receipt {$receipt_no}");

            mysqli_commit($conn);

            $_SESSION['flash_success'] = "Payment recorded! Receipt: <strong>{$receipt_no}</strong> &nbsp; <a href='receipt.php?receipt_no=" . urlencode($receipt_no) . "' target='_blank'>🖨 Print Receipt</a>";
            header('Location: fee_payments.php?student_id=' . $post_student);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Transaction failed: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Fee Collection</h1>
        <p class="page-header__desc">Search for a student, view their fee balance, and record a payment.</p>
    </div>
    <div class="page-header__actions">
        <a href="fee_dues.php" class="btn-admin btn-admin--secondary">View All Dues</a>
        <a href="dashboard.php" class="btn-admin btn-admin--ghost">← Dashboard</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <strong>Errors:</strong>
    <ul style="margin:6px 0 0 16px;"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>
<?php if ($success_msg): ?>
<div class="alert alert-success flash-auto-dismiss" role="alert"><?php echo $success_msg; // contains intentional HTML links ?></div>
<?php endif; ?>
<?php if ($error_msg): ?>
<div class="alert alert-danger flash-auto-dismiss" role="alert"><?php echo htmlspecialchars($error_msg); ?></div>
<?php endif; ?>

<!-- ─── STUDENT SEARCH ─────────────────────────────────────── -->
<div class="panel animate-in">
    <div class="panel__header"><h3 class="panel__title">🔍 Search Student</h3></div>
    <form method="GET" action="fee_payments.php" class="filter-form" style="padding:16px 20px;display:flex;gap:12px;align-items:flex-end;">
        <div class="form-group" style="margin:0;flex:1;">
            <label class="form-label">Student Name or Admission Number</label>
            <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="e.g. Ahmed, A-2024-001" autofocus>
        </div>
        <button type="submit" class="btn-admin btn-admin--primary">Search</button>
        <?php if ($student_id > 0): ?>
        <a href="fee_payments.php" class="btn-admin btn-admin--ghost">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($search_q !== '' && !empty($students)): ?>
    <div style="padding:0 20px 16px;">
        <p style="font-size:.85rem;color:var(--admin-text-muted);margin-bottom:8px;"><?php echo count($students); ?> student(s) found — click a name to load fees:</p>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($students as $st): ?>
            <a href="fee_payments.php?student_id=<?php echo $st['id']; ?>" class="btn-admin btn-admin--sm <?php echo $student_id === (int)$st['id'] ? 'btn-admin--primary' : 'btn-admin--secondary'; ?>">
                <?php echo htmlspecialchars($st['first_name'].' '.$st['last_name']); ?>
                <small>(<?php echo htmlspecialchars($st['admission_no']); ?>)</small>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif ($search_q !== ''): ?>
    <p style="padding:12px 20px;color:var(--admin-text-muted);font-size:.85rem;">No active students matched "<strong><?php echo htmlspecialchars($search_q); ?></strong>".</p>
    <?php endif; ?>
</div>

<?php if ($selected_student): ?>
<!-- ─── STUDENT PROFILE + FEE BALANCE ────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:0;">

    <div class="panel animate-in">
        <div class="panel__header"><h3 class="panel__title">👤 Student Details</h3></div>
        <div style="padding:16px 20px;display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;font-size:.9rem;">
            <div><span style="color:var(--admin-text-muted);">Name</span><br><strong><?php echo htmlspecialchars($selected_student['first_name'].' '.$selected_student['last_name']); ?></strong></div>
            <div><span style="color:var(--admin-text-muted);">Adm No</span><br><strong style="color:var(--admin-accent);"><?php echo htmlspecialchars($selected_student['admission_no']); ?></strong></div>
            <div><span style="color:var(--admin-text-muted);">Class</span><br><?php echo htmlspecialchars(($selected_student['class_name'] ?? 'N/A') . ' – ' . ($selected_student['section_name'] ?? 'N/A')); ?></div>
            <div><span style="color:var(--admin-text-muted);">Status</span><br><span class="badge badge--<?php echo $selected_student['status']==='active'?'success':'danger'; ?>"><?php echo ucfirst($selected_student['status']); ?></span></div>
        </div>
    </div>

    <!-- Fee Assignments Overview -->
    <div class="panel animate-in">
        <div class="panel__header"><h3 class="panel__title">💳 Fee Summary</h3></div>
        <?php
        $total_outstanding = array_sum(array_column(array_filter($fee_assignments, fn($a)=>$a['status']!=='paid'), 'net_amount'));
        $paid_count   = count(array_filter($fee_assignments, fn($a)=>$a['status']==='paid'));
        $unpaid_count = count(array_filter($fee_assignments, fn($a)=>$a['status']!=='paid'));
        ?>
        <div style="padding:16px 20px;display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;font-size:.9rem;">
            <div><span style="color:var(--admin-text-muted);">Total Due</span><br><strong style="color:var(--admin-red);font-size:1.2rem;"><?php echo $currency; ?> <?php echo number_format($total_outstanding, 2); ?></strong></div>
            <div><span style="color:var(--admin-text-muted);">Invoices</span><br><?php echo $unpaid_count; ?> pending, <?php echo $paid_count; ?> paid</div>
        </div>
    </div>
</div>

<!-- ─── FEE ASSIGNMENTS TABLE ────────────────────────────── -->
<div class="panel animate-in">
    <div class="panel__header"><h3 class="panel__title">📋 Fee Assignments</h3></div>
    <?php if (!empty($fee_assignments)): ?>
    <div class="table-responsive">
        <table class="recent-table">
            <thead>
                <tr>
                    <th>Fee Title</th>
                    <th>Due Date</th>
                    <th style="text-align:right;">Assigned</th>
                    <th style="text-align:right;">Discount</th>
                    <th style="text-align:right;color:var(--admin-red);">Balance Due</th>
                    <th>Status</th>
                    <th style="text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($fee_assignments as $fa): ?>
                <tr>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($fa['fee_title']); ?></td>
                    <td style="font-size:.85rem;color:<?php echo (strtotime($fa['due_date'])<time()&&$fa['status']!=='paid')?'var(--admin-red)':'var(--admin-text-muted)'; ?>;">
                        <?php echo date('M j, Y', strtotime($fa['due_date'])); ?>
                    </td>
                    <td style="text-align:right;"><?php echo $currency; ?> <?php echo number_format($fa['assigned_amount'],2); ?></td>
                    <td style="text-align:right;color:var(--admin-text-muted);"><?php echo $currency; ?> <?php echo number_format($fa['discount_amount'],2); ?></td>
                    <td style="text-align:right;font-weight:700;color:<?php echo $fa['status']==='paid'?'#22c55e':'var(--admin-red)'; ?>;">
                        <?php echo $fa['status']==='paid' ? '✅ Paid' : ($currency.' '.number_format($fa['net_amount'],2)); ?>
                    </td>
                    <td><span class="badge badge--<?php echo ['paid'=>'success','partial'=>'warning','unpaid'=>'danger'][$fa['status']] ?? 'secondary'; ?>"><?php echo ucfirst($fa['status']); ?></span></td>
                    <td style="text-align:right;">
                        <?php if ($fa['status'] !== 'paid'): ?>
                        <button type="button" class="btn-admin btn-admin--sm btn-admin--primary"
                            onclick="openPayForm(<?php echo $fa['sfa_id']; ?>, '<?php echo addslashes($fa['fee_title']); ?>', <?php echo $fa['net_amount']; ?>)">
                            Collect
                        </button>
                        <?php else: ?>
                        <span style="color:#22c55e;font-size:.8rem;">✔ Done</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><p>No fee assignments found for this student.</p></div>
    <?php endif; ?>
</div>

<!-- ─── PAYMENT HISTORY ──────────────────────────────────── -->
<?php if (!empty($payment_history)): ?>
<div class="panel animate-in">
    <div class="panel__header"><h3 class="panel__title">📂 Payment History</h3></div>
    <div class="table-responsive">
        <table class="recent-table">
            <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Fee</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th style="text-align:right;">Amount</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($payment_history as $ph): ?>
            <tr>
                <td style="font-weight:600;color:var(--admin-accent);font-size:.8rem;"><?php echo htmlspecialchars($ph['receipt_no']); ?></td>
                <td><?php echo htmlspecialchars($ph['fee_title']); ?></td>
                <td><span class="badge badge--info"><?php echo ucfirst(str_replace('_',' ',$ph['payment_method'])); ?></span></td>
                <td style="font-size:.8rem;color:var(--admin-text-muted);"><?php echo htmlspecialchars($ph['reference_no'] ?: '—'); ?></td>
                <td style="text-align:right;font-weight:700;color:#0d9488;"><?php echo $currency; ?> <?php echo number_format($ph['amount_paid'],2); ?></td>
                <td style="font-size:.8rem;"><?php echo date('M j, Y', strtotime($ph['payment_date'])); ?></td>
                <td>
                    <a href="receipt.php?receipt_no=<?php echo urlencode($ph['receipt_no']); ?>" target="_blank" class="btn-admin btn-admin--sm btn-admin--ghost">🖨 Print</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ─── PAYMENT MODAL ────────────────────────────────────── -->
<div id="paymentModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--admin-card);border:1px solid var(--admin-border);border-radius:16px;padding:28px 32px;width:100%;max-width:480px;box-shadow:0 24px 60px rgba(0,0,0,.4);">
        <h2 style="margin-top:0;font-size:1.15rem;color:var(--admin-heading);">Record Fee Payment</h2>
        <form method="POST" action="fee_payments.php?student_id=<?php echo $student_id; ?>" id="payForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <input type="hidden" name="action"         value="add_payment">
            <input type="hidden" name="student_id"     value="<?php echo $student_id; ?>">
            <input type="hidden" name="student_fee_id" id="modal_sfa_id" value="">

            <div style="background:rgba(13,148,136,.08);border:1px solid rgba(13,148,136,.2);border-radius:10px;padding:12px 16px;margin-bottom:16px;">
                <div style="font-size:.85rem;color:var(--admin-text-muted);">Fee</div>
                <div id="modal_fee_title" style="font-weight:700;color:var(--admin-heading);font-size:1rem;">—</div>
                <div style="font-size:.85rem;color:var(--admin-text-muted);margin-top:4px;">Outstanding Balance: <strong id="modal_balance" style="color:var(--admin-red);">—</strong></div>
            </div>

            <div class="form-group">
                <label class="form-label">Amount to Pay <span style="color:var(--admin-red);">*</span></label>
                <input type="number" name="amount_paid" id="modal_amount" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
                <small style="color:var(--admin-text-muted);">Must not exceed outstanding balance.</small>
            </div>
            <div class="form-group">
                <label class="form-label">Payment Method</label>
                <select name="payment_method" class="form-control">
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cheque">Cheque</option>
                    <option value="online">Online</option>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Payment Date</label>
                    <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Reference No.</label>
                    <input type="text" name="reference_no" class="form-control" placeholder="Optional">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="Optional notes"></textarea>
            </div>

            <div style="display:flex;gap:12px;margin-top:4px;">
                <button type="submit" class="btn-admin btn-admin--primary" style="flex:1;">✓ Confirm Payment</button>
                <button type="button" class="btn-admin btn-admin--ghost" onclick="document.getElementById('paymentModal').style.display='none';">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPayForm(sfaId, feeTitle, balance) {
    document.getElementById('modal_sfa_id').value    = sfaId;
    document.getElementById('modal_fee_title').textContent = feeTitle;
    document.getElementById('modal_balance').textContent   = '<?php echo $currency; ?> ' + parseFloat(balance).toFixed(2);
    document.getElementById('modal_amount').value    = '';
    document.getElementById('modal_amount').max      = balance;
    document.getElementById('paymentModal').style.display = 'flex';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.getElementById('paymentModal').style.display = 'none';
});
</script>

<?php endif; // if selected_student ?>

<?php include __DIR__ . '/footer.php'; ?>
