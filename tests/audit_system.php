<?php
/**
 * Comprehensive System Audit & Verification Script
 * Brightway School Management System — All 3 Roles + Public Frontend
 */
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', '1');

echo "====================================================================\n";
echo " BRIGHTWAY SCHOOL MANAGEMENT SYSTEM — FULL END-TO-END AUDIT\n";
echo "====================================================================\n\n";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../admin/auth.php';

if (!$conn) {
    die("FATAL: Could not connect to database!\n");
}
echo "[✓] Database Connection: OK (school_management_db)\n\n";

$auditPassed = 0;
$auditFailed = 0;
$bugsFound = [];

function recordTest($name, $passed, $details = '') {
    global $auditPassed, $auditFailed, $bugsFound;
    if ($passed) {
        echo "  [✓ PASS] $name\n";
        $auditPassed++;
    } else {
        echo "  [✗ FAIL] $name " . ($details ? "--> $details" : "") . "\n";
        $auditFailed++;
        $bugsFound[] = "$name: $details";
    }
}

// ──────────────────────────────────────────────────────────────────
// 1. COMPREHENSIVE PHP SYNTAX LINT FOR ENTIRE WORKSPACE
// ──────────────────────────────────────────────────────────────────
echo "--- 1. PHP Syntax Check Across Entire Project ---\n";
$allPhpFiles = [];
$dirIter = new RecursiveDirectoryIterator(__DIR__ . '/..', RecursiveDirectoryIterator::SKIP_DOTS);
$iter = new RecursiveIteratorIterator($dirIter);
foreach ($iter as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getRealPath();
        // Skip scratch or vendor if any
        if (strpos($path, '.git') === false) {
            $allPhpFiles[] = $path;
        }
    }
}

$syntaxFailures = 0;
foreach ($allPhpFiles as $phpFile) {
    $cmd = 'C:\xampp\php\php.exe -l ' . escapeshellarg($phpFile);
    $out = shell_exec($cmd);
    if (strpos($out, 'No syntax errors detected') === false) {
        echo "  [✗] Syntax error in " . str_replace(__DIR__ . '/..\\', '', $phpFile) . ":\n      " . trim($out) . "\n";
        $syntaxFailures++;
    }
}
recordTest("Zero syntax errors across all " . count($allPhpFiles) . " PHP files", $syntaxFailures === 0, "$syntaxFailures syntax error(s) found");
echo "\n";

// ──────────────────────────────────────────────────────────────────
// 2. DATABASE INTEGRITY & SCHEMA AUDIT
// ──────────────────────────────────────────────────────────────────
echo "--- 2. Database Integrity & Schema Audit ---\n";
$requiredTables = [
    'admins', 'academic_sessions', 'classes', 'sections', 'subjects',
    'class_subjects', 'staff', 'students', 'attendance', 'exams',
    'exam_schedules', 'marks', 'fee_structures', 'student_fee_assignments',
    'fee_payments', 'financial_transactions', 'staff_salaries', 'holidays',
    'announcements', 'website_content', 'website_images', 'gallery_images',
    'contact_inquiries', 'admissions_inquiries', 'school_settings', 'activity_logs'
];
foreach ($requiredTables as $tbl) {
    $chk = mysqli_query($conn, "SHOW TABLES LIKE '{$tbl}'");
    recordTest("Table exists: {$tbl}", mysqli_num_rows($chk) > 0);
}

// Check Referential Consistency
echo "\n--- Referential Integrity Checks ---\n";
// Orphan students (invalid class)
$orphStudClass = mysqli_query($conn, "SELECT COUNT(*) AS c FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.class_id > 0 AND c.id IS NULL");
$orphCount = (int)(mysqli_fetch_assoc($orphStudClass)['c'] ?? 0);
recordTest("Student records have valid class references", $orphCount === 0, "Found $orphCount students with invalid class_id");

// Orphan fee assignments
$orphFee = mysqli_query($conn, "SELECT COUNT(*) AS c FROM student_fee_assignments sfa LEFT JOIN students s ON sfa.student_id = s.id WHERE s.id IS NULL");
$orphFeeCount = (int)(mysqli_fetch_assoc($orphFee)['c'] ?? 0);
recordTest("Fee assignments have valid student references", $orphFeeCount === 0, "Found $orphFeeCount orphan fee assignments");

// Orphan fee payments
$orphPay = mysqli_query($conn, "SELECT COUNT(*) AS c FROM fee_payments fp LEFT JOIN students s ON fp.student_id = s.id WHERE s.id IS NULL");
$orphPayCount = (int)(mysqli_fetch_assoc($orphPay)['c'] ?? 0);
recordTest("Fee payments have valid student references", $orphPayCount === 0, "Found $orphPayCount orphan fee payments");

// Duplicate student admission_no check
$dupAdmNo = mysqli_query($conn, "SELECT admission_no, COUNT(*) as c FROM students GROUP BY admission_no HAVING c > 1");
$dupCount = mysqli_num_rows($dupAdmNo);
recordTest("All student admission numbers are unique", $dupCount === 0, "Found $dupCount duplicate admission numbers");

