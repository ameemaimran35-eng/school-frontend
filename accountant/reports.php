<?php
/**
 * accountant/reports.php
 * Financial Reports — Accountant Portal
 * SRS Section 7.5: daily/monthly collections, dues, ledgers, payment methods, expenses, salaries, summaries + CSV export
 */
require_once __DIR__ . '/auth_check.php';

$page_title   = 'Financial Reports';
$current_page = 'reports';

$tab   = $_GET['tab']    ?? 'collection';
$start = $_GET['start']  ?? date('Y-m-01');
$end   = $_GET['end']    ?? date('Y-m-d');
$export = $_GET['export'] ?? '';

// ─── CSV EXPORT HANDLERS ──────────────────────────────────────────
if ($export === 'csv') {
    $report_type = $_GET['report_type'] ?? $tab;

    if ($report_type === 'collection') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="fee_collection_' . $start . '_to_' . $end . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Receipt No', 'Student Name', 'Adm No', 'Class', 'Fee Title', 'Amount Paid', 'Method', 'Reference No', 'Date']);
        $stmt = mysqli_prepare($conn,
            "SELECT fp.receipt_no, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.admission_no,
                    c.class_name, fs.fee_title, fp.amount_paid, fp.payment_method, fp.reference_no, fp.payment_date
             FROM fee_payments fp
             JOIN students s ON fp.student_id = s.id
             JOIN student_fee_assignments sfa ON fp.student_fee_id = sfa.id
             JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
             LEFT JOIN classes c ON s.class_id = c.id
             WHERE fp.payment_date BETWEEN ? AND ?
             ORDER BY fp.payment_date DESC");
        mysqli_stmt_bind_param($stmt, 'ss', $start, $end);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) fputcsv($out, array_values($r));
        fclose($out); exit();
    }

    if ($report_type === 'dues') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="outstanding_dues_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Adm No', 'Student', 'Class', 'Fee Title', 'Due Date', 'Balance Due', 'Status']);
        $res = mysqli_query($conn,
            "SELECT s.admission_no, CONCAT(s.first_name,' ',s.last_name), c.class_name, fs.fee_title, fs.due_date, sfa.net_amount, sfa.status
             FROM student_fee_assignments sfa
             JOIN students s ON sfa.student_id = s.id
             LEFT JOIN classes c ON s.class_id = c.id
             JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
             WHERE sfa.status IN ('unpaid','partial') ORDER BY fs.due_date ASC");
        while ($r = mysqli_fetch_row($res)) fputcsv($out, $r);
        fclose($out); exit();
    }

    if ($report_type === 'expenses') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="expenses_' . $start . '_to_' . $end . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Category', 'Title', 'Description', 'Method', 'Amount', 'Type']);
        $stmt = mysqli_prepare($conn,
            "SELECT trans_date, category, title, description, payment_method, amount, type
             FROM financial_transactions
             WHERE type='expense' AND trans_date BETWEEN ? AND ?
             ORDER BY trans_date DESC");
        mysqli_stmt_bind_param($stmt, 'ss', $start, $end);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) fputcsv($out, array_values($r));
        fclose($out); exit();
    }

    if ($report_type === 'salaries') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="salary_report_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Staff Name', 'Employee Code', 'Month', 'Year', 'Basic', 'Allowances', 'Deductions', 'Net Salary', 'Method', 'Paid Date', 'Notes']);
        $stmt = mysqli_prepare($conn,
            "SELECT CONCAT(s.first_name,' ',s.last_name), s.employee_code,
                    ss.salary_month, ss.salary_year, ss.basic_amount, ss.allowances, ss.deductions, ss.net_salary,
                    ss.payment_method, ss.payment_date, ss.notes
             FROM staff_salaries ss JOIN staff s ON ss.staff_id = s.id
             WHERE ss.payment_date BETWEEN ? AND ?
             ORDER BY ss.payment_date DESC");
        mysqli_stmt_bind_param($stmt, 'ss', $start, $end);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_row($res)) fputcsv($out, $r);
        fclose($out); exit();
    }

    if ($report_type === 'ledger') {
        $student_id = (int)($_GET['student_id'] ?? 0);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="student_ledger_' . $student_id . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Receipt No', 'Fee Title', 'Method', 'Reference', 'Amount Paid', 'Date', 'Remarks']);
        $stmt = mysqli_prepare($conn,
            "SELECT fp.receipt_no, fs.fee_title, fp.payment_method, fp.reference_no, fp.amount_paid, fp.payment_date, fp.remarks
             FROM fee_payments fp
             JOIN student_fee_assignments sfa ON fp.student_fee_id = sfa.id
             JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
             WHERE fp.student_id = ?
             ORDER BY fp.payment_date DESC");
        mysqli_stmt_bind_param($stmt, 'i', $student_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_row($res)) fputcsv($out, $r);
        fclose($out); exit();
    }
}

