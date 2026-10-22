<?php
/**
 * Diagnostic & Verification Script for Database Setup
 * Usage:
 *  - CLI: php config/check-db.php
 *  - Browser: http://localhost/school-management-system/config/check-db.php
 */

$is_cli = (php_sapi_name() === 'cli');

function out($msg, $is_cli) {
    if ($is_cli) {
        echo $msg . "\n";
    } else {
        echo "<li>" . htmlspecialchars($msg) . "</li>";
    }
}

if (!$is_cli) {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Database Setup Check</title><style>body{font-family:sans-serif;padding:30px;background:#0c1a26;color:#f4f6f8;}ul{line-height:1.8;}strong{color:#a9f7a3;}</style></head><body>';
    echo '<h2>Brightway SMS — Database Setup Verification</h2><ul>';
}

out("[1/5] Checking PHP environment...", $is_cli);
out("  PHP Version: " . phpversion(), $is_cli);
if (!extension_loaded('mysqli')) {
    out("  [ERROR] MySQLi extension is not loaded in PHP!", $is_cli);
    exit(1);
}
out("  MySQLi extension is available and loaded.", $is_cli);

out("\n[2/5] Testing MySQL connection...", $is_cli);
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'school_management_db';

$test_conn = @mysqli_connect($host, $user, $pass);
if (!$test_conn) {
    out("  [FAIL] Cannot connect to MySQL server at $host: " . mysqli_connect_error(), $is_cli);
    out("  -> Make sure MySQL is started in XAMPP Control Panel.", $is_cli);
    exit(1);
}
out("  [OK] Connected to MySQL server successfully.", $is_cli);

out("\n[3/5] Checking database '$db'...", $is_cli);
$db_selected = @mysqli_select_db($test_conn, $db);
if (!$db_selected) {
    out("  [FAIL] Database '$db' does not exist yet.", $is_cli);
    out("  -> Import 'database/school_management_db.sql' in phpMyAdmin first.", $is_cli);
    exit(1);
}
out("  [OK] Database '$db' found and selected.", $is_cli);

out("\n[4/5] Inspecting tables...", $is_cli);
$tables_res = mysqli_query($test_conn, "SHOW TABLES;");
$tables = [];
while ($row = mysqli_fetch_row($tables_res)) {
    $tables[] = $row[0];
}
out("  Found " . count($tables) . " tables in database.", $is_cli);

$expected_tables = [
    'admins', 'academic_sessions', 'classes', 'sections', 'subjects', 'class_subjects',
    'staff', 'students', 'admissions_inquiries', 'fee_structures', 'student_fee_assignments',
    'fee_payments', 'attendance', 'exams', 'exam_schedules', 'marks', 'staff_salaries',
    'financial_transactions', 'timetables', 'holidays', 'announcements', 'website_content',
    'website_images', 'gallery_images', 'contact_inquiries', 'school_settings'
];

$missing = array_diff($expected_tables, $tables);
if (!empty($missing)) {
    out("  [WARNING] Missing tables: " . implode(', ', $missing), $is_cli);
} else {
    out("  [OK] All 26 required tables are present!", $is_cli);
}

out("\n[5/5] Checking fallback SVG assets on disk...", $is_cli);
$base_dir = dirname(__DIR__);
$sample_assets = [
    'assets/images/student-1.svg',
    'assets/images/student-2.svg',
    'assets/images/student-3.svg',
    'assets/images/about-teacher.svg',
    'assets/images/icons.svg',
    'assets/images/gallery/campus-1.svg',
    'assets/images/gallery/activity-1.svg'
];

$all_assets_ok = true;
foreach ($sample_assets as $asset) {
    if (file_exists($base_dir . '/' . $asset)) {
        out("  [OK] Found $asset (" . filesize($base_dir . '/' . $asset) . " bytes)", $is_cli);
    } else {
        out("  [FAIL] Missing asset: $asset", $is_cli);
        $all_assets_ok = false;
    }
}

if ($all_assets_ok && empty($missing)) {
    out("\n===> VERIFICATION PASSED: Database schema and assets are properly aligned!", $is_cli);
}

if (!$is_cli) {
    echo '</ul></body></html>';
}
