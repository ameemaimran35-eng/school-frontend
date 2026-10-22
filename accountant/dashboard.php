<?php
/**
 * accountant/dashboard.php
 * Accountant Portal — Financial Operations Dashboard
 * Brightway School Management System | SRS Section 7.1
 */
require_once __DIR__ . '/auth_check.php';

$page_title   = 'Accountant Dashboard';
$current_page = 'dashboard';

// ─── LIVE DATABASE STATS (SRS 7.1) ─────────────────────────────

// Today's fee collection
$r = mysqli_query($conn, "SELECT IFNULL(SUM(amount_paid),0) AS tot, COUNT(*) AS cnt FROM fee_payments WHERE payment_date = CURDATE()");
$fp_today = mysqli_fetch_assoc($r);
$today_collection   = (float)$fp_today['tot'];
$today_tx_count     = (int)$fp_today['cnt'];

// Monthly fee collection
$r = mysqli_query($conn, "SELECT IFNULL(SUM(amount_paid),0) AS tot FROM fee_payments WHERE YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())");
$monthly_collection = (float)(mysqli_fetch_assoc($r)['tot'] ?? 0);

// Outstanding fee dues (sum of all unpaid/partial net_amount)
$r = mysqli_query($conn, "SELECT IFNULL(SUM(net_amount),0) AS tot, COUNT(*) AS cnt FROM student_fee_assignments WHERE status IN ('unpaid','partial')");
$dues = mysqli_fetch_assoc($r);
$outstanding_total = (float)$dues['tot'];
$outstanding_count = (int)$dues['cnt'];

// Recent expenses (this month, direct only)
$r = mysqli_query($conn, "SELECT IFNULL(SUM(amount),0) AS tot FROM financial_transactions WHERE type='expense' AND reference_type='direct' AND YEAR(trans_date)=YEAR(CURDATE()) AND MONTH(trans_date)=MONTH(CURDATE())");
$monthly_expenses = (float)(mysqli_fetch_assoc($r)['tot'] ?? 0);

// Unpaid/partial fee count
$r = mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) AS cnt FROM student_fee_assignments WHERE status IN ('unpaid','partial')");
$students_with_dues = (int)(mysqli_fetch_assoc($r)['cnt'] ?? 0);

// Monthly salary paid
$r = mysqli_query($conn, "SELECT IFNULL(SUM(net_salary),0) AS tot FROM staff_salaries WHERE YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())");
$monthly_salary = (float)(mysqli_fetch_assoc($r)['tot'] ?? 0);

