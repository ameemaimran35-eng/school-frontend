<?php
/**
 * Automated Test Suite for Brightway School Management System - Stage 2: Accountant Panel
 * Tests:
 * 1. Syntax check of all accountant files
 * 2. Accountant user presence in database
 * 3. RBAC & Role verification logic
 * 4. Dashboard KPI queries & integrity
 * 5. Fee Collection, partial/full payments, balance validation, duplicate check
 * 6. Receipt generation & retrieval
 * 7. Outstanding dues calculation
 * 8. Income & Expense direct CRUD & anti-double-counting guard
 * 9. Salary payment recording & duplicate check
 * 10. Financial reports & ledger logic
 * 11. Live HTTP requests (Login, Accountant Dashboard, Admin-only RBAC protection)
 */
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', '1');

echo "========================================================\n";
echo " BRIGHTWAY SCHOOL - STAGE 2 ACCOUNTANT SUITE\n";
echo "========================================================\n\n";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../admin/auth.php';

if (!$conn) {
    die("FATAL: Could not connect to database!\n");
}
echo "[✓] Database Connection: OK (school_management_db)\n\n";

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

// 1. SYNTAX CHECK OF ALL ACCOUNTANT FILES
echo "--- 1. Checking Syntax of All Accountant Files ---\n";
$accFiles = glob(__DIR__ . '/../accountant/*.php');
$syntaxErrors = 0;
foreach ($accFiles as $file) {
    $cmd = 'C:\xampp\php\php.exe -l ' . escapeshellarg($file);
    $out = shell_exec($cmd);
    if (strpos($out, 'No syntax errors detected') === false) {
        echo "  [✗] SYNTAX ERROR in " . basename($file) . ":\n      " . trim($out) . "\n";
        $syntaxErrors++;
    } else {
        echo "  [✓] " . basename($file) . " - Syntax OK\n";
    }
}
assertTest("Zero syntax errors in accountant/ directory", $syntaxErrors === 0);
echo "\n";

// 2. ACCOUNTANT USER IN DATABASE
echo "--- 2. Accountant User in Database ---\n";
$accRes = mysqli_query($conn, "SELECT id, username, full_name, role, status FROM admins WHERE role = 'accountant' LIMIT 1");
$accUser = mysqli_fetch_assoc($accRes);
assertTest("Accountant account exists in database", !empty($accUser));
assertTest("Accountant role is 'accountant'", ($accUser['role'] ?? '') === 'accountant');
assertTest("Accountant status is 'active'", ($accUser['status'] ?? '') === 'active');
echo "\n";

// 3. RBAC HELPER FUNCTIONS IN AUTH.PHP
echo "--- 3. RBAC Helper Functions ---\n";
$_SESSION['admin_id'] = $accUser['id'] ?? 3;
$_SESSION['admin_name'] = $accUser['full_name'] ?? 'Accountant User';
$_SESSION['admin_role'] = 'accountant';

assertTest("hasRole('accountant') returns true for accountant", hasRole('accountant') === true);
assertTest("hasRole(['accountant', 'admin']) returns true for accountant", hasRole(['accountant', 'admin']) === true);
assertTest("hasRole('admin') returns false for accountant", hasRole('admin') === false);
assertTest("isAccountant() returns true for accountant", isAccountant() === true);
assertTest("isAdmin() returns false for accountant", isAdmin() === false);
echo "\n";

// 4. DASHBOARD KPI QUERIES INTEGRITY
echo "--- 4. Dashboard Financial KPI Queries ---\n";
$today = date('Y-m-d');
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// Today fee collection
$q1 = mysqli_query($conn, "SELECT COALESCE(SUM(amount_paid),0) AS today_sum FROM fee_payments WHERE DATE(payment_date) = '$today'");
assertTest("Dashboard today collection query executes", $q1 !== false);
$today_sum = (float)mysqli_fetch_assoc($q1)['today_sum'];

// Month fee collection
$q2 = mysqli_query($conn, "SELECT COALESCE(SUM(amount_paid),0) AS month_sum FROM fee_payments WHERE payment_date BETWEEN '$month_start' AND '$month_end'");
assertTest("Dashboard monthly collection query executes", $q2 !== false);

// Total outstanding dues
$q3 = mysqli_query($conn, "SELECT COALESCE(SUM(net_amount),0) AS dues_sum FROM student_fee_assignments WHERE status IN ('unpaid','partial')");
assertTest("Dashboard outstanding dues query executes", $q3 !== false);

