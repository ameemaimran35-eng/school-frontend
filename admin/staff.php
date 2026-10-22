<?php
/**
 * admin/staff.php
 * Staff Management Module
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Staff Management';
$current_page = 'staff';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$action = $_GET['action'] ?? 'list';
$staff_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Departments for filter/dropdown
$departments = ['Academics', 'Administration', 'Science', 'Mathematics', 'Humanities', 'Sports & Arts', 'Support Staff'];

// ─── POST HANDLERS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token). Please try again.';
        header('Location: staff.php');
        exit();
    }

    $post_action = $_POST['action'] ?? '';

    // ADD STAFF
    if ($post_action === 'create') {
        $employee_code  = trim($_POST['employee_code'] ?? '');
        $first_name     = trim($_POST['first_name'] ?? '');
        $last_name      = trim($_POST['last_name'] ?? '');
        $gender         = trim($_POST['gender'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $designation    = trim($_POST['designation'] ?? '');
        $department     = trim($_POST['department'] ?? '');
        $qualification  = trim($_POST['qualification'] ?? '');
        $joining_date   = trim($_POST['joining_date'] ?? '');
        $basic_salary   = (float)($_POST['basic_salary'] ?? 0);
        $status         = trim($_POST['status'] ?? 'active');

        // Validation
        if ($first_name === '' || $last_name === '') {
            $errors[] = 'First and last names are required.';
        }
        if ($employee_code === '') {
            $errors[] = 'Employee code is required.';
        } else {
            $chk = mysqli_prepare($conn, "SELECT id FROM staff WHERE employee_code = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 's', $employee_code);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) {
                $errors[] = "Employee code '{$employee_code}' is already registered.";
            }
            mysqli_stmt_close($chk);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        } else {
            $chk = mysqli_prepare($conn, "SELECT id FROM staff WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 's', $email);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) {
                $errors[] = "Email address '{$email}' is already in use.";
            }
            mysqli_stmt_close($chk);
        }
        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $errors[] = 'Please select a valid gender.';
        }
        if ($phone === '') {
            $errors[] = 'Contact phone number is required.';
        }
        if ($designation === '') {
            $errors[] = 'Designation/job title is required.';
        }
        if ($department === '') {
            $errors[] = 'Department is required.';
        }
        if ($joining_date === '') {
            $joining_date = date('Y-m-d');
        }
        if ($basic_salary < 0) {
            $errors[] = 'Basic salary cannot be negative.';
        }
        if (!in_array($status, ['active', 'on_leave', 'resigned', 'terminated'], true)) {
            $status = 'active';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO staff 
                (employee_code, first_name, last_name, gender, email, phone, designation, department, qualification, joining_date, basic_salary, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssssssssssds',
                $employee_code, $first_name, $last_name, $gender, $email,
                $phone, $designation, $department, $qualification,
                $joining_date, $basic_salary, $status
            );
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                $_SESSION['flash_success'] = "Staff member {$first_name} {$last_name} ({$employee_code}) added successfully.";
                header("Location: staff.php?action=view&id={$new_id}");
                exit();
            } else {
                $errors[] = 'Failed to create staff record: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }

    // UPDATE STAFF
    if ($post_action === 'update') {
        $id             = (int)($_POST['id'] ?? 0);
        $employee_code  = trim($_POST['employee_code'] ?? '');
        $first_name     = trim($_POST['first_name'] ?? '');
        $last_name      = trim($_POST['last_name'] ?? '');
        $gender         = trim($_POST['gender'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $designation    = trim($_POST['designation'] ?? '');
        $department     = trim($_POST['department'] ?? '');
        $qualification  = trim($_POST['qualification'] ?? '');
        $joining_date   = trim($_POST['joining_date'] ?? '');
        $basic_salary   = (float)($_POST['basic_salary'] ?? 0);
        $status         = trim($_POST['status'] ?? 'active');

        if ($id <= 0) {
            $errors[] = 'Invalid staff record identifier.';
        }
        if ($first_name === '' || $last_name === '') {
            $errors[] = 'First and last names are required.';
        }
        if ($employee_code === '') {
            $errors[] = 'Employee code is required.';
        } else {
            $chk = mysqli_prepare($conn, "SELECT id FROM staff WHERE employee_code = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'si', $employee_code, $id);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) {
                $errors[] = "Employee code '{$employee_code}' is used by another employee.";
            }
            mysqli_stmt_close($chk);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        } else {
            $chk = mysqli_prepare($conn, "SELECT id FROM staff WHERE email = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'si', $email, $id);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) {
                $errors[] = "Email address '{$email}' is used by another employee.";
            }
            mysqli_stmt_close($chk);
        }
        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $errors[] = 'Please select a valid gender.';
        }
        if ($phone === '' || $designation === '' || $department === '') {
            $errors[] = 'Phone, designation, and department are required.';
        }
        if ($basic_salary < 0) {
            $errors[] = 'Basic salary cannot be negative.';
        }
        if (!in_array($status, ['active', 'on_leave', 'resigned', 'terminated'], true)) {
            $status = 'active';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "UPDATE staff SET 
                employee_code = ?, first_name = ?, last_name = ?, gender = ?, email = ?,
                phone = ?, designation = ?, department = ?, qualification = ?,
                joining_date = ?, basic_salary = ?, status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ssssssssssdsi',
                $employee_code, $first_name, $last_name, $gender, $email,
                $phone, $designation, $department, $qualification,
                $joining_date, $basic_salary, $status, $id
            );
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $_SESSION['flash_success'] = "Staff #{$employee_code} updated successfully.";
                header("Location: staff.php?action=view&id={$id}");
                exit();
            } else {
                $errors[] = 'Failed to update staff: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }

    // UPDATE STATUS (SAFE ARCHIVE)
    if ($post_action === 'change_status') {
        $id = (int)($_POST['id'] ?? 0);
        $new_status = trim($_POST['new_status'] ?? '');
        if ($id > 0 && in_array($new_status, ['active', 'on_leave', 'resigned', 'terminated'], true)) {
            $stmt = mysqli_prepare($conn, "UPDATE staff SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $new_status, $id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_success'] = "Staff status updated to " . ucfirst(str_replace('_', ' ', $new_status)) . ". Historical records and salary payments remain intact.";
            } else {
                $_SESSION['flash_error'] = "Could not update status: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        header("Location: staff.php" . ($id > 0 ? "?action=view&id={$id}" : ""));
        exit();
    }
}

// ─── LOAD DATA FOR VIEW OR EDIT ──────────────────────────────────
$staff = null;
if (($action === 'view' || $action === 'edit') && $staff_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM staff WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $staff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $staff = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$staff) {
        $_SESSION['flash_error'] = 'Staff member not found.';
        header('Location: staff.php');
        exit();
    }

    // Fetch assigned subjects if any
    $assigned_subjects = [];
    $sub_stmt = mysqli_prepare($conn, "SELECT cs.id, s.subject_name, s.subject_code, c.class_name 
        FROM class_subjects cs
        JOIN subjects s ON cs.subject_id = s.id
        JOIN classes c ON cs.class_id = c.id
        WHERE cs.teacher_id = ?");
    mysqli_stmt_bind_param($sub_stmt, 'i', $staff_id);
    mysqli_stmt_execute($sub_stmt);
    $sub_res = mysqli_stmt_get_result($sub_stmt);
    while ($sub = mysqli_fetch_assoc($sub_res)) {
        $assigned_subjects[] = $sub;
    }
    mysqli_stmt_close($sub_stmt);
}

// Auto-suggest next employee code
$suggested_code = 'STF-' . str_pad((string)(rand(10, 99)), 3, '0', STR_PAD_LEFT);
$res = mysqli_query($conn, "SELECT employee_code FROM staff ORDER BY id DESC LIMIT 1");
if ($r = mysqli_fetch_assoc($res)) {
    if (preg_match('/STF-(\d+)/', $r['employee_code'], $m)) {
        $next_num = (int)$m[1] + 1;
        $suggested_code = 'STF-' . str_pad((string)$next_num, 3, '0', STR_PAD_LEFT);
    }
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Staff Management</h1>
        <p class="page-header__desc">Manage faculty, academic supervisors, and administrative personnel.</p>
    </div>
    <div class="page-header__actions">
        <?php if ($action !== 'list'): ?>
            <a href="staff.php" class="btn-admin btn-admin--secondary">← Back to List</a>
        <?php endif; ?>
        <?php if ($action !== 'add'): ?>
            <a href="staff.php?action=add" class="btn-admin btn-admin--primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Add Staff Member</span>
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
<!-- VIEW SINGLE STAFF                                                   -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php if ($action === 'view' && $staff): ?>
    <div class="detail-card animate-in">
        <div class="detail-card__header">
            <div style="display:flex;align-items:center;gap:14px;">
                <div class="student-avatar" style="width:48px;height:48px;font-size:18px;background:var(--admin-blue);">
                    <?php echo strtoupper(substr($staff['first_name'],0,1) . substr($staff['last_name'],0,1)); ?>
                </div>
                <div>
                    <h2 style="font-size:1.3rem;font-weight:700;color:var(--admin-heading);margin-bottom:2px;">
                        <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                    </h2>
                    <span style="color:var(--admin-text-muted);font-size:0.85rem;">
                        Code: <strong style="color:var(--admin-blue);"><?php echo htmlspecialchars($staff['employee_code']); ?></strong>
                        | Designation: <strong><?php echo htmlspecialchars($staff['designation']); ?></strong>
                    </span>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="badge badge--<?php echo $staff['status'] === 'active' ? 'active' : ($staff['status'] === 'on_leave' ? 'pending' : 'danger'); ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $staff['status'])); ?>
                </span>
                <a href="staff.php?action=edit&id=<?php echo $staff['id']; ?>" class="btn-admin btn-admin--sm btn-admin--primary">Edit</a>
            </div>
        </div>

        <div class="detail-card__body">
            <h3 style="font-size:0.95rem;font-weight:700;color:var(--admin-heading);margin-bottom:14px;border-bottom:1px solid var(--admin-border);padding-bottom:8px;">
                Employment Details
            </h3>
            <div class="detail-grid" style="margin-bottom:24px;">
                <div class="detail-item">
                    <span class="detail-label">Department</span>
                    <span class="detail-value"><?php echo htmlspecialchars($staff['department']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Designation</span>
                    <span class="detail-value"><?php echo htmlspecialchars($staff['designation']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Joining Date</span>
                    <span class="detail-value"><?php echo date('M j, Y', strtotime($staff['joining_date'])); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Basic Salary</span>
                    <span class="detail-value" style="color:var(--admin-accent);">Rs <?php echo number_format($staff['basic_salary'], 2); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Academic Qualification</span>
                    <span class="detail-value"><?php echo htmlspecialchars($staff['qualification'] ?: 'Not recorded'); ?></span>
                </div>
            </div>

            <h3 style="font-size:0.95rem;font-weight:700;color:var(--admin-heading);margin-bottom:14px;border-bottom:1px solid var(--admin-border);padding-bottom:8px;">
                Contact Details
            </h3>
            <div class="detail-grid" style="margin-bottom:24px;">
                <div class="detail-item">
                    <span class="detail-label">Email Address</span>
                    <span class="detail-value"><?php echo htmlspecialchars($staff['email']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Phone Number</span>
                    <span class="detail-value"><?php echo htmlspecialchars($staff['phone']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Gender</span>
                    <span class="detail-value"><?php echo htmlspecialchars($staff['gender']); ?></span>
                </div>
            </div>

            <!-- Teaching Assignments if any -->
            <?php if (!empty($assigned_subjects)): ?>
                <h3 style="font-size:0.95rem;font-weight:700;color:var(--admin-heading);margin-bottom:14px;border-bottom:1px solid var(--admin-border);padding-bottom:8px;">
                    Assigned Teaching Subjects
                </h3>
                <div class="table-responsive" style="margin-bottom:24px;">
                    <table class="recent-table" style="min-width:auto;">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Code</th>
                                <th>Class</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_subjects as $asub): ?>
                            <tr>
                                <td style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($asub['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($asub['subject_code']); ?></td>
                                <td><?php echo htmlspecialchars($asub['class_name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Safe Status Update Form -->
            <div style="background:var(--admin-input-bg);padding:16px;border-radius:var(--radius-sm);border:1px solid var(--admin-border);margin-top:20px;">
                <form method="POST" action="staff.php" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="id" value="<?php echo $staff['id']; ?>">
                    <label style="font-size:0.85rem;font-weight:600;color:var(--admin-text);">Quick Employment Status Update:</label>
                    <select name="new_status" class="form-control" style="width:auto;min-width:140px;">
                        <option value="active" <?php echo $staff['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="on_leave" <?php echo $staff['status'] === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                        <option value="resigned" <?php echo $staff['status'] === 'resigned' ? 'selected' : ''; ?>>Resigned</option>
                        <option value="terminated" <?php echo $staff['status'] === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                    </select>
                    <button type="submit" class="btn-admin btn-admin--sm btn-admin--secondary">Update Status</button>
                    <small style="color:var(--admin-text-muted);">Deactivating staff safely preserves historical payroll and attendance logs.</small>
                </form>
            </div>
        </div>
    </div>

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- ADD OR EDIT STAFF FORM                                              -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <?php
    $is_edit = ($action === 'edit' && $staff);
    $form_data = $is_edit ? $staff : [
        'employee_code' => $_POST['employee_code'] ?? $suggested_code,
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'gender' => $_POST['gender'] ?? 'Female',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'designation' => $_POST['designation'] ?? 'Teacher',
        'department' => $_POST['department'] ?? 'Academics',
        'qualification' => $_POST['qualification'] ?? '',
        'joining_date' => $_POST['joining_date'] ?? date('Y-m-d'),
        'basic_salary' => $_POST['basic_salary'] ?? '3500.00',
        'status' => $_POST['status'] ?? 'active',
    ];
    ?>
    <div class="form-card animate-in">
        <div class="form-card__header">
            <h2 class="form-card__title"><?php echo $is_edit ? 'Edit Staff Profile' : 'Add New Staff Member'; ?></h2>
            <span style="font-size:0.8rem;color:var(--admin-text-muted);">Fields with <span style="color:var(--admin-red);">*</span> are required</span>
        </div>

        <form method="POST" action="staff.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'update' : 'create'; ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="id" value="<?php echo $staff['id']; ?>">
            <?php endif; ?>

            <div class="form-grid form-grid--3">
                <div class="form-group">
                    <label class="form-label" for="employee_code">Employee Code <span class="required">*</span></label>
                    <input type="text" id="employee_code" name="employee_code" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['employee_code']); ?>" required>
                    <span class="form-hint">Unique staff identification code</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Employment Status <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="active" <?php echo ($form_data['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="on_leave" <?php echo ($form_data['status'] ?? '') === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                        <option value="resigned" <?php echo ($form_data['status'] ?? '') === 'resigned' ? 'selected' : ''; ?>>Resigned</option>
                        <option value="terminated" <?php echo ($form_data['status'] ?? '') === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="joining_date">Joining Date <span class="required">*</span></label>
                    <input type="date" id="joining_date" name="joining_date" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['joining_date']); ?>" required>
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
                        <option value="Female" <?php echo ($form_data['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Male" <?php echo ($form_data['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Other" <?php echo ($form_data['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Work Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['email']); ?>" placeholder="staff@brightway.edu" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number <span class="required">*</span></label>
                    <input type="text" id="phone" name="phone" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['phone']); ?>" placeholder="+1 (555) 000-0000" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="basic_salary">Basic Salary (Monthly) <span class="required">*</span></label>
                    <input type="number" step="0.01" id="basic_salary" name="basic_salary" class="form-control" 
                           value="<?php echo htmlspecialchars((string)$form_data['basic_salary']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="department">Department <span class="required">*</span></label>
                    <select id="department" name="department" class="form-control" required>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept; ?>" <?php echo ($form_data['department'] ?? '') === $dept ? 'selected' : ''; ?>>
                                <?php echo $dept; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="designation">Designation <span class="required">*</span></label>
                    <input type="text" id="designation" name="designation" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['designation']); ?>" placeholder="e.g. Mathematics Teacher" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="qualification">Highest Qualification</label>
                    <input type="text" id="qualification" name="qualification" class="form-control" 
                           value="<?php echo htmlspecialchars($form_data['qualification'] ?? ''); ?>" placeholder="e.g. M.Sc. Mathematics">
                </div>
            </div>

            <div class="form-actions">
                <a href="staff.php" class="btn-admin btn-admin--secondary">Cancel</a>
                <button type="submit" class="btn-admin btn-admin--primary">
                    <?php echo $is_edit ? 'Save Changes' : 'Register Staff Member'; ?>
                </button>
            </div>
        </form>
    </div>

<!-- ─────────────────────────────────────────────────────────────────── -->
<!-- STAFF LIST VIEW                                                     -->
<!-- ─────────────────────────────────────────────────────────────────── -->
<?php else: ?>
    <?php
    $search     = trim($_GET['search'] ?? '');
    $filter_dep = trim($_GET['department'] ?? '');
    $filter_st  = trim($_GET['status'] ?? '');
    $page       = max(1, (int)($_GET['page'] ?? 1));
    $limit      = 10;
    $offset     = ($page - 1) * $limit;

    $where_sql = "WHERE 1=1";
    $params = [];
    $types = '';

    if ($search !== '') {
        $where_sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR employee_code LIKE ? OR designation LIKE ? OR email LIKE ?)";
        $s_term = "%{$search}%";
        $params[] = $s_term;
        $params[] = $s_term;
        $params[] = $s_term;
        $params[] = $s_term;
        $params[] = $s_term;
        $types .= 'sssss';
    }

    if ($filter_dep !== '') {
        $where_sql .= " AND department = ?";
        $params[] = $filter_dep;
        $types .= 's';
    }

    if ($filter_st !== '' && in_array($filter_st, ['active', 'on_leave', 'resigned', 'terminated'], true)) {
        $where_sql .= " AND status = ?";
        $params[] = $filter_st;
        $types .= 's';
    }

    // Total Count
    $count_sql = "SELECT COUNT(*) AS total FROM staff $where_sql";
    $count_stmt = mysqli_prepare($conn, $count_sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    }
    mysqli_stmt_execute($count_stmt);
    $count_res = mysqli_stmt_get_result($count_stmt);
    $total_staff = mysqli_fetch_assoc($count_res)['total'] ?? 0;
    mysqli_stmt_close($count_stmt);

    $total_pages = ceil($total_staff / $limit);

    // List Query
    $list_sql = "SELECT * FROM staff $where_sql ORDER BY id DESC LIMIT ? OFFSET ?";
    $params_list = $params;
    $types_list = $types . 'ii';
    $params_list[] = $limit;
    $params_list[] = $offset;

    $list_stmt = mysqli_prepare($conn, $list_sql);
    mysqli_stmt_bind_param($list_stmt, $types_list, ...$params_list);
    mysqli_stmt_execute($list_stmt);
    $list_res = mysqli_stmt_get_result($list_stmt);

    $staff_list = [];
    while ($row = mysqli_fetch_assoc($list_res)) {
        $staff_list[] = $row;
    }
    mysqli_stmt_close($list_stmt);
    ?>

    <!-- Filter Card -->
    <div class="filter-card animate-in">
        <form method="GET" action="staff.php" class="filter-form">
            <div class="search-input-wrap">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" class="form-control" placeholder="Search by name, code, designation, email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="filter-group">
                <select name="department" class="form-control">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo $filter_dep === $d ? 'selected' : ''; ?>>
                            <?php echo $d; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $filter_st === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="on_leave" <?php echo $filter_st === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                    <option value="resigned" <?php echo $filter_st === 'resigned' ? 'selected' : ''; ?>>Resigned</option>
                    <option value="terminated" <?php echo $filter_st === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                </select>
            </div>

            <button type="submit" class="btn-admin btn-admin--sm btn-admin--secondary">Filter</button>
            <?php if ($search !== '' || $filter_dep !== '' || $filter_st !== ''): ?>
                <a href="staff.php" class="btn-admin btn-admin--sm btn-admin--ghost">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Staff Table Panel -->
    <div class="panel animate-in">
        <div class="panel__header">
            <h3 class="panel__title">Staff Directory (<?php echo number_format($total_staff); ?>)</h3>
            <a href="staff.php?action=add" class="panel__action">+ Add Staff</a>
        </div>

        <?php if (count($staff_list) > 0): ?>
            <div class="table-responsive">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Code</th>
                            <th>Designation</th>
                            <th>Department</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $colors = ['#3b82f6','#22d3a7','#a78bfa','#fbbf24','#f472b6'];
                        foreach ($staff_list as $i => $st): 
                            $initials = strtoupper(substr($st['first_name'],0,1) . substr($st['last_name'],0,1));
                            $color = $colors[$i % count($colors)];
                        ?>
                        <tr>
                            <td>
                                <div class="student-cell">
                                    <div class="student-avatar" style="background:<?php echo $color; ?>"><?php echo $initials; ?></div>
                                    <div>
                                        <div class="student-name"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></div>
                                        <small style="color:var(--admin-text-muted);font-size:0.75rem;"><?php echo htmlspecialchars($st['email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td style="font-weight:600;color:var(--admin-blue);"><?php echo htmlspecialchars($st['employee_code']); ?></td>
                            <td style="font-weight:500;color:var(--admin-heading);"><?php echo htmlspecialchars($st['designation']); ?></td>
                            <td style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($st['department']); ?></td>
                            <td style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($st['phone']); ?></td>
                            <td>
                                <span class="badge badge--<?php echo $st['status'] === 'active' ? 'active' : ($st['status'] === 'on_leave' ? 'pending' : 'danger'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $st['status'])); ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div class="table-actions" style="justify-content:flex-end;">
                                    <a href="staff.php?action=view&id=<?php echo $st['id']; ?>" class="action-btn action-btn--view" title="View Details">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <a href="staff.php?action=edit&id=<?php echo $st['id']; ?>" class="action-btn action-btn--edit" title="Edit Staff">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
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
                <div class="pagination-wrap">
                    <div class="pagination-info">
                        Showing <?php echo $offset + 1; ?>–<?php echo min($total_staff, $offset + $limit); ?> of <?php echo $total_staff; ?> staff members
                    </div>
                    <div class="pagination-nav">
                        <?php
                        $query_params = $_GET;
                        if ($page > 1):
                            $query_params['page'] = $page - 1;
                        ?>
                            <a href="staff.php?<?php echo http_build_query($query_params); ?>" class="page-btn">← Prev</a>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $total_pages; $p++): 
                            $query_params['page'] = $p;
                        ?>
                            <a href="staff.php?<?php echo http_build_query($query_params); ?>" 
                               class="page-btn <?php echo $p === $page ? 'page-btn--active' : ''; ?>">
                               <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>

                        <?php
                        if ($page < $total_pages):
                            $query_params['page'] = $page + 1;
                        ?>
                            <a href="staff.php?<?php echo http_build_query($query_params); ?>" class="page-btn">Next →</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <p>No staff records match your criteria.</p>
                <?php if ($search !== '' || $filter_dep !== '' || $filter_st !== ''): ?>
                    <a href="staff.php" class="btn-admin btn-admin--sm btn-admin--ghost" style="margin-top:12px;">Reset Filters</a>
                <?php else: ?>
                    <a href="staff.php?action=add" class="btn-admin btn-admin--sm btn-admin--primary" style="margin-top:12px;">Add First Staff Member</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