// ─── RECENT PAYMENTS (last 10) ───────────────────────────────────
$recent_payments = [];
$rp = mysqli_query($conn, "
    SELECT fp.receipt_no, fp.amount_paid, fp.payment_date, fp.payment_method,
           CONCAT(s.first_name,' ',s.last_name) AS student_name, s.admission_no,
           fs.fee_title
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    JOIN student_fee_assignments sfa ON fp.student_fee_id = sfa.id
    JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
    ORDER BY fp.id DESC LIMIT 10
");
if ($rp) while ($row = mysqli_fetch_assoc($rp)) $recent_payments[] = $row;

// ─── RECENT DIRECT EXPENSES (last 5) ────────────────────────────
$recent_expenses = [];
$re = mysqli_query($conn, "SELECT title, category, amount, trans_date, payment_method FROM financial_transactions WHERE type='expense' AND reference_type='direct' ORDER BY id DESC LIMIT 5");
if ($re) while ($row = mysqli_fetch_assoc($re)) $recent_expenses[] = $row;

// ─── MOST OVERDUE FEE ASSIGNMENTS ───────────────────────────────
$overdue_dues = [];
$od = mysqli_query($conn, "
    SELECT CONCAT(s.first_name,' ',s.last_name) AS student_name, s.admission_no,
           fs.fee_title, fs.due_date, sfa.net_amount, sfa.status
    FROM student_fee_assignments sfa
    JOIN students s ON sfa.student_id = s.id
    JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
    WHERE sfa.status IN ('unpaid','partial') AND fs.due_date < CURDATE()
    ORDER BY fs.due_date ASC LIMIT 5
");
if ($od) while ($row = mysqli_fetch_assoc($od)) $overdue_dues[] = $row;

include __DIR__ . '/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Financial Dashboard</h1>
        <p class="page-header__desc">Real-time fee collections, outstanding dues, and financial overview.</p>
    </div>
    <div class="page-header__actions">
        <a href="fee_payments.php" class="btn-admin btn-admin--primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Collect Fee
        </a>
        <a href="reports.php" class="btn-admin btn-admin--secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Reports
        </a>
    </div>
</div>

<?php
$success_flash = $_SESSION['flash_success'] ?? '';
$error_flash   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
if ($success_flash): ?>
<div class="alert alert-success flash-auto-dismiss" role="alert"><?php echo htmlspecialchars($success_flash); ?></div>
<?php endif; if ($error_flash): ?>
<div class="alert alert-danger flash-auto-dismiss" role="alert"><?php echo htmlspecialchars($error_flash); ?></div>
<?php endif; ?>

<!-- ─── KPI STATS GRID ─────────────────────────────────────── -->
<div class="stats-grid animate-in">

    <!-- Today's Collection -->
    <div class="stat-card">
        <div class="stat-card__icon" style="background:rgba(13,148,136,.15);color:#0d9488;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Today's Collection</div>
            <div class="stat-card__value"><?php echo $currency; ?> <?php echo number_format($today_collection, 0); ?></div>
            <span class="stat-card__change stat-card__change--up"><?php echo $today_tx_count; ?> transaction<?php echo $today_tx_count !== 1 ? 's' : ''; ?> today</span>
        </div>
    </div>

    <!-- Monthly Collection -->
    <div class="stat-card">
        <div class="stat-card__icon" style="background:rgba(59,130,246,.12);color:#3b82f6;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Monthly Collection</div>
            <div class="stat-card__value"><?php echo $currency; ?> <?php echo number_format($monthly_collection, 0); ?></div>
            <span class="stat-card__change"><?php echo date('F Y'); ?></span>
        </div>
    </div>

    <!-- Outstanding Dues -->
    <div class="stat-card">
        <div class="stat-card__icon" style="background:rgba(239,68,68,.12);color:#ef4444;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Outstanding Dues</div>
            <div class="stat-card__value" style="color:var(--admin-red);"><?php echo $currency; ?> <?php echo number_format($outstanding_total, 0); ?></div>
            <span class="stat-card__change stat-card__change--down"><?php echo $outstanding_count; ?> pending invoices</span>
        </div>
    </div>

    <!-- Students with Dues -->
    <div class="stat-card">
        <div class="stat-card__icon" style="background:rgba(245,158,11,.12);color:#f59e0b;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Students with Dues</div>
            <div class="stat-card__value" style="color:#f59e0b;"><?php echo $students_with_dues; ?></div>
            <span class="stat-card__change"><a href="fee_dues.php" style="color:inherit;">View all dues →</a></span>
        </div>
    </div>

    <!-- Monthly Expenses -->
    <div class="stat-card">
        <div class="stat-card__icon" style="background:rgba(168,85,247,.12);color:#a855f7;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Monthly Expenses</div>
            <div class="stat-card__value" style="color:#a855f7;"><?php echo $currency; ?> <?php echo number_format($monthly_expenses, 0); ?></div>
            <span class="stat-card__change">Direct expenses only</span>
        </div>
    </div>

    <!-- Monthly Salaries -->
    <div class="stat-card">
        <div class="stat-card__icon" style="background:rgba(20,184,166,.12);color:#14b8a6;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Salaries Paid (Month)</div>
            <div class="stat-card__value"><?php echo $currency; ?> <?php echo number_format($monthly_salary, 0); ?></div>
            <span class="stat-card__change"><a href="salaries.php" style="color:inherit;">Manage salaries →</a></span>
        </div>
    </div>
</div>

<!-- ─── RECENT PAYMENTS + OVERDUE TABLE ────────────────────── -->
<div class="dash-grid" style="margin-top:8px;">

    <!-- Recent Payments -->
    <div class="panel animate-in">
        <div class="panel__header">
            <h3 class="panel__title">Recent Fee Payments</h3>
            <a href="fee_payments.php" class="btn-admin btn-admin--sm btn-admin--ghost">View All</a>
        </div>
        <?php if (!empty($recent_payments)): ?>
        <div class="table-responsive">
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Student</th>
                        <th>Fee</th>
                        <th>Method</th>
                        <th style="text-align:right;">Amount</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_payments as $p): ?>
                    <tr>
                        <td style="font-weight:600;color:var(--admin-accent);font-size:.8rem;"><?php echo htmlspecialchars($p['receipt_no']); ?></td>
                        <td>
                            <strong style="color:var(--admin-heading);"><?php echo htmlspecialchars($p['student_name']); ?></strong>
                            <br><small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($p['admission_no']); ?></small>
                        </td>
                        <td style="font-size:.85rem;"><?php echo htmlspecialchars($p['fee_title']); ?></td>
                        <td><span class="badge badge--info"><?php echo ucfirst(str_replace('_',' ',$p['payment_method'])); ?></span></td>
                        <td style="text-align:right;font-weight:700;color:#0d9488;"><?php echo $currency; ?> <?php echo number_format($p['amount_paid'], 2); ?></td>
                        <td style="font-size:.8rem;color:var(--admin-text-muted);"><?php echo date('M j', strtotime($p['payment_date'])); ?></td>
                        <td><a href="receipt.php?receipt_no=<?php echo urlencode($p['receipt_no']); ?>" class="btn-admin btn-admin--sm btn-admin--ghost" target="_blank">Receipt</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:30px;">
            <p>No fee payments recorded yet.</p>
            <a href="fee_payments.php" class="btn-admin btn-admin--primary" style="margin-top:12px;">Record First Payment</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Overdue Sidebar -->
    <div style="display:flex;flex-direction:column;gap:24px;">

        <!-- Overdue Dues -->
        <div class="panel animate-in">
            <div class="panel__header">
                <h3 class="panel__title" style="color:var(--admin-red);">⚠ Overdue Fees</h3>
                <a href="fee_dues.php" class="btn-admin btn-admin--sm btn-admin--ghost">All Dues</a>
            </div>
            <?php if (!empty($overdue_dues)): ?>
            <div style="padding:0 4px;">
                <?php foreach ($overdue_dues as $d): ?>
                <div style="padding:10px 12px;border-bottom:1px solid var(--admin-border);display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-weight:600;font-size:.85rem;color:var(--admin-heading);"><?php echo htmlspecialchars($d['student_name']); ?></div>
                        <div style="font-size:.75rem;color:var(--admin-text-muted);"><?php echo htmlspecialchars($d['fee_title']); ?> · <?php echo date('M j', strtotime($d['due_date'])); ?></div>
                    </div>
                    <div style="font-weight:700;font-size:.9rem;color:var(--admin-red);"><?php echo $currency; ?> <?php echo number_format($d['net_amount'], 0); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="padding:20px;text-align:center;color:var(--admin-text-muted);font-size:.85rem;">
                ✅ No overdue fees!
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Expenses -->
        <div class="panel animate-in">
            <div class="panel__header">
                <h3 class="panel__title">Recent Expenses</h3>
                <a href="income_expense.php?type=expense" class="btn-admin btn-admin--sm btn-admin--ghost">Add More</a>
            </div>
            <?php if (!empty($recent_expenses)): ?>
            <div style="padding:0 4px;">
                <?php foreach ($recent_expenses as $e): ?>
                <div style="padding:10px 12px;border-bottom:1px solid var(--admin-border);display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-weight:600;font-size:.85rem;color:var(--admin-heading);"><?php echo htmlspecialchars($e['title']); ?></div>
                        <div style="font-size:.75rem;color:var(--admin-text-muted);"><?php echo htmlspecialchars($e['category']); ?> · <?php echo date('M j', strtotime($e['trans_date'])); ?></div>
                    </div>
                    <div style="font-weight:700;font-size:.9rem;color:#a855f7;"><?php echo $currency; ?> <?php echo number_format($e['amount'], 0); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="padding:20px;text-align:center;color:var(--admin-text-muted);font-size:.85rem;">No expenses recorded this month.</div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="panel animate-in">
            <div class="panel__header"><h3 class="panel__title">Quick Actions</h3></div>
            <div style="padding:12px;display:flex;flex-direction:column;gap:8px;">
                <a href="fee_payments.php" class="btn-admin btn-admin--primary" style="text-align:center;">💵 Collect Fee Payment</a>
                <a href="fee_dues.php" class="btn-admin btn-admin--secondary" style="text-align:center;">📋 View Outstanding Dues</a>
                <a href="income_expense.php" class="btn-admin btn-admin--ghost" style="text-align:center;">📊 Record Income/Expense</a>
                <a href="reports.php" class="btn-admin btn-admin--ghost" style="text-align:center;">📄 Financial Reports</a>
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width:900px) {
    .dashboard-grid { grid-template-columns: 1fr !important; }
}
</style>

<?php include __DIR__ . '/footer.php'; ?>
