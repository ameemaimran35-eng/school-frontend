<?php
/**
 * receptionist/admissions.php
 * Receptionist / Admission Officer Portal — Admissions Management
 * SRS Version 1.0 — Section 8.2, 8.3, 8.4, 8.5
 */
require_once __DIR__ . '/auth_check.php';

$page_title = 'Admissions Management';
$current_page = 'admissions';

// Ensure schema columns exist defensively
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

$action = $_GET['action'] ?? 'list';
$admission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$warnings = [];

$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ─── LOOKUP DATA FOR DROPDOWNS ────────────────────────────────────
$classes = [];
$resc = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY class_name ASC");
if ($resc) while ($c = mysqli_fetch_assoc($resc)) $classes[] = $c;

$sections = [];
$ressec = mysqli_query($conn, "SELECT id, class_id, section_name FROM sections ORDER BY class_id, section_name ASC");
if ($ressec) while ($s = mysqli_fetch_assoc($ressec)) $sections[] = $s;

$sessions = [];
$ressess = mysqli_query($conn, "SELECT id, session_name, is_current FROM academic_sessions ORDER BY is_current DESC, id ASC");
if ($ressess) while ($ss = mysqli_fetch_assoc($ressess)) $sessions[] = $ss;

$current_session_id = 0;
foreach ($sessions as $s) {
    if (!empty($s['is_current'])) {
        $current_session_id = (int)$s['id'];
        break;
    }
}
if ($current_session_id === 0 && !empty($sessions)) {
    $current_session_id = (int)$sessions[0]['id'];
}