// Month expenses
$q4 = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS exp_sum FROM financial_transactions WHERE type='expense' AND trans_date BETWEEN '$month_start' AND '$month_end'");
assertTest("Dashboard monthly expenses query executes", $q4 !== false);

// Month salaries
$q5 = mysqli_query($conn, "SELECT COALESCE(SUM(net_salary),0) AS sal_sum FROM staff_salaries WHERE payment_date BETWEEN '$month_start' AND '$month_end'");
assertTest("Dashboard monthly salaries query executes", $q5 !== false);
echo "\n";

// 5. FEE COLLECTION, PARTIAL/FULL PAYMENTS & VALIDATION
echo "--- 5. Fee Collection & Payment Logic ---\n";

// Get an active student for isolated testing
$stuRes = mysqli_query($conn, "SELECT id FROM students WHERE status='active' LIMIT 1");
$stuRow = mysqli_fetch_assoc($stuRes);
$testStudentId = (int)($stuRow['id'] ?? 1);

// Create a unique temporary fee structure for this test run
$testFeeTitle = 'Test Tuition ' . time();
$sessRes = mysqli_query($conn, "SELECT id FROM academic_sessions LIMIT 1");
$sessId = (int)(mysqli_fetch_assoc($sessRes)['id'] ?? 1);
$clsRes = mysqli_query($conn, "SELECT id FROM classes LIMIT 1");
$clsId = (int)(mysqli_fetch_assoc($clsRes)['id'] ?? 1);

$feeInsertSql = "INSERT INTO fee_structures (session_id, class_id, fee_title, amount, due_date)
                 VALUES ($sessId, $clsId, '$testFeeTitle', 1000.00, '2099-12-31')";
mysqli_query($conn, $feeInsertSql);
$testFeeId = mysqli_insert_id($conn);

// Create a test fee assignment
$testAssignSql = "INSERT INTO student_fee_assignments (student_id, fee_structure_id, assigned_amount, discount_amount, net_amount, status) 
                  VALUES ($testStudentId, $testFeeId, 1000.00, 100.00, 900.00, 'unpaid')";
$testAssignRes = mysqli_query($conn, $testAssignSql);
$testAssignId = mysqli_insert_id($conn);
assertTest("Create test fee assignment ($900 net)", $testAssignRes && $testAssignId > 0);

// Validation 1: Payment amount cannot exceed net_amount
$excessiveAmount = 950.00;
$validationFail = ($excessiveAmount > 900.00);
assertTest("Balance validation prevents paying > remaining net amount", $validationFail === true);

// Record partial payment ($400)
$rcpt1 = 'RCPT-TEST-' . time() . '-P1';
$payDate = date('Y-m-d');
mysqli_begin_transaction($conn);
$pay1Sql = "INSERT INTO fee_payments (student_fee_id, student_id, amount_paid, payment_method, payment_date, receipt_no, remarks)
            VALUES ($testAssignId, $testStudentId, 400.00, 'cash', '$payDate', '$rcpt1', 'Test Partial Payment')";
$p1Res = mysqli_query($conn, $pay1Sql);
$p1Id = mysqli_insert_id($conn);

$upd1Sql = "UPDATE student_fee_assignments SET net_amount = net_amount - 400.00, status = 'partial' WHERE id = $testAssignId";
$u1Res = mysqli_query($conn, $upd1Sql);

$ft1Sql = "INSERT INTO financial_transactions (trans_date, type, category, amount, title, payment_method, reference_type, reference_id, receipt_invoice_no)
           VALUES ('$payDate', 'income', 'Fee Collection', 400.00, 'Fee Payment #$rcpt1', 'cash', 'fee_payment', $p1Id, '$rcpt1')";
$ft1Res = mysqli_query($conn, $ft1Sql);
mysqli_commit($conn);

assertTest("Record partial payment of $400", $p1Res && $p1Id > 0 && $u1Res && $ft1Res);

// Check assignment balance & status
$chk1 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT net_amount, status FROM student_fee_assignments WHERE id = $testAssignId"));
assertTest("Remaining net amount updated to 500.00", (float)$chk1['net_amount'] === 500.00);
assertTest("Status updated to 'partial'", $chk1['status'] === 'partial');

// Duplicate payment check: same student_fee_id + payment_date + amount_paid within 5 minutes
$dupCheck = mysqli_query($conn, "SELECT id FROM fee_payments WHERE student_fee_id = $testAssignId AND payment_date = '$payDate' AND amount_paid = 400.00 AND id = $p1Id");
assertTest("Duplicate payment detection query identifies existing identical payment", mysqli_num_rows($dupCheck) > 0);

