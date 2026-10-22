<?php
// admin/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

/**
 * Fetch a single school setting from the database.
 * Returns the value as a string, or $default if the key is not found.
 *
 * @param  string $key     The setting_key to look up.
 * @param  string $default Value returned when the key does not exist.
 * @return string
 */
function getSetting(string $key, string $default = ''): string {
    global $conn;
    if (!$conn) return $default;
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    $stmt = mysqli_prepare($conn, "SELECT setting_value FROM school_settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) return $default;
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    $cache[$key] = $row ? (string)$row['setting_value'] : $default;
    return $cache[$key];
}

/**
 * Convenience wrapper – returns the currency symbol or a fallback.
 */
function getCurrencySymbol(): string {
    return getSetting('currency_symbol', 'Rs');
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
generateCsrfToken();

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Require that the user is logged in (any valid role).
 * Redirects to the login page if not authenticated.
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        // Determine login path relative to caller
        $self = $_SERVER['PHP_SELF'] ?? '';
        if (str_contains($self, '/accountant/') || str_contains($self, '/receptionist/')) {
            $loginPage = '../admin/login.php';
        } else {
            $loginPage = 'login.php';
        }
        header('Location: ' . $loginPage);
        exit();
    }
}

/**
 * Check if the current user has one of the allowed roles.
 *
 * @param  string|string[] $allowedRoles  e.g. 'admin' or ['admin','accountant']
 * @return bool
 */
function hasRole($allowedRoles): bool {
    $role = getAdminRole();
    if (is_string($allowedRoles)) {
        return $role === $allowedRoles;
    }
    return in_array($role, $allowedRoles, true);
}

/**
 * Require that the logged-in user holds one of the specified roles.
 * Returns HTTP 403 and stops execution if not authorised.
 *
 * @param  string|string[] $allowedRoles
 */
function requireRole($allowedRoles): void {
    requireAdminLogin();
    if (!hasRole($allowedRoles)) {
        http_response_code(403);
        $role = getAdminRole();
        if ($role === 'accountant') {
            $dashUrl = '../accountant/dashboard.php';
        } elseif ($role === 'receptionist') {
            $dashUrl = '../receptionist/dashboard.php';
        } else {
            $dashUrl = 'dashboard.php';
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Access Denied</title>'
            . '<style>body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;justify-content:center;align-items:center;min-height:100vh;}'
            . '.box{background:#1e293b;padding:40px;border-radius:12px;text-align:center;max-width:420px;}'
            . 'h2{color:#f87171;margin-top:0;}a{color:#60a5fa;}</style></head><body>'
            . '<div class="box"><h2>&#128274; Access Denied</h2>'
            . '<p>You do not have permission to access this page.</p>'
            . '<a href="' . htmlspecialchars($dashUrl) . '">Return to Dashboard</a></div></body></html>';
        exit();
    }
}

/**
 * Convenience: check if current user is an admin.
 */
function isAdmin(): bool {
    return getAdminRole() === 'admin';
}

/**
 * Convenience: check if current user is an accountant.
 */
function isAccountant(): bool {
    return getAdminRole() === 'accountant';
}

/**
 * Convenience: check if current user is a receptionist.
 */
function isReceptionist(): bool {
    return getAdminRole() === 'receptionist';
}

/**
 * Protect admin-only pages from accountant access.
 * Redirects the accountant to their dashboard with an error.
 */
function requireAdminOnlyAccess(): void {
    requireAdminLogin();
    if (!hasRole('admin')) {
        http_response_code(403);
        $_SESSION['flash_error'] = 'This section is restricted to Administrators only.';
        $role = getAdminRole();
        if ($role === 'receptionist') {
            header('Location: ../receptionist/dashboard.php');
        } else {
            header('Location: ../accountant/dashboard.php');
        }
        exit();
    }
}

function verifyCsrfToken($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function getAdminName() {
    $name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
    return htmlspecialchars($name);
}

function getAdminInitial() {
    $name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
    $name = trim($name);
    return strtoupper(substr($name !== '' ? $name : 'A', 0, 1));
}

function getAdminRole() {
    return $_SESSION['admin_role'] ?? 'admin';
}

function getAdminId() {
    return (int)($_SESSION['admin_id'] ?? 0);
}

function logActivity($action, $entity_type = '', $entity_id = null, $details = '') {
    global $conn;
    if (!$conn) return false;
    $user_id = getAdminId();
    $user_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'System';
    $user_role = getAdminRole();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $stmt = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, user_name, user_role, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $e_id = $entity_id !== null ? (int)$entity_id : null;
        mysqli_stmt_bind_param($stmt, 'issssiss', $user_id, $user_name, $user_role, $action, $entity_type, $e_id, $details, $ip);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return true;
    }
    return false;
}
?>