// ─── POST HANDLERS ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token). Please try again.';
        header('Location: admissions.php');
        exit();
    }

    $post_action = $_POST['action'] ?? '';

    // CREATE NEW APPLICATION
    if ($post_action === 'create') {
        $parent_name    = trim($_POST['parent_name'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $address        = trim($_POST['address'] ?? '');
        $child_name     = trim($_POST['child_name'] ?? '');
        $gender         = trim($_POST['gender'] ?? 'Male');
        $dob            = trim($_POST['dob'] ?? '');
        $dob_val        = !empty($dob) ? $dob : null;
        $grade_applying = trim($_POST['grade_applying'] ?? '');
        $class_id       = (int)($_POST['class_id'] ?? 0);
        $section_id     = (int)($_POST['section_id'] ?? 0);
        $session_id     = (int)($_POST['session_id'] ?? 0);
        $message        = trim($_POST['message'] ?? '');
        $confirm_dup    = isset($_POST['confirm_duplicate']) && $_POST['confirm_duplicate'] === '1';

        // Validations
        if ($parent_name === '')    $errors[] = 'Parent/Guardian name is required.';
        if ($phone === '')          $errors[] = 'Parent/Guardian phone number is required.';
        if ($child_name === '')     $errors[] = 'Child name is required.';
        if ($grade_applying === '') $errors[] = 'Grade applying for is required.';
        if (!in_array($gender, ['Male', 'Female', 'Other'])) $errors[] = 'Please select a valid gender.';
        if ($class_id <= 0)         $errors[] = 'Class selection is required.';
        if ($section_id <= 0)       $errors[] = 'Section selection is required.';
        if ($session_id <= 0)       $errors[] = 'Academic session selection is required.';

        // Verify section belongs to class
        if ($class_id > 0 && $section_id > 0) {
            $sec_chk = mysqli_prepare($conn, "SELECT id, class_id FROM sections WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($sec_chk, 'i', $section_id);
            mysqli_stmt_execute($sec_chk);
            $sec_row = mysqli_fetch_assoc(mysqli_stmt_get_result($sec_chk));
            mysqli_stmt_close($sec_chk);
            if (!$sec_row || (int)$sec_row['class_id'] !== $class_id) {
                $errors[] = 'The selected section does not belong to the selected class.';
            }
        }

        // Duplicate Check (SRS §8.3: check relevant identifiers)
        if (empty($errors) && !$confirm_dup) {
            // Check 1: Existing application with same child_name and parent_phone
            $dup_stmt = mysqli_prepare($conn, "SELECT id, child_name, parent_name, phone, status, created_at FROM admissions_inquiries WHERE LOWER(TRIM(child_name)) = LOWER(TRIM(?)) AND TRIM(phone) = TRIM(?) LIMIT 1");
            mysqli_stmt_bind_param($dup_stmt, 'ss', $child_name, $phone);
            mysqli_stmt_execute($dup_stmt);
            $dup_row = mysqli_fetch_assoc(mysqli_stmt_get_result($dup_stmt));
            mysqli_stmt_close($dup_stmt);

            if ($dup_row) {
                $errors[] = "Potential duplicate detected: An application for '{$dup_row['child_name']}' with phone '{$dup_row['phone']}' already exists (#APP-{$dup_row['id']}, Status: " . ucfirst($dup_row['status']) . ", Date: " . date('Y-m-d', strtotime($dup_row['created_at'])) . "). If you still wish to register this application, check the confirmation box below.";
                $warnings['duplicate_found'] = true;
                $warnings['duplicate_ref'] = $dup_row['id'];
            } else {
                // Check 2: Check if student already enrolled in students table
                $stud_dup = mysqli_prepare($conn, "SELECT id, admission_no, first_name, last_name FROM students WHERE LOWER(CONCAT(TRIM(first_name), ' ', TRIM(last_name))) = LOWER(TRIM(?)) AND TRIM(parent_phone) = TRIM(?) LIMIT 1");
                mysqli_stmt_bind_param($stud_dup, 'ss', $child_name, $phone);
                mysqli_stmt_execute($stud_dup);
                $sdup_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stud_dup));
                mysqli_stmt_close($stud_dup);
                if ($sdup_row) {
                    $errors[] = "Notice: An enrolled student with the name '{$sdup_row['first_name']} {$sdup_row['last_name']}' and phone '{$phone}' is already registered in the system (Admission No: {$sdup_row['admission_no']}). If this is a re-admission or sibling, check the confirmation box below.";
                    $warnings['duplicate_found'] = true;
                }
            }
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO admissions_inquiries (parent_name, email, phone, child_name, grade_applying, gender, class_id, section_id, session_id, message, status, dob, address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, 'ssssssiiisss', $parent_name, $email, $phone, $child_name, $grade_applying, $gender, $class_id, $section_id, $session_id, $message, $dob_val, $address);

            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                logActivity('create', 'admissions_inquiries', $new_id, "New admission application #APP-{$new_id} for {$child_name} registered by " . ($_SESSION['admin_username'] ?? 'receptionist'));
                $_SESSION['flash_success'] = "Admission application for " . htmlspecialchars($child_name) . " registered successfully (Ref: #APP-{$new_id}).";
                header('Location: admissions.php?action=view&id=' . $new_id);
                exit();
            } else {
                $errors[] = 'Failed to register application: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }

    // UPDATE APPLICATION
    if ($post_action === 'update') {
        $id             = (int)($_POST['id'] ?? 0);
        $parent_name    = trim($_POST['parent_name'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $address        = trim($_POST['address'] ?? '');
        $child_name     = trim($_POST['child_name'] ?? '');
        $gender         = trim($_POST['gender'] ?? 'Male');
        $dob            = trim($_POST['dob'] ?? '');
        $dob_val        = !empty($dob) ? $dob : null;
        $grade_applying = trim($_POST['grade_applying'] ?? '');
        $class_id       = (int)($_POST['class_id'] ?? 0);
        $section_id     = (int)($_POST['section_id'] ?? 0);
        $session_id     = (int)($_POST['session_id'] ?? 0);
        $message        = trim($_POST['message'] ?? '');
        $new_status     = trim($_POST['status'] ?? '');

        if ($id <= 0) $errors[] = 'Invalid application ID.';

        // Fetch existing record
        $curr_stmt = mysqli_prepare($conn, "SELECT * FROM admissions_inquiries WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($curr_stmt, 'i', $id);
        mysqli_stmt_execute($curr_stmt);
        $current_app = mysqli_fetch_assoc(mysqli_stmt_get_result($curr_stmt));
        mysqli_stmt_close($curr_stmt);

        if (!$current_app) {
            $_SESSION['flash_error'] = 'Application record not found.';
            header('Location: admissions.php');
            exit();
        }

        if ($parent_name === '')    $errors[] = 'Parent/Guardian name is required.';
        if ($phone === '')          $errors[] = 'Parent/Guardian phone number is required.';
        if ($child_name === '')     $errors[] = 'Child name is required.';
        if ($grade_applying === '') $errors[] = 'Grade applying for is required.';
        if (!in_array($gender, ['Male', 'Female', 'Other'])) $errors[] = 'Please select a valid gender.';
        if ($class_id <= 0)         $errors[] = 'Class selection is required.';
        if ($section_id <= 0)       $errors[] = 'Section selection is required.';
        if ($session_id <= 0)       $errors[] = 'Academic session selection is required.';

        // Status permissions enforcement:
        // SRS §4.3 & §8.4: Receptionist can update tracking status (pending, reviewing, interviewed).
        // Only Admin can set to accepted/rejected.
        // If already accepted or enrolled, receptionist cannot modify status.
        $status_to_save = $current_app['status'];
        if (isAdmin()) {
            $allowed_statuses = ['pending', 'reviewing', 'interviewed', 'accepted', 'rejected', 'enrolled'];
            if (in_array($new_status, $allowed_statuses)) {
                $status_to_save = $new_status;
            }
        } else {
            // Receptionist role
            $allowed_rec_statuses = ['pending', 'reviewing', 'interviewed'];
            if (in_array($current_app['status'], ['accepted', 'enrolled'])) {
                // Keep current approval status
                $status_to_save = $current_app['status'];
            } elseif (in_array($new_status, $allowed_rec_statuses)) {
                $status_to_save = $new_status;
            }
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "UPDATE admissions_inquiries SET parent_name = ?, email = ?, phone = ?, child_name = ?, grade_applying = ?, gender = ?, class_id = ?, section_id = ?, session_id = ?, message = ?, status = ?, dob = ?, address = ?, updated_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ssssssiiissssi', $parent_name, $email, $phone, $child_name, $grade_applying, $gender, $class_id, $section_id, $session_id, $message, $status_to_save, $dob_val, $address, $id);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                logActivity('update', 'admissions_inquiries', $id, "Admission application #APP-{$id} updated by " . ($_SESSION['admin_username'] ?? 'receptionist'));
                $_SESSION['flash_success'] = "Application #APP-{$id} updated successfully.";
                header('Location: admissions.php?action=view&id=' . $id);
                exit();
            } else {
                $errors[] = 'Failed to update application: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// ─── PRINT VIEW HANDLER (SRS §8.2) ────────────────────────────────
if ($action === 'print' && $admission_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT a.*, c.class_name, sec.section_name, s.session_name,
        st.admission_no AS enrolled_admission_no
        FROM admissions_inquiries a
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN sections sec ON a.section_id = sec.id
        LEFT JOIN academic_sessions s ON a.session_id = s.id
        LEFT JOIN students st ON a.converted_student_id = st.id
        WHERE a.id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $admission_id);
    mysqli_stmt_execute($stmt);
    $app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$app) {
        die('Application record not found.');
    }

    $school_name = getSetting('school_name', 'Brightway Public School');
    $school_phone = getSetting('school_phone', '+92 51 1234567');
    $school_email = getSetting('school_email', 'info@brightway.edu.pk');
    $school_address = getSetting('school_address', 'Main Campus, Sector H-8, Islamabad');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Admission Form #APP-<?php echo str_pad($app['id'], 5, '0', STR_PAD_LEFT); ?> – <?php echo htmlspecialchars($school_name); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Inter', sans-serif; color: #1e293b; background: #f8fafc; padding: 24px; font-size: 13px; line-height: 1.5; }
            .print-container { max-width: 820px; margin: 0 auto; background: #fff; padding: 36px 40px; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
            .print-actions { max-width: 820px; margin: 0 auto 20px auto; display: flex; justify-content: space-between; align-items: center; }
            .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 13px; font-weight: 600; border-radius: 6px; text-decoration: none; cursor: pointer; border: none; }
            .btn-primary { background: #8b5cf6; color: #fff; }
            .btn-secondary { background: #e2e8f0; color: #334155; }
            .header { text-align: center; border-bottom: 2px solid #8b5cf6; padding-bottom: 18px; margin-bottom: 22px; position: relative; }
            .school-title { font-size: 22px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: .03em; }
            .school-subtitle { font-size: 12px; color: #64748b; margin-top: 4px; }
            .form-title-badge { display: inline-block; background: #f1f5f9; border: 1px solid #cbd5e1; padding: 5px 18px; font-size: 13px; font-weight: 700; color: #1e293b; border-radius: 20px; text-transform: uppercase; margin-top: 10px; letter-spacing: .06em; }
            .meta-bar { display: flex; justify-content: space-between; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 20px; font-size: 12px; }
            .meta-bar div strong { color: #0f172a; }
            .section-heading { font-size: 13px; font-weight: 700; text-transform: uppercase; color: #8b5cf6; letter-spacing: .05em; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin: 20px 0 12px 0; }
            .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; }
            .info-row { display: flex; flex-direction: column; }
            .info-label { font-size: 11px; text-transform: uppercase; font-weight: 600; color: #64748b; margin-bottom: 2px; }
            .info-val { font-size: 13px; font-weight: 600; color: #0f172a; background: #f8fafc; padding: 6px 10px; border-radius: 4px; border: 1px solid #e2e8f0; min-height: 30px; }
            .declaration-box { background: #faf5ff; border: 1px dashed #d8b4fe; padding: 12px 16px; border-radius: 6px; font-size: 11.5px; color: #4b5563; margin-top: 24px; line-height: 1.6; }
            .sig-table { width: 100%; margin-top: 45px; border-collapse: collapse; }
            .sig-table td { width: 33.33%; text-align: center; vertical-align: bottom; padding: 0 15px; }
            .sig-line { border-top: 1px solid #94a3b8; padding-top: 6px; font-size: 12px; font-weight: 600; color: #334155; }
            .photo-box { width: 110px; height: 130px; border: 1px dashed #94a3b8; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #94a3b8; text-transform: uppercase; text-align: center; padding: 6px; position: absolute; right: 0; top: 0; }
            @media print {
                body { background: #fff; padding: 0; }
                .print-actions { display: none !important; }
                .print-container { box-shadow: none; border: none; padding: 0; max-width: 100%; }
            }
        </style>
    </head>
    <body>
        <div class="print-actions">
            <a href="admissions.php?action=view&id=<?php echo $app['id']; ?>" class="btn btn-secondary">← Back to Application</a>
            <div>
                <button onclick="window.print()" class="btn btn-primary">&#128438; Print Form</button>
            </div>
        </div>

        <div class="print-container">
            <div class="header">
                <div class="photo-box">Affix Recent<br>Photograph</div>
                <h1 class="school-title"><?php echo htmlspecialchars($school_name); ?></h1>
                <p class="school-subtitle"><?php echo htmlspecialchars($school_address); ?> &bull; Phone: <?php echo htmlspecialchars($school_phone); ?> &bull; Email: <?php echo htmlspecialchars($school_email); ?></p>
                <div class="form-title-badge">Official Student Admission Application Form</div>
            </div>

            <div class="meta-bar">
                <div>Application Ref: <strong>#APP-<?php echo str_pad($app['id'], 5, '0', STR_PAD_LEFT); ?></strong></div>
                <div>Application Date: <strong><?php echo date('F d, Y', strtotime($app['created_at'])); ?></strong></div>
                <div>Status: <strong style="text-transform: uppercase; color:#8b5cf6;"><?php echo htmlspecialchars($app['status'] === 'accepted' ? 'Approved' : ucfirst($app['status'])); ?></strong></div>
            </div>

            <div class="section-heading">1. Candidate Information</div>
            <div class="grid-2">
                <div class="info-row">
                    <span class="info-label">Applicant Full Name</span>
                    <span class="info-val"><?php echo htmlspecialchars($app['child_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gender</span>
                    <span class="info-val"><?php echo htmlspecialchars($app['gender'] ?? 'Not Specified'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-val"><?php echo !empty($app['dob']) ? date('F d, Y', strtotime($app['dob'])) : 'Not provided'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Grade / Level Applying For</span>
                    <span class="info-val"><?php echo htmlspecialchars($app['grade_applying']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Assigned Class & Section</span>
                    <span class="info-val"><?php echo htmlspecialchars(($app['class_name'] ?? 'Pending') . ' – ' . ($app['section_name'] ?? 'Section')); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Academic Session</span>
                    <span class="info-val"><?php echo htmlspecialchars($app['session_name'] ?? 'Current Session'); ?></span>
                </div>
            </div>

            <div class="section-heading">2. Parent / Guardian Information</div>
            <div class="grid-2">
                <div class="info-row">
                    <span class="info-label">Parent / Guardian Full Name</span>
                    <span class="info-val"><?php echo htmlspecialchars($app['parent_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Primary Contact Number</span>
                    <span class="info-val"><?php echo htmlspecialchars($app['phone']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email Address</span>
                    <span class="info-val"><?php echo htmlspecialchars($app['email'] ?: 'Not provided'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Residential Address</span>
                    <span class="info-val"><?php echo htmlspecialchars($app['address'] ?? 'Not provided'); ?></span>
                </div>
            </div>

            <div class="section-heading">3. Additional Remarks & Previous School</div>
            <div class="info-row">
                <span class="info-label">Remarks / Notes / Prior School Record</span>
                <span class="info-val" style="min-height:50px;font-weight:400;"><?php echo nl2br(htmlspecialchars($app['message'] ?: 'None specified.')); ?></span>
            </div>

            <div class="declaration-box">
                <strong>Parent / Guardian Declaration:</strong> I hereby certify that the information provided in this admission form is true, correct, and complete to the best of my knowledge. I agree to abide by the rules, regulations, and fee policies of <?php echo htmlspecialchars($school_name); ?>.
            </div>

            <table class="sig-table">
                <tr>
                    <td>
                        <div class="sig-line">Parent / Guardian Signature</div>
                    </td>
                    <td>
                        <div class="sig-line">Admission Officer Signature</div>
                    </td>
                    <td>
                        <div class="sig-line">Principal / Administrator Approval</div>
                    </td>
                </tr>
            </table>

            <?php if (!empty($app['enrolled_admission_no'])): ?>
            <div style="margin-top:25px;padding:8px 12px;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;font-size:11px;color:#166534;text-align:center;">
                &#10004; Formally Enrolled Student &bull; Official Admission No: <strong><?php echo htmlspecialchars($app['enrolled_admission_no']); ?></strong>
            </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ─── LOAD DATA FOR EDIT OR VIEW ───────────────────────────────────
$view_app = null;
if (($action === 'view' || $action === 'edit') && $admission_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT a.*, c.class_name, sec.section_name, s.session_name,
        st.id AS student_pk_id, st.admission_no AS student_adm_no, st.admission_date AS student_adm_date
        FROM admissions_inquiries a
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN sections sec ON a.section_id = sec.id
        LEFT JOIN academic_sessions s ON a.session_id = s.id
        LEFT JOIN students st ON a.converted_student_id = st.id
        WHERE a.id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $admission_id);
    mysqli_stmt_execute($stmt);
    $view_app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$view_app) {
        $_SESSION['flash_error'] = 'Application record not found.';
        header('Location: admissions.php');
        exit();
    }
}

// ─── LIST VIEW WITH SEARCH, STATUS FILTER & PAGINATION ───────────
$filter_status = trim($_GET['status'] ?? 'all');
$filter_class  = (int)($_GET['class_id'] ?? 0);
$filter_search = trim($_GET['search'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 15;
$offset        = ($page - 1) * $per_page;

// Count queries for status tabs
$status_counts = [
    'all'         => 0,
    'pending'     => 0,
    'reviewing'   => 0,
    'interviewed' => 0,
    'accepted'    => 0,
    'rejected'    => 0,
    'enrolled'    => 0,
];
$cres = mysqli_query($conn, "SELECT status, COUNT(*) AS cnt FROM admissions_inquiries GROUP BY status");
if ($cres) {
    while ($r = mysqli_fetch_assoc($cres)) {
        $st = $r['status'];
        if (isset($status_counts[$st])) {
            $status_counts[$st] = (int)$r['cnt'];
        }
        $status_counts['all'] += (int)$r['cnt'];
    }
}

// Enrolled count via converted_student_id
$eres = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries WHERE converted_student_id IS NOT NULL AND converted_student_id > 0");
if ($eres) {
    $status_counts['enrolled'] = (int)(mysqli_fetch_assoc($eres)['cnt'] ?? 0);
}

// Build list query
$where_clauses = ["1=1"];
$params = [];
$types  = "";

if ($filter_status === 'enrolled') {
    $where_clauses[] = "(a.converted_student_id IS NOT NULL AND a.converted_student_id > 0)";
} elseif ($filter_status !== 'all' && in_array($filter_status, ['pending','reviewing','interviewed','accepted','rejected'])) {
    $where_clauses[] = "a.status = ?";
    $params[] = $filter_status;
    $types   .= "s";
}

if ($filter_class > 0) {
    $where_clauses[] = "a.class_id = ?";
    $params[] = $filter_class;
    $types   .= "i";
}

if ($filter_search !== '') {
    $where_clauses[] = "(a.child_name LIKE ? OR a.parent_name LIKE ? OR a.phone LIKE ? OR a.email LIKE ?)";
    $like = "%" . $filter_search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= "ssss";
}

$where_sql = implode(" AND ", $where_clauses);

// Count total matching
$count_sql = "SELECT COUNT(*) AS total FROM admissions_inquiries a WHERE {$where_sql}";
$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$total_rows = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'] ?? 0);
mysqli_stmt_close($count_stmt);

$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Fetch page rows
$list_sql = "SELECT a.*, c.class_name, sec.section_name, s.session_name,
    st.admission_no AS enrolled_adm_no
    FROM admissions_inquiries a
    LEFT JOIN classes c ON a.class_id = c.id
    LEFT JOIN sections sec ON a.section_id = sec.id
    LEFT JOIN academic_sessions s ON a.session_id = s.id
    LEFT JOIN students st ON a.converted_student_id = st.id
    WHERE {$where_sql}
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT ?, ?";
$list_stmt = mysqli_prepare($conn, $list_sql);
$list_params = $params;
$list_params[] = $offset;
$list_params[] = $per_page;
$list_types = $types . "ii";
mysqli_stmt_bind_param($list_stmt, $list_types, ...$list_params);
mysqli_stmt_execute($list_stmt);
$apps_result = mysqli_stmt_get_result($list_stmt);
$applications = [];
while ($r = mysqli_fetch_assoc($apps_result)) $applications[] = $r;
mysqli_stmt_close($list_stmt);

include __DIR__ . '/header.php';
?>

<!-- Flash Alerts -->
<?php if ($success_msg): ?>
    <div class="alert alert--success flash-auto-dismiss"><div class="alert__content"><span><?php echo htmlspecialchars($success_msg); ?></span></div></div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert alert--danger flash-auto-dismiss"><div class="alert__content"><span><?php echo htmlspecialchars($error_msg); ?></span></div></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert--danger"><div><strong>Please resolve the following:</strong><ul style="margin-left:20px;margin-top:6px;">
        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
    </ul></div></div>
<?php endif; ?>

<?php if ($action === 'view' && $view_app): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- VIEW APPLICATION DETAILS (SRS §8.5)                             -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">
            Application #APP-<?php echo str_pad($view_app['id'], 5, '0', STR_PAD_LEFT); ?>
        </h1>
        <p class="page-header__desc">Detailed profile and tracking information for applicant.</p>
    </div>
    <div class="page-header__actions" style="display:flex;gap:10px;">
        <a href="admissions.php" class="btn-admin btn-admin--ghost">← Back to List</a>
        <a href="admissions.php?action=print&id=<?php echo $view_app['id']; ?>" target="_blank" class="btn-admin btn-admin--ghost" style="border-color:var(--admin-accent);color:var(--admin-accent);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Application Form
        </a>
        <a href="admissions.php?action=edit&id=<?php echo $view_app['id']; ?>" class="btn-admin btn-admin--primary">Edit Details</a>
    </div>
</div>

<div class="dash-grid" style="grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
    <div style="display:flex;flex-direction:column;gap:20px;">
        <!-- Candidate Profile Panel -->
        <div class="panel animate-in">
            <div class="panel__header" style="display:flex;justify-content:space-between;align-items:center;">
                <h3 class="panel__title">Applicant Information</h3>
                <?php
                $st = $view_app['status'];
                $is_enrolled = !empty($view_app['student_adm_no']);
                $badge_cls = $is_enrolled ? 'enrolled' : (in_array($st, ['accepted','approved']) ? 'approved' : ($st === 'rejected' ? 'rejected' : 'pending'));
                ?>
                <span class="status-badge status-<?php echo $badge_cls; ?>" style="font-size:.8rem;padding:4px 12px;">
                    <?php echo $is_enrolled ? 'Enrolled' : ($st === 'accepted' ? 'Approved' : ucfirst($st)); ?>
                </span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;padding:10px 0;">
                <div>
                    <span style="font-size:.75rem;text-transform:uppercase;color:var(--admin-text-muted);font-weight:600;">Applicant Full Name</span>
                    <div style="font-size:1.1rem;font-weight:700;color:var(--admin-heading);margin-top:2px;"><?php echo htmlspecialchars($view_app['child_name']); ?></div>
                </div>
                <div>
                    <span style="font-size:.75rem;text-transform:uppercase;color:var(--admin-text-muted);font-weight:600;">Gender</span>
                    <div style="font-size:1rem;font-weight:600;margin-top:2px;"><?php echo htmlspecialchars($view_app['gender'] ?? 'Not Specified'); ?></div>
                </div>
                <div>
                    <span style="font-size:.75rem;text-transform:uppercase;color:var(--admin-text-muted);font-weight:600;">Date of Birth</span>
                    <div style="font-size:1rem;margin-top:2px;"><?php echo !empty($view_app['dob']) ? date('F d, Y', strtotime($view_app['dob'])) : '<span style="color:var(--admin-text-muted);">Not provided</span>'; ?></div>
                </div>
                <div>
                    <span style="font-size:.75rem;text-transform:uppercase;color:var(--admin-text-muted);font-weight:600;">Grade / Level Applying</span>
                    <div style="font-size:1rem;font-weight:600;margin-top:2px;"><?php echo htmlspecialchars($view_app['grade_applying']); ?></div>
                </div>
                <div>
                    <span style="font-size:.75rem;text-transform:uppercase;color:var(--admin-text-muted);font-weight:600;">Class & Section</span>
                    <div style="font-size:1rem;font-weight:600;margin-top:2px;"><?php echo htmlspecialchars(($view_app['class_name'] ?? 'Pending') . ' – ' . ($view_app['section_name'] ?? 'Section')); ?></div>
                </div>
                <div>
                    <span style="font-size:.75rem;text-transform:uppercase;color:var(--admin-text-muted);font-weight:600;">Academic Session</span>
                    <div style="font-size:1rem;margin-top:2px;"><?php echo htmlspecialchars($view_app['session_name'] ?? 'Default'); ?></div>
                </div>
            </div>
        </div>

        <!-- Guardian Details Panel -->
        <div class="panel animate-in">
            <div class="panel__header"><h3 class="panel__title">Parent / Guardian Information</h3></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;padding:10px 0;">
                <div>
                    <span style="font-size:.75rem;text-transform:uppercase;color:var(--admin-text-muted);font-weight:600;">Parent / Guardian Name</span>
                    <div style="font-size:1rem;font-weight:600;color:var(--admin-heading);margin-top:2px;"><?php echo htmlspecialchars($view_app['parent_name']); ?></div>
                </div>
                <div>
                    <span style="font-size:.75rem;text-transform:uppercase;color:var(--admin-text-muted);font-weight:600;">Contact Phone</span>
                    <div style="font-size:1rem;font-weight:600;margin-top:2px;"><?php echo htmlspecialchars($view_app['phone']); ?></div>
                </div>
                <div>
                    <span style="font-size:.75rem;text-transform:uppercase;color:var(--admin-text-muted);font-weight:600;">Email Address</span>
                    <div style="font-size:.95rem;margin-top:2px;"><?php echo htmlspecialchars($view_app['email'] ?: 'Not provided'); ?></div>
                </div>
                <div>
                    <span style="font-size:.75rem;text-transform:uppercase;color:var(--admin-text-muted);font-weight:600;">Residential Address</span>
                    <div style="font-size:.95rem;margin-top:2px;"><?php echo htmlspecialchars($view_app['address'] ?: 'Not provided'); ?></div>
                </div>
            </div>
        </div>

        <!-- Remarks / Inquiry Message -->
        <div class="panel animate-in">
            <div class="panel__header"><h3 class="panel__title">Remarks & Previous School Notes</h3></div>
            <div style="padding:10px 0;color:var(--admin-heading);line-height:1.6;">
                <?php echo !empty($view_app['message']) ? nl2br(htmlspecialchars($view_app['message'])) : '<em style="color:var(--admin-text-muted);">No additional remarks or notes recorded.</em>'; ?>
            </div>
        </div>
    </div>

    <!-- Right Sidebar Metadata & Quick Info -->
    <div style="display:flex;flex-direction:column;gap:20px;">
        <div class="panel animate-in">
            <div class="panel__header"><h3 class="panel__title">Application Summary</h3></div>
            <div style="display:flex;flex-direction:column;gap:14px;padding:6px 0;font-size:.88rem;">
                <div style="display:flex;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.06);padding-bottom:8px;">
                    <span style="color:var(--admin-text-muted);">Application Ref:</span>
                    <strong style="color:var(--admin-accent);">#APP-<?php echo str_pad($view_app['id'], 5, '0', STR_PAD_LEFT); ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.06);padding-bottom:8px;">
                    <span style="color:var(--admin-text-muted);">Current Status:</span>
                    <span class="status-badge status-<?php echo $badge_cls; ?>"><?php echo ucfirst($view_app['status'] === 'accepted' ? 'Approved' : $view_app['status']); ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.06);padding-bottom:8px;">
                    <span style="color:var(--admin-text-muted);">Submission Date:</span>
                    <span><?php echo date('M d, Y', strtotime($view_app['created_at'])); ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.06);padding-bottom:8px;">
                    <span style="color:var(--admin-text-muted);">Last Updated:</span>
                    <span><?php echo !empty($view_app['updated_at']) ? date('M d, Y', strtotime($view_app['updated_at'])) : '—'; ?></span>
                </div>
            </div>

            <!-- Enrollment Card if converted -->
            <?php if (!empty($view_app['student_adm_no'])): ?>
            <div style="margin-top:18px;padding:14px;background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.3);border-radius:8px;">
                <div style="font-weight:700;color:#38bdf8;font-size:.9rem;display:flex;align-items:center;gap:6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Student Enrolled
                </div>
                <div style="margin-top:6px;font-size:.85rem;color:var(--admin-heading);">
                    Admission No: <strong><?php echo htmlspecialchars($view_app['student_adm_no']); ?></strong>
                </div>
                <div style="font-size:.78rem;color:var(--admin-text-muted);margin-top:3px;">
                    Enrolled on: <?php echo date('M d, Y', strtotime($view_app['student_adm_date'])); ?>
                </div>
            </div>
            <?php else: ?>
            <div style="margin-top:18px;padding:12px;background:rgba(255,255,255,.02);border:1px dashed rgba(255,255,255,.1);border-radius:8px;font-size:.8rem;color:var(--admin-text-muted);line-height:1.5;">
                &#9432; Admission approvals and student profile conversions are authorized by School Administration per SRS §8.4.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($action === 'add' || ($action === 'edit' && $view_app)): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- ADD OR EDIT APPLICATION FORM (SRS §8.2, §8.3)                  -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<?php $is_edit = ($action === 'edit'); ?>
<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title"><?php echo $is_edit ? 'Edit Application #APP-' . str_pad($view_app['id'], 5, '0', STR_PAD_LEFT) : 'New Admission Application'; ?></h1>
        <p class="page-header__desc"><?php echo $is_edit ? 'Update candidate registration details.' : 'Register a new applicant and guardian record.'; ?></p>
    </div>
    <div class="page-header__actions">
        <a href="admissions.php" class="btn-admin btn-admin--ghost">← Cancel & Return</a>
    </div>
</div>

<div class="form-card animate-in" style="max-width:960px;margin:0 auto;">
    <div class="form-card__header">
        <h2 class="form-card__title"><?php echo $is_edit ? 'Edit Application Details' : 'Candidate & Guardian Registration Form'; ?></h2>
    </div>

    <form method="POST" action="admissions.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="action" value="<?php echo $is_edit ? 'update' : 'create'; ?>">
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo $view_app['id']; ?>">
        <?php endif; ?>

        <?php if (!empty($warnings['duplicate_found'])): ?>
            <div class="alert alert--danger" style="margin-bottom:20px;border-left:4px solid #f59e0b;background:rgba(245,158,11,.1);">
                <div style="font-weight:700;color:#f59e0b;margin-bottom:6px;">Duplicate Record Warning</div>
                <label style="display:flex;align-items:center;gap:8px;font-size:.9rem;cursor:pointer;color:var(--admin-heading);">
                    <input type="checkbox" name="confirm_duplicate" value="1" style="width:16px;height:16px;">
                    I confirm that I have reviewed the duplicate alert and wish to proceed with registering this application.
                </label>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: APPLICANT DETAILS -->
        <h3 style="font-size:.95rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--admin-accent);margin:0 0 16px 0;padding-bottom:6px;border-bottom:1px solid rgba(255,255,255,.08);">
            1. Applicant Student Details
        </h3>

        <div class="dash-grid" style="grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div class="form-group">
                <label class="form-label" for="child_name">Child Full Name <span class="required" style="color:var(--admin-red);">*</span></label>
                <input type="text" id="child_name" name="child_name" class="form-control" placeholder="e.g. Ayan Ahmed" value="<?php echo htmlspecialchars($view_app['child_name'] ?? ($_POST['child_name'] ?? '')); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="gender">Gender <span class="required" style="color:var(--admin-red);">*</span></label>
                <?php $sel_g = $view_app['gender'] ?? ($_POST['gender'] ?? 'Male'); ?>
                <select id="gender" name="gender" class="form-control" required>
                    <option value="Male" <?php echo $sel_g === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $sel_g === 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo $sel_g === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="dob">Date of Birth</label>
                <input type="date" id="dob" name="dob" class="form-control" value="<?php echo htmlspecialchars($view_app['dob'] ?? ($_POST['dob'] ?? '')); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="grade_applying">Grade Applying For <span class="required" style="color:var(--admin-red);">*</span></label>
                <input type="text" id="grade_applying" name="grade_applying" class="form-control" placeholder="e.g. Class 5, Kindergarten, Grade 8" value="<?php echo htmlspecialchars($view_app['grade_applying'] ?? ($_POST['grade_applying'] ?? '')); ?>" required>
            </div>
        </div>

        <div class="dash-grid" style="grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px;">
            <div class="form-group">
                <label class="form-label" for="class_id">Target Class <span class="required" style="color:var(--admin-red);">*</span></label>
                <?php $sel_c = (int)($view_app['class_id'] ?? ($_POST['class_id'] ?? 0)); ?>
                <select id="class_id" name="class_id" class="form-control" required>
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $sel_c === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="section_id">Section <span class="required" style="color:var(--admin-red);">*</span></label>
                <?php $sel_s = (int)($view_app['section_id'] ?? ($_POST['section_id'] ?? 0)); ?>
                <select id="section_id" name="section_id" class="form-control" required>
                    <option value="">Select Section</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo $s['id']; ?>" data-class="<?php echo $s['class_id']; ?>" <?php echo $sel_s === (int)$s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="session_id">Academic Session <span class="required" style="color:var(--admin-red);">*</span></label>
                <?php $sel_sess = (int)($view_app['session_id'] ?? ($_POST['session_id'] ?? $current_session_id)); ?>
                <select id="session_id" name="session_id" class="form-control" required>
                    <option value="">Select Session</option>
                    <?php foreach ($sessions as $ss): ?>
                        <option value="<?php echo $ss['id']; ?>" <?php echo $sel_sess === (int)$ss['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ss['session_name'] . (!empty($ss['is_current']) ? ' (Current)' : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- SECTION 2: GUARDIAN DETAILS -->
        <h3 style="font-size:.95rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--admin-accent);margin:0 0 16px 0;padding-bottom:6px;border-bottom:1px solid rgba(255,255,255,.08);">
            2. Parent / Guardian Contact Details
        </h3>

        <div class="dash-grid" style="grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div class="form-group">
                <label class="form-label" for="parent_name">Parent / Guardian Name <span class="required" style="color:var(--admin-red);">*</span></label>
                <input type="text" id="parent_name" name="parent_name" class="form-control" placeholder="e.g. Tariq Ahmed" value="<?php echo htmlspecialchars($view_app['parent_name'] ?? ($_POST['parent_name'] ?? '')); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Primary Phone Number <span class="required" style="color:var(--admin-red);">*</span></label>
                <input type="text" id="phone" name="phone" class="form-control" placeholder="e.g. 0300-1234567" value="<?php echo htmlspecialchars($view_app['phone'] ?? ($_POST['phone'] ?? '')); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address (optional)</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="parent@example.com" value="<?php echo htmlspecialchars($view_app['email'] ?? ($_POST['email'] ?? '')); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="address">Residential Address</label>
                <input type="text" id="address" name="address" class="form-control" placeholder="House #, Street, City" value="<?php echo htmlspecialchars($view_app['address'] ?? ($_POST['address'] ?? '')); ?>">
            </div>
        </div>

        <!-- SECTION 3: STATUS & REMARKS -->
        <h3 style="font-size:.95rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--admin-accent);margin:0 0 16px 0;padding-bottom:6px;border-bottom:1px solid rgba(255,255,255,.08);">
            3. Admission Status & Remarks
        </h3>

        <?php if ($is_edit): ?>
        <div class="dash-grid" style="grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div class="form-group">
                <label class="form-label" for="status">Application Status</label>
                <?php
                $cur_st = $view_app['status'];
                $is_locked = in_array($cur_st, ['accepted', 'enrolled']) && !isAdmin();
                ?>
                <?php if ($is_locked): ?>
                    <div style="padding:10px 14px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:6px;font-size:.9rem;color:var(--admin-heading);">
                        <span class="status-badge status-<?php echo $cur_st === 'accepted' ? 'approved' : 'enrolled'; ?>">
                            <?php echo ucfirst($cur_st === 'accepted' ? 'Approved' : $cur_st); ?>
                        </span>
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($cur_st); ?>">
                        <span style="font-size:.78rem;color:var(--admin-text-muted);margin-left:8px;">(Approved by Administration per SRS §8.4)</span>
                    </div>
                <?php else: ?>
                    <select id="status" name="status" class="form-control">
                        <option value="pending" <?php echo $cur_st === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="reviewing" <?php echo $cur_st === 'reviewing' ? 'selected' : ''; ?>>Reviewing</option>
                        <option value="interviewed" <?php echo $cur_st === 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                        <?php if (isAdmin()): ?>
                            <option value="accepted" <?php echo $cur_st === 'accepted' ? 'selected' : ''; ?>>Accepted (Approved)</option>
                            <option value="rejected" <?php echo $cur_st === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <?php endif; ?>
                    </select>
                    <?php if (!isAdmin()): ?>
                        <small style="color:var(--admin-text-muted);margin-top:4px;display:block;">
                            Receptionists track inquiry stages (Pending/Reviewing/Interviewed). Official approval is managed by Admin.
                        </small>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-group" style="margin-bottom:24px;">
            <label class="form-label" for="message">Remarks / Previous School Record / Health Notes</label>
            <textarea id="message" name="message" class="form-control" rows="3" placeholder="Enter any previous school details, special requirements, or interviewer notes..."><?php echo htmlspecialchars($view_app['message'] ?? ($_POST['message'] ?? '')); ?></textarea>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:12px;border-top:1px solid rgba(255,255,255,.08);padding-top:18px;">
            <a href="admissions.php" class="btn-admin btn-admin--ghost">Cancel</a>
            <button type="submit" class="btn-admin btn-admin--primary" style="padding:10px 24px;font-weight:700;">
                <?php echo $is_edit ? 'Save Changes' : 'Register Application'; ?>
            </button>
        </div>
    </form>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- LIST APPLICATIONS TABLE (SRS §8.4, §8.6)                        -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Admission Applications</h1>
        <p class="page-header__desc">Register applicants, track inquiry statuses, and print admission forms.</p>
    </div>
    <div class="page-header__actions">
        <a href="admissions.php?action=add" class="btn-admin btn-admin--primary">+ New Application</a>
    </div>
</div>

<!-- Status Filter Tabs -->
<div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:12px;margin-bottom:16px;">
    <?php
    $tab_labels = [
        'all'         => 'All Applications',
        'pending'     => 'Pending',
        'reviewing'   => 'Reviewing',
        'interviewed' => 'Interviewed',
        'accepted'    => 'Approved',
        'rejected'    => 'Rejected',
        'enrolled'    => 'Enrolled',
    ];
    foreach ($tab_labels as $skey => $slabel):
        $is_active = ($filter_status === $skey);
        $count = $status_counts[$skey] ?? 0;
        $url_params = $_GET;
        $url_params['status'] = $skey;
        $url_params['page'] = 1;
        $tab_url = 'admissions.php?' . http_build_query($url_params);
    ?>
    <a href="<?php echo $tab_url; ?>" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:.82rem;font-weight:600;text-decoration:none;white-space:nowrap;transition:all .2s;background:<?php echo $is_active ? 'var(--admin-accent)' : 'rgba(255,255,255,.05)'; ?>;color:<?php echo $is_active ? '#fff' : 'var(--admin-text-muted)'; ?>;border:1px solid <?php echo $is_active ? 'var(--admin-accent)' : 'rgba(255,255,255,.1)'; ?>;">
        <?php echo $slabel; ?>
        <span style="background:<?php echo $is_active ? 'rgba(255,255,255,.25)' : 'rgba(255,255,255,.08)'; ?>;border-radius:10px;padding:1px 6px;font-size:.72rem;">
            <?php echo $count; ?>
        </span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Search & Class Filter Bar -->
<div class="panel animate-in" style="margin-bottom:20px;padding:16px;">
    <form method="GET" action="admissions.php" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">

        <div style="flex:1;min-width:240px;position:relative;">
            <input type="text" name="search" class="form-control" placeholder="Search applicant name, parent name, phone, or email..." value="<?php echo htmlspecialchars($filter_search); ?>">
        </div>

        <div style="min-width:180px;">
            <select name="class_id" class="form-control">
                <option value="0">All Classes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $filter_class === (int)$c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['class_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn-admin btn-admin--primary" style="padding:8px 16px;">Filter</button>
        <?php if ($filter_search !== '' || $filter_class > 0 || $filter_status !== 'all'): ?>
            <a href="admissions.php" class="btn-admin btn-admin--ghost" style="padding:8px 14px;">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Applications Table Panel -->
<div class="panel animate-in">
    <div class="panel__header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3 class="panel__title">
            Applications List
            <span style="font-size:.75rem;color:var(--admin-text-muted);font-weight:400;margin-left:8px;">
                (Showing <?php echo count($applications); ?> of <?php echo $total_rows; ?> results)
            </span>
        </h3>
    </div>

    <?php if (!empty($applications)): ?>
    <div class="table-responsive">
        <table class="recent-table">
            <thead>
                <tr>
                    <th style="width:70px;">Ref #</th>
                    <th>Applicant</th>
                    <th>Gender</th>
                    <th>Target Class</th>
                    <th>Parent / Guardian</th>
                    <th>Contact Phone</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th style="text-align:right;width:130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $a):
                    $st = $a['status'];
                    $is_enr = !empty($a['enrolled_adm_no']);
                    $st_badge = $is_enr ? 'enrolled' : (in_array($st, ['accepted','approved']) ? 'approved' : ($st === 'rejected' ? 'rejected' : 'pending'));
                    $st_text = $is_enr ? 'Enrolled' : ($st === 'accepted' ? 'Approved' : ucfirst($st));
                ?>
                <tr>
                    <td>
                        <strong style="color:var(--admin-accent);font-size:.85rem;">#APP-<?php echo str_pad($a['id'], 5, '0', STR_PAD_LEFT); ?></strong>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--admin-heading);"><?php echo htmlspecialchars($a['child_name']); ?></div>
                        <small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($a['grade_applying']); ?></small>
                    </td>
                    <td style="font-size:.85rem;color:var(--admin-text-muted);"><?php echo htmlspecialchars($a['gender'] ?? '—'); ?></td>
                    <td style="font-size:.85rem;">
                        <span style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($a['class_name'] ?? '—'); ?></span>
                        <small style="color:var(--admin-text-muted);display:block;"><?php echo htmlspecialchars($a['section_name'] ?? ''); ?></small>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--admin-heading);font-size:.85rem;"><?php echo htmlspecialchars($a['parent_name']); ?></div>
                        <?php if (!empty($a['email'])): ?>
                            <small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($a['email']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.85rem;font-weight:600;"><?php echo htmlspecialchars($a['phone']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $st_badge; ?>">
                            <?php echo $st_text; ?>
                        </span>
                        <?php if ($is_enr): ?>
                            <small style="display:block;color:var(--admin-text-muted);font-size:.7rem;margin-top:2px;">
                                <?php echo htmlspecialchars($a['enrolled_adm_no']); ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.8rem;color:var(--admin-text-muted);white-space:nowrap;">
                        <?php echo date('M d, Y', strtotime($a['created_at'])); ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex;gap:6px;align-items:center;">
                            <a href="admissions.php?action=view&id=<?php echo $a['id']; ?>" class="action-btn" title="View Details">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <a href="admissions.php?action=edit&id=<?php echo $a['id']; ?>" class="action-btn" title="Edit Application">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <a href="admissions.php?action=print&id=<?php echo $a['id']; ?>" target="_blank" class="action-btn" title="Print Admission Form" style="color:var(--admin-accent);">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px;border-top:1px solid rgba(255,255,255,.06);font-size:.85rem;">
        <span style="color:var(--admin-text-muted);">
            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
        </span>
        <div style="display:flex;gap:6px;">
            <?php if ($page > 1): ?>
                <?php
                $prev_params = $_GET;
                $prev_params['page'] = $page - 1;
                ?>
                <a href="admissions.php?<?php echo http_build_query($prev_params); ?>" class="btn-admin btn-admin--sm btn-admin--ghost">Previous</a>
            <?php endif; ?>

            <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                <?php
                $p_params = $_GET;
                $p_params['page'] = $p;
                ?>
                <a href="admissions.php?<?php echo http_build_query($p_params); ?>" class="btn-admin btn-admin--sm <?php echo $p === $page ? 'btn-admin--primary' : 'btn-admin--ghost'; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <?php
                $next_params = $_GET;
                $next_params['page'] = $page + 1;
                ?>
                <a href="admissions.php?<?php echo http_build_query($next_params); ?>" class="btn-admin btn-admin--sm btn-admin--ghost">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state" style="padding:48px 0;">
        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--admin-text-muted);margin-bottom:12px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <p style="font-size:.95rem;color:var(--admin-heading);font-weight:600;">No admission applications found.</p>
        <span style="color:var(--admin-text-muted);font-size:.85rem;">Try modifying your search criteria or register a new applicant.</span>
        <div style="margin-top:14px;">
            <a href="admissions.php?action=add" class="btn-admin btn-admin--primary">+ Register New Applicant</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