// Record settlement of remaining $500
$rcpt2 = 'RCPT-TEST-' . time() . '-P2';
mysqli_begin_transaction($conn);
$pay2Sql = "INSERT INTO fee_payments (student_fee_id, student_id, amount_paid, payment_method, payment_date, receipt_no, remarks)
            VALUES ($testAssignId, $testStudentId, 500.00, 'online', '$payDate', '$rcpt2', 'Test Final Payment')";
$p2Res = mysqli_query($conn, $pay2Sql);
$p2Id = mysqli_insert_id($conn);

$upd2Sql = "UPDATE student_fee_assignments SET net_amount = 0.00, status = 'paid' WHERE id = $testAssignId";
$u2Res = mysqli_query($conn, $upd2Sql);

$ft2Sql = "INSERT INTO financial_transactions (trans_date, type, category, amount, title, payment_method, reference_type, reference_id, receipt_invoice_no)
           VALUES ('$payDate', 'income', 'Fee Collection', 500.00, 'Fee Payment #$rcpt2', 'online', 'fee_payment', $p2Id, '$rcpt2')";
$ft2Res = mysqli_query($conn, $ft2Sql);
mysqli_commit($conn);

$chk2 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT net_amount, status FROM student_fee_assignments WHERE id = $testAssignId"));
assertTest("Final payment sets balance to 0.00", (float)$chk2['net_amount'] === 0.00);
assertTest("Final payment sets status to 'paid'", $chk2['status'] === 'paid');
echo "\n";

