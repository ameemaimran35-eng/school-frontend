<?php
/**
 * admin/attendance.php
 * Daily Attendance Management Module
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Attendance Management';
$current_page = 'attendance';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Tab: 'mark' or 'report'
$tab = $_GET['tab'] ?? 'mark';

// Selected Filters
$selected_session = (int)($_GET['session_id'] ?? 0);
$selected_class   = (int)($_GET['class_id'] ?? 0);
$selected_section = (int)($_GET['section_id'] ?? 0);
$selected_date    = trim($_GET['date'] ?? date('Y-m-d'));

// Fetch sessions, classes, sections
$sessions = [];
$res = mysqli_query($conn, "SELECT id, session_name, is_current FROM academic_sessions ORDER BY is_current DESC, id DESC");
while ($r = mysqli_fetch_assoc($res)) {
    $sessions[] = $r;
    if ($selected_session === 0 && $r['is_current'] == 1) {
        $selected_session = (int)$r['id'];
    }
}
if ($selected_session === 0 && !empty($sessions)) {
    $selected_session = (int)$sessions[0]['id'];
}

$classes = [];
$res = mysqli_query($conn, "SELECT id, class_name, numeric_grade FROM classes ORDER BY numeric_grade ASC, class_name ASC");
while ($r = mysqli_fetch_assoc($res)) {
    $classes[] = $r;
    if ($selected_class === 0) {
        $selected_class = (int)$r['id'];
    }
}

$sections = [];
$res = mysqli_query($conn, "SELECT id, class_id, section_name FROM sections ORDER BY section_name ASC");
while ($r = mysqli_fetch_assoc($res)) {
    $sections[] = $r;
}

// Ensure section belongs to selected class
$valid_sections = array_filter($sections, function($s) use ($selected_class) {
    return (int)$s['class_id'] === $selected_class;
});
if ($selected_section === 0 && !empty($valid_sections)) {
    $first_sec = reset($valid_sections);
    $selected_section = (int)$first_sec['id'];
}

// ─── POST HANDLER: SAVE ATTENDANCE ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
        header("Location: attendance.php?session_id={$selected_session}&class_id={$selected_class}&section_id={$selected_section}&date=" . urlencode($selected_date));
        exit();
    }

    $post_date = trim($_POST['attendance_date'] ?? '');
    $post_class = (int)($_POST['class_id'] ?? 0);
    $post_section = (int)($_POST['section_id'] ?? 0);
    $status_data = $_POST['status'] ?? [];
    $remarks_data = $_POST['remarks'] ?? [];

    if ($post_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $post_date)) {
        $errors[] = 'A valid attendance date is required.';
    }
    if ($post_class <= 0 || $post_section <= 0) {
        $errors[] = 'Valid class and section must be selected.';
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $saved_count = 0;
            $stmt = mysqli_prepare($conn, "INSERT INTO attendance (student_id, class_id, section_id, attendance_date, status, remarks) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)");

            foreach ($status_data as $student_id => $st) {
                $s_id = (int)$student_id;
                $valid_st = in_array($st, ['present', 'absent', 'late', 'excused'], true) ? $st : 'present';
                $rem = trim($remarks_data[$s_id] ?? '');

                mysqli_stmt_bind_param($stmt, 'iiisss', $s_id, $post_class, $post_section, $post_date, $valid_st, $rem);
                mysqli_stmt_execute($stmt);
                $saved_count++;
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            logActivity('attendance_saved', 'attendance', $post_class, "Saved attendance for class #{$post_class}, section #{$post_section} on {$post_date} ({$saved_count} students)");
            $_SESSION['flash_success'] = "Attendance successfully saved for {$saved_count} student(s) on {$post_date}.";
            header("Location: attendance.php?session_id={$selected_session}&class_id={$post_class}&section_id={$post_section}&date=" . urlencode($post_date));
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Database error while saving attendance: ' . $e->getMessage();
        }
    }
}

// ─── FETCH STUDENTS & ATTENDANCE RECORDS FOR SELECTED CLASS/SECTION ─────
$student_rows = [];
$attendance_summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0];

if ($selected_class > 0 && $selected_section > 0) {
    // Only active students
    $stmt = mysqli_prepare($conn, "SELECT s.id, s.admission_no, s.roll_no, s.first_name, s.last_name, s.gender,
        a.status AS current_status, a.remarks AS current_remarks
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = ?
        WHERE s.class_id = ? AND s.section_id = ? AND s.status = 'active'
        ORDER BY s.roll_no ASC, s.first_name ASC, s.last_name ASC");
    mysqli_stmt_bind_param($stmt, 'sii', $selected_date, $selected_class, $selected_section);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $student_rows[] = $r;
        $attendance_summary['total']++;
        if ($r['current_status']) {
            if (isset($attendance_summary[$r['current_status']])) {
                $attendance_summary[$r['current_status']]++;
            }
        }
    }
    mysqli_stmt_close($stmt);
}

// Monthly report data if tab == report
$month = $_GET['month'] ?? date('Y-m');
$monthly_records = [];
if ($tab === 'report' && $selected_class > 0 && $selected_section > 0) {
    $month_start = $month . '-01';
    $month_end   = date('Y-m-t', strtotime($month_start));

    $m_stmt = mysqli_prepare($conn, "SELECT s.id, s.admission_no, s.roll_no, s.first_name, s.last_name,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) AS count_present,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) AS count_absent,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) AS count_late,
        COUNT(CASE WHEN a.status = 'excused' THEN 1 END) AS count_excused,
        COUNT(a.id) AS days_marked
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date BETWEEN ? AND ?
        WHERE s.class_id = ? AND s.section_id = ? AND s.status = 'active'
        GROUP BY s.id
        ORDER BY s.roll_no ASC, s.first_name ASC");
    mysqli_stmt_bind_param($m_stmt, 'ssii', $month_start, $month_end, $selected_class, $selected_section);
    mysqli_stmt_execute($m_stmt);
    $m_res = mysqli_stmt_get_result($m_stmt);
    while ($m_row = mysqli_fetch_assoc($m_res)) {
        $monthly_records[] = $m_row;
    }
    mysqli_stmt_close($m_stmt);
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Student Attendance</h1>
        <p class="page-header__desc">Mark daily student attendance, view rosters, and generate attendance registers.</p>
    </div>
    <div class="page-header__actions">
        <a href="attendance.php?tab=mark" class="btn-admin <?php echo $tab === 'mark' ? 'btn-admin--primary' : 'btn-admin--secondary'; ?>">Daily Register</a>
        <a href="attendance.php?tab=report" class="btn-admin <?php echo $tab === 'report' ? 'btn-admin--primary' : 'btn-admin--secondary'; ?>">Monthly Summary</a>
        <button type="button" onclick="window.print();" class="btn-admin btn-admin--ghost">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            <span>Print View</span>
        </button>
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
            <strong>Validation errors:</strong>
            <ul style="margin-left: 20px; margin-top: 6px;">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="filter-card animate-in">
    <form method="GET" action="attendance.php" class="filter-form">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">

        <div class="form-group">
            <label class="form-label" style="font-size:0.8rem;">Academic Session</label>
            <select name="session_id" class="form-control">
                <?php foreach ($sessions as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $selected_session === (int)$s['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['session_name']); ?><?php echo $s['is_current'] ? ' (Active)' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" style="font-size:0.8rem;">Class</label>
            <select name="class_id" id="filter_class" class="form-control" onchange="this.form.submit()">
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $selected_class === (int)$c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['class_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" style="font-size:0.8rem;">Section</label>
            <select name="section_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($valid_sections as $sec): ?>
                    <option value="<?php echo $sec['id']; ?>" <?php echo $selected_section === (int)$sec['id'] ? 'selected' : ''; ?>>
                        Section <?php echo htmlspecialchars($sec['section_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($tab === 'mark'): ?>
            <div class="form-group">
                <label class="form-label" style="font-size:0.8rem;">Attendance Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($selected_date); ?>" max="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()">
            </div>
        <?php else: ?>
            <div class="form-group">
                <label class="form-label" style="font-size:0.8rem;">Select Month</label>
                <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($month); ?>" onchange="this.form.submit()">
            </div>
        <?php endif; ?>

        <button type="submit" class="btn-admin btn-admin--secondary btn-admin--sm">Load Roster</button>
    </form>
</div>

<?php if ($tab === 'mark'): ?>
    <!-- Summary Stats Badge -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px;">
        <div class="stat-card" style="padding:16px;">
            <div class="stat-card__label">Total Enrolled</div>
            <div class="stat-card__value"><?php echo $attendance_summary['total']; ?></div>
        </div>
        <div class="stat-card" style="padding:16px;">
            <div class="stat-card__label" style="color:var(--admin-accent);">Present Today</div>
            <div class="stat-card__value" style="color:var(--admin-accent);"><?php echo $attendance_summary['present']; ?></div>
        </div>
        <div class="stat-card" style="padding:16px;">
            <div class="stat-card__label" style="color:var(--admin-red);">Absent</div>
            <div class="stat-card__value" style="color:var(--admin-red);"><?php echo $attendance_summary['absent']; ?></div>
        </div>
        <div class="stat-card" style="padding:16px;">
            <div class="stat-card__label" style="color:var(--admin-amber);">Late / Excused</div>
            <div class="stat-card__value" style="color:var(--admin-amber);"><?php echo $attendance_summary['late'] + $attendance_summary['excused']; ?></div>
        </div>
    </div>

    <!-- Attendance Entry Form -->
    <div class="panel animate-in">
        <div class="panel__header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 class="panel__title">Attendance Sheet: <?php echo date('l, F j, Y', strtotime($selected_date)); ?></h3>
                <small style="color:var(--admin-text-muted);">Mark each student or use quick buttons to set all.</small>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" class="btn-admin btn-admin--sm btn-admin--secondary" onclick="markAll('present')">All Present</button>
                <button type="button" class="btn-admin btn-admin--sm btn-admin--ghost" onclick="markAll('absent')">All Absent</button>
            </div>
        </div>

        <?php if (!empty($student_rows)): ?>
            <form method="POST" action="attendance.php?session_id=<?php echo $selected_session; ?>&class_id=<?php echo $selected_class; ?>&section_id=<?php echo $selected_section; ?>&date=<?php echo urlencode($selected_date); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="save_attendance">
                <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
                <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selected_date); ?>">

                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th style="width:70px;">Roll #</th>
                                <th>Student Name</th>
                                <th>Admission No</th>
                                <th style="text-align:center;width:320px;">Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_rows as $st): 
                                $status = $st['current_status'] ?: 'present';
                            ?>
                            <tr>
                                <td style="font-weight:600;color:var(--admin-accent);"><?php echo htmlspecialchars($st['roll_no'] ?: '-'); ?></td>
                                <td>
                                    <div style="font-weight:600;color:var(--admin-heading);">
                                        <?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?>
                                    </div>
                                    <small style="color:var(--admin-text-muted);font-size:0.75rem;"><?php echo htmlspecialchars($st['gender']); ?></small>
                                </td>
                                <td style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($st['admission_no']); ?></td>
                                <td style="text-align:center;">
                                    <div style="display:inline-flex;gap:12px;background:var(--admin-input-bg);padding:6px 12px;border-radius:20px;border:1px solid var(--admin-border);">
                                        <label style="cursor:pointer;display:inline-flex;align-items:center;gap:4px;font-size:0.85rem;color:var(--admin-accent);font-weight:600;">
                                            <input type="radio" class="status-radio-present" name="status[<?php echo $st['id']; ?>]" value="present" <?php echo $status === 'present' ? 'checked' : ''; ?>>
                                            P
                                        </label>
                                        <label style="cursor:pointer;display:inline-flex;align-items:center;gap:4px;font-size:0.85rem;color:var(--admin-red);font-weight:600;">
                                            <input type="radio" class="status-radio-absent" name="status[<?php echo $st['id']; ?>]" value="absent" <?php echo $status === 'absent' ? 'checked' : ''; ?>>
                                            A
                                        </label>
                                        <label style="cursor:pointer;display:inline-flex;align-items:center;gap:4px;font-size:0.85rem;color:var(--admin-amber);font-weight:600;">
                                            <input type="radio" class="status-radio-late" name="status[<?php echo $st['id']; ?>]" value="late" <?php echo $status === 'late' ? 'checked' : ''; ?>>
                                            L
                                        </label>
                                        <label style="cursor:pointer;display:inline-flex;align-items:center;gap:4px;font-size:0.85rem;color:var(--admin-blue);font-weight:600;">
                                            <input type="radio" class="status-radio-excused" name="status[<?php echo $st['id']; ?>]" value="excused" <?php echo $status === 'excused' ? 'checked' : ''; ?>>
                                            E
                                        </label>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="remarks[<?php echo $st['id']; ?>]" class="form-control" style="padding:4px 8px;font-size:0.85rem;" placeholder="Optional note" value="<?php echo htmlspecialchars($st['current_remarks'] ?? ''); ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="padding:20px;border-top:1px solid var(--admin-border);display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn-admin btn-admin--primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span>Save Attendance Records</span>
                    </button>
                </div>
            </form>

            <script>
            function markAll(status) {
                const radios = document.querySelectorAll('.status-radio-' + status);
                radios.forEach(r => r.checked = true);
            }
            </script>
        <?php else: ?>
            <div class="empty-state">
                <p>No active students enrolled in the selected class and section.</p>
                <a href="students.php?action=add" class="btn-admin btn-admin--sm btn-admin--primary" style="margin-top:12px;">+ Add Student</a>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- Monthly Attendance Report Table -->
    <div class="panel animate-in">
        <div class="panel__header">
            <h3 class="panel__title">Monthly Attendance Summary: <?php echo date('F Y', strtotime($month . '-01')); ?></h3>
            <span style="color:var(--admin-text-muted);font-size:0.85rem;">Aggregated from stored daily registers</span>
        </div>

        <?php if (!empty($monthly_records)): ?>
            <div class="table-responsive">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Roll #</th>
                            <th>Student Name</th>
                            <th>Admission No</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Excused</th>
                            <th>Total Days Marked</th>
                            <th>Attendance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_records as $m): 
                            $pct = ($m['days_marked'] > 0) ? round(($m['count_present'] / $m['days_marked']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td style="font-weight:600;color:var(--admin-accent);"><?php echo htmlspecialchars($m['roll_no'] ?: '-'); ?></td>
                            <td style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></td>
                            <td style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($m['admission_no']); ?></td>
                            <td style="color:var(--admin-accent);font-weight:600;"><?php echo $m['count_present']; ?></td>
                            <td style="color:var(--admin-red);font-weight:600;"><?php echo $m['count_absent']; ?></td>
                            <td style="color:var(--admin-amber);"><?php echo $m['count_late']; ?></td>
                            <td style="color:var(--admin-blue);"><?php echo $m['count_excused']; ?></td>
                            <td><?php echo $m['days_marked']; ?></td>
                            <td>
                                <span class="badge badge--<?php echo $pct >= 75 ? 'active' : ($pct >= 50 ? 'warning' : 'danger'); ?>">
                                    <?php echo $pct; ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No attendance data recorded for this month.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
