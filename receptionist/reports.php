<?php
/**
 * receptionist/reports.php
 * Receptionist / Admission Officer Portal — Admission Reports & CSV Export
 * SRS Version 1.0 — Section 8.6
 */
require_once __DIR__ . '/auth_check.php';

$page_title   = 'Admission Reports';
$current_page = 'reports';

// Filter inputs
$start_date    = trim($_GET['start_date'] ?? date('Y-01-01'));
$end_date      = trim($_GET['end_date'] ?? date('Y-m-d'));
$filter_status = trim($_GET['status'] ?? 'all');
$filter_class  = (int)($_GET['class_id'] ?? 0);
$filter_session= (int)($_GET['session_id'] ?? 0);
$filter_search = trim($_GET['search'] ?? '');
$export        = trim($_GET['export'] ?? '');

// ─── QUERY BUILDER ───────────────────────────────────────────────
$where = ["1=1"];
$params = [];
$types  = "";

// Date range filter
if (!empty($start_date)) {
    $where[] = "DATE(a.created_at) >= ?";
    $params[] = $start_date;
    $types   .= "s";
}
if (!empty($end_date)) {
    $where[] = "DATE(a.created_at) <= ?";
    $params[] = $end_date;
    $types   .= "s";
}

// Status filter
if ($filter_status === 'enrolled') {
    $where[] = "(a.converted_student_id IS NOT NULL AND a.converted_student_id > 0)";
} elseif ($filter_status !== 'all' && in_array($filter_status, ['pending','reviewing','interviewed','accepted','rejected'])) {
    $where[] = "a.status = ?";
    $params[] = $filter_status;
    $types   .= "s";
}

// Class filter
if ($filter_class > 0) {
    $where[] = "a.class_id = ?";
    $params[] = $filter_class;
    $types   .= "i";
}

// Session filter
if ($filter_session > 0) {
    $where[] = "a.session_id = ?";
    $params[] = $filter_session;
    $types   .= "i";
}

// Search keyword
if ($filter_search !== '') {
    $where[] = "(a.child_name LIKE ? OR a.parent_name LIKE ? OR a.phone LIKE ? OR a.email LIKE ?)";
    $like = "%" . $filter_search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= "ssss";
}

$where_sql = implode(" AND ", $where);

// ─── CSV EXPORT (SRS §8.6) ────────────────────────────────────────
if ($export === 'csv') {
    $filename = "admission_report_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // CSV Header row
    fputcsv($out, [
        'Application Ref',
        'Application Date',
        'Applicant Name',
        'Gender',
        'Date of Birth',
        'Grade Applying',
        'Class',
        'Section',
        'Academic Session',
        'Parent / Guardian Name',
        'Phone',
        'Email',
        'Address',
        'Status',
        'Enrolled Student Admission No',
        'Notes / Remarks'
    ]);

    $csv_sql = "SELECT a.id, a.created_at, a.child_name, a.gender, a.dob, a.grade_applying,
        c.class_name, sec.section_name, s.session_name,
        a.parent_name, a.phone, a.email, a.address, a.status,
        st.admission_no AS enrolled_adm_no, a.message
        FROM admissions_inquiries a
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN sections sec ON a.section_id = sec.id
        LEFT JOIN academic_sessions s ON a.session_id = s.id
        LEFT JOIN students st ON a.converted_student_id = st.id
        WHERE {$where_sql}
        ORDER BY a.created_at DESC, a.id DESC";

    $csv_stmt = mysqli_prepare($conn, $csv_sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($csv_stmt, $types, ...$params);
    }
    mysqli_stmt_execute($csv_stmt);
    $res = mysqli_stmt_get_result($csv_stmt);

    while ($r = mysqli_fetch_assoc($res)) {
        $status_label = !empty($r['enrolled_adm_no']) ? 'Enrolled' : ($r['status'] === 'accepted' ? 'Approved' : ucfirst($r['status']));
        fputcsv($out, [
            '#APP-' . str_pad($r['id'], 5, '0', STR_PAD_LEFT),
            date('Y-m-d', strtotime($r['created_at'])),
            $r['child_name'],
            $r['gender'] ?? 'Not Specified',
            $r['dob'] ?? '',
            $r['grade_applying'],
            $r['class_name'] ?? 'Unassigned',
            $r['section_name'] ?? '',
            $r['session_name'] ?? '',
            $r['parent_name'],
            $r['phone'],
            $r['email'] ?? '',
            $r['address'] ?? '',
            $status_label,
            $r['enrolled_adm_no'] ?? '',
            $r['message'] ?? ''
        ]);
    }

    mysqli_stmt_close($csv_stmt);
    fclose($out);
    exit();
}