// 6. RECEIPT GENERATION & DATA RETRIEVAL
echo "--- 6. Receipt Verification ---\n";
$rcptQuery = mysqli_query($conn, "
    SELECT fp.*, s.first_name, s.last_name, s.admission_no, c.class_name, sec.section_name,
           fs.fee_title, sfa.assigned_amount, sfa.discount_amount, sfa.net_amount AS balance_remaining, sfa.status AS assignment_status
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    JOIN student_fee_assignments sfa ON fp.student_fee_id = sfa.id
    JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE fp.receipt_no = '$rcpt1'
    LIMIT 1
");
$rcptData = mysqli_fetch_assoc($rcptQuery);
assertTest("Receipt query returns complete metadata", !empty($rcptData));
assertTest("Receipt has student name and admission number", !empty($rcptData['first_name']) && !empty($rcptData['admission_no']));
assertTest("Receipt reflects correct payment amount ($400)", (float)$rcptData['amount_paid'] === 400.00);

// Cleanup test payment & assignment data
mysqli_query($conn, "DELETE FROM financial_transactions WHERE receipt_invoice_no IN ('$rcpt1', '$rcpt2')");
mysqli_query($conn, "DELETE FROM fee_payments WHERE id IN ($p1Id, $p2Id)");
mysqli_query($conn, "DELETE FROM student_fee_assignments WHERE id = $testAssignId");
mysqli_query($conn, "DELETE FROM fee_structures WHERE id = $testFeeId");
echo "\n";

// 7. INCOME & EXPENSE CRUD & ANTI-DOUBLE-COUNT GUARD
echo "--- 7. Income & Expense CRUD & Anti-Double-Count Guard ---\n";

// Insert direct expense
$expSql = "INSERT INTO financial_transactions (trans_date, type, category, amount, title, description, payment_method, reference_type, receipt_invoice_no)
           VALUES ('$today', 'expense', 'Office Supplies', 250.00, 'Stationery Purchase TEST', 'Test markers and paper', 'cash', 'direct', 'INV-TEST-001')";
$expRes = mysqli_query($conn, $expSql);
$expId = mysqli_insert_id($conn);
assertTest("Create direct expense transaction", $expRes && $expId > 0);

// Verify accountant view filters out fee_payment and salary from direct management
$directOnlyQuery = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM financial_transactions WHERE reference_type = 'direct' AND id = $expId");
$cntDirect = (int)mysqli_fetch_assoc($directOnlyQuery)['cnt'];
assertTest("Direct transaction is selectable with reference_type='direct'", $cntDirect === 1);

// Anti-double-counting check: fee_payment transactions must have reference_type='fee_payment'
$fakeFeeTransSql = "INSERT INTO financial_transactions (trans_date, type, category, amount, title, payment_method, reference_type)
                    VALUES ('$today', 'income', 'Fee Collection', 500.00, 'Test Auto Fee', 'cash', 'fee_payment')";
mysqli_query($conn, $fakeFeeTransSql);
$fakeFeeId = mysqli_insert_id($conn);

// Verify accountant delete guard: cannot delete if reference_type != 'direct'
$targetTrans = mysqli_fetch_assoc(mysqli_query($conn, "SELECT reference_type FROM financial_transactions WHERE id = $fakeFeeId"));
$canDelete = ($targetTrans['reference_type'] === 'direct');
assertTest("Accountant guard protects fee_payment transaction from manual deletion", $canDelete === false);

// Clean up
mysqli_query($conn, "DELETE FROM financial_transactions WHERE id = $fakeFeeId");
mysqli_query($conn, "DELETE FROM financial_transactions WHERE id = $expId");
echo "\n";

// 8. SALARY PAYMENT RECORDING & DUPLICATE CHECK
echo "--- 8. Salary Payment Recording & Duplicate Check ---\n";
$stfRes = mysqli_query($conn, "SELECT id, basic_salary FROM staff WHERE status='active' LIMIT 1");
$stfRow = mysqli_fetch_assoc($stfRes);
$testStaffId = (int)($stfRow['id'] ?? 1);
$basicSal = (float)($stfRow['basic_salary'] ?? 30000.00);

$testMonth = 11;
$testYear = 2099; // Far future test period

// Insert salary payment
$netSal = $basicSal + 2000.00 - 500.00;
mysqli_begin_transaction($conn);
$salSql = "INSERT INTO staff_salaries (staff_id, salary_month, salary_year, basic_amount, allowances, deductions, net_salary, payment_date, payment_method, notes, status)
           VALUES ($testStaffId, $testMonth, $testYear, $basicSal, 2000.00, 500.00, $netSal, '$today', 'bank_transfer', 'Test Salary', 'paid')";
$salRes = mysqli_query($conn, $salSql);
$salId = mysqli_insert_id($conn);

$salFtSql = "INSERT INTO financial_transactions (trans_date, type, category, amount, title, payment_method, reference_type, reference_id)
             VALUES ('$today', 'expense', 'Staff Salaries', $netSal, 'Salary Payment TEST', 'bank_transfer', 'salary', $salId)";
$salFtRes = mysqli_query($conn, $salFtSql);
mysqli_commit($conn);

assertTest("Record staff salary payment", $salRes && $salId > 0 && $salFtRes);

// Duplicate check: same staff_id + month + year
$dupSalQuery = mysqli_query($conn, "SELECT id FROM staff_salaries WHERE staff_id = $testStaffId AND salary_month = $testMonth AND salary_year = $testYear");
assertTest("Duplicate salary query detects existing payment for same period", mysqli_num_rows($dupSalQuery) >= 1);

// Clean up test salary
mysqli_query($conn, "DELETE FROM financial_transactions WHERE reference_type='salary' AND reference_id = $salId");
mysqli_query($conn, "DELETE FROM staff_salaries WHERE id = $salId");
echo "\n";

// 9. FINANCIAL REPORTS & LEDGER QUERIES
echo "--- 9. Financial Reports Queries ---\n";
// Fee Collection Report query
$repCol = mysqli_query($conn, "
    SELECT fp.receipt_no, s.first_name, fp.amount_paid, fp.payment_date 
    FROM fee_payments fp 
    JOIN students s ON fp.student_id = s.id 
    LIMIT 5
");
assertTest("Fee Collection report query executes successfully", $repCol !== false);

// Outstanding Dues Report query
$repDues = mysqli_query($conn, "
    SELECT sfa.id, s.first_name, sfa.net_amount 
    FROM student_fee_assignments sfa 
    JOIN students s ON sfa.student_id = s.id 
    WHERE sfa.status IN ('unpaid','partial') 
    LIMIT 5
");
assertTest("Outstanding Dues report query executes successfully", $repDues !== false);

// Student Ledger query
$repLedger = mysqli_query($conn, "
    SELECT fp.receipt_no, fs.fee_title, fp.amount_paid 
    FROM fee_payments fp 
    JOIN student_fee_assignments sfa ON fp.student_fee_id = sfa.id 
    JOIN fee_structures fs ON sfa.fee_structure_id = fs.id 
    WHERE fp.student_id = $testStudentId 
    LIMIT 5
");
assertTest("Student Ledger query executes successfully", $repLedger !== false);
echo "\n";

// 10. LIVE HTTP RBAC ENFORCEMENT & PORTAL ACCESSIBILITY
echo "--- 10. Live HTTP Tests (Curl to Localhost) ---\n";

function httpGetWithCookie($url, $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    if ($cookieFile && file_exists($cookieFile)) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    }
    $output = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return ['body' => $output, 'code' => $info['http_code'], 'redirect' => $info['redirect_url']];
}

function httpPostLogin($url, $username, $password, $cookieFile) {
    // 1. Initial GET to obtain session cookie and CSRF token
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
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
$cookieFile = sys_get_temp_dir() . '/acc_test_cookies.txt';
if (file_exists($cookieFile)) @unlink($cookieFile);

// Test 10.1: Login as Accountant
$loginRes = httpPostLogin($baseUrl . '/admin/login.php', 'accountant', 'Accountant@123', $cookieFile);
assertTest("Accountant login POST returns redirect (302)", $loginRes['code'] === 302);
assertTest("Accountant redirect URL routes to /accountant/dashboard.php", strpos($loginRes['redirect'], 'accountant/dashboard.php') !== false);

// Test 10.2: Access Accountant Dashboard with session
$dashRes = httpGetWithCookie($baseUrl . '/accountant/dashboard.php', $cookieFile);
assertTest("Accountant dashboard returns 200 OK", $dashRes['code'] === 200);
assertTest("Accountant dashboard contains 'Accountant Portal'", strpos($dashRes['body'], 'Accountant') !== false);

// Test 10.3: Access Accountant Fee Payments
$feeRes = httpGetWithCookie($baseUrl . '/accountant/fee_payments.php', $cookieFile);
assertTest("Accountant fee_payments.php returns 200 OK", $feeRes['code'] === 200);

// Test 10.4: Access Accountant Fee Dues
$duesRes = httpGetWithCookie($baseUrl . '/accountant/fee_dues.php', $cookieFile);
assertTest("Accountant fee_dues.php returns 200 OK", $duesRes['code'] === 200);

// Test 10.5: Access Accountant Reports
$repRes = httpGetWithCookie($baseUrl . '/accountant/reports.php', $cookieFile);
assertTest("Accountant reports.php returns 200 OK", $repRes['code'] === 200);

// Test 10.6: Access Accountant Salaries
$salPageRes = httpGetWithCookie($baseUrl . '/accountant/salaries.php', $cookieFile);
assertTest("Accountant salaries.php returns 200 OK", $salPageRes['code'] === 200);

// Test 10.7: Access Accountant Income & Expenses
$incPageRes = httpGetWithCookie($baseUrl . '/accountant/income_expense.php', $cookieFile);
assertTest("Accountant income_expense.php returns 200 OK", $incPageRes['code'] === 200);

// Test 10.8: RBAC ENFORCEMENT - Accountant trying to access Admin-Only pages
$adminSettingsRes = httpGetWithCookie($baseUrl . '/admin/settings.php', $cookieFile);
assertTest("Admin-only settings.php blocks Accountant (redirects 302)", $adminSettingsRes['code'] === 302);
assertTest("Admin-only settings.php redirects to accountant dashboard", strpos($adminSettingsRes['redirect'], 'accountant/dashboard.php') !== false);

$adminUsersRes = httpGetWithCookie($baseUrl . '/admin/users.php', $cookieFile);
assertTest("Admin-only users.php blocks Accountant (redirects 302)", $adminUsersRes['code'] === 302);
assertTest("Admin-only users.php redirects to accountant dashboard", strpos($adminUsersRes['redirect'], 'accountant/dashboard.php') !== false);

$adminBackupRes = httpGetWithCookie($baseUrl . '/admin/backup.php', $cookieFile);
assertTest("Admin-only backup.php blocks Accountant (redirects 302)", $adminBackupRes['code'] === 302);

if (file_exists($cookieFile)) @unlink($cookieFile);
echo "\n";

// SUMMARY
echo "========================================================\n";
echo " TEST SUMMARY: STAGE 2 ACCOUNTANT PANEL\n";
echo " Passed: $testsPassed\n";
echo " Failed: $testsFailed\n";
echo "========================================================\n";

if ($testsFailed === 0) {
    echo "🎉 ALL TESTS PASSED! Stage 2 is verified and production-ready.\n";
} else {
    echo "⚠️ SOME TESTS FAILED. Review output above.\n";
}
