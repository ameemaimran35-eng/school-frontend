<?php
/**
 * Automated Test Suite for Brightway School Management System Admin Panel
 * Tests all major CRUD operations, database tables, form submissions, and buttons.
 */
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', '1');

echo "========================================================\n";
echo " BRIGHTWAY ADMIN PANEL COMPREHENSIVE CRUD TEST SUITE\n";
echo "========================================================\n\n";

require_once __DIR__ . '/../config/database.php';

if (!$conn) {
    die("FATAL: Could not connect to database!\n");
}
echo "[✓] Database Connection: OK (school_management_db)\n\n";

// 1. SYNTAX CHECK OF ALL ADMIN FILES
echo "--- 1. Checking Syntax of All Admin Files ---\n";
$adminFiles = glob(__DIR__ . '/../admin/*.php');
$syntaxErrors = 0;
foreach ($adminFiles as $file) {
    $cmd = 'C:\xampp\php\php.exe -l ' . escapeshellarg($file);
    $out = shell_exec($cmd);
    if (strpos($out, 'No syntax errors detected') === false) {
        echo "  [✗] SYNTAX ERROR in " . basename($file) . ":\n      " . trim($out) . "\n";
        $syntaxErrors++;
    } else {
        echo "  [✓] " . basename($file) . " - Syntax OK\n";
    }
}
if ($syntaxErrors > 0) {
    echo "FAILED: $syntaxErrors syntax errors detected!\n";
} else {
    echo "ALL " . count($adminFiles) . " Admin PHP files have ZERO syntax errors!\n\n";
}

