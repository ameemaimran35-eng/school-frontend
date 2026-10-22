<?php
/**
 * Test Database Connection
 *
 * Run via command line: php -f tests/test_db_connection.php
 */
require __DIR__ . '/../config/database.php';

if (!$conn) {
    echo "❌ Connection failed. Check config/database.php for details.\n";
    exit(1);
}

// Simple verification query
$res = mysqli_query($conn, "SELECT DATABASE() AS db_name");
if ($res && $row = mysqli_fetch_assoc($res)) {
    echo "✅ Connected to MySQL. Database: {$row['db_name']}\n";
} else {
    echo "❌ Query error: " . mysqli_error($conn) . "\n";
    exit(1);
}
?>
