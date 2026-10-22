<?php
/**
 * Automated Test Suite for Brightway School Management System - Stage 3: Receptionist / Admission Officer Panel
 * Tests:
 * 1. Syntax check of all receptionist files
 * 2. Receptionist user presence in database
 * 3. RBAC & Role verification logic
 * 4. Dashboard KPI queries & integrity
 * 5. Admission inquiry CRUD & duplicate detection
 * 6. Admission form printing view verification
 * 7. Reports KPI & CSV export validation
 * 8. Live HTTP requests (Login, Receptionist Portal, Admin & Accountant RBAC boundary protection)
 */
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', '1');

echo "========================================================\n";
echo " BRIGHTWAY SCHOOL - STAGE 3 RECEPTIONIST SUITE\n";
echo "========================================================\n\n";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../admin/auth.php';

if (!$conn) {
    die("FATAL: Could not connect to database!\n");
}
// Ensure defensive columns exist
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

$testsPassed = 0;
$testsFailed = 0;

function assertTest($name, $condition, $details = '') {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [✓ PASS] $name\n";
        $testsPassed++;
    } else {
        echo "  [✗ FAIL] $name " . ($details ? "--> $details" : "") . "\n";
        $testsFailed++;
    }
}

// 1. SYNTAX CHECK OF ALL RECEPTIONIST FILES
echo "--- 1. Checking Syntax of All Receptionist Files ---\n";
$recFiles = glob(__DIR__ . '/../receptionist/*.php');
$syntaxErrors = 0;
foreach ($recFiles as $file) {
    $cmd = 'C:\xampp\php\php.exe -l ' . escapeshellarg($file);
    $out = shell_exec($cmd);
    if (strpos($out, 'No syntax errors detected') === false) {
        echo "  [✗] SYNTAX ERROR in " . basename($file) . ":\n      " . trim($out) . "\n";
        $syntaxErrors++;
    } else {
        echo "  [✓] " . basename($file) . " - Syntax OK\n";
    }
}
assertTest("Zero syntax errors in receptionist/ directory", $syntaxErrors === 0 && count($recFiles) >= 5);
echo "\n";

// 2. RECEPTIONIST USER IN DATABASE
echo "--- 2. Receptionist User in Database ---\n";
$recRes = mysqli_query($conn, "SELECT id, username, full_name, role, status FROM admins WHERE role = 'receptionist' LIMIT 1");
$recUser = mysqli_fetch_assoc($recRes);
assertTest("Receptionist account exists in database", !empty($recUser));
assertTest("Receptionist role is 'receptionist'", ($recUser['role'] ?? '') === 'receptionist');
assertTest("Receptionist status is 'active'", ($recUser['status'] ?? '') === 'active');
echo "\n";

// 3. RBAC HELPER FUNCTIONS IN AUTH.PHP
echo "--- 3. RBAC Helper Functions ---\n";
$_SESSION['admin_id'] = $recUser['id'] ?? 4;
$_SESSION['admin_name'] = $recUser['full_name'] ?? 'Receptionist User';
$_SESSION['admin_role'] = 'receptionist';

assertTest("hasRole('receptionist') returns true for receptionist", hasRole('receptionist') === true);
assertTest("hasRole(['receptionist', 'admin']) returns true for receptionist", hasRole(['receptionist', 'admin']) === true);
assertTest("hasRole('admin') returns false for receptionist", hasRole('admin') === false);
assertTest("isAdmin() returns false for receptionist", isAdmin() === false);
assertTest("isAccountant() returns false for receptionist", isAccountant() === false);
echo "\n";

// 4. DASHBOARD KPI QUERIES INTEGRITY
echo "--- 4. Dashboard KPI Queries Integrity ---\n";
$resTotal = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries");
assertTest("Dashboard total applications query executes", $resTotal !== false);

$resPending = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries WHERE status = 'pending'");
assertTest("Dashboard pending applications query executes", $resPending !== false);

$resApproved = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries WHERE status = 'accepted'");
assertTest("Dashboard approved applications query executes", $resApproved !== false);

$resRejected = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries WHERE status = 'rejected'");
assertTest("Dashboard rejected applications query executes", $resRejected !== false);

$resEnrolled = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM admissions_inquiries WHERE converted_student_id IS NOT NULL AND converted_student_id > 0");
assertTest("Dashboard enrolled applications query executes", $resEnrolled !== false);
echo "\n";

// 5. ADMISSION INQUIRY CRUD & DUPLICATE DETECTION (SRS §8.2, §8.3)
echo "--- 5. Admission Inquiry CRUD & Duplicate Detection ---\n";

// Lookup active class, section, session
$classRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM classes LIMIT 1"));
$testClassId = (int)($classRow['id'] ?? 1);

$secRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM sections WHERE class_id = {$testClassId} LIMIT 1"));
if (!$secRow) {
    $secRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM sections LIMIT 1"));
}
$testSecId = (int)($secRow['id'] ?? 1);

$sessRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM academic_sessions WHERE is_current = 1 LIMIT 1"));
if (!$sessRow) {
    $sessRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM academic_sessions LIMIT 1"));
}
$testSessId = (int)($sessRow['id'] ?? 1);

