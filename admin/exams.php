<?php
/**
 * admin/exams.php
 * Examination and Results Management Module
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Examinations & Marks';
$current_page = 'exams';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Tab: 'exams', 'schedules', 'marks', 'report_card'
$tab = $_GET['tab'] ?? 'exams';
$exam_id = (int)($_GET['exam_id'] ?? 0);
$class_id = (int)($_GET['class_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$schedule_id = (int)($_GET['schedule_id'] ?? 0);

// Fetch academic sessions, classes, subjects
$sessions = [];
$res = mysqli_query($conn, "SELECT id, session_name, is_current FROM academic_sessions ORDER BY is_current DESC, id DESC");
while ($r = mysqli_fetch_assoc($res)) {
    $sessions[] = $r;
}

$classes = [];
$res = mysqli_query($conn, "SELECT id, class_name, numeric_grade FROM classes ORDER BY numeric_grade ASC, class_name ASC");
while ($r = mysqli_fetch_assoc($res)) {
    $classes[] = $r;
}

$subjects = [];
$res = mysqli_query($conn, "SELECT id, subject_name, subject_code FROM subjects ORDER BY subject_name ASC");
while ($r = mysqli_fetch_assoc($res)) {
    $subjects[] = $r;
}

// ─── POST HANDLERS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
        header("Location: exams.php?tab={$tab}");
        exit();
    }

    $action = $_POST['action'] ?? '';

    // 1. CREATE EXAM
    if ($action === 'create_exam') {
        $exam_name  = trim($_POST['exam_name'] ?? '');
        $session_id = (int)($_POST['session_id'] ?? 0);
        $term       = trim($_POST['term'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date   = trim($_POST['end_date'] ?? '');

        if ($exam_name === '') $errors[] = 'Exam name is required.';
        if ($session_id <= 0) $errors[] = 'Academic session is required.';
        if ($term === '') $errors[] = 'Term is required (e.g. Mid Term, Final).';
        if ($start_date === '' || $end_date === '') $errors[] = 'Both start and end dates are required.';
        elseif ($end_date < $start_date) $errors[] = 'End date cannot be earlier than start date.';

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO exams (session_id, exam_name, start_date, end_date, term) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'issss', $session_id, $exam_name, $start_date, $end_date, $term);
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                logActivity('exam_created', 'exams', $new_id, "Created examination '{$exam_name}'");
                $_SESSION['flash_success'] = "Examination '{$exam_name}' created successfully.";
                header("Location: exams.php?tab=schedules&exam_id={$new_id}");
                exit();
            } else {
                $errors[] = 'Failed to create examination: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // 2. CREATE / UPDATE EXAM SCHEDULE
    if ($action === 'create_schedule') {
        $p_exam_id   = (int)($_POST['exam_id'] ?? 0);
        $p_class_id  = (int)($_POST['class_id'] ?? 0);
        $p_subj_id   = (int)($_POST['subject_id'] ?? 0);
        $exam_date   = trim($_POST['exam_date'] ?? '');
        $start_time  = trim($_POST['start_time'] ?? '09:00:00');
        $end_time    = trim($_POST['end_time'] ?? '12:00:00');
        $max_marks   = (float)($_POST['max_marks'] ?? 100);
        $pass_marks  = (float)($_POST['pass_marks'] ?? 40);

        if ($p_exam_id <= 0 || $p_class_id <= 0 || $p_subj_id <= 0) {
            $errors[] = 'Exam, Class, and Subject selections are required.';
        }
        if ($exam_date === '') $errors[] = 'Exam paper date is required.';
        if ($max_marks <= 0) $errors[] = 'Maximum marks must be greater than 0.';
        if ($pass_marks > $max_marks) $errors[] = 'Passing marks cannot exceed maximum marks.';

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO exam_schedules (exam_id, class_id, subject_id, exam_date, start_time, end_time, max_marks, pass_marks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE exam_date = VALUES(exam_date), start_time = VALUES(start_time), end_time = VALUES(end_time), max_marks = VALUES(max_marks), pass_marks = VALUES(pass_marks)");
            mysqli_stmt_bind_param($stmt, 'iiisssdd', $p_exam_id, $p_class_id, $p_subj_id, $exam_date, $start_time, $end_time, $max_marks, $pass_marks);
            if (mysqli_stmt_execute($stmt)) {
                logActivity('exam_schedule_saved', 'exam_schedules', $p_exam_id, "Scheduled paper for Exam #{$p_exam_id}, Class #{$p_class_id}, Subject #{$p_subj_id}");
                $_SESSION['flash_success'] = 'Exam schedule paper successfully saved.';
                header("Location: exams.php?tab=schedules&exam_id={$p_exam_id}&class_id={$p_class_id}");
                exit();
            } else {
                $errors[] = 'Database error: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // 3. ENTER / UPDATE MARKS
    if ($action === 'save_marks') {
        $p_sched_id = (int)($_POST['schedule_id'] ?? 0);
        $marks_data = $_POST['marks_obtained'] ?? [];
        $remarks_data = $_POST['remarks'] ?? [];

        // Fetch schedule to get max_marks & pass_marks
        $sc_chk = mysqli_prepare($conn, "SELECT max_marks, pass_marks FROM exam_schedules WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($sc_chk, 'i', $p_sched_id);
        mysqli_stmt_execute($sc_chk);
        $sc_res = mysqli_stmt_get_result($sc_chk);
        $schedule = mysqli_fetch_assoc($sc_res);
        mysqli_stmt_close($sc_chk);

        if (!$schedule) {
            $errors[] = 'Invalid exam schedule paper.';
        }

        if (empty($errors)) {
            $max_m = (float)$schedule['max_marks'];
            $pass_m = (float)$schedule['pass_marks'];

            mysqli_begin_transaction($conn);
            try {
                $saved = 0;
                $stmt = mysqli_prepare($conn, "INSERT INTO marks (exam_schedule_id, student_id, marks_obtained, grade, remarks) 
                    VALUES (?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), grade = VALUES(grade), remarks = VALUES(remarks)");

                foreach ($marks_data as $student_id => $val) {
                    $s_id = (int)$student_id;
                    if ($val === '') continue; // Skip blank unentered
                    $m_val = (float)$val;
                    if ($m_val < 0) $m_val = 0;
                    if ($m_val > $max_m) $m_val = $max_m;

                    // Calculate grade
                    $pct = ($max_m > 0) ? ($m_val / $max_m) * 100 : 0;
                    if ($pct >= 90) $grade = 'A+';
                    elseif ($pct >= 80) $grade = 'A';
                    elseif ($pct >= 70) $grade = 'B';
                    elseif ($pct >= 60) $grade = 'C';
                    elseif ($pct >= 50) $grade = 'D';
                    else $grade = 'F';

                    $rem = trim($remarks_data[$s_id] ?? '');

                    mysqli_stmt_bind_param($stmt, 'iidss', $p_sched_id, $s_id, $m_val, $grade, $rem);
                    mysqli_stmt_execute($stmt);
                    $saved++;
                }
                mysqli_stmt_close($stmt);

                mysqli_commit($conn);
                logActivity('marks_saved', 'marks', $p_sched_id, "Saved marks for Schedule #{$p_sched_id} ({$saved} students)");
                $_SESSION['flash_success'] = "Marks successfully saved for {$saved} student(s).";
                header("Location: exams.php?tab=marks&schedule_id={$p_sched_id}");
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $errors[] = 'Error saving marks: ' . $e->getMessage();
            }
        }
    }
}

// ─── QUERY DATA FOR TABS ────────────────────────────────────────
// Tab 1: All Exams
$all_exams = [];
$res = mysqli_query($conn, "SELECT e.*, ses.session_name, COUNT(es.id) AS papers_count 
    FROM exams e 
    LEFT JOIN academic_sessions ses ON e.session_id = ses.id
    LEFT JOIN exam_schedules es ON e.id = es.exam_id
    GROUP BY e.id ORDER BY e.id DESC");
while ($r = mysqli_fetch_assoc($res)) {
    $all_exams[] = $r;
    if ($exam_id === 0) {
        $exam_id = (int)$r['id'];
    }
}

// Tab 2: Schedules for selected exam
$exam_schedules = [];
if ($exam_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT es.*, c.class_name, sub.subject_name, sub.subject_code,
        COUNT(m.id) AS marks_entered_count
        FROM exam_schedules es
        LEFT JOIN classes c ON es.class_id = c.id
        LEFT JOIN subjects sub ON es.subject_id = sub.id
        LEFT JOIN marks m ON es.id = m.exam_schedule_id
        WHERE es.exam_id = ?
        GROUP BY es.id
        ORDER BY c.numeric_grade ASC, es.exam_date ASC");
    mysqli_stmt_bind_param($stmt, 'i', $exam_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $exam_schedules[] = $r;
    }
    mysqli_stmt_close($stmt);
}

// Tab 3: Marks entry for a specific schedule paper
$active_schedule = null;
$student_marks_roster = [];
if ($tab === 'marks' && $schedule_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT es.*, e.exam_name, e.term, c.class_name, sub.subject_name 
        FROM exam_schedules es
        JOIN exams e ON es.exam_id = e.id
        JOIN classes c ON es.class_id = c.id
        JOIN subjects sub ON es.subject_id = sub.id
        WHERE es.id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $schedule_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $active_schedule = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($active_schedule) {
        // Fetch all active students in that class
        $c_id = (int)$active_schedule['class_id'];
        $s_stmt = mysqli_prepare($conn, "SELECT s.id, s.admission_no, s.roll_no, s.first_name, s.last_name,
            sec.section_name, m.marks_obtained, m.grade, m.remarks
            FROM students s
            LEFT JOIN sections sec ON s.section_id = sec.id
            LEFT JOIN marks m ON m.exam_schedule_id = ? AND m.student_id = s.id
            WHERE s.class_id = ? AND s.status = 'active'
            ORDER BY s.roll_no ASC, s.first_name ASC");
        mysqli_stmt_bind_param($s_stmt, 'ii', $schedule_id, $c_id);
        mysqli_stmt_execute($s_stmt);
        $s_res = mysqli_stmt_get_result($s_stmt);
        while ($r = mysqli_fetch_assoc($s_res)) {
            $student_marks_roster[] = $r;
        }
        mysqli_stmt_close($s_stmt);
    }
}

// Tab 4: Student Result Card
$student_id = (int)($_GET['student_id'] ?? 0);
$result_student = null;
$result_marks = [];
if ($tab === 'report_card' && $student_id > 0 && $exam_id > 0) {
    // Fetch student info
    $st_stmt = mysqli_prepare($conn, "SELECT s.*, c.class_name, sec.section_name, ses.session_name 
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        LEFT JOIN sections sec ON s.section_id = sec.id 
        LEFT JOIN academic_sessions ses ON s.session_id = ses.id 
        WHERE s.id = ? LIMIT 1");
    mysqli_stmt_bind_param($st_stmt, 'i', $student_id);
    mysqli_stmt_execute($st_stmt);
    $result_student = mysqli_fetch_assoc(mysqli_stmt_get_result($st_stmt));
    mysqli_stmt_close($st_stmt);

    // Fetch marks in this exam
    $rm_stmt = mysqli_prepare($conn, "SELECT es.max_marks, es.pass_marks, sub.subject_name, sub.subject_code,
        m.marks_obtained, m.grade, m.remarks
        FROM exam_schedules es
        JOIN subjects sub ON es.subject_id = sub.id
        LEFT JOIN marks m ON es.id = m.exam_schedule_id AND m.student_id = ?
        WHERE es.exam_id = ? AND es.class_id = ?
        ORDER BY sub.subject_name ASC");
    mysqli_stmt_bind_param($rm_stmt, 'iii', $student_id, $exam_id, $result_student['class_id']);
    mysqli_stmt_execute($rm_stmt);
    $rm_res = mysqli_stmt_get_result($rm_stmt);
    while ($r = mysqli_fetch_assoc($rm_res)) {
        $result_marks[] = $r;
    }
    mysqli_stmt_close($rm_stmt);
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Examinations & Results</h1>
        <p class="page-header__desc">Manage exam terms, schedule subject papers, enter student marks, and generate result cards.</p>
    </div>
    <div class="page-header__actions">
        <a href="exams.php?tab=exams" class="btn-admin <?php echo $tab === 'exams' ? 'btn-admin--primary' : 'btn-admin--secondary'; ?>">All Exams</a>
        <a href="exams.php?tab=schedules&exam_id=<?php echo $exam_id; ?>" class="btn-admin <?php echo $tab === 'schedules' ? 'btn-admin--primary' : 'btn-admin--secondary'; ?>">Exam Schedules</a>
        <?php if ($tab === 'report_card' && $result_student): ?>
            <button type="button" onclick="window.print();" class="btn-admin btn-admin--primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span>Print Result Card</span>
            </button>
        <?php endif; ?>
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

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- TAB 1: ALL EXAMS LIST & NEW EXAM CREATION                           -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php if ($tab === 'exams'): ?>
    <div class="grid-2col" style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">
        <!-- Exams Table Panel -->
        <div class="panel animate-in">
            <div class="panel__header">
                <h3 class="panel__title">Existing Examinations</h3>
                <span style="color:var(--admin-text-muted);font-size:0.85rem;"><?php echo count($all_exams); ?> terms found</span>
            </div>

            <?php if (!empty($all_exams)): ?>
                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Exam Term</th>
                                <th>Academic Session</th>
                                <th>Date Range</th>
                                <th>Papers</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_exams as $ex): ?>
                            <tr>
                                <td style="font-weight:700;color:var(--admin-heading);">
                                    <?php echo htmlspecialchars($ex['exam_name']); ?>
                                    <div style="font-size:0.75rem;color:var(--admin-accent);font-weight:500;"><?php echo htmlspecialchars($ex['term']); ?></div>
                                </td>
                                <td style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($ex['session_name'] ?? 'N/A'); ?></td>
                                <td style="font-size:0.85rem;">
                                    <?php echo date('M j, Y', strtotime($ex['start_date'])); ?> – <?php echo date('M j, Y', strtotime($ex['end_date'])); ?>
                                </td>
                                <td>
                                    <span class="badge badge--info"><?php echo $ex['papers_count']; ?> Papers</span>
                                </td>
                                <td style="text-align:right;">
                                    <a href="exams.php?tab=schedules&exam_id=<?php echo $ex['id']; ?>" class="btn-admin btn-admin--sm btn-admin--primary">
                                        Manage Schedules
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No examinations configured yet. Create your first exam using the form.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Create Exam Card -->
        <div class="card animate-in" style="padding:24px;">
            <h3 style="font-size:1.1rem;font-weight:700;color:var(--admin-heading);margin-bottom:16px;">Create Examination</h3>
            <form method="POST" action="exams.php?tab=exams">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="create_exam">

                <div class="form-group">
                    <label class="form-label">Exam Title *</label>
                    <input type="text" name="exam_name" class="form-control" placeholder="e.g. Mid Term Examinations 2026" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Academic Session *</label>
                    <select name="session_id" class="form-control" required>
                        <?php foreach ($sessions as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['session_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Term *</label>
                    <select name="term" class="form-control" required>
                        <option value="First Term">First Term</option>
                        <option value="Mid Term">Mid Term</option>
                        <option value="Final Term">Final Term</option>
                        <option value="Monthly Assessment">Monthly Assessment</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Start Date *</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">End Date *</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+10 days')); ?>" required>
                </div>

                <button type="submit" class="btn-admin btn-admin--primary" style="width:100%;margin-top:8px;">
                    Create Examination
                </button>
            </form>
        </div>
    </div>

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- TAB 2: EXAM SCHEDULES (Papers)                                      -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php elseif ($tab === 'schedules'): ?>
    <div class="grid-2col" style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">
        <div class="panel animate-in">
            <div class="panel__header" style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h3 class="panel__title">Scheduled Papers</h3>
                    <small style="color:var(--admin-text-muted);">Papers configured for the selected examination</small>
                </div>
                <div>
                    <form method="GET" action="exams.php" style="display:inline-flex;gap:8px;">
                        <input type="hidden" name="tab" value="schedules">
                        <select name="exam_id" class="form-control" onchange="this.form.submit()">
                            <?php foreach ($all_exams as $ex): ?>
                                <option value="<?php echo $ex['id']; ?>" <?php echo $exam_id === (int)$ex['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ex['exam_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <?php if (!empty($exam_schedules)): ?>
                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Date & Time</th>
                                <th>Max / Pass Marks</th>
                                <th>Marks Entered</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exam_schedules as $sc): ?>
                            <tr>
                                <td style="font-weight:700;color:var(--admin-heading);"><?php echo htmlspecialchars($sc['class_name']); ?></td>
                                <td>
                                    <div style="font-weight:600;"><?php echo htmlspecialchars($sc['subject_name']); ?></div>
                                    <small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($sc['subject_code']); ?></small>
                                </td>
                                <td style="font-size:0.85rem;">
                                    <div><?php echo date('M j, Y', strtotime($sc['exam_date'])); ?></div>
                                    <small style="color:var(--admin-text-muted);"><?php echo substr($sc['start_time'], 0, 5); ?> – <?php echo substr($sc['end_time'], 0, 5); ?></small>
                                </td>
                                <td>
                                    <span style="font-weight:700;color:var(--admin-accent);"><?php echo number_format($sc['max_marks'], 0); ?></span>
                                    <span style="color:var(--admin-text-muted);">/ <?php echo number_format($sc['pass_marks'], 0); ?> pass</span>
                                </td>
                                <td>
                                    <span class="badge badge--<?php echo $sc['marks_entered_count'] > 0 ? 'active' : 'warning'; ?>">
                                        <?php echo $sc['marks_entered_count']; ?> students
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <a href="exams.php?tab=marks&schedule_id=<?php echo $sc['id']; ?>" class="btn-admin btn-admin--sm btn-admin--primary">
                                        Enter Marks
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No papers scheduled for this exam term yet. Add papers using the form on the right.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Schedule Paper Form -->
        <div class="card animate-in" style="padding:24px;">
            <h3 style="font-size:1.1rem;font-weight:700;color:var(--admin-heading);margin-bottom:16px;">Schedule Exam Paper</h3>
            <form method="POST" action="exams.php?tab=schedules&exam_id=<?php echo $exam_id; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="create_schedule">
                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">

                <div class="form-group">
                    <label class="form-label">Target Class *</label>
                    <select name="class_id" class="form-control" required>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <select name="subject_id" class="form-control" required>
                        <?php foreach ($subjects as $sb): ?>
                            <option value="<?php echo $sb['id']; ?>"><?php echo htmlspecialchars($sb['subject_name'] . ' (' . $sb['subject_code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Exam Paper Date *</label>
                    <input type="date" name="exam_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div class="form-group">
                        <label class="form-label">Start Time</label>
                        <input type="time" name="start_time" class="form-control" value="09:00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Time</label>
                        <input type="time" name="end_time" class="form-control" value="12:00" required>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div class="form-group">
                        <label class="form-label">Max Marks *</label>
                        <input type="number" name="max_marks" class="form-control" value="100" min="1" step="0.5" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pass Marks *</label>
                        <input type="number" name="pass_marks" class="form-control" value="40" min="1" step="0.5" required>
                    </div>
                </div>

                <button type="submit" class="btn-admin btn-admin--primary" style="width:100%;margin-top:8px;">
                    Add Paper to Schedule
                </button>
            </form>
        </div>
    </div>

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- TAB 3: MARKS ENTRY FOR SCHEDULED PAPER                             -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php elseif ($tab === 'marks'): ?>
    <?php if ($active_schedule): ?>
        <div class="panel animate-in">
            <div class="panel__header" style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h3 class="panel__title">Marks Entry: <?php echo htmlspecialchars($active_schedule['subject_name']); ?></h3>
                    <div style="color:var(--admin-accent);font-size:0.85rem;font-weight:600;">
                        <?php echo htmlspecialchars($active_schedule['exam_name']); ?> | Class: <?php echo htmlspecialchars($active_schedule['class_name']); ?> | Max Marks: <?php echo number_format($active_schedule['max_marks'], 0); ?>
                    </div>
                </div>
                <div>
                    <a href="exams.php?tab=schedules&exam_id=<?php echo $active_schedule['exam_id']; ?>" class="btn-admin btn-admin--secondary btn-admin--sm">← Back to Schedules</a>
                </div>
            </div>

            <?php if (!empty($student_marks_roster)): ?>
                <form method="POST" action="exams.php?tab=marks&schedule_id=<?php echo $schedule_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="save_marks">
                    <input type="hidden" name="schedule_id" value="<?php echo $schedule_id; ?>">

                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th style="width:70px;">Roll #</th>
                                    <th>Student</th>
                                    <th>Section</th>
                                    <th style="width:160px;">Marks Obtained (Max: <?php echo number_format($active_schedule['max_marks'],0); ?>)</th>
                                    <th style="width:100px;">Grade</th>
                                    <th>Remarks</th>
                                    <th style="text-align:right;">Report Card</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($student_marks_roster as $st): ?>
                                <tr>
                                    <td style="font-weight:600;color:var(--admin-accent);"><?php echo htmlspecialchars($st['roll_no'] ?: '-'); ?></td>
                                    <td style="font-weight:600;color:var(--admin-heading);">
                                        <?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?>
                                        <div style="font-size:0.75rem;color:var(--admin-text-muted);font-weight:400;"><?php echo htmlspecialchars($st['admission_no']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($st['section_name'] ?: 'A'); ?></td>
                                    <td>
                                        <input type="number" name="marks_obtained[<?php echo $st['id']; ?>]" 
                                               class="form-control" style="font-weight:700;padding:6px 10px;"
                                               step="0.5" min="0" max="<?php echo $active_schedule['max_marks']; ?>"
                                               placeholder="0 - <?php echo (int)$active_schedule['max_marks']; ?>"
                                               value="<?php echo $st['marks_obtained'] !== null ? htmlspecialchars($st['marks_obtained']) : ''; ?>">
                                    </td>
                                    <td>
                                        <?php if ($st['grade']): ?>
                                            <span class="badge badge--<?php echo $st['grade'] !== 'F' ? 'active' : 'danger'; ?>">
                                                <?php echo htmlspecialchars($st['grade']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--admin-text-muted);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="text" name="remarks[<?php echo $st['id']; ?>]" class="form-control" style="padding:6px 10px;font-size:0.85rem;" placeholder="Optional remarks" value="<?php echo htmlspecialchars($st['remarks'] ?? ''); ?>">
                                    </td>
                                    <td style="text-align:right;">
                                        <a href="exams.php?tab=report_card&exam_id=<?php echo $active_schedule['exam_id']; ?>&student_id=<?php echo $st['id']; ?>" class="action-btn action-btn--view" title="View Result Card">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="padding:20px;border-top:1px solid var(--admin-border);display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn-admin btn-admin--primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            <span>Save All Student Marks</span>
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <p>No active students found in this class.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>Please select an exam schedule paper from the schedules list to enter marks.</p>
            <a href="exams.php?tab=schedules" class="btn-admin btn-admin--primary" style="margin-top:12px;">Go to Schedules</a>
        </div>
    <?php endif; ?>

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- TAB 4: STUDENT RESULT CARD / MARKSHEET (PRINTABLE)                 -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php elseif ($tab === 'report_card'): ?>
    <?php if ($result_student): 
        $total_max = 0;
        $total_obtained = 0;
        $any_fail = false;
        foreach ($result_marks as $rm) {
            $total_max += (float)$rm['max_marks'];
            $total_obtained += (float)($rm['marks_obtained'] ?? 0);
            if ($rm['marks_obtained'] !== null && $rm['marks_obtained'] < $rm['pass_marks']) {
                $any_fail = true;
            }
        }
        $overall_pct = ($total_max > 0) ? round(($total_obtained / $total_max) * 100, 1) : 0;
        if ($overall_pct >= 90) $overall_grade = 'A+';
        elseif ($overall_pct >= 80) $overall_grade = 'A';
        elseif ($overall_pct >= 70) $overall_grade = 'B';
        elseif ($overall_pct >= 60) $overall_grade = 'C';
        elseif ($overall_pct >= 50) $overall_grade = 'D';
        else $overall_grade = 'F';
    ?>
        <div class="printable-card" style="background:#fff;color:#1e293b;padding:36px;border-radius:12px;border:1px solid #e2e8f0;max-width:800px;margin:0 auto;box-shadow:0 4px 20px rgba(0,0,0,0.06);">
            <!-- School Header -->
            <div style="text-align:center;border-bottom:2px solid #0d9488;padding-bottom:16px;margin-bottom:24px;">
                <h1 style="font-size:1.8rem;color:#0f172a;margin-bottom:4px;font-weight:800;">Brightway School</h1>
                <p style="color:#64748b;font-size:0.9rem;margin-bottom:6px;">Student Examination Result Card</p>
                <div style="display:inline-block;background:#f0fdfa;color:#0d9488;font-weight:700;padding:4px 14px;border-radius:20px;font-size:0.85rem;border:1px solid #99f6e4;">
                    <?php 
                        $cur_ex = array_filter($all_exams, function($e) use ($exam_id) { return (int)$e['id'] === $exam_id; });
                        $cur_ex = reset($cur_ex);
                        echo htmlspecialchars($cur_ex['exam_name'] ?? 'Term Examination'); 
                    ?>
                </div>
            </div>

            <!-- Student Meta Grid -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;background:#f8fafc;padding:16px;border-radius:8px;margin-bottom:24px;border:1px solid #e2e8f0;">
                <div>
                    <div style="font-size:0.8rem;color:#64748b;">Student Name</div>
                    <div style="font-size:1.1rem;font-weight:700;color:#0f172a;"><?php echo htmlspecialchars($result_student['first_name'] . ' ' . $result_student['last_name']); ?></div>
                </div>
                <div>
                    <div style="font-size:0.8rem;color:#64748b;">Admission / Roll #</div>
                    <div style="font-size:1.1rem;font-weight:700;color:#0f172a;"><?php echo htmlspecialchars($result_student['admission_no']); ?> (Roll: <?php echo htmlspecialchars($result_student['roll_no'] ?: '-'); ?>)</div>
                </div>
                <div>
                    <div style="font-size:0.8rem;color:#64748b;">Class & Section</div>
                    <div style="font-weight:600;color:#334155;"><?php echo htmlspecialchars($result_student['class_name'] . ' - ' . ($result_student['section_name'] ?: 'A')); ?></div>
                </div>
                <div>
                    <div style="font-size:0.8rem;color:#64748b;">Academic Session</div>
                    <div style="font-weight:600;color:#334155;"><?php echo htmlspecialchars($result_student['session_name']); ?></div>
                </div>
            </div>

            <!-- Marks Table -->
            <table style="width:100%;border-collapse:collapse;margin-bottom:24px;">
                <thead>
                    <tr style="background:#f1f5f9;border-bottom:2px solid #cbd5e1;text-align:left;">
                        <th style="padding:10px 12px;color:#334155;">Subject</th>
                        <th style="padding:10px 12px;color:#334155;text-align:center;">Max Marks</th>
                        <th style="padding:10px 12px;color:#334155;text-align:center;">Pass Marks</th>
                        <th style="padding:10px 12px;color:#334155;text-align:center;">Marks Obtained</th>
                        <th style="padding:10px 12px;color:#334155;text-align:center;">Grade</th>
                        <th style="padding:10px 12px;color:#334155;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result_marks as $rm): 
                        $is_pass = ($rm['marks_obtained'] !== null && $rm['marks_obtained'] >= $rm['pass_marks']);
                    ?>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:10px 12px;font-weight:600;color:#0f172a;">
                            <?php echo htmlspecialchars($rm['subject_name']); ?>
                            <small style="color:#64748b;display:block;font-size:0.75rem;"><?php echo htmlspecialchars($rm['subject_code']); ?></small>
                        </td>
                        <td style="padding:10px 12px;text-align:center;color:#475569;"><?php echo number_format($rm['max_marks'],0); ?></td>
                        <td style="padding:10px 12px;text-align:center;color:#475569;"><?php echo number_format($rm['pass_marks'],0); ?></td>
                        <td style="padding:10px 12px;text-align:center;font-weight:700;color:#0f172a;">
                            <?php echo $rm['marks_obtained'] !== null ? number_format($rm['marks_obtained'],1) : 'ABSENT'; ?>
                        </td>
                        <td style="padding:10px 12px;text-align:center;font-weight:700;color:<?php echo $is_pass ? '#0d9488' : '#dc2626'; ?>;">
                            <?php echo htmlspecialchars($rm['grade'] ?: '-'); ?>
                        </td>
                        <td style="padding:10px 12px;">
                            <span style="font-size:0.8rem;font-weight:700;color:<?php echo $is_pass ? '#0d9488' : '#dc2626'; ?>;">
                                <?php echo $is_pass ? 'PASSED' : 'FAILED'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8fafc;font-weight:800;border-top:2px solid #cbd5e1;">
                        <td style="padding:12px;">Total Summary</td>
                        <td style="padding:12px;text-align:center;"><?php echo number_format($total_max, 0); ?></td>
                        <td style="padding:12px;text-align:center;">-</td>
                        <td style="padding:12px;text-align:center;color:#0d9488;"><?php echo number_format($total_obtained, 1); ?></td>
                        <td style="padding:12px;text-align:center;color:#0d9488;"><?php echo $overall_grade; ?></td>
                        <td style="padding:12px;color:<?php echo !$any_fail ? '#0d9488' : '#dc2626'; ?>;">
                            <?php echo !$any_fail ? 'PASSED' : 'FAILED'; ?> (<?php echo $overall_pct; ?>%)
                        </td>
                    </tr>
                </tfoot>
            </table>

            <!-- Signatures -->
            <div style="display:flex;justify-content:space-between;margin-top:40px;padding-top:20px;border-top:1px dashed #cbd5e1;">
                <div style="text-align:center;width:180px;">
                    <div style="border-bottom:1px solid #475569;height:30px;"></div>
                    <div style="font-size:0.8rem;color:#475569;margin-top:6px;">Class Teacher's Signature</div>
                </div>
                <div style="text-align:center;width:180px;">
                    <div style="border-bottom:1px solid #475569;height:30px;"></div>
                    <div style="font-size:0.8rem;color:#475569;margin-top:6px;">Principal's Seal & Signature</div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>Please select a student and exam to generate the result card.</p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
