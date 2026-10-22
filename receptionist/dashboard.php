<?php
/**
 * receptionist/dashboard.php
 * Receptionist / Admission Officer Dashboard
 * Brightway School Management System
 */
require_once __DIR__ . '/auth_check.php';

$page_title = 'Admission Dashboard';
$current_page = 'dashboard';

// ─── ENSURE EXTENDED COLUMNS EXIST ───────────────────────────────
// (same defensive migration as admin/admissions.php)
$extend_cols = [
    'gender'     => "ALTER TABLE admissions_inquiries ADD COLUMN gender ENUM('Male','Female','Other') NOT NULL DEFAULT 'Male'",
    'class_id'   => "ALTER TABLE admissions_inquiries ADD COLUMN class_id INT NOT NULL DEFAULT 0",
    'section_id' => "ALTER TABLE admissions_inquiries ADD COLUMN section_id INT NOT NULL DEFAULT 0",
    'session_id' => "ALTER TABLE admissions_inquiries ADD COLUMN session_id INT NOT NULL DEFAULT 0",
    'dob'        => "ALTER TABLE admissions_inquiries ADD COLUMN dob DATE NULL",
    'address'    => "ALTER TABLE admissions_inquiries ADD COLUMN address TEXT NULL",
];
foreach ($extend_cols as $col => $sql) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM admissions_inquiries LIKE '{$col}'");
    if ($chk && mysqli_num_rows($chk) === 0) {
        mysqli_query($conn, $sql);
    }
}

// ─── REAL DASHBOARD STATS ─────────────────────────────────────────
$stats = [];

