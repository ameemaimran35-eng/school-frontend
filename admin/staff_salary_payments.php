<?php
/**
 * admin/staff_salary_payments.php
 * Staff Salary Management and Salary Slip Generation
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();

$page_title = 'Staff Salary Management';
$current_page = 'salary';

$staff_id = (int)($_GET['staff_id'] ?? 0);
$action = $_GET['action'] ?? 'list';
$slip_id = (int)($_GET['slip_id'] ?? 0);

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Fetch all active staff for the dropdown
$staff_list = [];
$res = mysqli_query($conn, "SELECT id, employee_code, first_name, last_name, designation, basic_salary FROM staff WHERE status='active' ORDER BY first_name ASC");
while ($r = mysqli_fetch_assoc($res)) {
    $staff_list[] = $r;
}

// Fetch staff details if recording for a specific staff member
$staff = null;
if ($staff_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM staff WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $staff_id);
    mysqli_stmt_execute($stmt);
    $staff = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

// ─── POST HANDLER: RECORD SALARY PAYMENT ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
        header("Location: staff_salary_payments.php" . ($staff_id > 0 ? "?staff_id={$staff_id}&action=add" : ""));
        exit();
    }

    $post_staff_id  = (int)($_POST['staff_id'] ?? $staff_id);
    $salary_month   = (int)($_POST['salary_month'] ?? date('n'));
    $salary_year    = (int)($_POST['salary_year'] ?? date('Y'));
    $allowances     = (float)($_POST['allowances'] ?? 0);
    $deductions     = (float)($_POST['deductions'] ?? 0);
    $payment_date   = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $payment_method = $_POST['payment_method'] ?? 'bank_transfer';
    $notes          = trim($_POST['notes'] ?? '');

    // Lookup staff
    $s_chk = mysqli_prepare($conn, "SELECT first_name, last_name, employee_code, basic_salary FROM staff WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($s_chk, 'i', $post_staff_id);
    mysqli_stmt_execute($s_chk);
    $selected_staff = mysqli_fetch_assoc(mysqli_stmt_get_result($s_chk));
    mysqli_stmt_close($s_chk);

    if (!$selected_staff) {
        $errors[] = 'Please select a valid staff member.';
    }
    if ($salary_month < 1 || $salary_month > 12) {
        $errors[] = 'Valid salary month (1-12) is required.';
    }
    if ($salary_year < 2000 || $salary_year > 2100) {
        $errors[] = 'Valid salary year is required.';
    }

    // Duplicate check for staff + month + year
    if (empty($errors)) {
        $dup = mysqli_prepare($conn, "SELECT id FROM staff_salaries WHERE staff_id = ? AND salary_month = ? AND salary_year = ? LIMIT 1");
        mysqli_stmt_bind_param($dup, 'iii', $post_staff_id, $salary_month, $salary_year);
        mysqli_stmt_execute($dup);
        mysqli_stmt_store_result($dup);
        if (mysqli_stmt_num_rows($dup) > 0) {
            $month_name = date('F', mktime(0, 0, 0, $salary_month, 10));
            $errors[] = "Salary for {$selected_staff['first_name']} for {$month_name} {$salary_year} has already been paid.";
        }
        mysqli_stmt_close($dup);
    }

    if (empty($errors)) {
        $basic_amount = (float)$selected_staff['basic_salary'];
        $net_salary = max(0, $basic_amount + $allowances - $deductions);
        $receipt_no = "SAL-{$salary_year}" . str_pad((string)$salary_month, 2, '0', STR_PAD_LEFT) . "-{$post_staff_id}";

        mysqli_begin_transaction($conn);
        try {
            // Insert into staff_salaries
            $stmt = mysqli_prepare($conn, "INSERT INTO staff_salaries 
                (staff_id, salary_month, salary_year, basic_amount, allowances, deductions, net_salary, payment_date, payment_method, status, transaction_ref, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iiiddddssss', $post_staff_id, $salary_month, $salary_year, $basic_amount, $allowances, $deductions, $net_salary, $payment_date, $payment_method, $receipt_no, $notes);
            mysqli_stmt_execute($stmt);
            $salary_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Insert into financial_transactions
            $ft_title = "Salary: {$selected_staff['first_name']} {$selected_staff['last_name']} (" . date('M Y', mktime(0,0,0,$salary_month,1,$salary_year)) . ")";
            $ft_desc = "Basic: Rs " . number_format($basic_amount, 2) . " | Allowances: Rs " . number_format($allowances, 2) . " | Deductions: Rs " . number_format($deductions, 2);
            $ft_stmt = mysqli_prepare($conn, "INSERT INTO financial_transactions 
                (trans_date, type, category, amount, title, description, payment_method, reference_type, reference_id, receipt_invoice_no) 
                VALUES (?, 'expense', 'Salaries', ?, ?, ?, ?, 'salary_payment', ?, ?)");
            mysqli_stmt_bind_param($ft_stmt, 'sdsssis', $payment_date, $net_salary, $ft_title, $ft_desc, $payment_method, $salary_id, $receipt_no);
            mysqli_stmt_execute($ft_stmt);
            mysqli_stmt_close($ft_stmt);

            mysqli_commit($conn);
            logActivity('salary_paid', 'staff_salaries', $salary_id, "Paid salary of Rs {$net_salary} to {$selected_staff['first_name']} {$selected_staff['last_name']} for {$salary_month}/{$salary_year}");
            $_SESSION['flash_success'] = "Salary payment recorded successfully for {$selected_staff['first_name']} {$selected_staff['last_name']}.";
            header("Location: staff_salary_payments.php?slip_id={$salary_id}");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// ─── QUERY SALARY PAYMENTS HISTORY ──────────────────────────────
$payments_history = [];
$total_salary_paid_all = 0;
$res = mysqli_query($conn, "SELECT ss.*, s.first_name, s.last_name, s.employee_code, s.designation, s.department
    FROM staff_salaries ss
    JOIN staff s ON ss.staff_id = s.id
    ORDER BY ss.payment_date DESC, ss.id DESC");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $payments_history[] = $r;
        $total_salary_paid_all += (float)$r['net_salary'];
    }
}

// ─── QUERY SALARY SLIP IF REQUESTED ─────────────────────────────
$salary_slip = null;
if ($slip_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT ss.*, s.first_name, s.last_name, s.employee_code, s.designation, s.department, s.phone, s.email, s.joining_date
        FROM staff_salaries ss
        JOIN staff s ON ss.staff_id = s.id
        WHERE ss.id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $slip_id);
    mysqli_stmt_execute($stmt);
    $salary_slip = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Staff Salary Payments</h1>
        <p class="page-header__desc">Record monthly staff salaries, track disbursement history, and print salary slips.</p>
    </div>
    <div class="page-header__actions">
        <a href="staff_salary_payments.php?action=add" class="btn-admin btn-admin--primary">+ Disburse Salary</a>
        <a href="staff.php" class="btn-admin btn-admin--secondary">Staff Directory</a>
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
            <strong>Please correct the following:</strong>
            <ul style="margin-left: 20px; margin-top: 6px;">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php if ($salary_slip): 
    $m_name = date('F', mktime(0,0,0, $salary_slip['salary_month'], 10));
?>
    <!-- Printable Salary Slip Card -->
    <div class="card animate-in" style="background:#fff;color:#1e293b;padding:36px;border-radius:12px;border:1px solid #cbd5e1;max-width:760px;margin:0 auto 30px auto;box-shadow:0 4px 20px rgba(0,0,0,0.06);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #0d9488;padding-bottom:16px;margin-bottom:24px;">
            <div>
                <h2 style="font-size:1.6rem;font-weight:800;color:#0f172a;margin-bottom:2px;">Brightway School</h2>
                <p style="color:#64748b;font-size:0.85rem;">Official Staff Salary Pay Slip</p>
            </div>
            <div style="text-align:right;">
                <button type="button" onclick="window.print();" class="btn-admin btn-admin--sm btn-admin--primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    <span>Print Slip</span>
                </button>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;background:#f8fafc;padding:16px;border-radius:8px;margin-bottom:24px;border:1px solid #e2e8f0;font-size:0.9rem;">
            <div>
                <span style="color:#64748b;display:block;font-size:0.8rem;">Employee Name:</span>
                <strong style="color:#0f172a;font-size:1.05rem;"><?php echo htmlspecialchars($salary_slip['first_name'] . ' ' . $salary_slip['last_name']); ?></strong>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:0.8rem;">Employee Code & Designation:</span>
                <strong><?php echo htmlspecialchars($salary_slip['employee_code']); ?> — <?php echo htmlspecialchars($salary_slip['designation']); ?></strong>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:0.8rem;">Salary Period:</span>
                <strong style="color:#0d9488;"><?php echo $m_name . ' ' . $salary_slip['salary_year']; ?></strong>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:0.8rem;">Disbursement Date & Ref:</span>
                <strong><?php echo date('M j, Y', strtotime($salary_slip['payment_date'])); ?> (<?php echo htmlspecialchars($salary_slip['transaction_ref'] ?? 'N/A'); ?>)</strong>
            </div>
        </div>

        <table style="width:100%;border-collapse:collapse;margin-bottom:24px;font-size:0.9rem;">
            <thead>
                <tr style="background:#f1f5f9;border-bottom:2px solid #cbd5e1;text-align:left;">
                    <th style="padding:10px 14px;color:#334155;">Salary Component</th>
                    <th style="padding:10px 14px;color:#334155;text-align:right;">Amount (PKR)</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:12px 14px;">Basic Salary</td>
                    <td style="padding:12px 14px;text-align:right;font-weight:600;">Rs <?php echo number_format($salary_slip['basic_amount'], 2); ?></td>
                </tr>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:12px 14px;color:#0d9488;">Allowances / Incentives (+)</td>
                    <td style="padding:12px 14px;text-align:right;color:#0d9488;font-weight:600;">+ Rs <?php echo number_format($salary_slip['allowances'], 2); ?></td>
                </tr>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:12px 14px;color:#dc2626;">Deductions / Penalties (-)</td>
                    <td style="padding:12px 14px;text-align:right;color:#dc2626;font-weight:600;">- Rs <?php echo number_format($salary_slip['deductions'], 2); ?></td>
                </tr>
                <tr style="background:#f0fdfa;font-weight:800;font-size:1.1rem;color:#0d9488;border-top:2px solid #0d9488;">
                    <td style="padding:14px;">Net Salary Payable</td>
                    <td style="padding:14px;text-align:right;">Rs <?php echo number_format($salary_slip['net_salary'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div style="display:flex;justify-content:space-between;margin-top:40px;padding-top:20px;border-top:1px dashed #cbd5e1;">
            <div style="text-align:center;width:180px;">
                <div style="border-bottom:1px solid #475569;height:30px;"></div>
                <div style="font-size:0.75rem;color:#475569;margin-top:6px;">Staff Signature</div>
            </div>
            <div style="text-align:center;width:180px;">
                <div style="border-bottom:1px solid #475569;height:30px;"></div>
                <div style="font-size:0.75rem;color:#475569;margin-top:6px;">Authorized Signatory / Seal</div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($action === 'add'): ?>
    <!-- Add Salary Payment Form Card -->
    <div class="card animate-in" style="padding:24px;max-width:680px;margin-bottom:28px;">
        <h3 style="font-size:1.15rem;font-weight:700;color:var(--admin-heading);margin-bottom:18px;">Disburse Staff Salary</h3>
        <form method="POST" action="staff_salary_payments.php?action=add">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="form-group">
                <label class="form-label">Select Staff Member *</label>
                <select name="staff_id" id="salary_staff_select" class="form-control" required onchange="updateBasicSalary(this)">
                    <option value="">-- Choose Staff Member --</option>
                    <?php foreach ($staff_list as $st): ?>
                        <option value="<?php echo $st['id']; ?>" data-salary="<?php echo $st['basic_salary']; ?>" <?php echo $staff_id === (int)$st['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['employee_code'] . ') — ' . $st['designation']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label">Salary Month *</label>
                    <select name="salary_month" class="form-control" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo (int)date('n') === $m ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0,0,0, $m, 10)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Salary Year *</label>
                    <input type="number" name="salary_year" class="form-control" value="<?php echo date('Y'); ?>" min="2020" max="2035" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Basic Salary (PKR)</label>
                <input type="text" id="display_basic_salary" class="form-control" value="<?php echo $staff ? number_format($staff['basic_salary'], 2) : '0.00'; ?>" readonly style="background:var(--admin-border);">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label">Allowances (+) (PKR)</label>
                    <input type="number" step="0.01" min="0" name="allowances" class="form-control" value="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Deductions (-) (PKR)</label>
                    <input type="number" step="0.01" min="0" name="deductions" class="form-control" value="0.00">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label">Disbursement Date *</label>
                    <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Mode *</label>
                    <select name="payment_method" class="form-control">
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Notes / Remarks</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes for payroll record"></textarea>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <a href="staff_salary_payments.php" class="btn-admin btn-admin--ghost">Cancel</a>
                <button type="submit" class="btn-admin btn-admin--primary">
                    Record & Post Salary
                </button>
            </div>
        </form>

        <script>
        function updateBasicSalary(sel) {
            const opt = sel.selectedOptions[0];
            const sal = opt ? opt.getAttribute('data-salary') : '0.00';
            document.getElementById('display_basic_salary').value = sal ? parseFloat(sal).toLocaleString('en-US', {minimumFractionDigits: 2}) : '0.00';
        }
        </script>
    </div>
<?php endif; ?>

<!-- Summary Stat Card -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card animate-in">
        <div class="stat-card__label">Total Salary Disbursements</div>
        <div class="stat-card__value">Rs <?php echo number_format($total_salary_paid_all, 2); ?></div>
        <span class="stat-card__change stat-card__change--neutral"><?php echo count($payments_history); ?> payments made</span>
    </div>
</div>

<!-- History Table Panel -->
<div class="panel animate-in">
    <div class="panel__header">
        <h3 class="panel__title">Salary Disbursement Ledger</h3>
        <span style="color:var(--admin-text-muted);font-size:0.85rem;">All historical staff payroll records</span>
    </div>

    <?php if (!empty($payments_history)): ?>
        <div class="table-responsive">
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Staff Member</th>
                        <th>Code & Dept</th>
                        <th>Period</th>
                        <th>Payment Mode</th>
                        <th style="text-align:right;">Basic</th>
                        <th style="text-align:right;">Net Salary</th>
                        <th style="text-align:right;">Salary Slip</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments_history as $p): 
                        $period = date('F Y', mktime(0,0,0, $p['salary_month'], 10, $p['salary_year']));
                    ?>
                    <tr>
                        <td style="font-size:0.85rem;color:var(--admin-text-muted);">
                            <?php echo date('M j, Y', strtotime($p['payment_date'])); ?>
                        </td>
                        <td style="font-weight:600;color:var(--admin-heading);">
                            <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                            <div style="font-size:0.75rem;color:var(--admin-text-muted);"><?php echo htmlspecialchars($p['designation']); ?></div>
                        </td>
                        <td style="font-size:0.85rem;">
                            <span style="color:var(--admin-accent);font-weight:600;"><?php echo htmlspecialchars($p['employee_code']); ?></span>
                            <div style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($p['department']); ?></div>
                        </td>
                        <td style="font-weight:600;"><?php echo $period; ?></td>
                        <td style="font-size:0.85rem;"><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></td>
                        <td style="text-align:right;color:var(--admin-text-muted);">Rs <?php echo number_format($p['basic_amount'], 2); ?></td>
                        <td style="text-align:right;font-weight:700;color:var(--admin-accent);">Rs <?php echo number_format($p['net_salary'], 2); ?></td>
                        <td style="text-align:right;">
                            <a href="staff_salary_payments.php?slip_id=<?php echo $p['id']; ?>" class="action-btn action-btn--view" title="View & Print Salary Slip">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>No salary disbursements recorded yet.</p>
            <a href="staff_salary_payments.php?action=add" class="btn-admin btn-admin--primary" style="margin-top:12px;">+ Record First Salary</a>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