// ─── FETCH CLASSES & SESSIONS FOR FILTERS ─────────────────────────
$classes = [];
$cres = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY class_name ASC");
if ($cres) while ($c = mysqli_fetch_assoc($cres)) $classes[] = $c;

$sessions = [];
$sres = mysqli_query($conn, "SELECT id, session_name, is_current FROM academic_sessions ORDER BY is_current DESC, id ASC");
if ($sres) while ($s = mysqli_fetch_assoc($sres)) $sessions[] = $s;

// ─── SUMMARY STATS FOR CURRENT FILTER ─────────────────────────────
$stat_sql = "SELECT
    COUNT(*) AS total_filtered,
    SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) AS count_pending,
    SUM(CASE WHEN a.status = 'reviewing' THEN 1 ELSE 0 END) AS count_reviewing,
    SUM(CASE WHEN a.status = 'interviewed' THEN 1 ELSE 0 END) AS count_interviewed,
    SUM(CASE WHEN a.status = 'accepted' THEN 1 ELSE 0 END) AS count_approved,
    SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) AS count_rejected,
    SUM(CASE WHEN a.converted_student_id IS NOT NULL AND a.converted_student_id > 0 THEN 1 ELSE 0 END) AS count_enrolled
    FROM admissions_inquiries a
    WHERE {$where_sql}";

$stat_stmt = mysqli_prepare($conn, $stat_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stat_stmt, $types, ...$params);
}
mysqli_stmt_execute($stat_stmt);
$filter_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stat_stmt));
mysqli_stmt_close($stat_stmt);

// ─── FETCH REPORT RECORDS ─────────────────────────────────────────
$report_sql = "SELECT a.*, c.class_name, sec.section_name, s.session_name,
    st.admission_no AS enrolled_adm_no
    FROM admissions_inquiries a
    LEFT JOIN classes c ON a.class_id = c.id
    LEFT JOIN sections sec ON a.section_id = sec.id
    LEFT JOIN academic_sessions s ON a.session_id = s.id
    LEFT JOIN students st ON a.converted_student_id = st.id
    WHERE {$where_sql}
    ORDER BY a.created_at DESC, a.id DESC";

$report_stmt = mysqli_prepare($conn, $report_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($report_stmt, $types, ...$params);
}
mysqli_stmt_execute($report_stmt);
$report_res = mysqli_stmt_get_result($report_stmt);
$records = [];
while ($r = mysqli_fetch_assoc($report_res)) $records[] = $r;
mysqli_stmt_close($report_stmt);

include __DIR__ . '/header.php';
?>

