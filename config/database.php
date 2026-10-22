<?php
/**
 * Database Configuration & Connection (MySQLi Procedural)
 * XAMPP Default: host=localhost, user=root, password="", database=school_management_db
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_management_db');
define('DB_PORT', 3306);

// Establish connection using MySQLi
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection and provide friendly guidance if database is not ready
if (!$conn) {
    $error_num = mysqli_connect_errno();
    $error_msg = mysqli_connect_error();
    
    // If running in browser, display a helpful setup guide instead of a cryptic crash
    if (php_sapi_name() !== 'cli') {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Database Connection Error — Brightway SMS</title>
            <style>
                body {
                    margin: 0;
                    padding: 40px 20px;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background-color: #0c1a26;
                    color: #f4f6f8;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 80vh;
                }
                .card {
                    background: #142535;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 18px;
                    padding: 32px 36px;
                    max-width: 600px;
                    box-shadow: 0 16px 40px rgba(0,0,0,0.4);
                }
                h2 { margin-top: 0; color: #ffc6dc; font-size: 1.5rem; }
                p { line-height: 1.6; color: #c4d1db; }
                .code-box {
                    background: #081119;
                    padding: 14px 18px;
                    border-radius: 8px;
                    font-family: Consolas, monospace;
                    font-size: 0.9rem;
                    color: #a9f7a3;
                    margin: 16px 0;
                    word-break: break-all;
                }
                ol { padding-left: 20px; line-height: 1.8; color: #e2e8f0; }
                li strong { color: #a9f7a3; }
                .note {
                    font-size: 0.85rem;
                    color: #8fa0ad;
                    margin-top: 20px;
                    border-top: 1px solid rgba(255, 255, 255, 0.08);
                    padding-top: 14px;
                }
            </style>
        </head>
        <body>
            <div class="card">
                <h2>Database Connection Required</h2>
                <p>The application could not connect to MySQL with the current settings.</p>
                <div class="code-box">Error [<?= htmlspecialchars((string)$error_num) ?>]: <?= htmlspecialchars($error_msg) ?></div>
                <p><strong>To resolve this in XAMPP:</strong></p>
                <ol>
                    <li>Open the <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> next to <strong>MySQL</strong>.</li>
                    <li>Open your browser and navigate to <strong>http://localhost/phpmyadmin</strong>.</li>
                    <li>Click the <strong>Import</strong> tab at the top.</li>
                    <li>Choose the file located at: <code>database/school_management_db.sql</code>.</li>
                    <li>Click <strong>Go / Import</strong> at the bottom of phpMyAdmin.</li>
                    <li>Refresh this page.</li>
                </ol>
                <div class="note">Settings: Host = <?= DB_HOST ?>, User = <?= DB_USER ?>, Database = <?= DB_NAME ?></div>
            </div>
        </body>
        </html>
        <?php
        exit;
    } else {
        die("Database Connection Error [" . $error_num . "]: " . $error_msg . "\n");
    }
}

// Set UTF-8 encoding for full international character and emoji support
mysqli_set_charset($conn, "utf8mb4");
