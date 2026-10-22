<?php
/**
 * admin/students.php
 * Students Management Module
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Students Management';
$current_page = 'students';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students_list_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Admission No', 'Roll No', 'Full Name', 'Gender', 'Class', 'Section', 'Session', 'Parent Name', 'Parent Phone', 'Admission Date', 'Status']);
    $res = mysqli_query($conn, "SELECT s.*, c.class_name, sec.section_name, ses.session_name 
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        LEFT JOIN sections sec ON s.section_id = sec.id 
        LEFT JOIN academic_sessions ses ON s.session_id = ses.id 
        ORDER BY s.id DESC");
    while ($r = mysqli_fetch_assoc($res)) {
        fputcsv($out, [
            $r['admission_no'],
            $r['roll_no'] ?: '-',
            $r['first_name'] . ' ' . $r['last_name'],
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

$action = $_GET['action'] ?? 'list';
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch sessions, classes, and sections for dropdowns
$sessions = [];
$res = mysqli_query($conn, "SELECT id, session_name, is_current FROM academic_sessions ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($res)) {
    $sessions[] = $row;
}

$classes = [];
$res = mysqli_query($conn, "SELECT id, class_name, numeric_grade FROM classes ORDER BY numeric_grade ASC, class_name ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $classes[] = $row;
}

$sections = [];
$res = mysqli_query($conn, "SELECT id, class_id, section_name, capacity FROM sections ORDER BY section_name ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $sections[] = $row;
}

// ─── POST HANDLERS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token). Please try again.';
        header('Location: students.php');
        exit();
    }

    $post_action = $_POST['action'] ?? '';

    // ADD STUDENT
    if ($post_action === 'create') {
        $first_name     = trim($_POST['first_name'] ?? '');
        $last_name      = trim($_POST['last_name'] ?? '');
        $admission_no   = trim($_POST['admission_no'] ?? '');
        $roll_no        = trim($_POST['roll_no'] ?? '');
        $gender         = trim($_POST['gender'] ?? '');
        $date_of_birth  = trim($_POST['date_of_birth'] ?? '');
        $session_id     = (int)($_POST['session_id'] ?? 0);
        $class_id       = (int)($_POST['class_id'] ?? 0);
        $section_id     = (int)($_POST['section_id'] ?? 0);
        $parent_name    = trim($_POST['parent_name'] ?? '');
        $parent_phone   = trim($_POST['parent_phone'] ?? '');
        $parent_email   = trim($_POST['parent_email'] ?? '');
        $address        = trim($_POST['address'] ?? '');
        $admission_date = trim($_POST['admission_date'] ?? '');
        $status         = trim($_POST['status'] ?? 'active');

        // Validation
        if ($first_name === '' || $last_name === '') {
            $errors[] = 'Student first and last names are required.';
        }
        if ($admission_no === '') {
            $errors[] = 'Admission number is required.';
        } else {
            // Check uniqueness
            $chk = mysqli_prepare($conn, "SELECT id FROM students WHERE admission_no = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 's', $admission_no);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) {
                $errors[] = "Admission number '{$admission_no}' is already registered.";
            }
            mysqli_stmt_close($chk);
        }
        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $errors[] = 'Please select a valid gender.';
        }
        if ($date_of_birth === '') {
            $errors[] = 'Date of birth is required.';
        }
        if ($admission_date === '') {
            $admission_date = date('Y-m-d');
        }
        if ($session_id <= 0 || $class_id <= 0 || $section_id <= 0) {
            $errors[] = 'Academic session, class, and section must be selected.';
        } else {
            // Verify section belongs to class
            $chk = mysqli_prepare($conn, "SELECT id FROM sections WHERE id = ? AND class_id = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'ii', $section_id, $class_id);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) === 0) {
                $errors[] = 'Selected section does not belong to the selected class.';
            }
            mysqli_stmt_close($chk);
        }
        if ($parent_name === '' || $parent_phone === '') {
            $errors[] = 'Parent/guardian name and phone number are required.';
        }
        if ($address === '') {
            $errors[] = 'Residential address is required.';
        }
        if (!in_array($status, ['active', 'inactive', 'graduated', 'archived'], true)) {
            $status = 'active';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO students 
                (admission_no, roll_no, first_name, last_name, gender, date_of_birth, session_id, class_id, section_id, parent_name, parent_phone, parent_email, address, admission_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssssssiiissssss',
                $admission_no, $roll_no, $first_name, $last_name, $gender,
                $date_of_birth, $session_id, $class_id, $section_id,
                $parent_name, $parent_phone, $parent_email, $address,
                $admission_date, $status
            );
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                $_SESSION['flash_success'] = "Student {$first_name} {$last_name} ({$admission_no}) added successfully.";
                header("Location: students.php?action=view&id={$new_id}");
                exit();
            } else {
                $errors[] = 'Failed to create student: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }

    // UPDATE STUDENT
    if ($post_action === 'update') {
        $id             = (int)($_POST['id'] ?? 0);
        $first_name     = trim($_POST['first_name'] ?? '');
        $last_name      = trim($_POST['last_name'] ?? '');
        $admission_no   = trim($_POST['admission_no'] ?? '');
        $roll_no        = trim($_POST['roll_no'] ?? '');
        $gender         = trim($_POST['gender'] ?? '');
        $date_of_birth  = trim($_POST['date_of_birth'] ?? '');
        $session_id     = (int)($_POST['session_id'] ?? 0);
        $class_id       = (int)($_POST['class_id'] ?? 0);
        $section_id     = (int)($_POST['section_id'] ?? 0);
        $parent_name    = trim($_POST['parent_name'] ?? '');
        $parent_phone   = trim($_POST['parent_phone'] ?? '');
        $parent_email   = trim($_POST['parent_email'] ?? '');
        $address        = trim($_POST['address'] ?? '');
        $admission_date = trim($_POST['admission_date'] ?? '');
        $status         = trim($_POST['status'] ?? 'active');

        if ($id <= 0) {
            $errors[] = 'Invalid student record identifier.';
        }
        if ($first_name === '' || $last_name === '') {
            $errors[] = 'First and last names are required.';
        }
        if ($admission_no === '') {
            $errors[] = 'Admission number is required.';
        } else {
            // Uniqueness check excluding self
            $chk = mysqli_prepare($conn, "SELECT id FROM students WHERE admission_no = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'si', $admission_no, $id);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) {
                $errors[] = "Admission number '{$admission_no}' is already used by another student.";
            }
            mysqli_stmt_close($chk);
        }
        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $errors[] = 'Please select a valid gender.';
        }
        if ($date_of_birth === '') {
            $errors[] = 'Date of birth is required.';
        }
        if ($session_id <= 0 || $class_id <= 0 || $section_id <= 0) {
            $errors[] = 'Session, class, and section must be selected.';
        } else {
            $chk = mysqli_prepare($conn, "SELECT id FROM sections WHERE id = ? AND class_id = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'ii', $section_id, $class_id);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) === 0) {
                $errors[] = 'Selected section does not belong to the selected class.';
            }
            mysqli_stmt_close($chk);
        }
        if ($parent_name === '' || $parent_phone === '') {
            $errors[] = 'Parent name and phone are required.';
        }
        if ($address === '') {
            $errors[] = 'Address is required.';
        }
        if (!in_array($status, ['active', 'inactive', 'graduated', 'archived'], true)) {
            $status = 'active';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "UPDATE students SET 
                admission_no = ?, roll_no = ?, first_name = ?, last_name = ?, gender = ?,
                date_of_birth = ?, session_id = ?, class_id = ?, section_id = ?,
                parent_name = ?, parent_phone = ?, parent_email = ?, address = ?,
                admission_date = ?, status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ssssssiiissssssi',
                $admission_no, $roll_no, $first_name, $last_name, $gender,
                $date_of_birth, $session_id, $class_id, $section_id,
                $parent_name, $parent_phone, $parent_email, $address,
                $admission_date, $status, $id
            );
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $_SESSION['flash_success'] = "Student #{$admission_no} updated successfully.";
                header("Location: students.php?action=view&id={$id}");
                exit();
            } else {
                $errors[] = 'Failed to update student: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }

    // UPDATE STATUS / ARCHIVE
    if ($post_action === 'change_status') {
        $id = (int)($_POST['id'] ?? 0);
        $new_status = trim($_POST['new_status'] ?? '');
        if ($id > 0 && in_array($new_status, ['active', 'inactive', 'graduated', 'archived'], true)) {
            $stmt = mysqli_prepare($conn, "UPDATE students SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $new_status, $id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_success'] = "Student status updated to " . ucfirst($new_status) . ".";
            } else {
                $_SESSION['flash_error'] = "Could not update status: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        header("Location: students.php" . ($id > 0 ? "?action=view&id={$id}" : ""));
        exit();
    }
}

// ─── LOAD DATA FOR VIEW OR EDIT ──────────────────────────────────
$student = null;
if (($action === 'view' || $action === 'edit') && $student_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT s.*, 
        c.class_name, sec.section_name, ses.session_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN academic_sessions ses ON s.session_id = ses.id
        WHERE s.id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$student) {
        $_SESSION['flash_error'] = 'Student not found.';
        header('Location: students.php');
        exit();
    }
}

// Auto-generate next admission number suggestion
$suggested_adm = 'BW-' . date('Y') . '-' . str_pad((string)(rand(100, 999)), 4, '0', STR_PAD_LEFT);
$res = mysqli_query($conn, "SELECT admission_no FROM students ORDER BY id DESC LIMIT 1");
if ($r = mysqli_fetch_assoc($res)) {
    if (preg_match('/BW-(\d{4})-(\d+)/', $r['admission_no'], $m)) {
        $next_num = (int)$m[2] + 1;
        $suggested_adm = 'BW-' . date('Y') . '-' . str_pad((string)$next_num, 4, '0', STR_PAD_LEFT);
    }
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Students Management</h1>
        <p class="page-header__desc">Manage student admissions, profiles, academic placements, and statuses.</p>
    </div>
    <div class="page-header__actions">
        <?php if ($action !== 'list'): ?>
            <a href="students.php" class="btn-admin btn-admin--secondary">← Back to List</a>
        <?php endif; ?>
        <?php if ($action !== 'add'): ?>
            <a href="students.php?action=add" class="btn-admin btn-admin--primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Add Student</span>
            </a>
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
            <strong>Please correct the following:</strong>
            <ul style="margin-left: 20px; margin-top: 6px;">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- VIEW SINGLE STUDENT                                                 -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php if ($action === 'view' && $student): ?>
    <div class="detail-card animate-in">
        <div class="detail-card__header">
            <div style="display:flex;align-items:center;gap:14px;">
                <div class="student-avatar" style="width:48px;height:48px;font-size:18px;background:var(--admin-accent);">
                    <?php echo strtoupper(substr($student['first_name'],0,1) . substr($student['last_name'],0,1)); ?>
                </div>
                <div>
                    <h2 style="font-size:1.3rem;font-weight:700;color:var(--admin-heading);margin-bottom:2px;">
                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                    </h2>
                    <span style="color:var(--admin-text-muted);font-size:0.85rem;">
                        Admission No: <strong style="color:var(--admin-accent);"><?php echo htmlspecialchars($student['admission_no']); ?></strong>
                        <?php if ($student['roll_no']): ?> | Roll No: <?php echo htmlspecialchars($student['roll_no']); ?><?php endif; ?>
                    </span>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="badge badge--<?php echo $student['status']; ?>">
                    <?php echo ucfirst($student['status']); ?>
                </span>
                <button onclick="window.print()" class="btn-admin btn-admin--sm btn-admin--ghost" title="Print Profile">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    <span>Print</span>
                </button>
                <a href="students.php?action=edit&id=<?php echo $student['id']; ?>" class="btn-admin btn-admin--sm btn-admin--primary">Edit</a>
            </div>
        </div>

        <div class="detail-card__body">
            <h3 style="font-size:0.95rem;font-weight:700;color:var(--admin-heading);margin-bottom:14px;border-bottom:1px solid var(--admin-border);padding-bottom:8px;">
                Academic Placement
            </h3>
            <div class="detail-grid" style="margin-bottom:24px;">
                <div class="detail-item">
                    <span class="detail-label">Academic Session</span>
                    <span class="detail-value"><?php echo htmlspecialchars($student['session_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Class</span>
                    <span class="detail-value"><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Section</span>
                    <span class="detail-value"><?php echo htmlspecialchars($student['section_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Admission Date</span>
                    <span class="detail-value"><?php echo date('M j, Y', strtotime($student['admission_date'])); ?></span>
                </div>
            </div>

            <h3 style="font-size:0.95rem;font-weight:700;color:var(--admin-heading);margin-bottom:14px;border-bottom:1px solid var(--admin-border);padding-bottom:8px;">
                Personal Information
            </h3>
            <div class="detail-grid" style="margin-bottom:24px;">
                <div class="detail-item">
                    <span class="detail-label">Gender</span>
                    <span class="detail-value"><?php echo htmlspecialchars($student['gender']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Date of Birth</span>
                    <span class="detail-value"><?php echo date('M j, Y', strtotime($student['date_of_birth'])); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Residential Address</span>
                    <span class="detail-value"><?php echo nl2br(htmlspecialchars($student['address'])); ?></span>
                </div>
            </div>

            <h3 style="font-size:0.95rem;font-weight:700;color:var(--admin-heading);margin-bottom:14px;border-bottom:1px solid var(--admin-border);padding-bottom:8px;">
                Parent / Guardian Contact
            </h3>
            <div class="detail-grid" style="margin-bottom:24px;">
                <div class="detail-item">
                    <span class="detail-label">Parent Name</span>
                    <span class="detail-value"><?php echo htmlspecialchars($student['parent_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Phone Number</span>
                    <span class="detail-value"><?php echo htmlspecialchars($student['parent_phone']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email Address</span>
                    <span class="detail-value"><?php echo htmlspecialchars($student['parent_email'] ?: 'Not provided'); ?></span>
                </div>
            </div>

            <?php
            // Linked Records Queries
            $sid = (int)$student['id'];
            
            // Attendance Summary
            $att_stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT 
                COUNT(*) as total,
                SUM(status = 'present') as presents,
                SUM(status = 'absent') as absents,
                SUM(status = 'leave') as leaves
                FROM attendance WHERE student_id = $sid"));
            $att_rate = ($att_stats['total'] > 0) ? round(($att_stats['presents'] / $att_stats['total']) * 100, 1) : 0;
            $recent_att = mysqli_query($conn, "SELECT attendance_date, status, remarks FROM attendance WHERE student_id = $sid ORDER BY attendance_date DESC LIMIT 5");

            // Fee Summary
            $fee_assigned = (float)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(net_amount), 0) as total FROM student_fee_assignments WHERE student_id = $sid"))['total'] ?? 0);
            $fee_paid = (float)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount_paid), 0) as total FROM fee_payments WHERE student_id = $sid"))['total'] ?? 0);
            $fee_balance = max(0, $fee_assigned - $fee_paid);
            $recent_payments = mysqli_query($conn, "SELECT id, receipt_no, amount_paid, payment_method, payment_date FROM fee_payments WHERE student_id = $sid ORDER BY payment_date DESC, id DESC LIMIT 5");

            // Exam Marks
            $recent_marks = mysqli_query($conn, "SELECT m.marks_obtained, m.grade, es.max_marks, es.pass_marks, sub.subject_name, ex.exam_name 
                FROM marks m
                JOIN exam_schedules es ON m.exam_schedule_id = es.id
                JOIN subjects sub ON es.subject_id = sub.id
                JOIN exams ex ON es.exam_id = ex.id
                WHERE m.student_id = $sid
                ORDER BY es.exam_date DESC LIMIT 5");
            ?>

            <!-- Attendance Overview -->
            <h3 style="font-size:0.95rem;font-weight:700;color:var(--admin-heading);margin-top:24px;margin-bottom:14px;border-bottom:1px solid var(--admin-border);padding-bottom:8px;">
                Attendance Overview
            </h3>
            <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px;">
                <div style="background:var(--admin-card-bg);border:1px solid var(--admin-border);padding:10px 16px;border-radius:6px;min-width:130px;">
                    <div style="font-size:0.75rem;color:var(--admin-text-muted);">Attendance Rate</div>
                    <div style="font-size:1.2rem;font-weight:700;color:var(--admin-accent);"><?php echo $att_rate; ?>%</div>
                </div>
                <div style="background:var(--admin-card-bg);border:1px solid var(--admin-border);padding:10px 16px;border-radius:6px;min-width:130px;">
                    <div style="font-size:0.75rem;color:var(--admin-text-muted);">Total Present</div>
                    <div style="font-size:1.2rem;font-weight:700;color:var(--admin-green);"><?php echo (int)($att_stats['presents'] ?? 0); ?></div>
                </div>
                <div style="background:var(--admin-card-bg);border:1px solid var(--admin-border);padding:10px 16px;border-radius:6px;min-width:130px;">
                    <div style="font-size:0.75rem;color:var(--admin-text-muted);">Total Absent</div>
                    <div style="font-size:1.2rem;font-weight:700;color:var(--admin-red);"><?php echo (int)($att_stats['absents'] ?? 0); ?></div>
                </div>
            </div>
            <?php if (mysqli_num_rows($recent_att) > 0): ?>
                <table class="recent-table" style="margin-bottom:20px;font-size:0.85rem;">
                    <thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
                    <tbody>
                        <?php while ($ar = mysqli_fetch_assoc($recent_att)): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($ar['attendance_date'])); ?></td>
                                <td><span class="badge badge--<?php echo $ar['status'] === 'present' ? 'active' : 'inactive'; ?>"><?php echo ucfirst($ar['status']); ?></span></td>
                                <td style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($ar['remarks'] ?: '-'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Fee History -->
            <h3 style="font-size:0.95rem;font-weight:700;color:var(--admin-heading);margin-top:24px;margin-bottom:14px;border-bottom:1px solid var(--admin-border);padding-bottom:8px;">
                Fee Ledger & Payments
            </h3>
            <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px;">
                <div style="background:var(--admin-card-bg);border:1px solid var(--admin-border);padding:10px 16px;border-radius:6px;min-width:130px;">
                    <div style="font-size:0.75rem;color:var(--admin-text-muted);">Assigned Dues</div>
                    <div style="font-size:1.2rem;font-weight:700;">Rs. <?php echo number_format($fee_assigned); ?></div>
                </div>
                <div style="background:var(--admin-card-bg);border:1px solid var(--admin-border);padding:10px 16px;border-radius:6px;min-width:130px;">
                    <div style="font-size:0.75rem;color:var(--admin-text-muted);">Paid Amount</div>
                    <div style="font-size:1.2rem;font-weight:700;color:var(--admin-green);">Rs. <?php echo number_format($fee_paid); ?></div>
                </div>
                <div style="background:var(--admin-card-bg);border:1px solid var(--admin-border);padding:10px 16px;border-radius:6px;min-width:130px;">
                    <div style="font-size:0.75rem;color:var(--admin-text-muted);">Remaining Balance</div>
                    <div style="font-size:1.2rem;font-weight:700;color:<?php echo $fee_balance > 0 ? 'var(--admin-red)' : 'var(--admin-green)'; ?>;">Rs. <?php echo number_format($fee_balance); ?></div>
                </div>
            </div>
            <?php if (mysqli_num_rows($recent_payments) > 0): ?>
                <table class="recent-table" style="margin-bottom:20px;font-size:0.85rem;">
                    <thead><tr><th>Receipt No</th><th>Date</th><th>Method</th><th>Paid Amount</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php while ($pr = mysqli_fetch_assoc($recent_payments)): ?>
                            <tr>
                                <td style="font-weight:600;color:var(--admin-accent);"><?php echo htmlspecialchars($pr['receipt_no']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($pr['payment_date'])); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $pr['payment_method'])); ?></td>
                                <td style="font-weight:600;">Rs. <?php echo number_format($pr['amount_paid'], 2); ?></td>
                                <td><a href="receipt.php?id=<?php echo $pr['id']; ?>" target="_blank" class="btn-admin btn-admin--sm btn-admin--ghost" style="padding:4px 8px;font-size:0.75rem;">Print Receipt</a></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Exam Performance -->
            <h3 style="font-size:0.95rem;font-weight:700;color:var(--admin-heading);margin-top:24px;margin-bottom:14px;border-bottom:1px solid var(--admin-border);padding-bottom:8px;">
                Examination Performance
            </h3>
            <?php if (mysqli_num_rows($recent_marks) > 0): ?>
                <table class="recent-table" style="margin-bottom:20px;font-size:0.85rem;">
                    <thead><tr><th>Exam</th><th>Subject</th><th>Max Marks</th><th>Obtained</th><th>Grade</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php while ($mr = mysqli_fetch_assoc($recent_marks)): 
                            $passed = ($mr['marks_obtained'] >= $mr['pass_marks']);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($mr['exam_name']); ?></td>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($mr['subject_name']); ?></td>
                                <td><?php echo $mr['max_marks']; ?></td>
                                <td><?php echo $mr['marks_obtained']; ?></td>
                                <td style="font-weight:700;color:var(--admin-accent);"><?php echo htmlspecialchars($mr['grade'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($passed): ?>
                                        <span class="badge badge--active">Passed</span>
                                    <?php else: ?>
                                        <span class="badge badge--danger" style="background:rgba(239,68,68,0.15);color:#ef4444;">Failed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:var(--admin-text-muted);font-size:0.85rem;margin-bottom:20px;">No examination marks recorded yet for this student.</p>
            <?php endif; ?>

            <!-- Safe Status Update Form -->
            <div style="background:var(--admin-input-bg);padding:16px;border-radius:var(--radius-sm);border:1px solid var(--admin-border);margin-top:20px;">
                <form method="POST" action="students.php" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                    <label style="font-size:0.85rem;font-weight:600;color:var(--admin-text);">Quick Status Update:</label>
                    <select name="new_status" class="form-control" style="width:auto;min-width:140px;">
                        <option value="active" <?php echo $student['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $student['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="graduated" <?php echo $student['status'] === 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                        <option value="archived" <?php echo $student['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                    <button type="submit" class="btn-admin btn-admin--sm btn-admin--secondary">Update Status</button>
                    <small style="color:var(--admin-text-muted);">Historical records and fee payments are never deleted when archiving.</small>
                </form>
            </div>
        </div>
    </div>

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- ADD OR EDIT STUDENT FORM                                            -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <?php
    $is_edit = ($action === 'edit' && $student);
    $form_data = $is_edit ? $student : [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'admission_no' => $_POST['admission_no'] ?? $suggested_adm,
        'roll_no' => $_POST['roll_no'] ?? '',
        'gender' => $_POST['gender'] ?? 'Male',
        'date_of_birth' => $_POST['date_of_birth'] ?? '',
        'session_id' => $_POST['session_id'] ?? ($sessions[0]['id'] ?? 1),
        'class_id' => $_POST['class_id'] ?? ($classes[0]['id'] ?? 1),
        'section_id' => $_POST['section_id'] ?? ($sections[0]['id'] ?? 1),
        'parent_name' => $_POST['parent_name'] ?? '',
        'parent_phone' => $_POST['parent_phone'] ?? '',
        'parent_email' => $_POST['parent_email'] ?? '',
        'address' => $_POST['address'] ?? '',
        'admission_date' => $_POST['admission_date'] ?? date('Y-m-d'),
        'status' => $_POST['status'] ?? 'active',
    ];
    ?>
    <div class="form-card animate-in">
        <div class="form-card__header">
            <h2 class="form-card__title"><?php echo $is_edit ? 'Edit Student Details' : 'Register New Student'; ?></h2>
            <span style="font-size:0.8rem;color:var(--admin-text-muted);">Fields with <span style="color:var(--admin-red);">*</span> are required</span>
        </div>

        <form method="POST" action="students.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'update' : 'create'; ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
            <?php endif; ?>

            <div class="form-grid form-grid--3">
                <div class="form-group">
                    <label class="form-label" for="admission_no">Admission No <span class="required">*</span></label>
                    <input type="text" id="admission_no" name="admission_no" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['admission_no']); ?>" required>
                    <span class="form-hint">Unique institutional identification number</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="roll_no">Roll Number</label>
                    <input type="text" id="roll_no" name="roll_no" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['roll_no'] ?? ''); ?>" placeholder="e.g. 01">
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Enrollment Status <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="active" <?php echo ($form_data['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($form_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="graduated" <?php echo ($form_data['status'] ?? '') === 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                        <option value="archived" <?php echo ($form_data['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['first_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['last_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="gender">Gender <span class="required">*</span></label>
                    <select id="gender" name="gender" class="form-control" required>
                        <option value="Male" <?php echo ($form_data['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($form_data['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($form_data['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="date_of_birth">Date of Birth <span class="required">*</span></label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['date_of_birth']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="session_id">Academic Session <span class="required">*</span></label>
                    <select id="session_id" name="session_id" class="form-control" required>
                        <?php foreach ($sessions as $ses): ?>
                            <option value="<?php echo $ses['id']; ?>" <?php echo (int)($form_data['session_id']) === (int)$ses['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ses['session_name']); ?><?php echo $ses['is_current'] ? ' (Current)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="admission_date">Admission Date <span class="required">*</span></label>
                    <input type="date" id="admission_date" name="admission_date" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['admission_date']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="class_id">Class <span class="required">*</span></label>
                    <select id="class_id" name="class_id" class="form-control" required onchange="filterSections()">
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?php echo $cls['id']; ?>" <?php echo (int)($form_data['class_id']) === (int)$cls['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cls['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="section_id">Section <span class="required">*</span></label>
                    <select id="section_id" name="section_id" class="form-control" required>
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?php echo $sec['id']; ?>" data-class="<?php echo $sec['class_id']; ?>" 
                                    <?php echo (int)($form_data['section_id']) === (int)$sec['id'] ? 'selected' : ''; ?>>
                                Section <?php echo htmlspecialchars($sec['section_name']); ?> (Cap: <?php echo $sec['capacity']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="parent_name">Parent / Guardian Name <span class="required">*</span></label>
                    <input type="text" id="parent_name" name="parent_name" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['parent_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="parent_phone">Parent Phone <span class="required">*</span></label>
                    <input type="text" id="parent_phone" name="parent_phone" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['parent_phone']); ?>" placeholder="+1 (555) 000-0000" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="parent_email">Parent Email</label>
                    <input type="email" id="parent_email" name="parent_email" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['parent_email'] ?? ''); ?>" placeholder="parent@example.com">
                </div>

                <div class="form-group form-group--full">
                    <label class="form-label" for="address">Residential Address <span class="required">*</span></label>
                    <textarea id="address" name="address" class="form-control" rows="2" required><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a href="students.php" class="btn-admin btn-admin--secondary">Cancel</a>
                <button type="submit" class="btn-admin btn-admin--primary">
                    <?php echo $is_edit ? 'Save Changes' : 'Register Student'; ?>
                </button>
            </div>
        </form>
    </div>

    <script>
    function filterSections() {
        const classSelect = document.getElementById('class_id');
        const sectionSelect = document.getElementById('section_id');
        if (!classSelect || !sectionSelect) return;
        const selectedClass = classSelect.value;
        let firstVisible = null;

        Array.from(sectionSelect.options).forEach(opt => {
            const optClass = opt.getAttribute('data-class');
            if (optClass === selectedClass) {
                opt.style.display = '';
                if (!firstVisible) firstVisible = opt;
            } else {
                opt.style.display = 'none';
            }
        });

        // If current selection is hidden, select first visible
        if (sectionSelect.selectedOptions.length === 0 || sectionSelect.selectedOptions[0].style.display === 'none') {
            if (firstVisible) firstVisible.selected = true;
        }
    }
    document.addEventListener('DOMContentLoaded', filterSections);
    </script>

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- STUDENTS LIST VIEW                                                  -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php else: ?>
    <?php
    // Filters & Pagination
    $search     = trim($_GET['search'] ?? '');
    $filter_cls = (int)($_GET['class_id'] ?? 0);
    $filter_ses = (int)($_GET['session_id'] ?? 0);
    $filter_st  = trim($_GET['status'] ?? '');
    $page       = max(1, (int)($_GET['page'] ?? 1));
    $limit      = 10;
    $offset     = ($page - 1) * $limit;

    // Build WHERE clauses
    $where_sql = "WHERE 1=1";
    $params = [];
    $types = '';

    if ($search !== '') {
        $where_sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ? OR s.parent_name LIKE ?)";
        $s_term = "%{$search}%";
        $params[] = $s_term;
        $params[] = $s_term;
        $params[] = $s_term;
        $params[] = $s_term;
        $types .= 'ssss';
    }

    if ($filter_cls > 0) {
        $where_sql .= " AND s.class_id = ?";
        $params[] = $filter_cls;
        $types .= 'i';
    }

    if ($filter_ses > 0) {
        $where_sql .= " AND s.session_id = ?";
        $params[] = $filter_ses;
        $types .= 'i';
    }

    if ($filter_st !== '' && in_array($filter_st, ['active', 'inactive', 'graduated', 'archived'], true)) {
        $where_sql .= " AND s.status = ?";
        $params[] = $filter_st;
        $types .= 's';
    }

    // Count Total
    $count_sql = "SELECT COUNT(*) AS total FROM students s $where_sql";
    $count_stmt = mysqli_prepare($conn, $count_sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    }
    mysqli_stmt_execute($count_stmt);
    $count_res = mysqli_stmt_get_result($count_stmt);
    $total_students = mysqli_fetch_assoc($count_res)['total'] ?? 0;
    mysqli_stmt_close($count_stmt);

    $total_pages = ceil($total_students / $limit);

    // Fetch Paginated List
    $list_sql = "SELECT s.id, s.admission_no, s.roll_no, s.first_name, s.last_name, s.gender, s.status,
                 c.class_name, sec.section_name, ses.session_name, s.parent_phone
                 FROM students s
                 LEFT JOIN classes c ON s.class_id = c.id
                 LEFT JOIN sections sec ON s.section_id = sec.id
                 LEFT JOIN academic_sessions ses ON s.session_id = ses.id
                 $where_sql
                 ORDER BY s.id DESC LIMIT ? OFFSET ?";
    $params_list = $params;
    $types_list = $types . 'ii';
    $params_list[] = $limit;
    $params_list[] = $offset;

    $list_stmt = mysqli_prepare($conn, $list_sql);
    mysqli_stmt_bind_param($list_stmt, $types_list, ...$params_list);
    mysqli_stmt_execute($list_stmt);
    $list_res = mysqli_stmt_get_result($list_stmt);

    $student_list = [];
    while ($row = mysqli_fetch_assoc($list_res)) {
        $student_list[] = $row;
    }
    mysqli_stmt_close($list_stmt);
    ?>

    <!-- Filter Card -->
    <div class="filter-card animate-in">
        <form method="GET" action="students.php" class="filter-form">
            <div class="search-input-wrap">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" class="form-control" placeholder="Search by name, admission no, parent..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="filter-group">
                <select name="class_id" class="form-control">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $filter_cls === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <select name="session_id" class="form-control">
                    <option value="">All Sessions</option>
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $filter_ses === (int)$s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['session_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $filter_st === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filter_st === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="graduated" <?php echo $filter_st === 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                    <option value="archived" <?php echo $filter_st === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>

            <button type="submit" class="btn-admin btn-admin--sm btn-admin--secondary">Filter</button>
            <?php if ($search !== '' || $filter_cls > 0 || $filter_ses > 0 || $filter_st !== ''): ?>
                <a href="students.php" class="btn-admin btn-admin--sm btn-admin--ghost">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Students Table Panel -->
    <div class="panel animate-in">
        <div class="panel__header">
            <h3 class="panel__title">Registered Students (<?php echo number_format($total_students); ?>)</h3>
            <div style="display:flex;gap:8px;">
                <a href="students.php?export=csv" class="panel__action" style="background:var(--admin-input-bg);border:1px solid var(--admin-border);color:var(--admin-text);">↓ Export CSV</a>
                <a href="students.php?action=add" class="panel__action">+ Add Student</a>
            </div>
        </div>

        <?php if (count($student_list) > 0): ?>
            <div class="table-responsive">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Adm. No</th>
                            <th>Class & Section</th>
                            <th>Session</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $colors = ['#22d3a7','#3b82f6','#f472b6','#fbbf24','#a78bfa'];
                        foreach ($student_list as $i => $st): 
                            $initials = strtoupper(substr($st['first_name'],0,1) . substr($st['last_name'],0,1));
                            $color = $colors[$i % count($colors)];
                        ?>
                        <tr>
                            <td>
                                <div class="student-cell">
                                    <div class="student-avatar" style="background:<?php echo $color; ?>"><?php echo $initials; ?></div>
                                    <div>
                                        <div class="student-name"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></div>
                                        <small style="color:var(--admin-text-muted);font-size:0.75rem;"><?php echo htmlspecialchars($st['gender']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td style="font-weight:600;color:var(--admin-accent);"><?php echo htmlspecialchars($st['admission_no']); ?></td>
                            <td style="color:var(--admin-text-muted);">
                                <?php echo htmlspecialchars(($st['class_name'] ?? 'N/A') . ' - ' . ($st['section_name'] ?? 'N/A')); ?>
                            </td>
                            <td style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($st['session_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge badge--<?php echo $st['status']; ?>">
                                    <?php echo ucfirst($st['status']); ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div class="table-actions" style="justify-content:flex-end;">
                                    <a href="students.php?action=view&id=<?php echo $st['id']; ?>" class="action-btn action-btn--view" title="View Student">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <a href="students.php?action=edit&id=<?php echo $st['id']; ?>" class="action-btn action-btn--edit" title="Edit Student">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-wrap">
                    <div class="pagination-info">
                        Showing <?php echo $offset + 1; ?>–<?php echo min($total_students, $offset + $limit); ?> of <?php echo $total_students; ?> students
                    </div>
                    <div class="pagination-nav">
                        <?php
                        $query_params = $_GET;
                        // Previous page
                        if ($page > 1):
                            $query_params['page'] = $page - 1;
                        ?>
                            <a href="students.php?<?php echo http_build_query($query_params); ?>" class="page-btn">← Prev</a>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $total_pages; $p++): 
                            $query_params['page'] = $p;
                        ?>
                            <a href="students.php?<?php echo http_build_query($query_params); ?>" 
                               class="page-btn <?php echo $p === $page ? 'page-btn--active' : ''; ?>">
                               <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>

                        <?php
                        // Next page
                        if ($page < $total_pages):
                            $query_params['page'] = $page + 1;
                        ?>
                            <a href="students.php?<?php echo http_build_query($query_params); ?>" class="page-btn">Next →</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <p>No students match your criteria.</p>
                <?php if ($search !== '' || $filter_cls > 0 || $filter_ses > 0 || $filter_st !== ''): ?>
                    <a href="students.php" class="btn-admin btn-admin--sm btn-admin--ghost" style="margin-top:12px;">Reset Filters</a>
                <?php else: ?>
                    <a href="students.php?action=add" class="btn-admin btn-admin--sm btn-admin--primary" style="margin-top:12px;">Add First Student</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