<style>
@media print {
    .admin-sidebar, .admin-topbar, .report-filter-panel, .page-header__actions, .no-print {
        display: none !important;
    }
    .admin-main { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
    .admin-content { padding: 0 !important; }
    .print-header { display: block !important; margin-bottom: 20px; text-align: center; border-bottom: 2px solid #333; padding-bottom: 12px; }
    .print-header h2 { font-size: 20px; font-weight: 800; margin: 0; color: #000; text-transform: uppercase; }
    .print-header p { font-size: 11px; margin: 4px 0 0 0; color: #444; }
    .print-signatures { display: flex !important; justify-content: space-between; margin-top: 50px; page-break-inside: avoid; }
    .print-signature-box { width: 220px; text-align: center; border-top: 1px solid #444; padding-top: 6px; font-size: 11px; font-weight: 600; color: #000; }
    body { background: #fff !important; color: #000 !important; font-size: 11px !important; }
    .panel { box-shadow: none !important; border: 1px solid #ccc !important; }
    .recent-table th { background: #f0f0f0 !important; color: #000 !important; }
    .recent-table td { color: #000 !important; border-bottom: 1px solid #ddd !important; }
    .status-badge { border: 1px solid #888 !important; color: #000 !important; background: transparent !important; }
}
.print-header, .print-signatures { display: none; }
</style>

<!-- Printable Header for Print/PDF -->
<div class="print-header">
    <h2><?php echo htmlspecialchars($school_name); ?></h2>
    <p>Official Admission & Registration Report</p>
    <p style="font-size:10px;color:#666;margin-top:2px;">
        Period: <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?> &bull; Generated on <?php echo date('F d, Y, h:i A'); ?>
    </p>
</div>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Admission Reports</h1>
        <p class="page-header__desc">Generate filtered admission summaries, print official applicant reports, or export to CSV.</p>
    </div>
    <div class="page-header__actions" style="display:flex;gap:10px;">
        <button onclick="window.print()" class="btn-admin btn-admin--ghost" style="border-color:var(--admin-accent);color:var(--admin-accent);">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Report
        </button>

        <?php
        $export_params = $_GET;
        $export_params['export'] = 'csv';
        $export_url = 'reports.php?' . http_build_query($export_params);
        ?>
        <a href="<?php echo $export_url; ?>" class="btn-admin btn-admin--primary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export to CSV
        </a>
    </div>
</div>

<!-- ── SUMMARY KPI TILES (SRS §8.6) ───────────────────────────── -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-bottom:24px;">
    <div class="stat-card animate-in" style="border-left:4px solid var(--admin-accent);">
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($filter_stats['total_filtered'] ?? 0); ?></span>
            <span class="stat-card__label">Total Filtered</span>
        </div>
    </div>
    <div class="stat-card animate-in" style="border-left:4px solid #f59e0b;">
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($filter_stats['count_pending'] ?? 0); ?></span>
            <span class="stat-card__label">Pending</span>
        </div>
    </div>
    <div class="stat-card animate-in" style="border-left:4px solid #a78bfa;">
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format(($filter_stats['count_reviewing'] ?? 0) + ($filter_stats['count_interviewed'] ?? 0)); ?></span>
            <span class="stat-card__label">In Review</span>
        </div>
    </div>
    <div class="stat-card animate-in" style="border-left:4px solid #22c55e;">
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($filter_stats['count_approved'] ?? 0); ?></span>
            <span class="stat-card__label">Approved</span>
        </div>
    </div>
    <div class="stat-card animate-in" style="border-left:4px solid #ef4444;">
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($filter_stats['count_rejected'] ?? 0); ?></span>
            <span class="stat-card__label">Rejected</span>
        </div>
    </div>
    <div class="stat-card animate-in" style="border-left:4px solid #38bdf8;">
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($filter_stats['count_enrolled'] ?? 0); ?></span>
            <span class="stat-card__label">Enrolled Students</span>
        </div>
    </div>
</div>

<!-- ── FILTERS BAR ────────────────────────────────────────────── -->
<div class="panel report-filter-panel animate-in" style="margin-bottom:24px;padding:20px;">
    <form method="GET" action="reports.php" style="display:flex;flex-direction:column;gap:14px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:14px;">
            <div class="form-group">
                <label class="form-label" style="font-size:.78rem;font-weight:600;">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" style="font-size:.78rem;font-weight:600;">To Date</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" style="font-size:.78rem;font-weight:600;">Status</label>
                <select name="status" class="form-control">
                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="reviewing" <?php echo $filter_status === 'reviewing' ? 'selected' : ''; ?>>Reviewing</option>
                    <option value="interviewed" <?php echo $filter_status === 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                    <option value="accepted" <?php echo $filter_status === 'accepted' ? 'selected' : ''; ?>>Accepted (Approved)</option>
                    <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="enrolled" <?php echo $filter_status === 'enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-size:.78rem;font-weight:600;">Class</label>
                <select name="class_id" class="form-control">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $filter_class === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-size:.78rem;font-weight:600;">Session</label>
                <select name="session_id" class="form-control">
                    <option value="0">All Sessions</option>
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $filter_session === (int)$s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['session_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display:flex;gap:12px;align-items:center;">
            <div style="flex:1;">
                <input type="text" name="search" class="form-control" placeholder="Search applicant name, parent name, contact phone, or email..." value="<?php echo htmlspecialchars($filter_search); ?>">
            </div>
            <button type="submit" class="btn-admin btn-admin--primary" style="padding:9px 20px;">Generate Report</button>
            <a href="reports.php" class="btn-admin btn-admin--ghost" style="padding:9px 16px;">Reset</a>
        </div>
    </form>
</div>

<!-- ── REPORT TABLE ───────────────────────────────────────────── -->
<div class="panel animate-in">
    <div class="panel__header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3 class="panel__title">
            Admission Inquiries & Applications
            <span style="font-size:.75rem;color:var(--admin-text-muted);font-weight:400;margin-left:8px;">
                (<?php echo count($records); ?> records)
            </span>
        </h3>
        <span style="font-size:.75rem;color:var(--admin-text-muted);">
            Report Date: <?php echo date('M d, Y'); ?>
        </span>
    </div>

    <?php if (!empty($records)): ?>
    <div class="table-responsive">
        <table class="recent-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Date</th>
                    <th>Applicant Name</th>
                    <th>Gender</th>
                    <th>Target Class</th>
                    <th>Parent / Guardian</th>
                    <th>Contact Phone</th>
                    <th>Status</th>
                    <th>Enrolled Student</th>
                    <th class="no-print" style="text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r):
                    $st = $r['status'];
                    $is_enr = !empty($r['enrolled_adm_no']);
                    $st_badge = $is_enr ? 'enrolled' : (in_array($st, ['accepted','approved']) ? 'approved' : ($st === 'rejected' ? 'rejected' : 'pending'));
                    $st_text = $is_enr ? 'Enrolled' : ($st === 'accepted' ? 'Approved' : ucfirst($st));
                ?>
                <tr>
                    <td>
                        <strong style="color:var(--admin-accent);font-size:.85rem;">#APP-<?php echo str_pad($r['id'], 5, '0', STR_PAD_LEFT); ?></strong>
                    </td>
                    <td style="font-size:.82rem;color:var(--admin-text-muted);white-space:nowrap;">
                        <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--admin-heading);"><?php echo htmlspecialchars($r['child_name']); ?></div>
                        <small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($r['grade_applying']); ?></small>
                    </td>
                    <td style="font-size:.85rem;color:var(--admin-text-muted);"><?php echo htmlspecialchars($r['gender'] ?? '—'); ?></td>
                    <td style="font-size:.85rem;">
                        <span style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($r['class_name'] ?? '—'); ?></span>
                        <small style="color:var(--admin-text-muted);display:block;"><?php echo htmlspecialchars($r['section_name'] ?? ''); ?></small>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--admin-heading);font-size:.85rem;"><?php echo htmlspecialchars($r['parent_name']); ?></div>
                        <?php if (!empty($r['email'])): ?>
                            <small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($r['email']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.85rem;font-weight:600;"><?php echo htmlspecialchars($r['phone']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $st_badge; ?>">
                            <?php echo $st_text; ?>
                        </span>
                    </td>
                    <td style="font-size:.82rem;">
                        <?php if ($is_enr): ?>
                            <strong style="color:#38bdf8;"><?php echo htmlspecialchars($r['enrolled_adm_no']); ?></strong>
                        <?php else: ?>
                            <span style="color:var(--admin-text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="no-print" style="text-align:right;">
                        <a href="admissions.php?action=view&id=<?php echo $r['id']; ?>" class="action-btn" title="View Application">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>
                        <a href="admissions.php?action=print&id=<?php echo $r['id']; ?>" target="_blank" class="action-btn" title="Print Form" style="color:var(--admin-accent);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Print Signatures Section for Official Report Printing -->
    <div class="print-signatures">
        <div class="print-signature-box">
            Admission Officer Signature<br>
            <span style="font-size:9px;color:#666;">Prepared by <?php echo htmlspecialchars($rec_name); ?></span>
        </div>
        <div class="print-signature-box">
            Principal / Administrator Signature<br>
            <span style="font-size:9px;color:#666;">Date: <?php echo date('d-m-Y'); ?></span>
        </div>
    </div>

    <?php else: ?>
    <div class="empty-state" style="padding:48px 0;">
        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--admin-text-muted);margin-bottom:12px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <p style="font-size:.95rem;color:var(--admin-heading);font-weight:600;">No admission records match the selected report criteria.</p>
        <span style="color:var(--admin-text-muted);font-size:.85rem;">Adjust the date range or status filters to view records.</span>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