// Check converted admissions point to valid students
$invConv = mysqli_query($conn, "SELECT COUNT(*) AS c FROM admissions_inquiries a LEFT JOIN students s ON a.converted_student_id = s.id WHERE a.converted_student_id IS NOT NULL AND a.converted_student_id > 0 AND s.id IS NULL");
$invConvCount = (int)(mysqli_fetch_assoc($invConv)['c'] ?? 0);
recordTest("Converted admission inquiries link to existing student records", $invConvCount === 0, "Found $invConvCount broken student links");
echo "\n";

// ──────────────────────────────────────────────────────────────────
// 3. USER ACCOUNTS & ROLE VERIFICATION
// ──────────────────────────────────────────────────────────────────
echo "--- 3. Role Accounts & Authentication Readiness ---\n";
$roles = ['admin' => 'admin', 'accountant' => 'accountant', 'receptionist' => 'receptionist'];
foreach ($roles as $roleKey => $roleName) {
    $uq = mysqli_query($conn, "SELECT id, username, full_name, status, password_hash FROM admins WHERE role = '{$roleKey}' AND status = 'active' LIMIT 1");
    $uRow = mysqli_fetch_assoc($uq);
    recordTest("Active {$roleName} user exists", !empty($uRow), "No active {$roleName} found in admins");
    if ($uRow) {
        recordTest("{$roleName} password is valid bcrypt hash", strpos($uRow['password_hash'], '$2y$') === 0 || strpos($uRow['password_hash'], '$2a$') === 0);
    }
}
echo "\n";

// ──────────────────────────────────────────────────────────────────
// 4. HTTP REQUEST UTILITY & END-TO-END SECURITY BOUNDARIES
// ──────────────────────────────────────────────────────────────────
echo "--- 4. HTTP Role-Based Access Control (RBAC) & Security ---\n";

function curlGet($url, $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    }
    $output = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return ['body' => $output, 'code' => $info['http_code'], 'redirect' => $info['redirect_url']];
}

function curlPostLogin($url, $username, $password, $cookieFile) {
    // 1. GET page for CSRF token
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    $page = curl_exec($ch);
    curl_close($ch);

    $csrf = '';
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $page, $m)) {
        $csrf = $m[1];
    }

    // 2. POST login
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'username'   => $username,
        'password'   => $password,
        'csrf_token' => $csrf
    ]));
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    $output = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return ['body' => $output, 'code' => $info['http_code'], 'redirect' => $info['redirect_url']];
}

$baseUrl = 'http://localhost/school-management-system';

// Test 4.1: Unauthenticated Guest access to protected areas
$guestAdmin = curlGet($baseUrl . '/admin/dashboard.php');
recordTest("Guest blocked from Admin dashboard (redirects to login)", $guestAdmin['code'] === 302 && strpos($guestAdmin['redirect'], 'login.php') !== false);

$guestAcc = curlGet($baseUrl . '/accountant/dashboard.php');
recordTest("Guest blocked from Accountant dashboard (redirects to login)", $guestAcc['code'] === 302 && strpos($guestAcc['redirect'], 'login.php') !== false);

$guestRec = curlGet($baseUrl . '/receptionist/dashboard.php');
recordTest("Guest blocked from Receptionist dashboard (redirects to login)", $guestRec['code'] === 302 && strpos($guestRec['redirect'], 'login.php') !== false);

// Test 4.2: Direct POST without CSRF token is blocked
$noCsrfPost = curl_init($baseUrl . '/admin/users.php');
curl_setopt($noCsrfPost, CURLOPT_RETURNTRANSFER, true);
curl_setopt($noCsrfPost, CURLOPT_POST, true);
curl_setopt($noCsrfPost, CURLOPT_POSTFIELDS, ['action' => 'create', 'username' => 'hackertest']);
$noCsrfRes = curl_exec($noCsrfPost);
$noCsrfInfo = curl_getinfo($noCsrfPost);
curl_close($noCsrfPost);
recordTest("Unauthenticated POST to admin/users.php is blocked", in_array($noCsrfInfo['http_code'], [302, 403]));

// Test 4.3: Session Login & Role Isolation Matrix
$adminCookie = sys_get_temp_dir() . '/audit_admin_cookie.txt';
$accCookie   = sys_get_temp_dir() . '/audit_acc_cookie.txt';
$recCookie   = sys_get_temp_dir() . '/audit_rec_cookie.txt';
if (file_exists($adminCookie)) @unlink($adminCookie);
if (file_exists($accCookie)) @unlink($accCookie);
if (file_exists($recCookie)) @unlink($recCookie);

// Login Admin
$adminLog = curlPostLogin($baseUrl . '/admin/login.php', 'admin', 'admin123', $adminCookie);
recordTest("Admin login authentication", $adminLog['code'] === 302 && strpos($adminLog['redirect'], 'dashboard.php') !== false);

// Login Accountant
$accLog = curlPostLogin($baseUrl . '/admin/login.php', 'accountant', 'Accountant@123', $accCookie);
recordTest("Accountant login authentication", $accLog['code'] === 302 && strpos($accLog['redirect'], 'accountant/dashboard.php') !== false);

