<?php
/**
 * admin/reports.php
 * Unified Reports & Data Export Module
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();

$page_title = 'Reports & Data Exports';
$current_page = 'reports';

$tab = $_GET['tab'] ?? 'financial';
$export = $_GET['export'] ?? '';

// ─── CSV EXPORT HANDLERS ─────────────────────────────────────────
if ($export === 'csv') {
    $report_type = $_GET['report_type'] ?? $tab;

    if ($report_type === 'financial') {
        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-d');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="financial_report_' . $start . '_to_' . $end . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Type', 'Category', 'Title', 'Payment Mode', 'Amount', 'Reference Type', 'Ref Number']);

        $stmt = mysqli_prepare($conn, "SELECT trans_date, type, category, title, payment_method, amount, reference_type, receipt_invoice_no 
            FROM financial_transactions 
            WHERE trans_date BETWEEN ? AND ? 
            ORDER BY trans_date DESC");
        mysqli_stmt_bind_param($stmt, 'ss', $start, $end);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) {
            fputcsv($out, [
                $r['trans_date'],
                ucfirst($r['type']),
                $r['category'],
                $r['title'],
                ucfirst(str_replace('_', ' ', $r['payment_method'])),
                $r['amount'],
                $r['reference_type'],
                $r['receipt_invoice_no']
            ]);
        }
        fclose($out);
        exit();
    }

    if ($report_type === 'students') {
        $class_id = (int)($_GET['class_id'] ?? 0);
        $status = trim($_GET['status'] ?? '');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="student_roster_report_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Admission No', 'Roll No', 'First Name', 'Last Name', 'Gender', 'Class', 'Section', 'Session', 'Parent Name', 'Parent Phone', 'Admission Date', 'Status']);

        $w = "WHERE 1=1";
        $p = [];
        $t = '';
        if ($class_id > 0) {
            $w .= " AND s.class_id = ?";
            $p[] = $class_id;
            $t .= 'i';
        }
        if ($status !== '') {
            $w .= " AND s.status = ?";
            $p[] = $status;
            $t .= 's';
        }

        $stmt = mysqli_prepare($conn, "SELECT s.*, c.class_name, sec.section_name, ses.session_name 
            FROM students s 
            LEFT JOIN classes c ON s.class_id = c.id 
            LEFT JOIN sections sec ON s.section_id = sec.id 
            LEFT JOIN academic_sessions ses ON s.session_id = ses.id 
            $w ORDER BY s.id DESC");
        if (!empty($p)) {
            mysqli_stmt_bind_param($stmt, $t, ...$p);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) {
            fputcsv($out, [
                $r['admission_no'],
                $r['roll_no'] ?: '-',
                $r['first_name'],
                $r['last_name'],
                $r['gender'],
                $r['class_name'] ?: 'N/A',
                $r['section_name'] ?: 'A',
                $r['session_name'] ?: 'N/A',
                $r['parent_name'],
                $r['parent_phone'],
                $r['admission_date'],
                ucfirst($r['status'])
            ]);
        }
        fclose($out);
        exit();
    }

    if ($report_type === 'fees') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="fee_payments_report_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Receipt No', 'Payment Date', 'Student Name', 'Admission No', 'Class', 'Fee Title', 'Amount Paid', 'Payment Mode', 'Reference']);

        $stmt = mysqli_prepare($conn, "SELECT fp.*, s.first_name, s.last_name, s.admission_no, c.class_name, f.fee_title 
            FROM fee_payments fp 
            JOIN students s ON fp.student_id = s.id 
            LEFT JOIN classes c ON s.class_id = c.id 
            JOIN student_fee_assignments sfa ON fp.student_fee_id = sfa.id 
            JOIN fee_structures f ON sfa.fee_structure_id = f.id 
            ORDER BY fp.payment_date DESC, fp.id DESC");
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) {
            fputcsv($out, [
                $r['receipt_no'],
                $r['payment_date'],
                $r['first_name'] . ' ' . $r['last_name'],
                $r['admission_no'],
                $r['class_name'] ?: 'N/A',
                $r['fee_title'],
                $r['amount_paid'],
                ucfirst(str_replace('_', ' ', $r['payment_method'])),
                $r['reference_no'] ?: '-'
            ]);
        }
        fclose($out);
        exit();
    }

    if ($report_type === 'salaries') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="staff_salaries_report_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Payment Date', 'Employee Code', 'Staff Name', 'Department', 'Designation', 'Period', 'Basic Salary', 'Allowances', 'Deductions', 'Net Salary', 'Payment Mode']);

        $res = mysqli_query($conn, "SELECT ss.*, s.first_name, s.last_name, s.employee_code, s.department, s.designation 
            FROM staff_salaries ss 
            JOIN staff s ON ss.staff_id = s.id 
            ORDER BY ss.payment_date DESC");
        while ($r = mysqli_fetch_assoc($res)) {
            $period = date('F Y', mktime(0,0,0, $r['salary_month'], 10, $r['salary_year']));
            fputcsv($out, [
                $r['payment_date'],
                $r['employee_code'],
                $r['first_name'] . ' ' . $r['last_name'],
                $r['department'],
                $r['designation'],
                $period,
                $r['basic_amount'],
                $r['allowances'],
                $r['deductions'],
                $r['net_salary'],
                ucfirst(str_replace('_', ' ', $r['payment_method']))
            ]);
        }
        fclose($out);
        exit();
    }
}

// ─── QUERY DATA FOR REPORTS DISPLAY ──────────────────────────────
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date   = $_GET['end'] ?? date('Y-m-d');

// 1. Financial totals
$income_sum = 0;
$expense_sum = 0;
$fee_sum = 0;
$salary_sum = 0;

$stmt = mysqli_prepare($conn, "SELECT 
    IFNULL(SUM(CASE WHEN type='income' THEN amount END), 0) AS total_inc,
    IFNULL(SUM(CASE WHEN type='expense' THEN amount END), 0) AS total_exp,
    IFNULL(SUM(CASE WHEN type='income' AND reference_type='fee_payment' THEN amount END), 0) AS total_fees,
    IFNULL(SUM(CASE WHEN type='expense' AND reference_type='salary_payment' THEN amount END), 0) AS total_salaries
    FROM financial_transactions 
    WHERE trans_date BETWEEN ? AND ?");
mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
mysqli_stmt_execute($stmt);
$fin_res = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$income_sum = (float)$fin_res['total_inc'];
$expense_sum = (float)$fin_res['total_exp'];
$fee_sum = (float)$fin_res['total_fees'];
$salary_sum = (float)$fin_res['total_salaries'];

// Outstanding dues overall
$out_res = mysqli_query($conn, "SELECT IFNULL(SUM(net_amount), 0) AS outstanding FROM student_fee_assignments WHERE status IN ('unpaid','partial')");
$total_outstanding_fees = (float)mysqli_fetch_assoc($out_res)['outstanding'];

// Classes & Sessions for dropdowns
$classes = [];
$c_res = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY numeric_grade ASC");
while ($c = mysqli_fetch_assoc($c_res)) { $classes[] = $c; }

$sessions = [];
$s_res = mysqli_query($conn, "SELECT id, session_name FROM academic_sessions ORDER BY id DESC");
while ($s = mysqli_fetch_assoc($s_res)) { $sessions[] = $s; }

// Student status breakdown
$student_stats = [];
$st_res = mysqli_query($conn, "SELECT status, COUNT(*) AS cnt FROM students GROUP BY status");
while ($r = mysqli_fetch_assoc($st_res)) { $student_stats[$r['status']] = (int)$r['cnt']; }

// Admission applications summary
$admission_stats = [];
$ad_res = mysqli_query($conn, "SELECT status, COUNT(*) AS cnt FROM admissions_inquiries GROUP BY status");
while ($r = mysqli_fetch_assoc($ad_res)) { $admission_stats[$r['status']] = (int)$r['cnt']; }

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Reports & Data Exports</h1>
        <p class="page-header__desc">Generate comprehensive administrative, academic, and financial reports with CSV export.</p>
    </div>
    <div class="page-header__actions">
        <a href="reports.php?tab=financial" class="btn-admin <?php echo $tab === 'financial' ? 'btn-admin--primary' : 'btn-admin--secondary'; ?>">Financial</a>
        <a href="reports.php?tab=students" class="btn-admin <?php echo $tab === 'students' ? 'btn-admin--primary' : 'btn-admin--secondary'; ?>">Students</a>
        <a href="reports.php?tab=fees" class="btn-admin <?php echo $tab === 'fees' ? 'btn-admin--primary' : 'btn-admin--secondary'; ?>">Fees</a>
        <a href="reports.php?tab=salaries" class="btn-admin <?php echo $tab === 'salaries' ? 'btn-admin--primary' : 'btn-admin--secondary'; ?>">Salaries</a>
        <a href="reports.php?tab=admissions" class="btn-admin <?php echo $tab === 'admissions' ? 'btn-admin--primary' : 'btn-admin--secondary'; ?>">Admissions</a>
    </div>
</div>

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- TAB 1: FINANCIAL REPORTS                                            -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php if ($tab === 'financial'): ?>
    <!-- Date Filter Form -->
    <div class="filter-card animate-in">
        <form method="GET" action="reports.php" class="filter-form" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="tab" value="financial">
            <div class="form-group" style="margin-bottom:0;min-width:180px;">
                <label class="form-label" style="font-size:0.8rem;">From Date</label>
                <input type="date" name="start" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;min-width:180px;">
                <label class="form-label" style="font-size:0.8rem;">To Date</label>
                <input type="date" name="end" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <button type="submit" class="btn-admin btn-admin--secondary btn-admin--sm">Apply Filter</button>
            <a href="reports.php?tab=financial&export=csv&start=<?php echo urlencode($start_date); ?>&end=<?php echo urlencode($end_date); ?>" class="btn-admin btn-admin--primary btn-admin--sm">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>Export CSV</span>
            </a>
            <button type="button" onclick="window.print();" class="btn-admin btn-admin--ghost btn-admin--sm">Print</button>
        </form>
    </div>

    <!-- Summary Metrics -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
        <div class="stat-card animate-in">
            <div class="stat-card__label" style="color:var(--admin-accent);">Total Income</div>
            <div class="stat-card__value" style="color:var(--admin-accent);">Rs <?php echo number_format($income_sum, 2); ?></div>
            <span class="stat-card__change stat-card__change--up">Includes Rs <?php echo number_format($fee_sum, 2); ?> fees</span>
        </div>
        <div class="stat-card animate-in">
            <div class="stat-card__label" style="color:var(--admin-red);">Total Expenses</div>
            <div class="stat-card__value" style="color:var(--admin-red);">Rs <?php echo number_format($expense_sum, 2); ?></div>
            <span class="stat-card__change stat-card__change--down">Includes Rs <?php echo number_format($salary_sum, 2); ?> salaries</span>
        </div>
        <div class="stat-card animate-in">
            <div class="stat-card__label">Net Operating Cash Flow</div>
            <?php $net = $income_sum - $expense_sum; ?>
            <div class="stat-card__value" style="color:<?php echo $net >= 0 ? 'var(--admin-accent)' : 'var(--admin-red)'; ?>;">
                Rs <?php echo number_format($net, 2); ?>
            </div>
            <span class="stat-card__change stat-card__change--neutral"><?php echo date('M j', strtotime($start_date)); ?> – <?php echo date('M j, Y', strtotime($end_date)); ?></span>
        </div>
        <div class="stat-card animate-in">
            <div class="stat-card__label" style="color:var(--admin-amber);">Current Uncollected Dues</div>
            <div class="stat-card__value" style="color:var(--admin-amber);">Rs <?php echo number_format($total_outstanding_fees, 2); ?></div>
            <span class="stat-card__change stat-card__change--neutral">Outstanding student balances</span>
        </div>
    </div>

    <!-- Breakdown Table -->
    <div class="panel animate-in">
        <div class="panel__header">
            <h3 class="panel__title">Financial Transactions Breakdown</h3>
            <span style="color:var(--admin-text-muted);font-size:0.85rem;"><?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?></span>
        </div>
        <div class="table-responsive">
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Narration</th>
                        <th>Payment Mode</th>
                        <th style="text-align:right;">Amount (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $t_stmt = mysqli_prepare($conn, "SELECT * FROM financial_transactions WHERE trans_date BETWEEN ? AND ? ORDER BY trans_date DESC LIMIT 50");
                    mysqli_stmt_bind_param($t_stmt, 'ss', $start_date, $end_date);
                    mysqli_stmt_execute($t_stmt);
                    $t_res = mysqli_stmt_get_result($t_stmt);
                    $has_trans = false;
                    while ($tr = mysqli_fetch_assoc($t_res)):
                        $has_trans = true;
                        $is_inc = ($tr['type'] === 'income');
                    ?>
                    <tr>
                        <td style="font-size:0.85rem;color:var(--admin-text-muted);"><?php echo date('M j, Y', strtotime($tr['trans_date'])); ?></td>
                        <td><span class="badge badge--<?php echo $is_inc ? 'active' : 'danger'; ?>"><?php echo ucfirst($tr['type']); ?></span></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($tr['category']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($tr['title']); ?>
                            <?php if ($tr['receipt_invoice_no']): ?><small style="color:var(--admin-accent);font-weight:600;">(<?php echo htmlspecialchars($tr['receipt_invoice_no']); ?>)</small><?php endif; ?>
                        </td>
                        <td style="font-size:0.85rem;"><?php echo ucfirst(str_replace('_', ' ', $tr['payment_method'])); ?></td>
                        <td style="text-align:right;font-weight:700;color:<?php echo $is_inc ? 'var(--admin-accent)' : 'var(--admin-red)'; ?>;">
                            <?php echo $is_inc ? '+' : '-'; ?> Rs <?php echo number_format($tr['amount'], 2); ?>
                        </td>
                    </tr>
                    <?php endwhile; 
                    mysqli_stmt_close($t_stmt);
                    if (!$has_trans):
                    ?>
                    <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--admin-text-muted);">No financial transactions found in this date range.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- TAB 2: STUDENTS REPORTS                                             -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php elseif ($tab === 'students'): 
    $filter_c = (int)($_GET['class_id'] ?? 0);
    $filter_s = trim($_GET['status'] ?? '');
?>
    <div class="filter-card animate-in">
        <form method="GET" action="reports.php" class="filter-form" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="tab" value="students">
            <div class="form-group" style="margin-bottom:0;min-width:180px;">
                <label class="form-label" style="font-size:0.8rem;">Class</label>
                <select name="class_id" class="form-control">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $filter_c === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;min-width:160px;">
                <label class="form-label" style="font-size:0.8rem;">Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $filter_s === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filter_s === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="graduated" <?php echo $filter_s === 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                    <option value="archived" <?php echo $filter_s === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>
            <button type="submit" class="btn-admin btn-admin--secondary btn-admin--sm">Filter</button>
            <a href="reports.php?tab=students&export=csv<?php echo $filter_c > 0 ? '&class_id='.$filter_c : ''; ?><?php echo $filter_s !== '' ? '&status='.urlencode($filter_s) : ''; ?>" class="btn-admin btn-admin--primary btn-admin--sm">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>Export CSV</span>
            </a>
            <button type="button" onclick="window.print();" class="btn-admin btn-admin--ghost btn-admin--sm">Print List</button>
        </form>
    </div>

    <!-- Student Status Summary Cards -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-bottom: 24px;">
        <div class="stat-card" style="padding:16px;">
            <div class="stat-card__label" style="color:var(--admin-accent);">Active Enrolled</div>
            <div class="stat-card__value"><?php echo $student_stats['active'] ?? 0; ?></div>
        </div>
        <div class="stat-card" style="padding:16px;">
            <div class="stat-card__label" style="color:var(--admin-amber);">Inactive</div>
            <div class="stat-card__value"><?php echo $student_stats['inactive'] ?? 0; ?></div>
        </div>
        <div class="stat-card" style="padding:16px;">
            <div class="stat-card__label" style="color:var(--admin-blue);">Graduated</div>
            <div class="stat-card__value"><?php echo $student_stats['graduated'] ?? 0; ?></div>
        </div>
        <div class="stat-card" style="padding:16px;">
            <div class="stat-card__label" style="color:var(--admin-text-muted);">Archived</div>
            <div class="stat-card__value"><?php echo $student_stats['archived'] ?? 0; ?></div>
        </div>
    </div>

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- TAB 3: FEES REPORT                                                  -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php elseif ($tab === 'fees'): ?>
    <div class="filter-card animate-in">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <strong>Fee Collections & Receipts Master Register</strong>
                <p style="color:var(--admin-text-muted);font-size:0.85rem;margin-top:2px;">Detailed ledger of all fee collections, payment methods, and references.</p>
            </div>
            <div>
                <a href="reports.php?tab=fees&export=csv" class="btn-admin btn-admin--primary btn-admin--sm">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Export All Payments (CSV)</span>
                </a>
            </div>
        </div>
    </div>

    <div class="panel animate-in">
        <div class="table-responsive">
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Fee Title</th>
                        <th>Payment Mode</th>
                        <th style="text-align:right;">Amount Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $f_res = mysqli_query($conn, "SELECT fp.*, s.first_name, s.last_name, s.admission_no, c.class_name, f.fee_title 
                        FROM fee_payments fp 
                        JOIN students s ON fp.student_id = s.id 
                        LEFT JOIN classes c ON s.class_id = c.id 
                        JOIN student_fee_assignments sfa ON fp.student_fee_id = sfa.id 
                        JOIN fee_structures f ON sfa.fee_structure_id = f.id 
                        ORDER BY fp.payment_date DESC LIMIT 50");
                    $has_fp = false;
                    while ($fp = mysqli_fetch_assoc($f_res)):
                        $has_fp = true;
                    ?>
                    <tr>
                        <td style="color:var(--admin-accent);font-weight:700;"><?php echo htmlspecialchars($fp['receipt_no']); ?></td>
                        <td style="color:var(--admin-text-muted);font-size:0.85rem;"><?php echo date('M j, Y', strtotime($fp['payment_date'])); ?></td>
                        <td style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($fp['first_name'] . ' ' . $fp['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($fp['class_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($fp['fee_title']); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $fp['payment_method'])); ?></td>
                        <td style="text-align:right;font-weight:700;color:var(--admin-accent);">Rs <?php echo number_format($fp['amount_paid'], 2); ?></td>
                    </tr>
                    <?php endwhile; 
                    if (!$has_fp): ?>
                    <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--admin-text-muted);">No fee payment records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- TAB 4: SALARIES REPORT                                              -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php elseif ($tab === 'salaries'): ?>
    <div class="filter-card animate-in">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <strong>Staff Salary Expenditure Register</strong>
                <p style="color:var(--admin-text-muted);font-size:0.85rem;margin-top:2px;">Complete historical log of all staff salary disbursements and payroll records.</p>
            </div>
            <div>
                <a href="reports.php?tab=salaries&export=csv" class="btn-admin btn-admin--primary btn-admin--sm">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Export Salary Log (CSV)</span>
                </a>
            </div>
        </div>
    </div>

    <div class="panel animate-in">
        <div class="table-responsive">
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Salary Period</th>
                        <th style="text-align:right;">Basic</th>
                        <th style="text-align:right;">Allowances</th>
                        <th style="text-align:right;">Deductions</th>
                        <th style="text-align:right;">Net Salary</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sal_res = mysqli_query($conn, "SELECT ss.*, s.first_name, s.last_name, s.employee_code, s.department 
                        FROM staff_salaries ss 
                        JOIN staff s ON ss.staff_id = s.id 
                        ORDER BY ss.payment_date DESC LIMIT 50");
                    $has_sal = false;
                    while ($sal = mysqli_fetch_assoc($sal_res)):
                        $has_sal = true;
                        $period = date('F Y', mktime(0,0,0, $sal['salary_month'], 10, $sal['salary_year']));
                    ?>
                    <tr>
                        <td style="font-size:0.85rem;color:var(--admin-text-muted);"><?php echo date('M j, Y', strtotime($sal['payment_date'])); ?></td>
                        <td style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($sal['first_name'] . ' ' . $sal['last_name']); ?> (<?php echo htmlspecialchars($sal['employee_code']); ?>)</td>
                        <td><?php echo htmlspecialchars($sal['department']); ?></td>
                        <td style="font-weight:600;"><?php echo $period; ?></td>
                        <td style="text-align:right;">Rs <?php echo number_format($sal['basic_amount'], 2); ?></td>
                        <td style="text-align:right;color:var(--admin-accent);">+ Rs <?php echo number_format($sal['allowances'], 2); ?></td>
                        <td style="text-align:right;color:var(--admin-red);">- Rs <?php echo number_format($sal['deductions'], 2); ?></td>
                        <td style="text-align:right;font-weight:700;color:var(--admin-accent);">Rs <?php echo number_format($sal['net_salary'], 2); ?></td>
                    </tr>
                    <?php endwhile; 
                    if (!$has_sal): ?>
                    <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--admin-text-muted);">No staff salary payments recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- TAB 5: ADMISSIONS REPORT                                            -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php elseif ($tab === 'admissions'): ?>
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px;">
        <div class="stat-card" style="padding:16px;">
            <div class="stat-card__label" style="color:var(--admin-amber);">Pending Review</div>
            <div class="stat-card__value"><?php echo $admission_stats['pending'] ?? 0; ?></div>
        </div>
        <div class="stat-card" style="padding:16px;">
            <div class="stat-card__label" style="color:var(--admin-blue);">Accepted</div>
            <div class="stat-card__value"><?php echo $admission_stats['accepted'] ?? 0; ?></div>
        </div>
        <div class="stat-card" style="padding:16px;">
            <div class="stat-card__label" style="color:var(--admin-accent);">Converted / Enrolled</div>
            <div class="stat-card__value"><?php echo $admission_stats['enrolled'] ?? 0; ?></div>
        </div>
        <div class="stat-card" style="padding:16px;">
            <div class="stat-card__label" style="color:var(--admin-red);">Rejected</div>
            <div class="stat-card__value"><?php echo $admission_stats['rejected'] ?? 0; ?></div>
        </div>
    </div>

    <div class="panel animate-in">
        <div class="panel__header">
            <h3 class="panel__title">Admissions Inquiries Pipeline</h3>
            <a href="admissions.php" class="panel__action">Manage Admissions →</a>
        </div>
        <div class="table-responsive">
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Applicant Student</th>
                        <th>Parent Name</th>
                        <th>Phone</th>
                        <th>Grade Applying</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $ad_q = mysqli_query($conn, "SELECT * FROM admissions_inquiries ORDER BY id DESC LIMIT 50");
                    $has_ad = false;
                    while ($ad = mysqli_fetch_assoc($ad_q)):
                        $has_ad = true;
                    ?>
                    <tr>
                        <td style="font-size:0.85rem;color:var(--admin-text-muted);"><?php echo date('M j, Y', strtotime($ad['created_at'])); ?></td>
                        <td style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($ad['child_name']); ?></td>
                        <td><?php echo htmlspecialchars($ad['parent_name']); ?></td>
                        <td><?php echo htmlspecialchars($ad['phone']); ?></td>
                        <td><?php echo htmlspecialchars($ad['grade_applying']); ?></td>
                        <td><span class="badge badge--<?php echo $ad['status'] === 'enrolled' ? 'active' : ($ad['status'] === 'accepted' ? 'info' : 'warning'); ?>"><?php echo ucfirst($ad['status']); ?></span></td>
                    </tr>
                    <?php endwhile; 
                    if (!$has_ad): ?>
                    <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--admin-text-muted);">No admission applications received yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
