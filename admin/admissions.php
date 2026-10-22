<?php
/**
 * admin/admissions.php
 * Admissions Management Module
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Admissions Management';
$current_page = 'admissions';
// Ensure required columns exist in admissions_inquiries table
if (mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM admissions_inquiries LIKE 'gender'")) === 0) {
    mysqli_query($conn, "ALTER TABLE admissions_inquiries ADD COLUMN gender ENUM('Male','Female','Other') NOT NULL DEFAULT 'Male'");
}
if (mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM admissions_inquiries LIKE 'class_id'")) === 0) {
    mysqli_query($conn, "ALTER TABLE admissions_inquiries ADD COLUMN class_id INT NOT NULL DEFAULT 0");
}
if (mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM admissions_inquiries LIKE 'section_id'")) === 0) {
    mysqli_query($conn, "ALTER TABLE admissions_inquiries ADD COLUMN section_id INT NOT NULL DEFAULT 0");
}
if (mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM admissions_inquiries LIKE 'session_id'")) === 0) {
    mysqli_query($conn, "ALTER TABLE admissions_inquiries ADD COLUMN session_id INT NOT NULL DEFAULT 0");
}

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$action = $_GET['action'] ?? 'list';
$admission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ─── POST HANDLERS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token). Please try again.';
        header('Location: admissions.php');
        exit();
    }
    $post_action = $_POST['action'] ?? '';

    // CREATE NEW ADMISSION (admin can manually add)
    if ($post_action === 'create') {
                $parent_name = trim($_POST['parent_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $child_name = trim($_POST['child_name'] ?? '');
        $grade_applying = trim($_POST['grade_applying'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $class_id = (int)($_POST['class_id'] ?? 0);
        $section_id = (int)($_POST['section_id'] ?? 0);
        $session_id = (int)($_POST['session_id'] ?? 0);
        if ($parent_name === '') $errors[] = 'Parent name required.';
        if ($phone === '') $errors[] = 'Phone required.';
        if ($child_name === '') $errors[] = 'Child name required.';
        if ($grade_applying === '') $errors[] = 'Grade applying required.';
        if (empty($errors)) {
    // Validate gender
    if (!in_array($gender, ['Male','Female','Other'])) $errors[] = 'Valid gender required.';
    // Validate class selection
    if ($class_id <= 0) $errors[] = 'Class selection required.';
    // Validate section selection and relation
    if ($section_id <= 0) $errors[] = 'Section selection required.';
    else {
        $sec_check = mysqli_prepare($conn, "SELECT class_id FROM sections WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($sec_check, 'i', $section_id);
        mysqli_stmt_execute($sec_check);
        $sec_res = mysqli_stmt_get_result($sec_check);
        $sec_row = mysqli_fetch_assoc($sec_res);
        mysqli_stmt_close($sec_check);
        if (!$sec_row) $errors[] = 'Invalid section selected.';
        elseif ($sec_row['class_id'] != $class_id) $errors[] = 'Selected section does not belong to selected class.';
    }
    // Validate session selection
    if ($session_id <= 0) $errors[] = 'Academic session selection required.';
    else {
        $sess_check = mysqli_prepare($conn, "SELECT id FROM academic_sessions WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($sess_check, 'i', $session_id);
        mysqli_stmt_execute($sess_check);
        $sess_res = mysqli_stmt_get_result($sess_check);
        $sess_row = mysqli_fetch_assoc($sess_res);
        mysqli_stmt_close($sess_check);
        if (!$sess_row) $errors[] = 'Invalid academic session selected.';
    }

            $stmt = mysqli_prepare($conn, "INSERT INTO admissions_inquiries (parent_name, email, phone, child_name, grade_applying, gender, class_id, section_id, session_id, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssssssiiis', $parent_name, $email, $phone, $child_name, $grade_applying, $gender, $class_id, $section_id, $session_id, $message);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_success'] = 'Admission inquiry added.';
                header('Location: admissions.php');
                exit();
            } else {
                $errors[] = 'Failed to add admission: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }

    // UPDATE ADMISSION (status, details)
    if ($post_action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $parent_name = trim($_POST['parent_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $child_name = trim($_POST['child_name'] ?? '');
        $grade_applying = trim($_POST['grade_applying'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $status = trim($_POST['status'] ?? 'pending');
        $gender = trim($_POST['gender'] ?? 'Male');
        $class_id = (int)($_POST['class_id'] ?? 0);
        $section_id = (int)($_POST['section_id'] ?? 0);
        $session_id = (int)($_POST['session_id'] ?? 0);
        if ($id <= 0) $errors[] = 'Invalid admission ID.';
        if ($parent_name === '') $errors[] = 'Parent name required.';
        if ($phone === '') $errors[] = 'Phone required.';
        if ($child_name === '') $errors[] = 'Child name required.';
        if (empty($errors)) {
    // Same validation as in create (gender, class, section, session)
    if (!in_array($gender, ['Male','Female','Other'])) $errors[] = 'Valid gender required.';
    if ($class_id <= 0) $errors[] = 'Class selection required.';
    if ($section_id <= 0) $errors[] = 'Section selection required.';
    else {
        $sec_check = mysqli_prepare($conn, "SELECT class_id FROM sections WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($sec_check, 'i', $section_id);
        mysqli_stmt_execute($sec_check);
        $sec_res = mysqli_stmt_get_result($sec_check);
        $sec_row = mysqli_fetch_assoc($sec_res);
        mysqli_stmt_close($sec_check);
        if (!$sec_row) $errors[] = 'Invalid section selected.';
        elseif ($sec_row['class_id'] != $class_id) $errors[] = 'Selected section does not belong to selected class.';
    }
    if ($session_id <= 0) $errors[] = 'Academic session selection required.';
    else {
        $sess_check = mysqli_prepare($conn, "SELECT id FROM academic_sessions WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($sess_check, 'i', $session_id);
        mysqli_stmt_execute($sess_check);
        $sess_res = mysqli_stmt_get_result($sess_check);
        $sess_row = mysqli_fetch_assoc($sess_res);
        mysqli_stmt_close($sess_check);
        if (!$sess_row) $errors[] = 'Invalid academic session selected.';
    }

            $stmt = mysqli_prepare($conn, "UPDATE admissions_inquiries SET parent_name = ?, email = ?, phone = ?, child_name = ?, grade_applying = ?, gender = ?, class_id = ?, section_id = ?, session_id = ?, message = ?, status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ssssssiiissi', $parent_name, $email, $phone, $child_name, $grade_applying, $gender, $class_id, $section_id, $session_id, $message, $status, $id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_success'] = 'Admission updated.';
                header('Location: admissions.php');
                exit();
            } else {
                $errors[] = 'Failed to update admission: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }

    // DELETE ADMISSION (only if not converted)
    if ($post_action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $del = mysqli_prepare($conn, "DELETE FROM admissions_inquiries WHERE id = ?");
            mysqli_stmt_bind_param($del, 'i', $id);
            if (mysqli_stmt_execute($del)) {
                $_SESSION['flash_success'] = 'Admission record deleted.';
            } else {
                $_SESSION['flash_error'] = 'Delete failed: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($del);
        }
        header('Location: admissions.php');
        exit();
    }

    // CONVERT TO STUDENT (only when status is accepted and not already converted)
    if ($post_action === 'convert') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // fetch admission record
            $stmt = mysqli_prepare($conn, "SELECT * FROM admissions_inquiries WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $ad = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);
            if (!$ad) {
                $_SESSION['flash_error'] = 'Admission not found.';
                header('Location: admissions.php');
                exit();
            }
            if ($ad['status'] !== 'accepted') {
                $_SESSION['flash_error'] = 'Only accepted admissions can be converted.';
                header('Location: admissions.php');
                exit();
            }
            if ($ad['converted_student_id']) {
                $_SESSION['flash_error'] = 'Admission already converted to a student.';
                header('Location: admissions.php');
                exit();
            }
            // Begin transaction
            mysqli_begin_transaction($conn);
            // Resolve class id based on grade_applying (simple lookup)
            // Use class_id stored in admission record
            $class_id = (int)$ad['class_id'];
            if ($class_id <= 0) {
                mysqli_rollback($conn);
                $_SESSION['flash_error'] = 'Invalid class reference in admission.';
                header('Location: admissions.php');
                exit();
            }
            // Find a section for that class
            // Use section_id stored in admission record
            $section_id = (int)$ad['section_id'];
            if ($section_id <= 0) {
                mysqli_rollback($conn);
                $_SESSION['flash_error'] = 'Invalid section reference in admission.';
                header('Location: admissions.php');
                exit();
            }
            // Current session id
            // Use session_id stored in admission record
            $session_id = (int)$ad['session_id'];
            if ($session_id <= 0) {
                mysqli_rollback($conn);
                $_SESSION['flash_error'] = 'Invalid session reference in admission.';
                header('Location: admissions.php');
                exit();
            }
            // Generate admission_no
            $admission_no = 'ADM-' . date('Ymd') . '-' . strtoupper(substr($ad['child_name'], 0, 3)) . '-' . $id;
            // Split child_name into first/last (simple split)
            $names = explode(' ', $ad['child_name'], 2);
            $first_name = $names[0] ?? $ad['child_name'];
            $last_name = $names[1] ?? '';
            // Insert student record (gender defaulted to Male for demo)
            $stud_stmt = mysqli_prepare($conn, "INSERT INTO students (admission_no, first_name, last_name, gender, date_of_birth, session_id, class_id, section_id, parent_name, parent_phone, parent_email, address, admission_date, status) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, '', CURDATE(), 'active')");
            mysqli_stmt_bind_param($stud_stmt, 'ssssiiisss', $admission_no, $first_name, $last_name, $ad['gender'], $session_id, $class_id, $section_id, $ad['parent_name'], $ad['phone'], $ad['email']);
            $ok = mysqli_stmt_execute($stud_stmt);
            $new_student_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stud_stmt);
            if ($ok) {
                // Update admission record with linked student id
                $upd = mysqli_prepare($conn, "UPDATE admissions_inquiries SET converted_student_id = ? WHERE id = ?");
                mysqli_stmt_bind_param($upd, 'ii', $new_student_id, $id);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
                mysqli_commit($conn);
                $_SESSION['flash_success'] = 'Admission converted to student with ID ' . $new_student_id . '.';
            } else {
                mysqli_rollback($conn);
                $_SESSION['flash_error'] = 'Failed to create student: ' . mysqli_error($conn);
            }
        }
        header('Location: admissions.php');
        exit();
    }
}

// ─── LOAD DATA FOR EDIT ──────────────────────────────────────────
$edit_admission = null;
if ($action === 'edit' && $admission_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM admissions_inquiries WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $admission_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $edit_admission = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$edit_admission) {
        $_SESSION['flash_error'] = 'Admission not found.';
        header('Location: admissions.php');
        exit();
    }
}

// Fetch admissions list with optional search
$search = trim($_GET['search'] ?? '');
$admissions_list = [];
// Fetch lookup data for form dropdowns
$classes_for_form = [];
$resc = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY class_name ASC");
while($c = mysqli_fetch_assoc($resc)) $classes_for_form[] = $c;
$sections_for_form = [];
$ressec = mysqli_query($conn, "SELECT id, class_id, section_name FROM sections ORDER BY class_id, section_name ASC");
while($s = mysqli_fetch_assoc($ressec)) $sections_for_form[] = $s;
$sessions_for_form = [];
$ressess = mysqli_query($conn, "SELECT id, session_name FROM academic_sessions ORDER BY is_current DESC, id ASC");
while($ss = mysqli_fetch_assoc($ressess)) $sessions_for_form[] = $ss;
$query = "SELECT * FROM admissions_inquiries";
if ($search !== '') {
    $search_like = '%' . $conn->real_escape_string($search) . '%';
    $query .= " WHERE parent_name LIKE '{$search_like}' OR child_name LIKE '{$search_like}' OR email LIKE '{$search_like}'";
}
$query .= " ORDER BY created_at DESC";
$res = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($res)) $admissions_list[] = $row;

include __DIR__ . '/header.php';
?>
<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Admissions Management</h1>
        <p class="page-header__desc">Handle admission inquiries and convert accepted ones to students.</p>
    </div>
    <div class="page-header__actions">
        <?php if ($action === 'edit'): ?>
            <a href="admissions.php" class="btn-admin btn-admin--ghost">← Cancel Edit</a>
        <?php endif; ?>
    </div>
</div>
<?php if ($success_msg): ?>
    <div class="alert alert--success"><div class="alert__content"><span><?php echo htmlspecialchars($success_msg); ?></span></div></div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert alert--danger"><div class="alert__content"><span><?php echo htmlspecialchars($error_msg); ?></span></div></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert--danger"><div><strong>Please resolve the following:</strong><ul style="margin-left:20px;margin-top:6px;">
        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
    </ul></div></div>
<?php endif; ?>
<div class="dash-grid" style="grid-template-columns: 360px 1fr; align-items: start;">
    <!-- Add / Edit Form -->
    <div class="form-card animate-in">
        <div class="form-card__header"><h2 class="form-card__title"><?php echo $edit_admission ? 'Edit Admission' : 'Add New Admission'; ?></h2></div>
        <form method="POST" action="admissions.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="<?php echo $edit_admission ? 'update' : 'create'; ?>">
            <?php if ($edit_admission): ?><input type="hidden" name="id" value="<?php echo $edit_admission['id']; ?>"><?php endif; ?>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="parent_name">Parent / Guardian Name <span class="required">*</span></label>
                <input type="text" id="parent_name" name="parent_name" class="form-control" value="<?php echo htmlspecialchars($edit_admission['parent_name'] ?? ($_POST['parent_name'] ?? '')); ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="email">Parent Email (optional)</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($edit_admission['email'] ?? ($_POST['email'] ?? '')); ?>">
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="phone">Parent Phone <span class="required">*</span></label>
                <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($edit_admission['phone'] ?? ($_POST['phone'] ?? '')); ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="child_name">Child Name <span class="required">*</span></label>
                <input type="text" id="child_name" name="child_name" class="form-control" value="<?php echo htmlspecialchars($edit_admission['child_name'] ?? ($_POST['child_name'] ?? '')); ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="grade_applying">Grade Applying For <span class="required">*</span></label>
                <input type="text" id="grade_applying" name="grade_applying" class="form-control" value="<?php echo htmlspecialchars($edit_admission['grade_applying'] ?? ($_POST['grade_applying'] ?? '')); ?>" placeholder="e.g. Primary, Middle School" required>
                
                <!-- Gender -->
                <label class="form-label" for="gender">Gender <span class="required">*</span></label>
                <select id="gender" name="gender" class="form-control" required>
                    <?php $g = $edit_admission['gender'] ?? ($_POST['gender'] ?? ''); ?>
                    <option value="Male" <?php echo $g === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $g === 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo $g === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
                
                <!-- Class -->
                <label class="form-label" for="class_id">Class <span class="required">*</span></label>
                <select id="class_id" name="class_id" class="form-control" required>
                    <?php $csel = $edit_admission['class_id'] ?? ($_POST['class_id'] ?? 0); ?>
                    <option value="">Select Class</option>
                    <?php foreach ($classes_for_form as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $csel == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <!-- Section -->
                <label class="form-label" for="section_id">Section <span class="required">*</span></label>
                <select id="section_id" name="section_id" class="form-control" required>
                    <?php $ssel = $edit_admission['section_id'] ?? ($_POST['section_id'] ?? 0); ?>
                    <option value="">Select Section</option>
                    <?php foreach ($sections_for_form as $s): ?>
                        <option value="<?php echo $s['id']; ?>" data-class="<?php echo $s['class_id']; ?>" <?php echo $ssel == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['section_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <!-- Session -->
                <label class="form-label" for="session_id">Academic Session <span class="required">*</span></label>
                <select id="session_id" name="session_id" class="form-control" required>
                    <?php $ssel = $edit_admission['session_id'] ?? ($_POST['session_id'] ?? 0); ?>
                    <option value="">Select Session</option>
                    <?php foreach ($sessions_for_form as $ss): ?>
                        <option value="<?php echo $ss['id']; ?>" <?php echo $ssel == $ss['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($ss['session_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="message">Message / Notes</label>
                <textarea id="message" name="message" class="form-control" rows="3"><?php echo htmlspecialchars($edit_admission['message'] ?? ($_POST['message'] ?? '')); ?></textarea>
            </div>
            <?php if ($edit_admission): ?>
                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-control" required>
                        <?php $statuses = ['pending','reviewing','interviewed','accepted','rejected','enrolled']; ?>
                        <?php foreach ($statuses as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo $edit_admission['status'] === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-actions" style="margin-top:0;padding-top:16px;">
                <?php if ($edit_admission): ?><a href="admissions.php" class="btn-admin btn-admin--secondary">Cancel</a><?php endif; ?>
                <button type="submit" class="btn-admin btn-admin--primary"><?php echo $edit_admission ? 'Save Changes' : 'Create Admission'; ?></button>
            </div>
        </form>
    </div>
    <!-- Admissions List -->
    <div class="panel animate-in">
        <div class="panel__header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3 class="panel__title">Admission Inquiries (<?php echo count($admissions_list); ?>)</h3>
            <form method="GET" action="admissions.php" style="display:flex;gap:4px;">
                <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="width:200px;">
                <button type="submit" class="btn-admin btn-admin--primary">Search</button>
            </form>
        </div>
        <?php if (!empty($admissions_list)): ?>
            <div class="table-responsive">
                <table class="recent-table">
                    <thead><tr>
                        <th>Child</th>
                        <th>Parent</th>
                        <th>Grade</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($admissions_list as $adm): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($adm['child_name']); ?></td>
                                <td><?php echo htmlspecialchars($adm['parent_name']); ?></td>
                                <td><?php echo htmlspecialchars($adm['grade_applying']); ?></td>
                                <td><span class="badge badge--info"><?php echo htmlspecialchars($adm['status']); ?></span></td>
                                <td style="text-align:right;">
                                    <div class="table-actions" style="justify-content:flex-end;">
                                        <a href="admissions.php?action=edit&id=<?php echo $adm['id']; ?>" class="action-btn action-btn--edit" title="Edit Admission"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                                        <?php if ($adm['status'] === 'accepted' && !$adm['converted_student_id']): ?>
                                            <form method="POST" action="admissions.php" style="display:inline;" onsubmit="return confirm('Convert this admission to a student?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="action" value="convert">
                                                <input type="hidden" name="id" value="<?php echo $adm['id']; ?>">
                                                <button type="submit" class="action-btn action-btn--edit" title="Convert to Student"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg></button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($adm['converted_student_id']): ?>
                                            <a href="students.php?action=view&id=<?php echo $adm['converted_student_id']; ?>" class="action-btn" title="View Student"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg></a>
                                        <?php endif; ?>
                                        <form method="POST" action="admissions.php" style="display:inline;" onsubmit="return confirm('Delete admission <?php echo htmlspecialchars($adm['child_name']); ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $adm['id']; ?>">
                                            <button type="submit" class="action-btn action-btn--delete" title="Delete Admission"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><p>No admission inquiries found.</p></div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