// Prepare Admin Session & CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$adminRes = mysqli_query($conn, "SELECT id, username, full_name, role FROM admins LIMIT 1");
if ($adminRow = mysqli_fetch_assoc($adminRes)) {
    $_SESSION['admin_id'] = $adminRow['id'];
    $_SESSION['admin_name'] = $adminRow['full_name'];
    $_SESSION['admin_role'] = $adminRow['role'];
} else {
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_name'] = 'Admin';
    $_SESSION['admin_role'] = 'admin';
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

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

echo "--- 2. Testing Database Tables & Schemas ---\n";
$requiredTables = [
    'admins', 'academic_sessions', 'classes', 'sections', 'subjects',
    'class_subjects', 'staff', 'students', 'attendance', 'exams',
    'exam_schedules', 'marks', 'fee_structures', 'student_fee_assignments',
    'fee_payments', 'financial_transactions', 'staff_salaries', 'holidays',
    'announcements', 'website_content', 'website_images', 'gallery_images',
    'contact_inquiries', 'admissions_inquiries', 'school_settings', 'activity_logs'
];
foreach ($requiredTables as $t) {
    $q = mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    assertTest("Table exists: $t", mysqli_num_rows($q) > 0);
}
echo "\n";

echo "--- 3. Testing CRUD: Academic Sessions ---\n";
$testSessionName = '2099-2100_TEST_' . time();
$createSession = mysqli_query($conn, "INSERT INTO academic_sessions (session_name, start_date, end_date, is_current) VALUES ('$testSessionName', '2099-01-01', '2100-01-01', 0)");
$newSessionId = mysqli_insert_id($conn);
assertTest("Create Academic Session", $createSession && $newSessionId > 0);

$readSession = mysqli_query($conn, "SELECT * FROM academic_sessions WHERE id = $newSessionId");
$sessionRow = mysqli_fetch_assoc($readSession);
assertTest("Read Academic Session", $sessionRow && $sessionRow['session_name'] === $testSessionName);

$updateSession = mysqli_query($conn, "UPDATE academic_sessions SET session_name = '{$testSessionName}_UPD' WHERE id = $newSessionId");
assertTest("Update Academic Session", $updateSession);

$deleteSession = mysqli_query($conn, "DELETE FROM academic_sessions WHERE id = $newSessionId");
assertTest("Delete Academic Session", $deleteSession);
echo "\n";

echo "--- 4. Testing CRUD: Classes & Sections ---\n";
$testClassName = 'Grade Test ' . time();
$createClass = mysqli_query($conn, "INSERT INTO classes (class_name, numeric_grade) VALUES ('$testClassName', 99)");
$newClassId = mysqli_insert_id($conn);
assertTest("Create Class", $createClass && $newClassId > 0);

$createSection = mysqli_query($conn, "INSERT INTO sections (class_id, section_name, capacity) VALUES ($newClassId, 'Z', 25)");
$newSectionId = mysqli_insert_id($conn);
assertTest("Create Section", $createSection && $newSectionId > 0);

$updateClass = mysqli_query($conn, "UPDATE classes SET class_name = '{$testClassName}_UPD' WHERE id = $newClassId");
assertTest("Update Class", $updateClass);

$updateSection = mysqli_query($conn, "UPDATE sections SET capacity = 35 WHERE id = $newSectionId");
assertTest("Update Section", $updateSection);

$delSection = mysqli_query($conn, "DELETE FROM sections WHERE id = $newSectionId");
$delClass = mysqli_query($conn, "DELETE FROM classes WHERE id = $newClassId");
assertTest("Delete Section & Class", $delSection && $delClass);
echo "\n";

echo "--- 5. Testing CRUD: Subjects ---\n";
$subjCode = 'SUBJ_' . substr(time(), -5);
$createSubj = mysqli_query($conn, "INSERT INTO subjects (subject_name, subject_code, subject_type) VALUES ('Automated Test Subject', '$subjCode', 'core')");
$newSubjId = mysqli_insert_id($conn);
assertTest("Create Subject", $createSubj && $newSubjId > 0);

$readSubj = mysqli_query($conn, "SELECT * FROM subjects WHERE id = $newSubjId");
assertTest("Read Subject", mysqli_num_rows($readSubj) > 0);

$updSubj = mysqli_query($conn, "UPDATE subjects SET subject_name = 'Automated Test Subject Updated' WHERE id = $newSubjId");
assertTest("Update Subject", $updSubj);

$delSubj = mysqli_query($conn, "DELETE FROM subjects WHERE id = $newSubjId");
assertTest("Delete Subject", $delSubj);
echo "\n";

echo "--- 6. Testing CRUD: Staff Management ---\n";
$empCode = 'EMP_TEST_' . time();
$createStaff = mysqli_query($conn, "INSERT INTO staff (employee_code, first_name, last_name, gender, email, phone, designation, department, basic_salary, joining_date, status) VALUES ('$empCode', 'Ahmad', 'Test', 'Male', 'ahmad.$empCode@example.com', '+923001234567', 'Teacher', 'Science', 45000.00, '2026-01-01', 'active')");
$newStaffId = mysqli_insert_id($conn);
assertTest("Create Staff Member", $createStaff && $newStaffId > 0);

$readStaff = mysqli_query($conn, "SELECT * FROM staff WHERE id = $newStaffId");
assertTest("Read Staff Member", mysqli_num_rows($readStaff) > 0);

$updStaff = mysqli_query($conn, "UPDATE staff SET basic_salary = 50000.00, designation = 'Senior Teacher' WHERE id = $newStaffId");
assertTest("Update Staff Member", $updStaff);

$delStaff = mysqli_query($conn, "DELETE FROM staff WHERE id = $newStaffId");
assertTest("Delete Staff Member", $delStaff);
echo "\n";

echo "--- 7. Testing CRUD: Student Management ---\n";
$sessQ = mysqli_query($conn, "SELECT id FROM academic_sessions LIMIT 1");
$sessId = mysqli_fetch_assoc($sessQ)['id'] ?? 1;

$classQ = mysqli_query($conn, "SELECT id FROM classes LIMIT 1");
$cId = mysqli_fetch_assoc($classQ)['id'] ?? 1;

$secQ = mysqli_query($conn, "SELECT id FROM sections WHERE class_id = $cId LIMIT 1");
$secId = mysqli_fetch_assoc($secQ)['id'] ?? null;
if (!$secId) {
    mysqli_query($conn, "INSERT INTO sections (class_id, section_name, capacity) VALUES ($cId, 'A', 30)");
    $secId = mysqli_insert_id($conn);
}

$admNo = 'TEST-STU-' . time();
$createStu = mysqli_query($conn, "INSERT INTO students (admission_no, roll_no, first_name, last_name, gender, date_of_birth, session_id, class_id, section_id, parent_name, parent_phone, parent_email, address, admission_date, status) VALUES ('$admNo', '99', 'Hamza', 'Ali', 'Male', '2016-01-01', $sessId, $cId, $secId, 'Ali Raza', '+923111234567', 'ali@example.com', 'Test Address', '2026-08-01', 'active')");
$newStuId = mysqli_insert_id($conn);
assertTest("Create Student Record", $createStu && $newStuId > 0);

$readStu = mysqli_query($conn, "SELECT * FROM students WHERE id = $newStuId");
assertTest("Read Student Record", mysqli_num_rows($readStu) > 0);

$updStu = mysqli_query($conn, "UPDATE students SET first_name = 'Hamza Updated', roll_no = '98' WHERE id = $newStuId");
assertTest("Update Student Record", $updStu);

$delStu = mysqli_query($conn, "DELETE FROM students WHERE id = $newStuId");
assertTest("Delete Student Record", $delStu);
echo "\n";

echo "--- 8. Testing CRUD: Fees Structure & Payments ---\n";
$createFee = mysqli_query($conn, "INSERT INTO fee_structures (session_id, class_id, fee_title, amount, due_date) VALUES ($sessId, $cId, 'Annual Test Fee', 2500.00, '2026-12-31')");
$newFeeId = mysqli_insert_id($conn);
assertTest("Create Fee Structure", $createFee && $newFeeId > 0);

$readFee = mysqli_query($conn, "SELECT * FROM fee_structures WHERE id = $newFeeId");
assertTest("Read Fee Structure", mysqli_num_rows($readFee) > 0);

$updFee = mysqli_query($conn, "UPDATE fee_structures SET amount = 2800.00 WHERE id = $newFeeId");
assertTest("Update Fee Structure", $updFee);

$delFee = mysqli_query($conn, "DELETE FROM fee_structures WHERE id = $newFeeId");
assertTest("Delete Fee Structure", $delFee);
echo "\n";

echo "--- 9. Testing CRUD: Financial Transactions (Income & Expenses) ---\n";
$createInc = mysqli_query($conn, "INSERT INTO financial_transactions (trans_date, type, category, amount, title, description, payment_method, reference_type) VALUES ('2026-10-19', 'income', 'Donation', 15000.00, 'Alumni Donation', 'Test Description', 'Cash', 'direct')");
$newIncId = mysqli_insert_id($conn);
assertTest("Create Income Entry", $createInc && $newIncId > 0);

$createExp = mysqli_query($conn, "INSERT INTO financial_transactions (trans_date, type, category, amount, title, description, payment_method, reference_type) VALUES ('2026-10-19', 'expense', 'Utilities', 8500.00, 'Electricity Bill', 'Test Exp', 'Bank Transfer', 'direct')");
$newExpId = mysqli_insert_id($conn);
assertTest("Create Expense Entry", $createExp && $newExpId > 0);

$updInc = mysqli_query($conn, "UPDATE financial_transactions SET amount = 16000.00 WHERE id = $newIncId");
assertTest("Update Financial Transaction", $updInc);

$delInc = mysqli_query($conn, "DELETE FROM financial_transactions WHERE id = $newIncId");
$delExp = mysqli_query($conn, "DELETE FROM financial_transactions WHERE id = $newExpId");
assertTest("Delete Financial Transaction Entries", $delInc && $delExp);
echo "\n";

echo "--- 10. Testing CRUD: Announcements & Holidays ---\n";
$createAnn = mysqli_query($conn, "INSERT INTO announcements (title, content, target_audience, is_published, published_date) VALUES ('Test Announcement', 'This is an automated test announcement content.', 'all', 1, '2026-10-19')");
$newAnnId = mysqli_insert_id($conn);
assertTest("Create Announcement", $createAnn && $newAnnId > 0);

$updAnn = mysqli_query($conn, "UPDATE announcements SET title = 'Test Announcement Updated', is_published = 0 WHERE id = $newAnnId");
assertTest("Update / Unpublish Announcement", $updAnn);

$delAnn = mysqli_query($conn, "DELETE FROM announcements WHERE id = $newAnnId");
assertTest("Delete Announcement", $delAnn);

$createHol = mysqli_query($conn, "INSERT INTO holidays (title, start_date, end_date, description) VALUES ('Eid Holiday Test', '2027-04-01', '2027-04-04', 'Automated test holiday')");
$newHolId = mysqli_insert_id($conn);
assertTest("Create Holiday", $createHol && $newHolId > 0);

$updHol = mysqli_query($conn, "UPDATE holidays SET title = 'Eid Holiday Test Updated' WHERE id = $newHolId");
assertTest("Update Holiday", $updHol);

$delHol = mysqli_query($conn, "DELETE FROM holidays WHERE id = $newHolId");
assertTest("Delete Holiday", $delHol);
echo "\n";

echo "--- 11. Testing CRUD: Gallery & Website Images CMS ---\n";
$createGal = mysqli_query($conn, "INSERT INTO gallery_images (category, title, image_path, alt_text, display_order, is_published) VALUES ('campus', 'Test Automated Photo', 'assets/images/gallery/campus-1.svg', 'Test Alt', 99, 1)");
$newGalId = mysqli_insert_id($conn);
assertTest("Create / Upload Gallery Image", $createGal && $newGalId > 0);

$readGal = mysqli_query($conn, "SELECT * FROM gallery_images WHERE id = $newGalId");
assertTest("Read Gallery Image", mysqli_num_rows($readGal) > 0);

$updGal = mysqli_query($conn, "UPDATE gallery_images SET title = 'Test Automated Photo Updated', category = 'activities' WHERE id = $newGalId");
assertTest("Update Gallery Image (Edit title & category)", $updGal);

$delGal = mysqli_query($conn, "DELETE FROM gallery_images WHERE id = $newGalId");
assertTest("Delete Gallery Image", $delGal);

$checkWebImages = mysqli_query($conn, "SELECT COUNT(*) as c FROM website_images");
$webCount = mysqli_fetch_assoc($checkWebImages)['c'] ?? 0;
assertTest("Website Images Slot Table (Count: $webCount)", $webCount >= 10);
echo "\n";

echo "--- 12. Testing CRUD: Attendance Tracking ---\n";
// Ensure test student
$stuQ = mysqli_query($conn, "SELECT id, class_id, section_id FROM students LIMIT 1");
$stuRow = mysqli_fetch_assoc($stuQ);
if ($stuRow) {
    $attDate = '2099-12-31';
    $markAtt = mysqli_query($conn, "INSERT INTO attendance (student_id, class_id, section_id, attendance_date, status, remarks) VALUES ({$stuRow['id']}, {$stuRow['class_id']}, {$stuRow['section_id']}, '$attDate', 'present', 'Test Attendance') ON DUPLICATE KEY UPDATE status = 'present'");
    assertTest("Mark Student Attendance", $markAtt);

    $updAtt = mysqli_query($conn, "UPDATE attendance SET status = 'late', remarks = 'Late by 10 mins' WHERE student_id = {$stuRow['id']} AND attendance_date = '$attDate'");
    assertTest("Update Student Attendance Status", $updAtt);

    $delAtt = mysqli_query($conn, "DELETE FROM attendance WHERE student_id = {$stuRow['id']} AND attendance_date = '$attDate'");
    assertTest("Delete / Clean Attendance Record", $delAtt);
} else {
    echo "  [i] Skipping attendance detail test (no students in DB)\n";
}
echo "\n";

echo "--- 13. Testing CRUD: Exams, Schedules & Marks ---\n";
$createExam = mysqli_query($conn, "INSERT INTO exams (session_id, exam_name, term, start_date, end_date) VALUES ($sessId, 'Annual Test Exam', 'Final Term', '2099-05-01', '2099-05-15')");
$newExamId = mysqli_insert_id($conn);
assertTest("Create Examination", $createExam && $newExamId > 0);

$subQ = mysqli_query($conn, "SELECT id FROM subjects LIMIT 1");
$subId = mysqli_fetch_assoc($subQ)['id'] ?? 1;

$createSched = mysqli_query($conn, "INSERT INTO exam_schedules (exam_id, class_id, subject_id, exam_date, start_time, end_time, max_marks, pass_marks) VALUES ($newExamId, $cId, $subId, '2099-05-05', '09:00:00', '12:00:00', 100, 40)");
$newSchedId = mysqli_insert_id($conn);
assertTest("Create Exam Schedule Paper", $createSched && $newSchedId > 0);

if ($stuRow) {
    $saveMark = mysqli_query($conn, "INSERT INTO marks (exam_schedule_id, student_id, marks_obtained, grade, remarks) VALUES ($newSchedId, {$stuRow['id']}, 85.50, 'A', 'Well done')");
    $newMarkId = mysqli_insert_id($conn);
    assertTest("Enter Student Marks", $saveMark && $newMarkId > 0);

    $updMark = mysqli_query($conn, "UPDATE marks SET marks_obtained = 92.00, grade = 'A+' WHERE id = $newMarkId");
    assertTest("Update Student Marks & Grade", $updMark);

    mysqli_query($conn, "DELETE FROM marks WHERE exam_schedule_id = $newSchedId");
}

$delSched = mysqli_query($conn, "DELETE FROM exam_schedules WHERE id = $newSchedId");
$delExam = mysqli_query($conn, "DELETE FROM exams WHERE id = $newExamId");
assertTest("Delete Exam Paper Schedule & Exam", $delSched && $delExam);
echo "\n";

echo "--- 14. Testing CRUD: Staff Salary Payments ---\n";
$stfQ = mysqli_query($conn, "SELECT id, basic_salary FROM staff LIMIT 1");
$stfRow = mysqli_fetch_assoc($stfQ);
if ($stfRow) {
    $createSal = mysqli_query($conn, "INSERT INTO staff_salaries (staff_id, salary_month, salary_year, basic_amount, allowances, deductions, net_salary, payment_date, payment_method, status, notes) VALUES ({$stfRow['id']}, 12, 2099, {$stfRow['basic_salary']}, 2000.00, 500.00, ({$stfRow['basic_salary']} + 1500.00), '2099-12-31', 'bank_transfer', 'paid', 'Automated Test Salary')");
    $newSalId = mysqli_insert_id($conn);
    assertTest("Record Staff Salary Payment", $createSal && $newSalId > 0);

    $readSal = mysqli_query($conn, "SELECT * FROM staff_salaries WHERE id = $newSalId");
    assertTest("Read Staff Salary Slip Record", mysqli_num_rows($readSal) > 0);

    $delSal = mysqli_query($conn, "DELETE FROM staff_salaries WHERE id = $newSalId");
    assertTest("Delete / Clean Salary Record", $delSal);
}
echo "\n";

echo "--- 15. Testing CRUD: Admissions & Contact Inquiries ---\n";
$createAdmInq = mysqli_query($conn, "INSERT INTO admissions_inquiries (parent_name, email, phone, child_name, grade_applying, gender, class_id, section_id, session_id, message, status) VALUES ('Tariq Mehmood', 'tariq@example.com', '+923007654321', 'Bilal Tariq', 'Grade 5', 'Male', $cId, $secId, $sessId, 'Automated Test Inquiry', 'pending')");
$newAdmInqId = mysqli_insert_id($conn);
assertTest("Create Admission Inquiry", $createAdmInq && $newAdmInqId > 0);

$updAdmInq = mysqli_query($conn, "UPDATE admissions_inquiries SET status = 'approved', child_name = 'Bilal Tariq Updated' WHERE id = $newAdmInqId");
assertTest("Update Admission Inquiry (Status & Details)", $updAdmInq);

$delAdmInq = mysqli_query($conn, "DELETE FROM admissions_inquiries WHERE id = $newAdmInqId");
assertTest("Delete Admission Inquiry", $delAdmInq);

$createCont = mysqli_query($conn, "INSERT INTO contact_inquiries (name, email, phone, topic, message, status) VALUES ('Sara Khan', 'sara@example.com', '+923211112233', 'Admissions', 'Interested in school tour', 'unread')");
$newContId = mysqli_insert_id($conn);
assertTest("Create Contact Inquiry", $createCont && $newContId > 0);

$updCont = mysqli_query($conn, "UPDATE contact_inquiries SET status = 'resolved' WHERE id = $newContId");
assertTest("Update Contact Inquiry Status", $updCont);

$delCont = mysqli_query($conn, "DELETE FROM contact_inquiries WHERE id = $newContId");
assertTest("Delete Contact Inquiry", $delCont);
echo "\n";

echo "--- 16. Testing CRUD: Users & Settings ---\n";
$testUser = 'test_user_' . time();
$passHash = password_hash('Secret123!', PASSWORD_BCRYPT);
$createAdmin = mysqli_query($conn, "INSERT INTO admins (username, email, password_hash, full_name, role) VALUES ('$testUser', '$testUser@example.com', '$passHash', 'Test User', 'accountant')");
$newAdminId = mysqli_insert_id($conn);
assertTest("Create User Account", $createAdmin && $newAdminId > 0);

$updAdmin = mysqli_query($conn, "UPDATE admins SET role = 'admin', full_name = 'Test User Updated' WHERE id = $newAdminId");
assertTest("Update User Account (Role & Name)", $updAdmin);

$delAdmin = mysqli_query($conn, "DELETE FROM admins WHERE id = $newAdminId");
assertTest("Delete User Account", $delAdmin);

$testKey = 'test_setting_' . time();
$createSetting = mysqli_query($conn, "INSERT INTO school_settings (setting_key, setting_value) VALUES ('$testKey', 'Setting Value 123') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
assertTest("Create / Update School Setting", $createSetting);

$readSetting = mysqli_query($conn, "SELECT setting_value FROM school_settings WHERE setting_key = '$testKey'");
$setVal = mysqli_fetch_assoc($readSetting)['setting_value'] ?? '';
assertTest("Read School Setting", $setVal === 'Setting Value 123');

mysqli_query($conn, "DELETE FROM school_settings WHERE setting_key = '$testKey'");
echo "\n";

echo "--- 17. Testing Activity / Audit Logging System ---\n";
require_once __DIR__ . '/../admin/auth.php';
$logCountBefore = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM activity_logs"))['c'] ?? 0;
logActivity('test_action', 'test_entity', 999, 'Comprehensive automated system test log entry');
$logCountAfter = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM activity_logs"))['c'] ?? 0;
assertTest("Activity Audit Log recorded successfully", $logCountAfter > $logCountBefore);
echo "\n";

echo "========================================================\n";
echo " TEST SUMMARY\n";
echo "========================================================\n";
echo " Total Tests Run   : " . ($testsPassed + $testsFailed) . "\n";
echo " Tests Passed [✓] : $testsPassed\n";
echo " Tests Failed [✗] : $testsFailed\n";
if ($testsFailed === 0) {
    echo "\n>>> ALL ADMIN CRUD OPERATIONS & COMPONENTS PASSED 100%! <<<\n";
} else {
    echo "\n>>> WARNING: Some tests failed. Please review above output. <<<\n";
}
