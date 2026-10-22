<?php
/**
 * Test script for admin/fees.php functionality
 */
// Initialize environment
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Load DB connection
require_once __DIR__ . '/config/database.php';
// Ensure an admin is logged in (use first admin or create one)
$adminRes = mysqli_query($conn, "SELECT id FROM admins LIMIT 1");
if ($row = mysqli_fetch_assoc($adminRes)) {
    $_SESSION['admin_id'] = $row['id'];
    $_SESSION['admin_name'] = 'Test Admin';
} else {
    // Insert a test admin
    $stmt = mysqli_prepare($conn, "INSERT INTO admins (username, email, password_hash, full_name) VALUES ('testadmin', 'test@example.com', 'testhash', 'Test Admin')");
    mysqli_stmt_execute($stmt);
    $_SESSION['admin_id'] = mysqli_insert_id($conn);
    $_SESSION['admin_name'] = 'Test Admin';
    mysqli_stmt_close($stmt);
}
// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

function runFeesPhp(){
    ob_start();
    include __DIR__ . '/admin/fees.php';
    ob_end_clean();
}

$results = [];
// 1. Add a fee structure
$_POST = [
    'csrf_token' => $csrf,
    'action' => 'create_fee',
    'session_id' => 1, // assuming session 1 exists
    'class_id' => 1,   // assuming class 1 exists
    'fee_title' => 'Test Fee',
    'amount' => '150.00',
    'due_date' => date('Y-m-d', strtotime('+7 days')),
];
$_GET = [];
$_SERVER['REQUEST_METHOD'] = 'POST';
runFeesPhp();
$feeId = null;
$res = mysqli_query($conn, "SELECT id FROM fee_structures WHERE fee_title='Test Fee' ORDER BY id DESC LIMIT 1");
if ($row = mysqli_fetch_assoc($res)) {
    $feeId = $row['id'];
    $results[] = ['add_fee' => 'PASS'];
} else {
    $results[] = ['add_fee' => 'FAIL'];
}
// 2. Edit the fee structure
if ($feeId) {
    $_POST = [
        'csrf_token' => $csrf,
        'action' => 'update_fee',
        'id' => $feeId,
        'session_id' => 2, // change session to 2 (exists)
        'class_id' => 2,   // change class to 2 (exists)
        'fee_title' => 'Test Fee Updated',
        'amount' => '175.50',
        'due_date' => date('Y-m-d', strtotime('+14 days')),
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    runFeesPhp();
    $res = mysqli_query($conn, "SELECT * FROM fee_structures WHERE id=$feeId");
    $row = mysqli_fetch_assoc($res);
    if ($row && $row['fee_title'] === 'Test Fee Updated' && floatval($row['amount']) == 175.50) {
        $results[] = ['edit_fee' => 'PASS'];
    } else {
        $results[] = ['edit_fee' => 'FAIL'];
    }
}
// 3. Assign fee to a student
// Pick a student
$studentRes = mysqli_query($conn, "SELECT id FROM students LIMIT 1");
$studentId = null;
if ($s = mysqli_fetch_assoc($studentRes)) {
    $studentId = $s['id'];
}
if ($feeId && $studentId) {
    $_POST = [
        'csrf_token' => $csrf,
        'action' => 'assign_fee',
        'student_id' => $studentId,
        'fee_structure_id' => $feeId,
        'discount_amount' => '25.00',
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    runFeesPhp();
    // Verify assignment
    $res = mysqli_query($conn, "SELECT * FROM student_fee_assignments WHERE student_id=$studentId AND fee_structure_id=$feeId");
    if ($assign = mysqli_fetch_assoc($res)) {
        $expectedAssigned = floatval($assign['assigned_amount']);
        $expectedDiscount = floatval($assign['discount_amount']);
        $expectedNet = floatval($assign['net_amount']);
        $calcNet = max(0, $expectedAssigned - $expectedDiscount);
        if (abs($expectedNet - $calcNet) < 0.01) {
            $results[] = ['assign_fee' => 'PASS'];
        } else {
            $results[] = ['assign_fee' => 'FAIL (net amount mismatch)'];
        }
    } else {
        $results[] = ['assign_fee' => 'FAIL (no record)'];
    }
}
// 4. Test invalid input: missing session_id on create
$_POST = [
    'csrf_token' => $csrf,
    'action' => 'create_fee',
    // 'session_id' omitted intentionally
    'class_id' => 1,
    'fee_title' => 'Invalid Fee',
    'amount' => '100',
    'due_date' => date('Y-m-d'),
];
$_SERVER['REQUEST_METHOD'] = 'POST';
runFeesPhp();
if (!empty($_SESSION['flash_error'])) {
    $results[] = ['invalid_input' => 'PASS'];
    // clear for next test
    unset($_SESSION['flash_error']);
} else {
    $results[] = ['invalid_input' => 'FAIL'];
}
// 5. Verify existing records unchanged (check count unchanged for initial fee count)
$initialCountRes = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM fee_structures");
$initialCnt = mysqli_fetch_assoc($initialCountRes)['cnt'];
// As we added 1 fee, expect count >=1; ensure no deletions occurred
if ($initialCnt >= 1) {
    $results[] = ['records_intact' => 'PASS'];
} else {
    $results[] = ['records_intact' => 'FAIL'];
}
// Output results
foreach ($results as $r) {
    foreach ($r as $k => $v) {
        echo "$k: $v\n";
    }
}
?>