// Total applications
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries");
$stats['total'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

// Pending
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries WHERE status = 'pending'");
$stats['pending'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

// Approved / Accepted
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries WHERE status = 'accepted'");
$stats['approved'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

// Rejected
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries WHERE status = 'rejected'");
$stats['rejected'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

// Enrolled (converted to student)
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries WHERE converted_student_id IS NOT NULL AND converted_student_id > 0");
$stats['enrolled'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

// Reviewing / Interviewed
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries WHERE status IN ('reviewing','interviewed')");
$stats['in_review'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

// This month's applications
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
$stats['this_month'] = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

// Recently registered students (last 30 days)
$recent_students = [];
$res = mysqli_query($conn, "SELECT s.id, s.admission_no, s.first_name, s.last_name, s.gender, c.class_name, sec.section_name, s.admission_date
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    WHERE s.admission_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY s.admission_date DESC, s.id DESC LIMIT 8");
while ($r = mysqli_fetch_assoc($res)) $recent_students[] = $r;

// Recent applications
$recent_apps = [];
$res = mysqli_query($conn, "SELECT id, child_name, parent_name, grade_applying, status, created_at FROM admissions_inquiries ORDER BY created_at DESC LIMIT 10");
while ($r = mysqli_fetch_assoc($res)) $recent_apps[] = $r;

// Applications requiring action (accepted but not yet converted, or pending > 7 days)
$action_needed = [];
$res = mysqli_query($conn, "SELECT id, child_name, parent_name, grade_applying, status, created_at,
    DATEDIFF(NOW(), created_at) AS days_ago
    FROM admissions_inquiries
    WHERE (status = 'accepted' AND (converted_student_id IS NULL OR converted_student_id = 0))
       OR (status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY))
    ORDER BY days_ago DESC LIMIT 10");
while ($r = mysqli_fetch_assoc($res)) $action_needed[] = $r;

// Flash messages
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include __DIR__ . '/header.php';
?>

<?php if ($success_msg): ?>
    <div class="alert alert--success flash-auto-dismiss"><div class="alert__content"><span><?php echo htmlspecialchars($success_msg); ?></span></div></div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert alert--danger flash-auto-dismiss"><div class="alert__content"><span><?php echo htmlspecialchars($error_msg); ?></span></div></div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Admission Dashboard</h1>
        <p class="page-header__desc">Overview of admission applications and student registrations.</p>
    </div>
    <div class="page-header__actions">
        <a href="admissions.php?action=add" class="btn-admin btn-admin--primary">+ New Application</a>
    </div>
</div>

<!-- ── STAT CARDS ─────────────────────────────────────────────── -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:18px;margin-bottom:28px;">

    <div class="stat-card animate-in" style="border-left:4px solid var(--admin-accent);">
        <div class="stat-card__icon" style="background:rgba(139,92,246,.12);color:#8b5cf6;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($stats['total']); ?></span>
            <span class="stat-card__label">Total Applications</span>
        </div>
    </div>

    <div class="stat-card animate-in" style="border-left:4px solid #f59e0b;">
        <div class="stat-card__icon" style="background:rgba(245,158,11,.12);color:#f59e0b;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($stats['pending']); ?></span>
            <span class="stat-card__label">Pending</span>
        </div>
    </div>

    <div class="stat-card animate-in" style="border-left:4px solid #22c55e;">
        <div class="stat-card__icon" style="background:rgba(34,197,94,.12);color:#22c55e;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($stats['approved']); ?></span>
            <span class="stat-card__label">Approved</span>
        </div>
    </div>

    <div class="stat-card animate-in" style="border-left:4px solid #ef4444;">
        <div class="stat-card__icon" style="background:rgba(239,68,68,.12);color:#ef4444;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </div>
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($stats['rejected']); ?></span>
            <span class="stat-card__label">Rejected</span>
        </div>
    </div>

    <div class="stat-card animate-in" style="border-left:4px solid #38bdf8;">
        <div class="stat-card__icon" style="background:rgba(56,189,248,.12);color:#38bdf8;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($stats['enrolled']); ?></span>
            <span class="stat-card__label">Enrolled</span>
        </div>
    </div>

    <div class="stat-card animate-in" style="border-left:4px solid #a78bfa;">
        <div class="stat-card__icon" style="background:rgba(167,139,250,.12);color:#a78bfa;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($stats['this_month']); ?></span>
            <span class="stat-card__label">This Month</span>
        </div>
    </div>

</div>

<!-- ── QUICK ACTIONS ──────────────────────────────────────────── -->
<div class="dash-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:28px;">
    <a href="admissions.php?action=add" class="card animate-in" style="padding:18px;display:flex;align-items:center;gap:12px;text-decoration:none;border:1px solid rgba(139,92,246,.2);transition:border-color .2s;" onmouseover="this.style.borderColor='#8b5cf6'" onmouseout="this.style.borderColor='rgba(139,92,246,.2)'">
        <div style="width:40px;height:40px;border-radius:10px;background:rgba(139,92,246,.12);display:flex;align-items:center;justify-content:center;color:#8b5cf6;flex-shrink:0;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </div>
        <div><div style="font-weight:700;color:var(--admin-heading);font-size:.9rem;">New Application</div><div style="font-size:.75rem;color:var(--admin-text-muted);">Register applicant</div></div>
    </a>
    <a href="admissions.php" class="card animate-in" style="padding:18px;display:flex;align-items:center;gap:12px;text-decoration:none;border:1px solid rgba(34,197,94,.2);transition:border-color .2s;" onmouseover="this.style.borderColor='#22c55e'" onmouseout="this.style.borderColor='rgba(34,197,94,.2)'">
        <div style="width:40px;height:40px;border-radius:10px;background:rgba(34,197,94,.12);display:flex;align-items:center;justify-content:center;color:#22c55e;flex-shrink:0;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div><div style="font-weight:700;color:var(--admin-heading);font-size:.9rem;">All Applications</div><div style="font-size:.75rem;color:var(--admin-text-muted);">Browse & filter</div></div>
    </a>
    <a href="admissions.php?filter=pending" class="card animate-in" style="padding:18px;display:flex;align-items:center;gap:12px;text-decoration:none;border:1px solid rgba(245,158,11,.2);transition:border-color .2s;" onmouseover="this.style.borderColor='#f59e0b'" onmouseout="this.style.borderColor='rgba(245,158,11,.2)'">
        <div style="width:40px;height:40px;border-radius:10px;background:rgba(245,158,11,.12);display:flex;align-items:center;justify-content:center;color:#f59e0b;flex-shrink:0;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div><div style="font-weight:700;color:var(--admin-heading);font-size:.9rem;">Pending</div><div style="font-size:.75rem;color:var(--admin-text-muted)"><?php echo $stats['pending']; ?> awaiting review</div></div>
    </a>
    <a href="reports.php" class="card animate-in" style="padding:18px;display:flex;align-items:center;gap:12px;text-decoration:none;border:1px solid rgba(56,189,248,.2);transition:border-color .2s;" onmouseover="this.style.borderColor='#38bdf8'" onmouseout="this.style.borderColor='rgba(56,189,248,.2)'">
        <div style="width:40px;height:40px;border-radius:10px;background:rgba(56,189,248,.12);display:flex;align-items:center;justify-content:center;color:#38bdf8;flex-shrink:0;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        </div>
        <div><div style="font-weight:700;color:var(--admin-heading);font-size:.9rem;">Reports</div><div style="font-size:.75rem;color:var(--admin-text-muted);">Admission reports</div></div>
    </a>
</div>

<!-- ── TWO-COLUMN LOWER ────────────────────────────────────────── -->
<div class="dash-grid" style="grid-template-columns:1fr 1fr;gap:22px;margin-bottom:28px;">

    <!-- Applications Requiring Action -->
    <div class="panel animate-in">
        <div class="panel__header">
            <h3 class="panel__title">&#9888; Requires Action</h3>
            <span style="font-size:.75rem;color:var(--admin-text-muted);">Accepted but not enrolled, or pending > 7 days</span>
        </div>
        <?php if (!empty($action_needed)): ?>
        <div class="table-responsive">
            <table class="recent-table">
                <thead><tr><th>Applicant</th><th>Status</th><th>Days</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($action_needed as $a): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($a['child_name']); ?></div>
                        <small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($a['parent_name']); ?></small>
                    </td>
                    <td><span class="status-badge status-<?php echo in_array($a['status'],['accepted','approved']) ? 'approved' : 'pending'; ?>"><?php echo ucfirst($a['status']); ?></span></td>
                    <td style="font-size:.8rem;color:var(--admin-text-muted);"><?php echo (int)$a['days_ago']; ?>d ago</td>
                    <td><a href="admissions.php?action=view&id=<?php echo $a['id']; ?>" class="btn-admin btn-admin--sm btn-admin--ghost" style="font-size:.72rem;padding:3px 8px;">View</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:28px 0;"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--admin-text-muted);margin-bottom:8px;"><polyline points="20 6 9 17 4 12"/></svg><p>No actions required.</p></div>
        <?php endif; ?>
    </div>

    <!-- Recently Registered Students -->
    <div class="panel animate-in">
        <div class="panel__header">
            <h3 class="panel__title">Recently Registered Students</h3>
            <span style="font-size:.75rem;color:var(--admin-text-muted);">Last 30 days</span>
        </div>
        <?php if (!empty($recent_students)): ?>
        <div class="table-responsive">
            <table class="recent-table">
                <thead><tr><th>Student</th><th>Class</th><th>Admitted</th></tr></thead>
                <tbody>
                <?php foreach ($recent_students as $s): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></div>
                        <small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($s['admission_no']); ?></small>
                    </td>
                    <td style="font-size:.85rem;"><?php echo htmlspecialchars($s['class_name'] . ' – ' . $s['section_name']); ?></td>
                    <td style="font-size:.8rem;color:var(--admin-text-muted);"><?php echo date('M j', strtotime($s['admission_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:28px 0;"><p>No students registered in the last 30 days.</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- ── RECENT APPLICATIONS ────────────────────────────────────── -->
<div class="panel animate-in">
    <div class="panel__header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3 class="panel__title">Recent Applications</h3>
        <a href="admissions.php" class="btn-admin btn-admin--sm btn-admin--ghost">View All</a>
    </div>
    <?php if (!empty($recent_apps)): ?>
    <div class="table-responsive">
        <table class="recent-table">
            <thead><tr><th>Applicant</th><th>Parent</th><th>Grade</th><th>Status</th><th>Applied</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
            <?php foreach ($recent_apps as $a): ?>
            <tr>
                <td style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($a['child_name']); ?></td>
                <td style="font-size:.85rem;color:var(--admin-text-muted);"><?php echo htmlspecialchars($a['parent_name']); ?></td>
                <td style="font-size:.85rem;"><?php echo htmlspecialchars($a['grade_applying']); ?></td>
                <td>
                    <?php
                    $st = $a['status'];
                    $cls = match($st) {
                        'pending'   => 'pending',
                        'accepted'  => 'approved',
                        'rejected'  => 'rejected',
                        'enrolled'  => 'enrolled',
                        default     => 'pending',
                    };
                    ?>
                    <span class="status-badge status-<?php echo $cls; ?>"><?php echo ucfirst($st); ?></span>
                </td>
                <td style="font-size:.8rem;color:var(--admin-text-muted);"><?php echo date('M j, Y', strtotime($a['created_at'])); ?></td>
                <td style="text-align:right;">
                    <a href="admissions.php?action=view&id=<?php echo $a['id']; ?>" class="action-btn" title="View">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:40px 0;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--admin-text-muted);margin-bottom:10px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <p>No applications yet. <a href="admissions.php?action=add">Create the first one</a>.</p>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
