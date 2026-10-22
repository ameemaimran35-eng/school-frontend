<?php
/**
 * admin/users.php
 * Staff User Accounts & Role Permissions Management
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'User Accounts Management';
$current_page = 'users';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$action = $_GET['action'] ?? 'list';
$current_admin_id = getAdminId();

// ─── POST HANDLERS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
        header('Location: users.php');
        exit();
    }

    $op = $_POST['op'] ?? '';

    // 1. CREATE USER ACCOUNT
    if ($op === 'create') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $full_name= trim($_POST['full_name'] ?? '');
        $role     = trim($_POST['role'] ?? 'admin');
        $password = $_POST['password'] ?? '';

        if ($username === '') $errors[] = 'Username is required.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if ($full_name === '') $errors[] = 'Full name is required.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if (!in_array($role, ['admin', 'accountant', 'receptionist'], true)) $role = 'admin';

        // Check uniqueness of username and email
        if (empty($errors)) {
            $chk = mysqli_prepare($conn, "SELECT id FROM admins WHERE username = ? OR email = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'ss', $username, $email);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) {
                $errors[] = 'Username or Email is already registered.';
            }
            mysqli_stmt_close($chk);
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO admins (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
            mysqli_stmt_bind_param($stmt, 'sssss', $username, $email, $hash, $full_name, $role);
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                logActivity('user_created', 'admins', $new_id, "Created user '{$username}' with role '{$role}'");
                $_SESSION['flash_success'] = "User account '{$username}' created successfully.";
                header('Location: users.php');
                exit();
            } else {
                $errors[] = 'Failed to create user: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // 2. TOGGLE ACTIVE / INACTIVE STATUS
    if ($op === 'toggle_status') {
        $target_id = (int)($_POST['id'] ?? 0);
        $new_status = $_POST['status'] === 'active' ? 'active' : 'inactive';

        // Prevent self-deactivation
        if ($target_id === $current_admin_id && $new_status === 'inactive') {
            $_SESSION['flash_error'] = 'You cannot deactivate your own logged-in account.';
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE admins SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $new_status, $target_id);
            if (mysqli_stmt_execute($stmt)) {
                logActivity('user_status_changed', 'admins', $target_id, "Changed user #{$target_id} status to {$new_status}");
                $_SESSION['flash_success'] = "Account status updated to " . ucfirst($new_status) . ".";
            }
            mysqli_stmt_close($stmt);
        }
        header('Location: users.php');
        exit();
    }

    // 3. RESET PASSWORD
    if ($op === 'reset_password') {
        $target_id = (int)($_POST['id'] ?? 0);
        $new_pass = $_POST['new_password'] ?? '';

        if (strlen($new_pass) < 6) {
            $_SESSION['flash_error'] = 'New password must be at least 6 characters.';
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE admins SET password_hash = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $hash, $target_id);
            if (mysqli_stmt_execute($stmt)) {
                logActivity('password_reset', 'admins', $target_id, "Reset password for user #{$target_id}");
                $_SESSION['flash_success'] = 'Password successfully updated.';
            } else {
                $_SESSION['flash_error'] = 'Error updating password: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        header('Location: users.php');
        exit();
    }
}

// Fetch users
$users = [];
$res = mysqli_query($conn, "SELECT id, username, email, full_name, role, status, last_login, created_at FROM admins ORDER BY id ASC");
while ($r = mysqli_fetch_assoc($res)) {
    $users[] = $r;
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">User Accounts & Roles</h1>
        <p class="page-header__desc">Manage authorized system accounts for School Admin, Accountant, and Admission Officer roles.</p>
    </div>
    <div class="page-header__actions">
        <a href="users.php?action=add" class="btn-admin btn-admin--primary">+ Create User Account</a>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert--success"><div class="alert__content"><span><?php echo htmlspecialchars($success_msg); ?></span></div></div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert alert--danger"><div class="alert__content"><span><?php echo htmlspecialchars($error_msg); ?></span></div></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert--danger">
        <ul><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($action === 'add'): ?>
    <div class="card animate-in" style="padding:28px;max-width:680px;margin-bottom:28px;">
        <h3 style="font-size:1.15rem;font-weight:700;color:var(--admin-heading);margin-bottom:18px;">Create Staff Account</h3>
        <form method="POST" action="users.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="op" value="create">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" placeholder="e.g. accountant1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. Tariq Mehmood" required>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. staff@school.edu" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Assigned Role *</label>
                    <select name="role" class="form-control" required>
                        <option value="admin">School Admin / Principal</option>
                        <option value="accountant">Accountant / Fee Manager</option>
                        <option value="receptionist">Receptionist / Admission Officer</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Initial Password * (minimum 6 characters)</label>
                <input type="password" name="password" class="form-control" placeholder="Enter secure password" required minlength="6">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:10px;">
                <a href="users.php" class="btn-admin btn-admin--ghost">Cancel</a>
                <button type="submit" class="btn-admin btn-admin--primary">Create Account</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Users Table Panel -->
<div class="panel animate-in">
    <div class="panel__header">
        <h3 class="panel__title">Authorized User Accounts (<?php echo count($users); ?>)</h3>
        <span style="color:var(--admin-text-muted);font-size:0.85rem;">Role-based access control</span>
    </div>

    <div class="table-responsive">
        <table class="recent-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Assigned Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): 
                    $role_badge = ($u['role'] === 'admin') ? 'accent' : (($u['role'] === 'accountant') ? 'amber' : 'blue');
                    $is_self = ($u['id'] === $current_admin_id);
                ?>
                <tr>
                    <td>
                        <div style="font-weight:700;color:var(--admin-heading);"><?php echo htmlspecialchars($u['full_name']); ?></div>
                        <small style="color:var(--admin-text-muted);">@<?php echo htmlspecialchars($u['username']); ?><?php if ($is_self) echo ' <strong style="color:var(--admin-accent);">(You)</strong>'; ?></small>
                    </td>
                    <td style="font-size:0.85rem;color:var(--admin-text-muted);"><?php echo htmlspecialchars($u['email']); ?></td>
                    <td>
                        <span class="badge badge--<?php echo $u['status'] === 'active' ? 'active' : 'danger'; ?>" style="text-transform:capitalize;">
                            <?php echo htmlspecialchars($u['role']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge--<?php echo $u['status'] === 'active' ? 'active' : 'danger'; ?>">
                            <?php echo ucfirst($u['status']); ?>
                        </span>
                    </td>
                    <td style="font-size:0.85rem;color:var(--admin-text-muted);">
                        <?php echo $u['last_login'] ? date('M j, Y H:i', strtotime($u['last_login'])) : 'Never'; ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex;gap:8px;align-items:center;">
                            <!-- Toggle status -->
                            <?php if (!$is_self): ?>
                                <form method="POST" action="users.php" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="op" value="toggle_status">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="status" value="<?php echo $u['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                    <button type="submit" class="btn-admin btn-admin--sm <?php echo $u['status'] === 'active' ? 'btn-admin--secondary' : 'btn-admin--primary'; ?>" style="font-size:0.75rem;padding:4px 8px;">
                                        <?php echo $u['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Reset password button / form -->
                            <button type="button" class="btn-admin btn-admin--sm btn-admin--ghost" style="font-size:0.75rem;padding:4px 8px;" onclick="promptResetPassword(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')">
                                Reset Password
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Password Reset Hidden Form -->
<form id="resetPassForm" method="POST" action="users.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <input type="hidden" name="op" value="reset_password">
    <input type="hidden" name="id" id="reset_user_id">
    <input type="hidden" name="new_password" id="reset_new_password">
</form>

<script>
function promptResetPassword(userId, username) {
    const newPass = prompt("Enter new password for @" + username + " (minimum 6 characters):");
    if (newPass && newPass.length >= 6) {
        document.getElementById('reset_user_id').value = userId;
        document.getElementById('reset_new_password').value = newPass;
        document.getElementById('resetPassForm').submit();
    } else if (newPass !== null) {
        alert("Password must be at least 6 characters.");
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
