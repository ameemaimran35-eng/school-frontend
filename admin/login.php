<?php
// admin/login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// CSRF token generation (same as auth helper)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if already logged in
if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $login_error = 'Invalid CSRF token.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username && $password) {
            $stmt = mysqli_prepare($conn, "SELECT id, password_hash, full_name, role, status FROM admins WHERE username = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $admin_id, $hash, $full_name, $role, $status);
            if (mysqli_stmt_fetch($stmt) && password_verify($password, $hash)) {
                mysqli_stmt_close($stmt);

                if ($status !== 'active') {
                    $login_error = 'Account is inactive. Please contact administration.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $admin_id;
                    $_SESSION['admin_username'] = $username;
                    $_SESSION['admin_name'] = !empty($full_name) ? $full_name : $username;
                    $_SESSION['admin_role'] = !empty($role) ? $role : 'admin';

                    // Update last login
                    $upd = mysqli_prepare($conn, "UPDATE admins SET last_login = NOW() WHERE id = ?");
                    mysqli_stmt_bind_param($upd, 'i', $admin_id);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);

                    require_once __DIR__ . '/auth.php';
                    logActivity('login', 'admins', $admin_id, "User {$username} logged in successfully");

                    // Route to role-appropriate portal
                    if ($role === 'accountant') {
                        header('Location: ../accountant/dashboard.php');
                    } elseif ($role === 'receptionist') {
                        header('Location: ../receptionist/dashboard.php');
                    } else {
                        header('Location: dashboard.php');
                    }
                    exit();
                }
            } else {
                $login_error = 'Invalid username or password.';
                mysqli_stmt_close($stmt);
            }
        } else {
            $login_error = 'Please fill both fields.';
        }
    }
}
$selected_role = strtolower(trim($_GET['role'] ?? ''));
$role_info = [
    'admin' => [
        'page_title' => 'School Admin Login – Brightway School',
        'subtitle'   => 'School Admin Portal',
        'desc'       => 'Principal & System Administration',
        'badge'      => 'Admin'
    ],
    'accountant' => [
        'page_title' => 'Accountant Login – Brightway School',
        'subtitle'   => 'Accountant / Fee Manager Portal',
        'desc'       => 'Fee Collection, Dues & Financial Accounts',
        'badge'      => 'Finance'
    ],
    'receptionist' => [
        'page_title' => 'Receptionist Login – Brightway School',
        'subtitle'   => 'Receptionist / Admission Officer Portal',
        'desc'       => 'Inquiries, Admissions & Visitor Desk',
        'badge'      => 'Front Desk'
    ]
];
$portal = $role_info[$selected_role] ?? [
    'page_title' => 'Admin Login – Brightway School',
    'subtitle'   => 'Admin Portal Authentication',
    'desc'       => 'Authorized Personnel Authentication',
    'badge'      => 'Portal'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($portal['page_title']); ?></title>
    <link rel="icon" href="../assets/images/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-auth-body">
    <div class="admin-auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo-badge">B</div>
                <h1 class="auth-title">Brightway School</h1>
                <p class="auth-subtitle"><?php echo htmlspecialchars($portal['subtitle']); ?></p>
                <?php if (!empty($selected_role) && isset($role_info[$selected_role])): ?>
                    <div style="display:inline-block;margin-top:8px;padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;background:rgba(255,255,255,0.06);color:var(--admin-accent,#38bdf8);border:1px solid rgba(255,255,255,0.12);">
                        <?php echo htmlspecialchars($portal['desc']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($login_error)): ?>
                <div class="auth-alert-error" role="alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?php echo htmlspecialchars($login_error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="auth-form-group">
                    <label for="username" class="auth-label">Username</label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <input type="text" id="username" name="username" class="auth-input" placeholder="Enter username" required autocomplete="username" autofocus>
                    </div>
                </div>

                <div class="auth-form-group">
                    <label for="password" class="auth-label">Password</label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <input type="password" id="password" name="password" class="auth-input" placeholder="Enter password" required autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="btn-auth-submit">
                    <span>Sign In</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>

            <div class="auth-footer">
                <a href="../index.php" class="auth-back-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    <span>Return to Website</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
