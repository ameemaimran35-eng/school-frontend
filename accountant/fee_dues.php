<?php
/**
 * accountant/fee_dues.php
 * Outstanding Fee Dues — Student-wise with search/filter and CSV export.
 * SRS Section 7.3, 7.5
 */
require_once __DIR__ . '/auth_check.php';

$page_title   = 'Outstanding Fee Dues';
$current_page = 'fee_dues';

// ─── CSV Export ─────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="outstanding_dues_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Admission No', 'Student Name', 'Class', 'Section', 'Fee Title', 'Due Date', 'Assigned Amount', 'Discount', 'Balance Due', 'Status', 'Overdue?']);
    $res = mysqli_query($conn,
        "SELECT s.admission_no, s.first_name, s.last_name, c.class_name, sec.section_name,
                fs.fee_title, fs.due_date, sfa.assigned_amount, sfa.discount_amount, sfa.net_amount, sfa.status
         FROM student_fee_assignments sfa
         JOIN students s ON sfa.student_id = s.id
         LEFT JOIN classes c ON s.class_id = c.id
         LEFT JOIN sections sec ON s.section_id = sec.id
         JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
         WHERE sfa.status IN ('unpaid','partial')
         ORDER BY fs.due_date ASC");
    while ($row = mysqli_fetch_assoc($res)) {
        fputcsv($out, [
            $row['admission_no'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['class_name'] ?? 'N/A',
            $row['section_name'] ?? 'N/A',
            $row['fee_title'],
            $row['due_date'],
            number_format($row['assigned_amount'], 2),
            number_format($row['discount_amount'], 2),
            number_format($row['net_amount'], 2),
            ucfirst($row['status']),
            strtotime($row['due_date']) < time() ? 'Yes' : 'No',
        ]);
    }
    fclose($out);
    exit();
}

// ─── FILTERS ────────────────────────────────────────────────────
$class_filter  = (int)($_GET['class_id'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$search_q      = trim($_GET['q'] ?? '');

$classes = [];
$cr = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY numeric_grade ASC");
while ($c = mysqli_fetch_assoc($cr)) $classes[] = $c;

// Build query
$where_clauses = ["sfa.status IN ('unpaid','partial')"];
if ($class_filter > 0)      $where_clauses[] = "s.class_id = " . $class_filter;
if ($status_filter !== '')  $where_clauses[] = "sfa.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
if ($search_q !== '')       $where_clauses[] = "(s.first_name LIKE '%" . mysqli_real_escape_string($conn, $search_q) . "%' OR s.last_name LIKE '%" . mysqli_real_escape_string($conn, $search_q) . "%' OR s.admission_no LIKE '%" . mysqli_real_escape_string($conn, $search_q) . "%')";

$where = implode(' AND ', $where_clauses);
$query = "
    SELECT s.id AS student_id, s.admission_no, s.first_name, s.last_name,
           c.class_name, sec.section_name,
           fs.fee_title, fs.due_date,
           sfa.id AS sfa_id, sfa.assigned_amount, sfa.discount_amount, sfa.net_amount, sfa.status
    FROM student_fee_assignments sfa
    JOIN students s ON sfa.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
    WHERE {$where}
    ORDER BY fs.due_date ASC, s.first_name ASC";

$res = mysqli_query($conn, $query);
$dues = [];
$total_dues_sum = 0;
$overdue_sum    = 0;
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $dues[] = $row;
        $total_dues_sum += (float)$row['net_amount'];
        if (strtotime($row['due_date']) < time()) $overdue_sum += (float)$row['net_amount'];
    }
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Outstanding Fee Dues</h1>
        <p class="page-header__desc">Track pending and partially paid fee balances. Export and collect from here.</p>
    </div>
    <div class="page-header__actions">
        <a href="fee_dues.php?export=csv<?php echo $class_filter?'&class_id='.$class_filter:''; echo $status_filter?'&status='.urlencode($status_filter):''; ?>" class="btn-admin btn-admin--secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
        </a>
        <button type="button" onclick="window.print();" class="btn-admin btn-admin--ghost">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print
        </button>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid animate-in" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));">
    <div class="stat-card">
        <div class="stat-card__label" style="color:var(--admin-red);">Total Outstanding</div>
        <div class="stat-card__value" style="color:var(--admin-red);"><?php echo $currency; ?> <?php echo number_format($total_dues_sum, 2); ?></div>
        <span class="stat-card__change stat-card__change--down"><?php echo count($dues); ?> pending invoices</span>
    </div>
    <div class="stat-card">
        <div class="stat-card__label" style="color:#f59e0b;">Overdue Amount</div>
        <div class="stat-card__value" style="color:#f59e0b;"><?php echo $currency; ?> <?php echo number_format($overdue_sum, 2); ?></div>
        <span class="stat-card__change">Past due date</span>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-card animate-in">
    <form method="GET" action="fee_dues.php" class="filter-form" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="margin:0;min-width:200px;">
            <label class="form-label" style="font-size:.8rem;">Search Student</label>
            <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="Name or admission no.">
        </div>
        <div class="form-group" style="margin:0;min-width:160px;">
            <label class="form-label" style="font-size:.8rem;">Class</label>
            <select name="class_id" class="form-control">
                <option value="">All Classes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo $class_filter===$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;min-width:140px;">
            <label class="form-label" style="font-size:.8rem;">Status</label>
            <select name="status" class="form-control">
                <option value="">All</option>
                <option value="unpaid"  <?php echo $status_filter==='unpaid'?'selected':''; ?>>Unpaid</option>
                <option value="partial" <?php echo $status_filter==='partial'?'selected':''; ?>>Partial</option>
            </select>
        </div>
        <button type="submit" class="btn-admin btn-admin--primary">Filter</button>
        <a href="fee_dues.php" class="btn-admin btn-admin--ghost">Reset</a>
    </form>
</div>

<!-- Dues Table -->
<div class="panel animate-in">
    <div class="panel__header">
        <h3 class="panel__title">Pending Invoices (<?php echo count($dues); ?>)</h3>
        <span style="color:var(--admin-text-muted);font-size:.85rem;">Sorted by earliest due date</span>
    </div>

    <?php if (!empty($dues)): ?>
    <div class="table-responsive">
        <table class="recent-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Adm #</th>
                    <th>Class</th>
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
            <?php foreach ($dues as $d):
                $overdue = strtotime($d['due_date']) < time();
            ?>
            <tr>
                <td style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($d['first_name'].' '.$d['last_name']); ?></td>
                <td style="color:var(--admin-accent);font-weight:600;"><?php echo htmlspecialchars($d['admission_no']); ?></td>
                <td><?php echo htmlspecialchars(($d['class_name']??'N/A').' '.($d['section_name']??'')); ?></td>
                <td><?php echo htmlspecialchars($d['fee_title']); ?></td>
                <td style="font-size:.85rem;color:<?php echo $overdue?'var(--admin-red)':'var(--admin-text-muted)'; ?>;">
                    <?php echo date('M j, Y', strtotime($d['due_date'])); ?>
                    <?php if ($overdue): ?><span style="font-size:.7rem;font-weight:700;">(Overdue)</span><?php endif; ?>
                </td>
                <td style="text-align:right;"><?php echo $currency; ?> <?php echo number_format($d['assigned_amount'],2); ?></td>
                <td style="text-align:right;color:var(--admin-text-muted);"><?php echo $currency; ?> <?php echo number_format($d['discount_amount'],2); ?></td>
                <td style="text-align:right;font-weight:700;color:var(--admin-red);"><?php echo $currency; ?> <?php echo number_format($d['net_amount'],2); ?></td>
                <td><span class="badge badge--<?php echo $d['status']==='partial'?'warning':'danger'; ?>"><?php echo ucfirst($d['status']); ?></span></td>
                <td style="text-align:right;">
                    <a href="fee_payments.php?student_id=<?php echo $d['student_id']; ?>" class="btn-admin btn-admin--sm btn-admin--primary">Collect</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <p>No outstanding fee dues found! All fees are settled for the current filter.</p>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
