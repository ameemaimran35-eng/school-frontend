<?php
/**
 * admin/dashboard.php
 * School Admin Operations Central Dashboard
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();

$page_title = 'Admin Operations Dashboard';
$current_page = 'dashboard';

// ─── FETCH ALL ACTUAL DATABASE STATS (SRS Section 5) ────────────
$stats = [];

// 1. Total active students
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM students WHERE status='active'");
$stats['students'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

// 2. Total active staff
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM staff WHERE status='active'");
$stats['staff'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

// 3. Classes and sections
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM classes");
$stats['classes'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM sections");
$stats['sections'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM subjects");
$stats['subjects'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

// 4. Today's attendance summary (Present & Absent counts)
$res = mysqli_query($conn, "SELECT 
    COUNT(CASE WHEN status='present' THEN 1 END) AS present_today,
    COUNT(CASE WHEN status='absent' THEN 1 END) AS absent_today
    FROM attendance WHERE attendance_date = CURDATE()");
$att_today = mysqli_fetch_assoc($res);
$stats['present_today'] = (int)($att_today['present_today'] ?? 0);
$stats['absent_today']  = (int)($att_today['absent_today'] ?? 0);

// 5. Fee collection for today (amount)
$res = mysqli_query($conn, "SELECT IFNULL(SUM(amount_paid), 0) AS total, COUNT(*) AS cnt FROM fee_payments WHERE payment_date = CURDATE()");
$fp_today = mysqli_fetch_assoc($res);
$stats['fee_collected_today'] = (float)($fp_today['total'] ?? 0);
$stats['fee_payments_count_today'] = (int)($fp_today['cnt'] ?? 0);

// 6. Fee collection for current month (amount)
$res = mysqli_query($conn, "SELECT IFNULL(SUM(amount_paid), 0) AS total FROM fee_payments WHERE YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())");
$stats['fee_collected_month'] = (float)(mysqli_fetch_assoc($res)['total'] ?? 0);

// 7. Total outstanding student fee balances
$res = mysqli_query($conn, "SELECT IFNULL(SUM(net_amount), 0) AS total FROM student_fee_assignments WHERE status IN ('unpaid', 'partial')");
$stats['outstanding_fees'] = (float)(mysqli_fetch_assoc($res)['total'] ?? 0);

// 8. Monthly income & expenses from financial transactions
$res = mysqli_query($conn, "SELECT 
    IFNULL(SUM(CASE WHEN type='income' THEN amount END), 0) AS income,
    IFNULL(SUM(CASE WHEN type='expense' THEN amount END), 0) AS expense
    FROM financial_transactions 
    WHERE YEAR(trans_date)=YEAR(CURDATE()) AND MONTH(trans_date)=MONTH(CURDATE())");
$fin_m = mysqli_fetch_assoc($res);
$stats['income_this_month'] = (float)($fin_m['income'] ?? 0);
$stats['expenses_this_month'] = (float)($fin_m['expense'] ?? 0);

// 9. Monthly salary expenditure
$res = mysqli_query($conn, "SELECT IFNULL(SUM(net_salary), 0) AS total FROM staff_salaries WHERE YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())");
$stats['salary_expenditure_month'] = (float)(mysqli_fetch_assoc($res)['total'] ?? 0);

// 10. Pending inquiries (admissions + contact)
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries WHERE status='pending'");
$stats['pending_admissions'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM contact_inquiries WHERE status='unread'");
$stats['unread_contact'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

// ─── RECENT RECORDS FOR WIDGETS ──────────────────────────────────
// Recent admissions
$recent_admissions = [];
$res = mysqli_query($conn, "SELECT id, child_name, parent_name, grade_applying, status, created_at FROM admissions_inquiries ORDER BY id DESC LIMIT 5");
while ($r = mysqli_fetch_assoc($res)) {
    $recent_admissions[] = $r;
}

// Recent fee payments
$recent_payments = [];
$res = mysqli_query($conn, "SELECT fp.receipt_no, fp.amount_paid, fp.payment_date, fp.payment_method,
    CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.admission_no
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    ORDER BY fp.id DESC LIMIT 5");
while ($r = mysqli_fetch_assoc($res)) {
    $recent_payments[] = $r;
}

// Upcoming examinations
$upcoming_exams = [];
$res = mysqli_query($conn, "SELECT e.*, ses.session_name, COUNT(es.id) AS papers_count 
    FROM exams e 
    LEFT JOIN academic_sessions ses ON e.session_id = ses.id 
    LEFT JOIN exam_schedules es ON e.id = es.exam_id 
    WHERE e.end_date >= CURDATE()
    GROUP BY e.id ORDER BY e.start_date ASC LIMIT 3");
while ($r = mysqli_fetch_assoc($res)) {
    $upcoming_exams[] = $r;
}

// Recent announcements
$announcements = [];
$res = mysqli_query($conn, "SELECT id, title, target_audience, published_date FROM announcements ORDER BY published_date DESC, id DESC LIMIT 4");
while ($r = mysqli_fetch_assoc($res)) {
    $announcements[] = $r;
}

include __DIR__ . '/header.php';
?>

<!-- ─── TOP PRIMARY METRICS GRID ────────────────────────────────── -->
<div class="stats-grid">
    <!-- Active Students -->
    <div class="stat-card animate-in">
        <div class="stat-card__icon stat-card__icon--accent">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Active Students</div>
            <div class="stat-card__value"><?php echo number_format($stats['students']); ?></div>
            <span class="stat-card__change stat-card__change--up">
                <?php echo $stats['classes']; ?> classes / <?php echo $stats['sections']; ?> sections
            </span>
        </div>
    </div>

    <!-- Active Staff -->
    <div class="stat-card animate-in">
        <div class="stat-card__icon stat-card__icon--blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Active Staff</div>
            <div class="stat-card__value"><?php echo number_format($stats['staff']); ?></div>
            <span class="stat-card__change stat-card__change--up">Faculty & Administration</span>
        </div>
    </div>

    <!-- Today's Attendance -->
    <div class="stat-card animate-in">
        <div class="stat-card__icon stat-card__icon--pink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Today's Attendance</div>
            <div class="stat-card__value" style="font-size:1.5rem;">
                <span style="color:var(--admin-accent);"><?php echo $stats['present_today']; ?> P</span>
                <span style="color:var(--admin-border);font-weight:400;margin:0 4px;">/</span>
                <span style="color:var(--admin-red);"><?php echo $stats['absent_today']; ?> A</span>
            </div>
            <span class="stat-card__change stat-card__change--neutral">Recorded for today</span>
        </div>
    </div>

    <!-- Fee Collection Today & Month -->
    <div class="stat-card animate-in">
        <div class="stat-card__icon stat-card__icon--amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Fee Collection Today</div>
            <div class="stat-card__value">Rs <?php echo number_format($stats['fee_collected_today']); ?></div>
            <span class="stat-card__change stat-card__change--neutral">
                Rs <?php echo number_format($stats['fee_collected_month']); ?> this month
            </span>
        </div>
    </div>

    <!-- Outstanding Dues -->
    <div class="stat-card animate-in">
        <div class="stat-card__icon stat-card__icon--red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Outstanding Dues</div>
            <div class="stat-card__value" style="color:var(--admin-red);">Rs <?php echo number_format($stats['outstanding_fees']); ?></div>
            <span class="stat-card__change stat-card__change--down">Pending fee balances</span>
        </div>
    </div>

    <!-- Monthly Net Flow -->
    <div class="stat-card animate-in">
        <div class="stat-card__icon stat-card__icon--purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        <div class="stat-card__body">
            <div class="stat-card__label">Monthly Net Cash Flow</div>
            <?php $net = $stats['income_this_month'] - $stats['expenses_this_month']; ?>
            <div class="stat-card__value" style="color:<?php echo $net >= 0 ? 'var(--admin-accent)' : 'var(--admin-red)'; ?>;">
                Rs <?php echo number_format($net); ?>
            </div>
            <span class="stat-card__change stat-card__change--neutral">
                Inc: Rs <?php echo number_format($stats['income_this_month']); ?> | Exp: Rs <?php echo number_format($stats['expenses_this_month']); ?>
            </span>
        </div>
    </div>
</div>

<!-- ─── DASHBOARD MIDDLE GRID: ADMISSIONS & QUICK ACTIONS ───────── -->
<div class="dash-grid">
    <!-- Left Column: Recent Admissions Inquiries -->
    <div class="panel animate-in">
        <div class="panel__header">
            <div>
                <h3 class="panel__title">Recent Admission Applications</h3>
                <small style="color:var(--admin-text-muted);"><?php echo $stats['pending_admissions']; ?> applications awaiting decision</small>
            </div>
            <a href="admissions.php" class="panel__action">Manage All →</a>
        </div>

        <?php if (!empty($recent_admissions)): ?>
            <div class="table-responsive">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Parent</th>
                            <th>Grade</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_admissions as $adm): ?>
                        <tr>
                            <td style="font-weight:600;color:var(--admin-heading);">
                                <?php echo htmlspecialchars($adm['child_name']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($adm['parent_name']); ?></td>
                            <td><?php echo htmlspecialchars($adm['grade_applying']); ?></td>
                            <td>
                                <span class="badge badge--<?php echo $adm['status'] === 'enrolled' ? 'active' : ($adm['status'] === 'accepted' ? 'info' : 'warning'); ?>">
                                    <?php echo ucfirst($adm['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><p>No admission applications received yet.</p></div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Quick Operations & Upcoming Exams -->
    <div>
        <!-- Quick Actions Panel -->
        <div class="panel animate-in" style="margin-bottom:24px;">
            <div class="panel__header">
                <h3 class="panel__title">Administrative Quick Operations</h3>
            </div>
            <div class="quick-actions">
                <a href="students.php?action=add" class="quick-action">
                    <div class="quick-action__icon" style="background:var(--admin-accent-soft);color:var(--admin-accent);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    </div>
                    <span class="quick-action__label">Add Student</span>
                </a>
                <a href="attendance.php" class="quick-action">
                    <div class="quick-action__icon" style="background:var(--admin-pink-soft);color:var(--admin-pink);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <span class="quick-action__label">Mark Attendance</span>
                </a>
                <a href="fee_payments.php" class="quick-action">
                    <div class="quick-action__icon" style="background:var(--admin-amber-soft);color:var(--admin-amber);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <span class="quick-action__label">Collect Fee</span>
                </a>
                <a href="exams.php" class="quick-action">
                    <div class="quick-action__icon" style="background:var(--admin-purple-soft);color:var(--admin-purple);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <span class="quick-action__label">Exams & Marks</span>
                </a>
                <a href="staff_salary_payments.php?action=add" class="quick-action">
                    <div class="quick-action__icon" style="background:var(--admin-blue-soft);color:var(--admin-blue);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <span class="quick-action__label">Pay Salary</span>
                </a>
                <a href="reports.php" class="quick-action">
                    <div class="quick-action__icon" style="background:var(--admin-red-soft);color:var(--admin-red);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </div>
                    <span class="quick-action__label">Run Reports</span>
                </a>
            </div>
        </div>

        <!-- Upcoming Exams Widget -->
        <div class="panel animate-in">
            <div class="panel__header">
                <h3 class="panel__title">Upcoming Examinations</h3>
                <a href="exams.php" class="panel__action">View All →</a>
            </div>
            <?php if (!empty($upcoming_exams)): ?>
                <div style="padding:16px;">
                    <?php foreach ($upcoming_exams as $ue): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:var(--admin-input-bg);border:1px solid var(--admin-border);border-radius:8px;margin-bottom:10px;">
                            <div>
                                <div style="font-weight:700;color:var(--admin-heading);"><?php echo htmlspecialchars($ue['exam_name']); ?></div>
                                <small style="color:var(--admin-accent);font-weight:600;"><?php echo htmlspecialchars($ue['term']); ?> (<?php echo $ue['papers_count']; ?> papers)</small>
                            </div>
                            <div style="text-align:right;font-size:0.8rem;color:var(--admin-text-muted);">
                                <?php echo date('M j', strtotime($ue['start_date'])); ?> – <?php echo date('M j, Y', strtotime($ue['end_date'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding:20px;"><p>No upcoming examination terms scheduled.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ─── RECENT FEE PAYMENTS FULL PANEL ───────────────────────────── -->
<div class="full-section" style="margin-top:24px;">
    <div class="panel animate-in">
        <div class="panel__header">
            <div>
                <h3 class="panel__title">Recent Fee Transactions</h3>
                <small style="color:var(--admin-text-muted);">Latest verified payments posted to financial ledger</small>
            </div>
            <a href="fee_payments.php" class="panel__action">All Fee Payments →</a>
        </div>

        <?php if (!empty($recent_payments)): ?>
            <div class="table-responsive">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Receipt No</th>
                            <th>Student Name</th>
                            <th>Adm #</th>
                            <th>Payment Date</th>
                            <th>Payment Mode</th>
                            <th style="text-align:right;">Amount Received</th>
                            <th style="text-align:right;">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_payments as $rp): ?>
                        <tr>
                            <td style="color:var(--admin-accent);font-weight:700;"><?php echo htmlspecialchars($rp['receipt_no']); ?></td>
                            <td style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($rp['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($rp['admission_no']); ?></td>
                            <td style="font-size:0.85rem;color:var(--admin-text-muted);"><?php echo date('M j, Y', strtotime($rp['payment_date'])); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $rp['payment_method'])); ?></td>
                            <td style="text-align:right;font-weight:700;color:var(--admin-accent);">Rs <?php echo number_format($rp['amount_paid'], 2); ?></td>
                            <td style="text-align:right;">
                                <a href="receipt.php?receipt_no=<?php echo urlencode($rp['receipt_no']); ?>" class="action-btn action-btn--view" title="Print Receipt">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><p>No fee payment transactions recorded yet.</p></div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