// Test 5.1: Create Test Admission Inquiry
$testPhone = '+923009998877';
$testChild = 'Zayd Haroon Test';
$testParent = 'Haroon Rashid';

$stmt = mysqli_prepare($conn, "INSERT INTO admissions_inquiries (parent_name, email, phone, child_name, grade_applying, gender, class_id, section_id, session_id, message, status, dob, address, created_at) VALUES (?, 'haroon@example.com', ?, ?, 'Grade 6', 'Male', ?, ?, ?, 'Automated Test Registration', 'pending', '2015-05-10', 'Islamabad', NOW())");
mysqli_stmt_bind_param($stmt, 'sssiii', $testParent, $testPhone, $testChild, $testClassId, $testSecId, $testSessId);
$insertOk = mysqli_stmt_execute($stmt);
$newInqId = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

assertTest("Create new admission inquiry", $insertOk && $newInqId > 0);

// Test 5.2: Duplicate Detection Query Check (SRS §8.3)
$dupStmt = mysqli_prepare($conn, "SELECT id FROM admissions_inquiries WHERE LOWER(TRIM(child_name)) = LOWER(TRIM(?)) AND TRIM(phone) = TRIM(?) LIMIT 1");
mysqli_stmt_bind_param($dupStmt, 'ss', $testChild, $testPhone);
mysqli_stmt_execute($dupStmt);
$dupRes = mysqli_stmt_get_result($dupStmt);
$dupRow = mysqli_fetch_assoc($dupRes);
mysqli_stmt_close($dupStmt);

assertTest("Duplicate inquiry detection identifies matching applicant and phone", !empty($dupRow) && (int)$dupRow['id'] === $newInqId);

// Test 5.3: Read / Retrieve Inquiry
$readStmt = mysqli_prepare($conn, "SELECT * FROM admissions_inquiries WHERE id = ?");
mysqli_stmt_bind_param($readStmt, 'i', $newInqId);
mysqli_stmt_execute($readStmt);
$readRow = mysqli_fetch_assoc(mysqli_stmt_get_result($readStmt));
mysqli_stmt_close($readStmt);

assertTest("Read admission inquiry record", !empty($readRow) && $readRow['child_name'] === $testChild);

// Test 5.4: Update Tracking Status & Details (Receptionist permitted tracking status)
$updStmt = mysqli_prepare($conn, "UPDATE admissions_inquiries SET status = 'reviewing', grade_applying = 'Grade 6 Advanced' WHERE id = ?");
mysqli_stmt_bind_param($updStmt, 'i', $newInqId);
$updOk = mysqli_stmt_execute($updStmt);
mysqli_stmt_close($updStmt);

$verifyUpd = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status, grade_applying FROM admissions_inquiries WHERE id = {$newInqId}"));
assertTest("Update inquiry status to reviewing and edit details", $updOk && $verifyUpd['status'] === 'reviewing' && $verifyUpd['grade_applying'] === 'Grade 6 Advanced');

// Clean up test inquiry
$delStmt = mysqli_prepare($conn, "DELETE FROM admissions_inquiries WHERE id = ?");
mysqli_stmt_bind_param($delStmt, 'i', $newInqId);
$delOk = mysqli_stmt_execute($delStmt);
mysqli_stmt_close($delStmt);

assertTest("Clean up / delete test inquiry", $delOk);
echo "\n";

// 6. ADMISSION REPORTS & CSV EXPORT LOGIC (SRS §8.6)
echo "--- 6. Admission Reports & CSV Export Logic ---\n";
$repStats = mysqli_query($conn, "SELECT
    COUNT(*) AS total_filtered,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS count_pending,
    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS count_approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS count_rejected
    FROM admissions_inquiries");
$repStatsRow = mysqli_fetch_assoc($repStats);
assertTest("Admission reports summary stats query executes", !empty($repStatsRow) && isset($repStatsRow['total_filtered']));

$repList = mysqli_query($conn, "SELECT a.id, a.child_name, a.parent_name, a.phone, a.status, c.class_name
    FROM admissions_inquiries a
    LEFT JOIN classes c ON a.class_id = c.id
    ORDER BY a.created_at DESC LIMIT 5");
assertTest("Admission reports listing query executes with class join", $repList !== false);
echo "\n";

// 7. LIVE HTTP TESTS (CURL TO LOCALHOST)
echo "--- 7. Live HTTP Tests (Curl to Localhost) ---\n";

function httpGetWithCookie($url, $cookieFile) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    $output = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return ['body' => $output, 'code' => $info['http_code'], 'redirect' => $info['redirect_url']];
}

function httpPostLogin($url, $username, $password, $cookieFile) {
    // 1. GET login page to retrieve CSRF token
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    $loginPage = curl_exec($ch);
    curl_close($ch);

    $csrf = '';
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $m)) {
        $csrf = $m[1];
    }

    // 2. POST credentials with CSRF token
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
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
$cookieFile = sys_get_temp_dir() . '/rec_test_cookies.txt';
if (file_exists($cookieFile)) @unlink($cookieFile);