// Login Receptionist
$recLog = curlPostLogin($baseUrl . '/admin/login.php', 'receptionist', 'Receptionist@123', $recCookie);
recordTest("Receptionist login authentication", $recLog['code'] === 302 && strpos($recLog['redirect'], 'receptionist/dashboard.php') !== false);

// Verify Receptionist CANNOT access Accountant fee collection
$recToFee = curlGet($baseUrl . '/accountant/fee_payments.php', $recCookie);
recordTest("Receptionist forbidden from Accountant fee collection (HTTP 403)", $recToFee['code'] === 403);

// Verify Receptionist CANNOT access Accountant salaries
$recToSal = curlGet($baseUrl . '/accountant/salaries.php', $recCookie);
recordTest("Receptionist forbidden from Accountant salaries (HTTP 403)", $recToSal['code'] === 403);

// Verify Receptionist CANNOT access Admin settings
$recToSet = curlGet($baseUrl . '/admin/settings.php', $recCookie);
recordTest("Receptionist forbidden from Admin settings (Redirect 302)", $recToSet['code'] === 302 && strpos($recToSet['redirect'], 'receptionist/dashboard.php') !== false);

// Verify Receptionist CANNOT access Admin user management
$recToUsr = curlGet($baseUrl . '/admin/users.php', $recCookie);
recordTest("Receptionist forbidden from Admin user management (Redirect 302)", $recToUsr['code'] === 302 && strpos($recToUsr['redirect'], 'receptionist/dashboard.php') !== false);

// Verify Accountant CANNOT access Admin settings
$accToSet = curlGet($baseUrl . '/admin/settings.php', $accCookie);
recordTest("Accountant forbidden from Admin settings (Redirect 302)", $accToSet['code'] === 302 && strpos($accToSet['redirect'], 'accountant/dashboard.php') !== false);

// Verify Accountant CANNOT access Admin exams
$accToExm = curlGet($baseUrl . '/admin/exams.php', $accCookie);
recordTest("Accountant forbidden from Admin exams (Redirect 302)", $accToExm['code'] === 302 && strpos($accToExm['redirect'], 'accountant/dashboard.php') !== false);

// Verify Admin CAN access all 3 portals (Master oversight)
$admToAdm = curlGet($baseUrl . '/admin/dashboard.php', $adminCookie);
recordTest("Admin has access to Admin Dashboard (HTTP 200)", $admToAdm['code'] === 200);

$admToAcc = curlGet($baseUrl . '/accountant/dashboard.php', $adminCookie);
recordTest("Admin has oversight of Accountant Dashboard (HTTP 200)", $admToAcc['code'] === 200);

$admToRec = curlGet($baseUrl . '/receptionist/dashboard.php', $adminCookie);
recordTest("Admin has oversight of Receptionist Dashboard (HTTP 200)", $admToRec['code'] === 200);

// Test Logout invalidates session
$logoutRes = curlGet($baseUrl . '/admin/logout.php', $adminCookie);
$postLogout = curlGet($baseUrl . '/admin/dashboard.php', $adminCookie);
recordTest("Admin logout successfully invalidates session", $postLogout['code'] === 302 && strpos($postLogout['redirect'], 'login.php') !== false);

// Clean up cookies
if (file_exists($adminCookie)) @unlink($adminCookie);
if (file_exists($accCookie)) @unlink($accCookie);
if (file_exists($recCookie)) @unlink($recCookie);

echo "\n";

// ──────────────────────────────────────────────────────────────────
// 5. PUBLIC FRONTEND INTEGRITY
// ──────────────────────────────────────────────────────────────────
echo "--- 5. Public Website Frontend Verification ---\n";
$publicPages = [
    'Home'       => '/index.php',
    'About'      => '/about.php',
    'Academics'  => '/academics.php',
    'Admissions' => '/admissions.php',
    'Contact'    => '/contact.php',
    'Gallery'    => '/gallery.php',
];
foreach ($publicPages as $pName => $pUri) {
    $pRes = curlGet($baseUrl . $pUri);
    recordTest("Public page '{$pName}' returns HTTP 200", $pRes['code'] === 200, "Received HTTP {$pRes['code']}");
    // Check for fatal errors or database exception outputs in public page
    $hasDbErr = (stripos($pRes['body'], 'Fatal error') !== false || stripos($pRes['body'], 'mysqli_sql_exception') !== false);
    recordTest("Public page '{$pName}' has zero unhandled PHP/DB exceptions", !$hasDbErr);
}

echo "\n";
echo "====================================================================\n";
echo " AUDIT SUMMARY\n";
echo " Passed: $auditPassed\n";
echo " Failed: $auditFailed\n";
echo "====================================================================\n";

if ($auditFailed === 0) {
    echo "🎉 AUDIT COMPLETE: 100% OF SYSTEM TESTS & SECURITY POLICIES VERIFIED!\n";
} else {
    echo "❌ AUDIT COMPLETED WITH ISSUES:\n";
    foreach ($bugsFound as $b) {
        echo "   - $b\n";
    }
    exit(1);
}