// ─── DATA FETCH FOR ACTIVE TAB ────────────────────────────────────
$data = [];

if ($tab === 'collection') {
    // Daily/Monthly fee collection report
    $stmt = mysqli_prepare($conn,
        "SELECT fp.receipt_no, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.admission_no,
                c.class_name, sec.section_name, fs.fee_title, fp.amount_paid, fp.payment_method, fp.payment_date, fp.reference_no
         FROM fee_payments fp
         JOIN students s ON fp.student_id = s.id
         JOIN student_fee_assignments sfa ON fp.student_fee_id = sfa.id
         JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
         LEFT JOIN classes c ON s.class_id = c.id
         LEFT JOIN sections sec ON s.section_id = sec.id
         WHERE fp.payment_date BETWEEN ? AND ?
         ORDER BY fp.payment_date DESC");
    mysqli_stmt_bind_param($stmt, 'ss', $start, $end);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) $data[] = $r;
    $total_collection = array_sum(array_column($data, 'amount_paid'));

    // Payment method summary
    $method_sums = [];
    foreach ($data as $d) {
        $method_sums[$d['payment_method']] = ($method_sums[$d['payment_method']] ?? 0) + $d['amount_paid'];
    }

} elseif ($tab === 'dues') {
    $res = mysqli_query($conn,
        "SELECT s.admission_no, CONCAT(s.first_name,' ',s.last_name) AS student_name,
                c.class_name, sec.section_name, fs.fee_title, fs.due_date,
                sfa.net_amount, sfa.status
         FROM student_fee_assignments sfa
         JOIN students s ON sfa.student_id = s.id
         LEFT JOIN classes c ON s.class_id = c.id
         LEFT JOIN sections sec ON s.section_id = sec.id
         JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
         WHERE sfa.status IN ('unpaid','partial') ORDER BY fs.due_date ASC");
    if ($res) while ($r = mysqli_fetch_assoc($res)) $data[] = $r;
    $total_dues = array_sum(array_column($data, 'net_amount'));

} elseif ($tab === 'expenses') {
    $stmt = mysqli_prepare($conn,
        "SELECT trans_date, category, title, description, payment_method, amount, type, reference_type
         FROM financial_transactions WHERE trans_date BETWEEN ? AND ? ORDER BY trans_date DESC");
    mysqli_stmt_bind_param($stmt, 'ss', $start, $end);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) $data[] = $r;
    $total_expenses = array_sum(array_column(array_filter($data, fn($d)=>$d['type']==='expense'), 'amount'));
    $total_income   = array_sum(array_column(array_filter($data, fn($d)=>$d['type']==='income'), 'amount'));

} elseif ($tab === 'salaries') {
    $stmt = mysqli_prepare($conn,
        "SELECT ss.*, CONCAT(s.first_name,' ',s.last_name) AS staff_name, s.employee_code, s.designation
         FROM staff_salaries ss JOIN staff s ON ss.staff_id = s.id
         WHERE ss.payment_date BETWEEN ? AND ?
         ORDER BY ss.payment_date DESC");
    mysqli_stmt_bind_param($stmt, 'ss', $start, $end);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) $data[] = $r;
    $total_salaries = array_sum(array_column($data, 'net_salary'));

} elseif ($tab === 'ledger') {
    $ledger_student_id = (int)($_GET['student_id'] ?? 0);
    $ledger_student    = null;
    if ($ledger_student_id > 0) {
        $st = mysqli_prepare($conn, "SELECT s.*, c.class_name, sec.section_name FROM students s LEFT JOIN classes c ON s.class_id=c.id LEFT JOIN sections sec ON s.section_id=sec.id WHERE s.id=? LIMIT 1");
        mysqli_stmt_bind_param($st, 'i', $ledger_student_id);
        mysqli_stmt_execute($st);
        $ledger_student = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);

        $stmt = mysqli_prepare($conn,
            "SELECT fp.receipt_no, fs.fee_title, fp.payment_method, fp.reference_no, fp.amount_paid, fp.payment_date, fp.remarks,
                    sfa.assigned_amount, sfa.discount_amount, sfa.net_amount AS remaining_net, sfa.status AS assign_status
             FROM fee_payments fp
             JOIN student_fee_assignments sfa ON fp.student_fee_id = sfa.id
             JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
             WHERE fp.student_id = ?
             ORDER BY fp.payment_date DESC");
        mysqli_stmt_bind_param($stmt, 'i', $ledger_student_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) $data[] = $r;
    }
    // Also get all outstanding assignments for this student
    $ledger_dues = [];
    if ($ledger_student_id > 0) {
        $st2 = mysqli_prepare($conn,
            "SELECT fs.fee_title, fs.due_date, sfa.assigned_amount, sfa.discount_amount, sfa.net_amount, sfa.status
             FROM student_fee_assignments sfa JOIN fee_structures fs ON sfa.fee_structure_id=fs.id
             WHERE sfa.student_id=? AND sfa.status IN ('unpaid','partial')");
        mysqli_stmt_bind_param($st2, 'i', $ledger_student_id);
        mysqli_stmt_execute($st2);
        $r2 = mysqli_stmt_get_result($st2);
        while ($row = mysqli_fetch_assoc($r2)) $ledger_dues[] = $row;
        mysqli_stmt_close($st2);
    }
}

// For ledger student search
$ledger_students = [];
$ls_q = trim($_GET['ls_q'] ?? '');
if ($tab === 'ledger' && $ls_q !== '') {
    $sq = '%'.$ls_q.'%';
    $lstmt = mysqli_prepare($conn, "SELECT id, admission_no, first_name, last_name FROM students WHERE status='active' AND (first_name LIKE ? OR last_name LIKE ? OR admission_no LIKE ?) ORDER BY first_name LIMIT 15");
    mysqli_stmt_bind_param($lstmt, 'sss', $sq, $sq, $sq);
    mysqli_stmt_execute($lstmt);
    $lr = mysqli_stmt_get_result($lstmt);
    while ($r = mysqli_fetch_assoc($lr)) $ledger_students[] = $r;
    mysqli_stmt_close($lstmt);
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Financial Reports</h1>
        <p class="page-header__desc">Generate, view, and export authorized financial reports.</p>
    </div>
    <div class="page-header__actions">
        <button type="button" onclick="window.print();" class="btn-admin btn-admin--ghost">🖨 Print</button>
    </div>
</div>

<!-- Tab Navigation -->
<div style="display:flex;gap:4px;background:var(--admin-card);border:1px solid var(--admin-border);border-radius:10px;padding:6px;margin-bottom:20px;flex-wrap:wrap;">
    <?php
    $tabs = [
        'collection' => '💵 Fee Collection',
        'dues'       => '⚠ Outstanding Dues',
        'expenses'   => '📊 Income & Expenses',
        'salaries'   => '🏦 Salaries',
        'ledger'     => '📋 Student Ledger',
    ];
    foreach ($tabs as $t => $label):
    ?>
    <a href="reports.php?tab=<?php echo $t; ?>&start=<?php echo urlencode($start); ?>&end=<?php echo urlencode($end); ?>"
       style="padding:8px 18px;border-radius:7px;font-size:.85rem;font-weight:600;text-decoration:none;transition:all .2s;<?php echo $tab===$t?'background:var(--admin-accent);color:#fff;':'color:var(--admin-text);'; ?>">
        <?php echo $label; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Date Filter (not for dues/ledger) -->
<?php if (!in_array($tab, ['dues'])): ?>
<div class="filter-card animate-in">
    <form method="GET" action="reports.php" class="filter-form" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        <?php if ($tab === 'ledger'): ?>
        <input type="hidden" name="student_id" value="<?php echo (int)($ledger_student_id ?? 0); ?>">
        <div class="form-group" style="margin:0;flex:1;">
            <label class="form-label" style="font-size:.8rem;">Search Student</label>
            <input type="text" name="ls_q" class="form-control" value="<?php echo htmlspecialchars($ls_q); ?>" placeholder="Name or admission no">
        </div>
        <?php else: ?>
        <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:.8rem;">From</label>
            <input type="date" name="start" class="form-control" value="<?php echo htmlspecialchars($start); ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:.8rem;">To</label>
            <input type="date" name="end" class="form-control" value="<?php echo htmlspecialchars($end); ?>">
        </div>
        <?php endif; ?>
        <button type="submit" class="btn-admin btn-admin--primary">Apply</button>
        <a href="reports.php?tab=<?php echo $tab; ?>" class="btn-admin btn-admin--ghost">Reset</a>

        <!-- Export buttons -->
        <?php if ($tab !== 'ledger' || !empty($ledger_student_id ?? 0)): ?>
        <a href="reports.php?tab=<?php echo $tab; ?>&export=csv&report_type=<?php echo $tab; ?>&start=<?php echo urlencode($start); ?>&end=<?php echo urlencode($end); ?><?php echo ($tab==='ledger'&&!empty($ledger_student_id??0)) ? '&student_id='.(int)($ledger_student_id??0) : ''; ?>"
           class="btn-admin btn-admin--secondary">⬇ Export CSV</a>
        <?php endif; ?>
    </form>

    <!-- Ledger student search results -->
    <?php if ($tab === 'ledger' && !empty($ledger_students)): ?>
    <div style="padding:0 0 12px 0;display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach ($ledger_students as $ls): ?>
        <a href="reports.php?tab=ledger&student_id=<?php echo $ls['id']; ?>" class="btn-admin btn-admin--sm btn-admin--secondary">
            <?php echo htmlspecialchars($ls['first_name'].' '.$ls['last_name']); ?> (<?php echo htmlspecialchars($ls['admission_no']); ?>)
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ─── REPORT CONTENT ────────────────────────────────────────── -->

<?php if ($tab === 'collection'): ?>
<div class="stats-grid animate-in" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:8px;">
    <div class="stat-card">
        <div class="stat-card__label">Total Collected</div>
        <div class="stat-card__value" style="color:#0d9488;"><?php echo $currency; ?> <?php echo number_format($total_collection ?? 0, 2); ?></div>
        <span class="stat-card__change"><?php echo count($data); ?> payments · <?php echo $start; ?> to <?php echo $end; ?></span>
    </div>
    <?php foreach (($method_sums ?? []) as $method => $amt): ?>
    <div class="stat-card">
        <div class="stat-card__label"><?php echo ucfirst(str_replace('_',' ',$method)); ?></div>
        <div class="stat-card__value"><?php echo $currency; ?> <?php echo number_format($amt, 2); ?></div>
    </div>
    <?php endforeach; ?>
</div>
<div class="panel animate-in">
    <div class="panel__header"><h3 class="panel__title">Fee Collection (<?php echo count($data); ?>)</h3></div>
    <?php if (!empty($data)): ?>
    <div class="table-responsive">
        <table class="recent-table">
            <thead><tr><th>Receipt</th><th>Student</th><th>Class</th><th>Fee</th><th>Method</th><th style="text-align:right;">Amount</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($data as $d): ?>
            <tr>
                <td style="font-weight:600;color:var(--admin-accent);font-size:.8rem;"><a href="receipt.php?receipt_no=<?php echo urlencode($d['receipt_no']); ?>" target="_blank"><?php echo htmlspecialchars($d['receipt_no']); ?></a></td>
                <td><?php echo htmlspecialchars($d['student_name']); ?><br><small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($d['admission_no']); ?></small></td>
                <td style="font-size:.85rem;"><?php echo htmlspecialchars($d['class_name']??''); ?></td>
                <td style="font-size:.85rem;"><?php echo htmlspecialchars($d['fee_title']); ?></td>
                <td><span class="badge badge--info"><?php echo ucfirst(str_replace('_',' ',$d['payment_method'])); ?></span></td>
                <td style="text-align:right;font-weight:700;color:#0d9488;"><?php echo $currency; ?> <?php echo number_format($d['amount_paid'],2); ?></td>
                <td style="font-size:.8rem;"><?php echo date('M j, Y', strtotime($d['payment_date'])); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><div class="empty-state"><p>No fee payments found for the selected period.</p></div><?php endif; ?>
</div>

<?php elseif ($tab === 'dues'): ?>
<div class="stats-grid animate-in" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:8px;">
    <div class="stat-card">
        <div class="stat-card__label" style="color:var(--admin-red);">Total Outstanding</div>
        <div class="stat-card__value" style="color:var(--admin-red);"><?php echo $currency; ?> <?php echo number_format($total_dues ?? 0, 2); ?></div>
        <span class="stat-card__change"><?php echo count($data); ?> pending invoices</span>
    </div>
</div>
<div class="panel animate-in">
    <div class="panel__header">
        <h3 class="panel__title">Outstanding Dues</h3>
        <a href="reports.php?tab=dues&export=csv&report_type=dues" class="btn-admin btn-admin--sm btn-admin--secondary">⬇ CSV</a>
    </div>
    <?php if (!empty($data)): ?>
    <div class="table-responsive">
        <table class="recent-table">
            <thead><tr><th>Student</th><th>Adm #</th><th>Class</th><th>Fee</th><th>Due Date</th><th style="text-align:right;color:var(--admin-red);">Balance</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($data as $d): ?>
            <tr>
                <td style="font-weight:600;"><?php echo htmlspecialchars($d['student_name']); ?></td>
                <td style="color:var(--admin-accent);"><?php echo htmlspecialchars($d['admission_no']); ?></td>
                <td><?php echo htmlspecialchars($d['class_name']??''); ?></td>
                <td><?php echo htmlspecialchars($d['fee_title']); ?></td>
                <td style="color:<?php echo strtotime($d['due_date'])<time()?'var(--admin-red)':'var(--admin-text-muted)'; ?>;font-size:.85rem;"><?php echo date('M j, Y',strtotime($d['due_date'])); ?></td>
                <td style="text-align:right;font-weight:700;color:var(--admin-red);"><?php echo $currency; ?> <?php echo number_format($d['net_amount'],2); ?></td>
                <td><span class="badge badge--<?php echo $d['status']==='partial'?'warning':'danger'; ?>"><?php echo ucfirst($d['status']); ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><div class="empty-state"><p>No outstanding dues! All fees settled.</p></div><?php endif; ?>
</div>

<?php elseif ($tab === 'expenses'): ?>
<div class="stats-grid animate-in" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:8px;">
    <div class="stat-card"><div class="stat-card__label" style="color:#22c55e;">Income</div><div class="stat-card__value" style="color:#22c55e;"><?php echo $currency; ?> <?php echo number_format($total_income ?? 0, 2); ?></div></div>
    <div class="stat-card"><div class="stat-card__label" style="color:var(--admin-red);">Expenses</div><div class="stat-card__value" style="color:var(--admin-red);"><?php echo $currency; ?> <?php echo number_format($total_expenses ?? 0, 2); ?></div></div>
    <div class="stat-card"><div class="stat-card__label">Net</div><div class="stat-card__value" style="color:<?php echo (($total_income??0)-($total_expenses??0))>=0?'#22c55e':'var(--admin-red)'; ?>;"><?php echo $currency; ?> <?php echo number_format(($total_income??0)-($total_expenses??0), 2); ?></div></div>
</div>
<div class="panel animate-in">
    <div class="panel__header"><h3 class="panel__title">Income &amp; Expenses</h3></div>
    <?php if (!empty($data)): ?>
    <div class="table-responsive">
        <table class="recent-table">
            <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Title</th><th>Method</th><th style="text-align:right;">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($data as $d): ?>
            <tr>
                <td style="font-size:.85rem;"><?php echo date('M j, Y',strtotime($d['trans_date'])); ?></td>
                <td><span class="badge badge--<?php echo $d['type']==='income'?'success':'danger'; ?>"><?php echo ucfirst($d['type']); ?></span></td>
                <td style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($d['category']); ?></td>
                <td><?php echo htmlspecialchars($d['title']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$d['payment_method']))); ?></td>
                <td style="text-align:right;font-weight:700;color:<?php echo $d['type']==='income'?'#22c55e':'var(--admin-red)'; ?>;"><?php echo $currency; ?> <?php echo number_format($d['amount'],2); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><div class="empty-state"><p>No records for the selected period.</p></div><?php endif; ?>
</div>

<?php elseif ($tab === 'salaries'): ?>
<div class="stats-grid animate-in" style="margin-bottom:8px;">
    <div class="stat-card"><div class="stat-card__label">Total Salaries Paid</div><div class="stat-card__value"><?php echo $currency; ?> <?php echo number_format($total_salaries ?? 0, 2); ?></div><span class="stat-card__change"><?php echo count($data); ?> payments · <?php echo $start; ?> to <?php echo $end; ?></span></div>
</div>
<div class="panel animate-in">
    <div class="panel__header"><h3 class="panel__title">Salary Payments</h3></div>
    <?php if (!empty($data)): ?>
    <div class="table-responsive">
        <table class="recent-table">
            <thead><tr><th>Staff</th><th>Period</th><th style="text-align:right;">Basic</th><th style="text-align:right;">Net</th><th>Method</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($data as $d): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($d['staff_name']); ?></strong><br><small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($d['employee_code']); ?></small></td>
                <td><?php echo date('M Y', mktime(0,0,0,$d['salary_month'],1,$d['salary_year'])); ?></td>
                <td style="text-align:right;"><?php echo $currency; ?> <?php echo number_format($d['basic_amount'],2); ?></td>
                <td style="text-align:right;font-weight:700;color:#0d9488;"><?php echo $currency; ?> <?php echo number_format($d['net_salary'],2); ?></td>
                <td><?php echo ucfirst(str_replace('_',' ',$d['payment_method'])); ?></td>
                <td style="font-size:.85rem;"><?php echo date('M j, Y', strtotime($d['payment_date'])); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><div class="empty-state"><p>No salary payments for this period.</p></div><?php endif; ?>
</div>

<?php elseif ($tab === 'ledger'): ?>
<?php if (!empty($ledger_student ?? null)): ?>
<div class="stats-grid animate-in" style="margin-bottom:8px;">
    <div class="stat-card">
        <div class="stat-card__label">Student</div>
        <div class="stat-card__value" style="font-size:1.1rem;"><?php echo htmlspecialchars(($ledger_student['first_name']??'').' '.($ledger_student['last_name']??'')); ?></div>
        <span class="stat-card__change"><?php echo htmlspecialchars($ledger_student['admission_no']??''); ?> · <?php echo htmlspecialchars(($ledger_student['class_name']??'').' '.($ledger_student['section_name']??'')); ?></span>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Total Paid</div>
        <div class="stat-card__value" style="color:#0d9488;"><?php echo $currency; ?> <?php echo number_format(array_sum(array_column($data,'amount_paid')), 2); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Remaining Balance</div>
        <div class="stat-card__value" style="color:var(--admin-red);"><?php echo $currency; ?> <?php echo number_format(array_sum(array_column($ledger_dues ?? [], 'net_amount')), 2); ?></div>
    </div>
</div>

<?php if (!empty($data)): ?>
<div class="panel animate-in">
    <div class="panel__header">
        <h3 class="panel__title">Payment Ledger</h3>
        <a href="reports.php?tab=ledger&export=csv&report_type=ledger&student_id=<?php echo (int)($ledger_student_id??0); ?>" class="btn-admin btn-admin--sm btn-admin--secondary">⬇ CSV</a>
    </div>
    <div class="table-responsive">
        <table class="recent-table">
            <thead><tr><th>Receipt</th><th>Fee</th><th>Method</th><th style="text-align:right;">Amount</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($data as $d): ?>
            <tr>
                <td style="font-weight:600;color:var(--admin-accent);font-size:.8rem;"><?php echo htmlspecialchars($d['receipt_no']); ?></td>
                <td><?php echo htmlspecialchars($d['fee_title']); ?></td>
                <td><?php echo ucfirst(str_replace('_',' ',$d['payment_method'])); ?></td>
                <td style="text-align:right;font-weight:700;color:#0d9488;"><?php echo $currency; ?> <?php echo number_format($d['amount_paid'],2); ?></td>
                <td style="font-size:.85rem;"><?php echo date('M j, Y',strtotime($d['payment_date'])); ?></td>
                <td><a href="receipt.php?receipt_no=<?php echo urlencode($d['receipt_no']); ?>" target="_blank" class="btn-admin btn-admin--sm btn-admin--ghost">Print</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="empty-state"><p>No payment history recorded for this student yet.</p></div>
<?php endif; ?>
<?php else: ?>
<div class="empty-state" style="padding:40px;"><p>Search for a student above to view their fee ledger.</p></div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