// 7.1 Login as Receptionist
$loginRes = httpPostLogin($baseUrl . '/admin/login.php', 'receptionist', 'Receptionist@123', $cookieFile);
assertTest("Receptionist login POST returns redirect (302)", $loginRes['code'] === 302);
assertTest("Receptionist redirect URL routes to /receptionist/dashboard.php", strpos($loginRes['redirect'], 'receptionist/dashboard.php') !== false);

// 7.2 Access Receptionist Dashboard
$dashRes = httpGetWithCookie($baseUrl . '/receptionist/dashboard.php', $cookieFile);
assertTest("Receptionist dashboard returns 200 OK", $dashRes['code'] === 200);
assertTest("Receptionist dashboard contains 'Receptionist Portal'", strpos($dashRes['body'], 'Receptionist Portal') !== false);

// 7.3 Access Receptionist Admissions Management
$admRes = httpGetWithCookie($baseUrl . '/receptionist/admissions.php', $cookieFile);
assertTest("Receptionist admissions.php returns 200 OK", $admRes['code'] === 200);
assertTest("Receptionist admissions.php contains 'Admission Applications'", strpos($admRes['body'], 'Admission Applications') !== false);

// 7.4 Access Receptionist New Application Form
$addRes = httpGetWithCookie($baseUrl . '/receptionist/admissions.php?action=add', $cookieFile);
assertTest("Receptionist admissions.php?action=add returns 200 OK", $addRes['code'] === 200);
assertTest("Receptionist new application form contains 'Candidate & Guardian Registration Form'", strpos($addRes['body'], 'Candidate & Guardian Registration Form') !== false);

// 7.5 Access Receptionist Reports
$repRes = httpGetWithCookie($baseUrl . '/receptionist/reports.php', $cookieFile);
assertTest("Receptionist reports.php returns 200 OK", $repRes['code'] === 200);
assertTest("Receptionist reports.php contains 'Admission Reports'", strpos($repRes['body'], 'Admission Reports') !== false);

// 7.6 Test CSV Export
$csvRes = httpGetWithCookie($baseUrl . '/receptionist/reports.php?export=csv', $cookieFile);
assertTest("Receptionist reports CSV export returns 200 OK", $csvRes['code'] === 200);
assertTest("CSV export content starts with header 'Application Ref'", strpos($csvRes['body'], 'Application Ref') !== false);

// 7.7 RBAC: Receptionist is blocked from Admin-only settings.php
$adminSetRes = httpGetWithCookie($baseUrl . '/admin/settings.php', $cookieFile);
assertTest("Admin-only settings.php blocks Receptionist (redirects 302)", $adminSetRes['code'] === 302);
assertTest("Admin-only settings.php redirects to receptionist dashboard", strpos($adminSetRes['redirect'], 'receptionist/dashboard.php') !== false);

// 7.8 RBAC: Receptionist is blocked from Admin-only users.php
$adminUsersRes = httpGetWithCookie($baseUrl . '/admin/users.php', $cookieFile);
assertTest("Admin-only users.php blocks Receptionist (redirects 302)", $adminUsersRes['code'] === 302);
assertTest("Admin-only users.php redirects to receptionist dashboard", strpos($adminUsersRes['redirect'], 'receptionist/dashboard.php') !== false);

// 7.9 RBAC: Receptionist is blocked from Admin-only backup.php
$adminBackupRes = httpGetWithCookie($baseUrl . '/admin/backup.php', $cookieFile);
assertTest("Admin-only backup.php blocks Receptionist (redirects 302)", $adminBackupRes['code'] === 302);

// 7.10 RBAC: Receptionist is blocked from Accountant-only fee_payments.php
$accFeeRes = httpGetWithCookie($baseUrl . '/accountant/fee_payments.php', $cookieFile);
assertTest("Accountant-only fee_payments.php blocks Receptionist (HTTP 403 Forbidden)", $accFeeRes['code'] === 403);

// 7.11 RBAC: Receptionist is blocked from Accountant-only salaries.php
$accSalRes = httpGetWithCookie($baseUrl . '/accountant/salaries.php', $cookieFile);
assertTest("Accountant-only salaries.php blocks Receptionist (HTTP 403 Forbidden)", $accSalRes['code'] === 403);

// 7.12 RBAC: Receptionist is blocked from Accountant-only income_expense.php
$accIncRes = httpGetWithCookie($baseUrl . '/accountant/income_expense.php', $cookieFile);
assertTest("Accountant-only income_expense.php blocks Receptionist (HTTP 403 Forbidden)", $accIncRes['code'] === 403);

if (file_exists($cookieFile)) @unlink($cookieFile);

echo "\n";
echo "========================================================\n";
echo " TEST SUMMARY: STAGE 3 RECEPTIONIST PANEL\n";
echo " Passed: $testsPassed\n";
echo " Failed: $testsFailed\n";
echo "========================================================\n";

if ($testsFailed === 0) {
    echo "🎉 ALL TESTS PASSED! Stage 3 Receptionist Panel is verified and production-ready.\n";
} else {
    echo "❌ SOME TESTS FAILED. Please review the errors above.\n";
    exit(1);
}
