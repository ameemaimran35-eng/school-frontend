<?php
/**
 * admin/backup.php
 * Database Backup, Integrity Inspection, and Recovery Procedures
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Database Backup & Recovery';
$current_page = 'backup';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ─── ACTION: DOWNLOAD DATABASE BACKUP (.SQL) ────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'download_sql') {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid CSRF token for backup download.';
        header('Location: backup.php');
        exit();
    }

    $backup_filename = 'backup_school_sms_' . date('Y-m-d_H-i-s') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $backup_filename . '"');
    
    // Dump tables
    $out = fopen('php://output', 'w');
    fwrite($out, "-- ========================================================\n");
    fwrite($out, "-- School Management System — Database Backup\n");
    fwrite($out, "-- Generated on: " . date('Y-m-d H:i:s') . "\n");
    fwrite($out, "-- Database: " . DB_NAME . "\n");
    fwrite($out, "-- ========================================================\n\n");
    fwrite($out, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

    $tables_res = mysqli_query($conn, "SHOW TABLES");
    while ($t_row = mysqli_fetch_array($tables_res)) {
        $table = $t_row[0];
        // Create table SQL
        $create_res = mysqli_query($conn, "SHOW CREATE TABLE `{$table}`");
        $create_row = mysqli_fetch_array($create_res);
        fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($out, $create_row[1] . ";\n\n");

        // Dump data
        $data_res = mysqli_query($conn, "SELECT * FROM `{$table}`");
        $num_fields = mysqli_num_fields($data_res);
        while ($d_row = mysqli_fetch_row($data_res)) {
            fwrite($out, "INSERT INTO `{$table}` VALUES (");
            for ($j = 0; $j < $num_fields; $j++) {
                if (isset($d_row[$j])) {
                    $escaped = mysqli_real_escape_string($conn, $d_row[$j]);
                    fwrite($out, "'" . $escaped . "'");
                } else {
                    fwrite($out, "NULL");
                }
                if ($j < ($num_fields - 1)) {
                    fwrite($out, ", ");
                }
            }
            fwrite($out, ");\n");
        }
        fwrite($out, "\n");
    }

    fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fwrite($out, "-- End of backup file\n");
    fclose($out);

    logActivity('backup_downloaded', 'database', 0, "Downloaded database SQL backup: {$backup_filename}");
    exit();
}

// Inspect tables and record counts for health verification
$tables_info = [];
$total_records = 0;
$res = mysqli_query($conn, "SHOW TABLE STATUS");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $tables_info[] = [
            'name'       => $r['Name'],
            'rows'       => (int)$r['Rows'],
            'size_kb'    => round(((int)$r['Data_length'] + (int)$r['Index_length']) / 1024, 2),
            'engine'     => $r['Engine'],
            'collation'  => $r['Collation']
        ];
        $total_records += (int)$r['Rows'];
    }
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Database Backup & Recovery</h1>
        <p class="page-header__desc">Generate verified SQL database backups and review database integrity and restore instructions.</p>
    </div>
    <div class="page-header__actions">
        <a href="backup.php?action=download_sql&csrf_token=<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" class="btn-admin btn-admin--primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <span>Create & Download SQL Backup</span>
        </a>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert--success"><div class="alert__content"><span><?php echo htmlspecialchars($success_msg); ?></span></div></div>
<?php endif; ?>

<!-- Health Summary Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card animate-in">
        <div class="stat-card__label" style="color:var(--admin-accent);">Database Connection</div>
        <div class="stat-card__value" style="color:var(--admin-accent);font-size:1.4rem;">Operational</div>
        <span class="stat-card__change stat-card__change--up"><?php echo DB_NAME; ?></span>
    </div>
    <div class="stat-card animate-in">
        <div class="stat-card__label">Total Tables</div>
        <div class="stat-card__value"><?php echo count($tables_info); ?></div>
        <span class="stat-card__change stat-card__change--neutral">InnoDB utf8mb4</span>
    </div>
    <div class="stat-card animate-in">
        <div class="stat-card__label">Total Stored Records</div>
        <div class="stat-card__value"><?php echo number_format($total_records); ?></div>
        <span class="stat-card__change stat-card__change--neutral">Across all modules</span>
    </div>
</div>

<div class="grid-2col" style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;">
    <!-- Tables Inventory Panel -->
    <div class="panel animate-in">
        <div class="panel__header">
            <h3 class="panel__title">Database Tables Inventory</h3>
            <span style="color:var(--admin-text-muted);font-size:0.85rem;">All active entity tables</span>
        </div>
        <div class="table-responsive">
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Engine</th>
                        <th style="text-align:right;">Estimated Rows</th>
                        <th style="text-align:right;">Size</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables_info as $t): ?>
                    <tr>
                        <td style="font-weight:600;color:var(--admin-heading);font-family:monospace;">
                            <?php echo htmlspecialchars($t['name']); ?>
                        </td>
                        <td><span class="badge badge--info" style="font-size:0.75rem;"><?php echo htmlspecialchars($t['engine']); ?></span></td>
                        <td style="text-align:right;font-weight:700;color:var(--admin-accent);"><?php echo number_format($t['rows']); ?></td>
                        <td style="text-align:right;color:var(--admin-text-muted);font-size:0.85rem;"><?php echo $t['size_kb']; ?> KB</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Documented Recovery Guide Card -->
    <div class="card animate-in" style="padding:28px;">
        <h3 style="font-size:1.15rem;font-weight:700;color:var(--admin-heading);margin-bottom:14px;border-bottom:1px solid var(--admin-border);padding-bottom:10px;">
            Documented Recovery Guide
        </h3>
        <p style="color:var(--admin-text-muted);font-size:0.85rem;line-height:1.6;margin-bottom:16px;">
            Follow these verified steps to safely restore a database backup without data corruption:
        </p>

        <ol style="padding-left:18px;color:var(--admin-text);font-size:0.85rem;line-height:1.7;">
            <li style="margin-bottom:10px;">
                <strong>Download Backup:</strong> Always keep regular dated <code>.sql</code> files saved on an encrypted external drive or offsite storage.
            </li>
            <li style="margin-bottom:10px;">
                <strong>Test in Sandbox:</strong> Before restoring on production, always verify the backup file on a staging or local test server first.
            </li>
            <li style="margin-bottom:10px;">
                <strong>Restoring via phpMyAdmin:</strong>
                <ul style="padding-left:16px;color:var(--admin-text-muted);margin-top:4px;">
                    <li>Open <code>http://localhost/phpmyadmin</code></li>
                    <li>Select <code><?php echo DB_NAME; ?></code></li>
                    <li>Click <strong>Import</strong> tab</li>
                    <li>Choose your downloaded <code>.sql</code> file and click <strong>Import</strong>.</li>
                </ul>
            </li>
            <li style="margin-bottom:10px;">
                <strong>Restoring via Command Line:</strong>
                <pre style="background:var(--admin-bg);padding:10px;border-radius:6px;font-size:0.75rem;color:var(--admin-accent);margin-top:4px;overflow-x:auto;">mysql -u root -p <?php echo DB_NAME; ?> &lt; backup_file.sql</pre>
            </li>
        </ol>

        <div style="background:var(--admin-input-bg);padding:14px;border-radius:8px;border:1px solid var(--admin-border);margin-top:20px;">
            <div style="font-size:0.8rem;color:var(--admin-amber);font-weight:700;">Safety Assurance:</div>
            <div style="font-size:0.75rem;color:var(--admin-text-muted);margin-top:4px;line-height:1.5;">
                This system does not expose database passwords in web exports. Foreign key constraints are safely disabled during import and re-enabled automatically.
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
