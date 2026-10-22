<?php
/**
 * accountant/salaries.php
 * Staff Salary Payment Recording — Accountant Portal
 * SRS Section 7.4 — permitted salary recording
 */
require_once __DIR__ . '/auth_check.php';

$page_title   = 'Salary Payments';
$current_page = 'salaries';

$errors      = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$action    = $_GET['action']   ?? 'list';
$staff_id  = (int)($_GET['staff_id'] ?? 0);
$year_f    = (int)($_GET['year']     ?? date('Y'));
$month_f   = (int)($_GET['month']    ?? date('n'));

// Fetch active staff list
$staff_list = [];
$res = mysqli_query($conn, "SELECT id, employee_code, first_name, last_name, designation, basic_salary FROM staff WHERE status='active' ORDER BY first_name ASC");
while ($r = mysqli_fetch_assoc($res)) $staff_list[] = $r;

// Fetch selected staff
$selected_staff = null;
if ($staff_id > 0) {
    $st = mysqli_prepare($conn, "SELECT * FROM staff WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($st, 'i', $staff_id);
    mysqli_stmt_execute($st);
    $selected_staff = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
}

// ─── POST: Record Salary Payment ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security token invalid.';
        header("Location: salaries.php?action=add&staff_id={$staff_id}");
        exit();
    }

    $post_staff_id    = (int)($_POST['staff_id']       ?? 0);
    $salary_month     = (int)($_POST['salary_month']   ?? date('n'));
    $salary_year      = (int)($_POST['salary_year']    ?? date('Y'));
    $allowances       = (float)($_POST['allowances']   ?? 0);
    $deductions       = (float)($_POST['deductions']   ?? 0);
    $payment_date     = trim($_POST['payment_date']    ?? date('Y-m-d'));
    $payment_method   = $_POST['payment_method']       ?? 'bank_transfer';
    $notes            = trim($_POST['notes']           ?? '');

    if ($post_staff_id <= 0) $errors[] = 'Please select a staff member.';
    if ($salary_month < 1 || $salary_month > 12) $errors[] = 'Invalid salary month.';
    if ($salary_year < 2000 || $salary_year > 2100) $errors[] = 'Invalid salary year.';

    // Get staff salary info
    $staff_data = null;
    if (empty($errors)) {
        $schk = mysqli_prepare($conn, "SELECT first_name, last_name, employee_code, basic_salary FROM staff WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($schk, 'i', $post_staff_id);
        mysqli_stmt_execute($schk);
        $staff_data = mysqli_fetch_assoc(mysqli_stmt_get_result($schk));
        mysqli_stmt_close($schk);
        if (!$staff_data) $errors[] = 'Staff member not found.';
    }

    // Check for duplicate payment for same month/year
    if (empty($errors)) {
        $dup = mysqli_prepare($conn, "SELECT id FROM staff_salaries WHERE staff_id=? AND salary_month=? AND salary_year=? LIMIT 1");
        mysqli_stmt_bind_param($dup, 'iii', $post_staff_id, $salary_month, $salary_year);
        mysqli_stmt_execute($dup);
        mysqli_stmt_store_result($dup);
        if (mysqli_stmt_num_rows($dup) > 0)
            $errors[] = 'A salary record already exists for this staff member for the selected month/year.';
        mysqli_stmt_close($dup);
    }

    if (empty($errors)) {
        $basic        = (float)($staff_data['basic_salary'] ?? 0);
        $net_salary   = $basic + $allowances - $deductions;

        mysqli_begin_transaction($conn);
        try {
            $ins = mysqli_prepare($conn,
                "INSERT INTO staff_salaries (staff_id, salary_month, salary_year, basic_amount, allowances, deductions, net_salary, payment_date, payment_method, notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid')");
            mysqli_stmt_bind_param($ins, 'iiiddddsss',
                $post_staff_id, $salary_month, $salary_year,
                $basic, $allowances, $deductions, $net_salary,
                $payment_date, $payment_method, $notes);
            mysqli_stmt_execute($ins);
            $sal_id = mysqli_insert_id($conn);
            mysqli_stmt_close($ins);

            // Record in financial ledger
            $month_name = date('F', mktime(0, 0, 0, $salary_month, 1));
            $ft_title = "Salary – {$staff_data['first_name']} {$staff_data['last_name']} ({$month_name} {$salary_year})";
            $ft = mysqli_prepare($conn,
                "INSERT INTO financial_transactions (trans_date, type, category, amount, title, description, payment_method, reference_type, reference_id)
                 VALUES (?, 'expense', 'Salary', ?, ?, ?, ?, 'salary', ?)");
            mysqli_stmt_bind_param($ft, 'sdsssi',
                $payment_date, $net_salary, $ft_title, $ft_title, $payment_method, $sal_id);
            mysqli_stmt_execute($ft);
            mysqli_stmt_close($ft);

            logActivity('salary_recorded', 'staff_salaries', $sal_id,
                "Salary {$currency} {$net_salary} recorded for staff #{$post_staff_id} ({$month_name} {$salary_year})");

            mysqli_commit($conn);
            $_SESSION['flash_success'] = "Salary of {$currency} " . number_format($net_salary, 2) . " recorded for {$staff_data['first_name']} {$staff_data['last_name']}.";
            header("Location: salaries.php");
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Transaction failed: ' . $e->getMessage();
        }
    }
}

// Fetch salary records
$salary_records = [];
$where = "WHERE 1=1";
if ($staff_id > 0) $where .= " AND ss.staff_id = " . $staff_id;
if ($year_f > 0)   $where .= " AND ss.salary_year = " . $year_f;
if ($month_f > 0)  $where .= " AND ss.salary_month = " . $month_f;

$res = mysqli_query($conn, "
    SELECT ss.*, CONCAT(s.first_name,' ',s.last_name) AS staff_name, s.employee_code, s.designation
    FROM staff_salaries ss
    JOIN staff s ON ss.staff_id = s.id
    {$where}
    ORDER BY ss.salary_year DESC, ss.salary_month DESC, ss.id DESC
");
if ($res) while ($r = mysqli_fetch_assoc($res)) $salary_records[] = $r;
$total_paid = array_sum(array_column($salary_records, 'net_salary'));

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Staff Salary Payments</h1>
        <p class="page-header__desc">Record and view authorized monthly salary payments.</p>
    </div>
    <div class="page-header__actions">
        <a href="salaries.php?action=add" class="btn-admin btn-admin--primary">+ Record Salary</a>
        <a href="reports.php?tab=salaries" class="btn-admin btn-admin--ghost">Salary Report</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong>Error:</strong>
    <ul style="margin:6px 0 0 16px;"><?php foreach($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>
<?php if ($success_msg): ?><div class="alert alert-success flash-auto-dismiss"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>
<?php if ($error_msg):   ?><div class="alert alert-danger flash-auto-dismiss"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>

<!-- Record Salary Form -->
<?php if ($action === 'add' || !empty($errors)): ?>
<div class="panel animate-in">
    <div class="panel__header">
        <h3 class="panel__title">Record Salary Payment</h3>
        <a href="salaries.php" class="btn-admin btn-admin--sm btn-admin--ghost">Cancel</a>
    </div>
    <form method="POST" action="salaries.php?action=add" style="padding:16px 20px;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label">Staff Member <span style="color:var(--admin-red);">*</span></label>
                <select name="staff_id" class="form-control" required onchange="loadBasicSalary(this)">
                    <option value="">— Select Staff —</option>
                    <?php foreach ($staff_list as $s): ?>
                    <option value="<?php echo $s['id']; ?>" data-basic="<?php echo $s['basic_salary']; ?>" <?php echo $staff_id===$s['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($s['first_name'].' '.$s['last_name'].' ('.$s['employee_code'].')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Basic Salary (<?php echo $currency; ?>)</label>
                <input type="text" id="display_basic" class="form-control" disabled placeholder="Auto-filled from staff record">
            </div>
            <div class="form-group">
                <label class="form-label">Salary Month <span style="color:var(--admin-red);">*</span></label>
                <select name="salary_month" class="form-control">
                    <?php for ($m=1;$m<=12;$m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo (int)date('n')===$m?'selected':''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Salary Year <span style="color:var(--admin-red);">*</span></label>
                <input type="number" name="salary_year" class="form-control" value="<?php echo date('Y'); ?>" min="2020" max="2099">
            </div>
            <div class="form-group">
                <label class="form-label">Allowances (<?php echo $currency; ?>)</label>
                <input type="number" name="allowances" class="form-control" step="0.01" min="0" value="0" id="inp_allowances" oninput="calcNet()">
            </div>
            <div class="form-group">
                <label class="form-label">Deductions (<?php echo $currency; ?>)</label>
                <input type="number" name="deductions" class="form-control" step="0.01" min="0" value="0" id="inp_deductions" oninput="calcNet()">
            </div>
            <div class="form-group">
                <label class="form-label">Net Salary</label>
                <input type="text" id="display_net" class="form-control" disabled style="font-weight:700;color:#0d9488;">
            </div>
            <div class="form-group">
                <label class="form-label">Payment Date</label>
                <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Payment Method</label>
                <select name="payment_method" class="form-control">
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cash">Cash</option>
                    <option value="cheque">Cheque</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Optional"></textarea>
        </div>
        <button type="submit" class="btn-admin btn-admin--primary">✓ Record Salary Payment</button>
    </form>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filter-card animate-in">
    <form method="GET" action="salaries.php" class="filter-form" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="margin:0;min-width:200px;">
            <label class="form-label" style="font-size:.8rem;">Staff Member</label>
            <select name="staff_id" class="form-control">
                <option value="">All Staff</option>
                <?php foreach ($staff_list as $s): ?>
                <option value="<?php echo $s['id']; ?>" <?php echo $staff_id===$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:.8rem;">Month</label>
            <select name="month" class="form-control">
                <option value="">All Months</option>
                <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?php echo $m; ?>" <?php echo $month_f===$m?'selected':''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:.8rem;">Year</label>
            <input type="number" name="year" class="form-control" value="<?php echo $year_f; ?>" min="2020" max="2099" style="width:90px;">
        </div>
        <button type="submit" class="btn-admin btn-admin--primary">Filter</button>
        <a href="salaries.php" class="btn-admin btn-admin--ghost">Reset</a>
    </form>
</div>

<!-- Salary Records Table -->
<div class="panel animate-in">
    <div class="panel__header">
        <h3 class="panel__title">Salary Records (<?php echo count($salary_records); ?>)</h3>
        <strong style="color:#0d9488;"><?php echo $currency; ?> <?php echo number_format($total_paid, 2); ?> total paid</strong>
    </div>
    <?php if (!empty($salary_records)): ?>
    <div class="table-responsive">
        <table class="recent-table">
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Designation</th>
                    <th>Period</th>
                    <th style="text-align:right;">Basic</th>
                    <th style="text-align:right;">+Allow</th>
                    <th style="text-align:right;">-Ded.</th>
                    <th style="text-align:right;">Net</th>
                    <th>Method</th>
                    <th>Paid On</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($salary_records as $sr): ?>
            <tr>
                <td style="font-weight:600;"><?php echo htmlspecialchars($sr['staff_name']); ?><br><small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($sr['employee_code']); ?></small></td>
                <td style="font-size:.85rem;color:var(--admin-text-muted);"><?php echo htmlspecialchars($sr['designation']); ?></td>
                <td><?php echo date('M Y', mktime(0,0,0,$sr['salary_month'],1,$sr['salary_year'])); ?></td>
                <td style="text-align:right;"><?php echo $currency; ?> <?php echo number_format($sr['basic_amount'],2); ?></td>
                <td style="text-align:right;color:#22c55e;">+ <?php echo number_format($sr['allowances'],2); ?></td>
                <td style="text-align:right;color:var(--admin-red);">- <?php echo number_format($sr['deductions'],2); ?></td>
                <td style="text-align:right;font-weight:700;color:#0d9488;"><?php echo $currency; ?> <?php echo number_format($sr['net_salary'],2); ?></td>
                <td><span class="badge badge--info"><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$sr['payment_method']))); ?></span></td>
                <td style="font-size:.85rem;"><?php echo date('M j, Y', strtotime($sr['payment_date'])); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><p>No salary records for the selected filter.</p></div>
    <?php endif; ?>
</div>

<script>
var basicSalary = 0;
function loadBasicSalary(sel) {
    basicSalary = parseFloat(sel.options[sel.selectedIndex].dataset.basic || 0);
    document.getElementById('display_basic').value = '<?php echo $currency; ?> ' + basicSalary.toFixed(2);
    calcNet();
}
function calcNet() {
    var allow = parseFloat(document.getElementById('inp_allowances').value) || 0;
    var ded   = parseFloat(document.getElementById('inp_deductions').value) || 0;
    document.getElementById('display_net').value = '<?php echo $currency; ?> ' + (basicSalary + allow - ded).toFixed(2);
}
// Pre-fill if staff selected
var selStaff = document.querySelector('select[name="staff_id"]');
if (selStaff && selStaff.value) loadBasicSalary(selStaff);
</script>

<?php include __DIR__ . '/footer.php'; ?>
