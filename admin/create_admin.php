<?php
// admin/create_admin.php - Temporary script to create a default admin account
session_start();
require_once __DIR__ . '/../config/database.php';

// Define default admin credentials
$default_username = 'admin';
$default_email = 'admin@brightway.edu';
$default_password = 'admin123'; // Change after first login

// Check if an admin already exists
$check_sql = "SELECT COUNT(*) AS cnt FROM admins";
$res = mysqli_query($conn, $check_sql);
if ($res) {
    $row = mysqli_fetch_assoc($res);
    if ($row['cnt'] > 0) {
        echo "Admin account(s) already exist. No action taken.";
        exit;
    }
}

// Hash the password securely
$password_hash = password_hash($default_password, PASSWORD_DEFAULT);

// Insert the admin record
$insert_sql = "INSERT INTO admins (username, email, password_hash, full_name, role) VALUES (?, ?, ?, 'Default Admin', 'admin')";
$stmt = mysqli_prepare($conn, $insert_sql);
if (!$stmt) {
    echo "Failed to prepare statement: " . mysqli_error($conn);
    exit;
}
mysqli_stmt_bind_param($stmt, 'sss', $default_username, $default_email, $password_hash);
if (mysqli_stmt_execute($stmt)) {
    echo "Default admin created successfully. Username: $default_username, Password: $default_password";
} else {
    echo "Failed to create admin: " . mysqli_stmt_error($stmt);
}
mysqli_stmt_close($stmt);
?>
