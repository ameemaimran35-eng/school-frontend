<?php
/**
 * Master Test Suite for Brightway School Management System
 * Executes all 3 role test suites:
 * - Admin Suite (89 tests)
 * - Accountant Suite (47 tests)
 * - Receptionist Suite (41 tests)
 */
echo "====================================================================\n";
echo " BRIGHTWAY SCHOOL MANAGEMENT SYSTEM - COMPREHENSIVE INTEGRATION SUITE\n";
echo "====================================================================\n\n";

$suites = [
    'Admin Portal Suite'        => __DIR__ . '/test_admin_suite.php',
    'Accountant Portal Suite'   => __DIR__ . '/test_accountant_stage.php',
    'Receptionist Portal Suite' => __DIR__ . '/test_receptionist_stage.php',
];

$allPassed = true;
foreach ($suites as $name => $path) {
    echo "====================================================================\n";
    echo " RUNNING: $name\n";
    echo "====================================================================\n";
    passthru('C:\xampp\php\php.exe ' . escapeshellarg($path), $returnCode);
    if ($returnCode !== 0) {
        $allPassed = false;
        echo "\n[✗] FAILED: $name exited with code $returnCode\n\n";
    } else {
        echo "\n[✓] PASSED: $name completed successfully!\n\n";
    }
}

echo "====================================================================\n";
if ($allPassed) {
    echo "🎉 ALL ROLES VERIFIED (Admin, Accountant, Receptionist) - 100% PASS!\n";
} else {
    echo "❌ INTEGRATION REGRESSION DETECTED.\n";
    exit(1);
}
echo "====================================================================\n";
