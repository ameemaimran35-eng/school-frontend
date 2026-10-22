<?php
/**
 * admin/fee_dues.php
 * List unpaid and partially paid fee assignments with outstanding amounts.
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();

$page_title = 'Outstanding Fee Dues';
$current_page = 'fee_dues';

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fee_dues_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Admission No', 'Student Name', 'Class', 'Fee Title', 'Due Date', 'Assigned Amount', 'Discount', 'Balance Due', 'Status']);

    $q = "SELECT s.admission_no, s.first_name, s.last_name, c.class_name, sec.section_name, f.fee_title, f.due_date, sfa.assigned_amount, sfa.discount_amount, sfa.net_amount, sfa.status
          FROM student_fee_assignments sfa
          JOIN students s ON sfa.student_id = s.id
          LEFT JOIN classes c ON s.class_id = c.id
          LEFT JOIN sections sec ON s.section_id = sec.id
          JOIN fee_structures f ON sfa.fee_structure_id = f.id
          WHERE sfa.status IN ('unpaid','partial')
          ORDER BY f.due_date ASC";
    $res = mysqli_query($conn, $q);
    while ($row = mysqli_fetch_assoc($res)) {
        fputcsv($output, [
            $row['admission_no'],
            $row['first_name'] . ' ' . $row['last_name'],
            ($row['class_name'] ?? 'N/A') . ' - ' . ($row['section_name'] ?? 'A'),
            $row['fee_title'],
            $row['due_date'],
            $row['assigned_amount'],
            $row['discount_amount'],
            $row['net_amount'],
            ucfirst($row['status'])
        ]);
    }
    fclose($output);
    exit();
}

$class_filter = (int)($_GET['class_id'] ?? 0);
$classes = [];
$c_res = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY numeric_grade ASC");
while ($cr = mysqli_fetch_assoc($c_res)) {
    $classes[] = $cr;
}

// Build query
$where = "WHERE sfa.status IN ('unpaid','partial')";
if ($class_filter > 0) {
    $where .= " AND s.class_id = " . $class_filter;
}

$query = "SELECT s.id AS student_id, s.admission_no, s.first_name, s.last_name, c.class_name, sec.section_name, f.fee_title, sfa.id AS student_fee_id, sfa.assigned_amount, sfa.discount_amount, sfa.net_amount, sfa.status, f.due_date
          FROM student_fee_assignments sfa
          JOIN students s ON sfa.student_id = s.id
          LEFT JOIN classes c ON s.class_id = c.id
          LEFT JOIN sections sec ON s.section_id = sec.id
          JOIN fee_structures f ON sfa.fee_structure_id = f.id
          $where
          ORDER BY f.due_date ASC";
$res = mysqli_query($conn, $query);
$dues = [];
$total_dues_sum = 0;
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $dues[] = $row;
        $total_dues_sum += (float)$row['net_amount'];
    }
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Outstanding Fee Dues</h1>
        <p class="page-header__desc">Track students with pending and partial fee balances across all classes.</p>
    </div>
    <div class="page-header__actions">
        <a href="fee_dues.php?export=csv<?php echo $class_filter > 0 ? '&class_id='.$class_filter : ''; ?>" class="btn-admin btn-admin--secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <span>Export CSV</span>
        </a>
        <button type="button" onclick="window.print();" class="btn-admin btn-admin--ghost">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            <span>Print Report</span>
        </button>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card animate-in">
        <div class="stat-card__label" style="color:var(--admin-red);">Total Outstanding Amount</div>
        <div class="stat-card__value" style="color:var(--admin-red);">Rs <?php echo number_format($total_dues_sum, 2); ?></div>
        <span class="stat-card__change stat-card__change--down"><?php echo count($dues); ?> pending invoices</span>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-card animate-in">
    <form method="GET" action="fee_dues.php" class="filter-form" style="display:flex;gap:12px;align-items:flex-end;">
        <div class="form-group" style="margin-bottom:0;min-width:180px;">
            <label class="form-label" style="font-size:0.8rem;">Filter by Class</label>
            <select name="class_id" class="form-control" onchange="this.form.submit()">
                <option value="">All Classes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $class_filter === (int)$c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['class_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($class_filter > 0): ?>
            <a href="fee_dues.php" class="btn-admin btn-admin--sm btn-admin--ghost">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Dues Table Panel -->
<div class="panel animate-in">
    <div class="panel__header">
        <h3 class="panel__title">Pending Invoices (<?php echo count($dues); ?>)</h3>
        <span style="color:var(--admin-text-muted);font-size:0.85rem;">Sorted by earliest due date</span>
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
                        <th style="text-align:right;color:var(--admin-red);">Due Balance</th>
                        <th>Status</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dues as $d): ?>
                    <tr>
                        <td style="font-weight:600;color:var(--admin-heading);">
                            <?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name']); ?>
                        </td>
                        <td style="color:var(--admin-accent);font-weight:600;"><?php echo htmlspecialchars($d['admission_no']); ?></td>
                        <td><?php echo htmlspecialchars(($d['class_name'] ?? 'N/A') . ' - ' . ($d['section_name'] ?? 'A')); ?></td>
                        <td><?php echo htmlspecialchars($d['fee_title']); ?></td>
                        <td style="font-size:0.85rem;color:<?php echo (strtotime($d['due_date']) < time()) ? 'var(--admin-red)' : 'var(--admin-text-muted)'; ?>;">
                            <?php echo date('M j, Y', strtotime($d['due_date'])); ?>
                            <?php if (strtotime($d['due_date']) < time()): ?><span style="font-size:0.7rem;font-weight:700;">(Overdue)</span><?php endif; ?>
                        </td>
                        <td style="text-align:right;">Rs <?php echo number_format($d['assigned_amount'], 2); ?></td>
                        <td style="text-align:right;color:var(--admin-text-muted);">Rs <?php echo number_format($d['discount_amount'], 2); ?></td>
                        <td style="text-align:right;font-weight:700;color:var(--admin-red);">Rs <?php echo number_format($d['net_amount'], 2); ?></td>
                        <td>
                            <span class="badge badge--<?php echo $d['status'] === 'partial' ? 'warning' : 'danger'; ?>">
                                <?php echo ucfirst($d['status']); ?>
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <a href="fee_payments.php" class="btn-admin btn-admin--sm btn-admin--primary">
                                Collect
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <p>No outstanding fee dues found! All active student fees are settled.</p>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
