<?php
/**
 * admin/settings.php
 * General School Settings Management
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'School Settings';
$current_page = 'settings';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ─── POST HANDLER: SAVE SETTINGS ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
        header('Location: settings.php');
        exit();
    }

    $settings_to_update = [
        'school_name'            => trim($_POST['school_name'] ?? ''),
        'school_tagline'         => trim($_POST['school_tagline'] ?? ''),
        'school_email'           => trim($_POST['school_email'] ?? ''),
        'school_phone'           => trim($_POST['school_phone'] ?? ''),
        'school_address'         => trim($_POST['school_address'] ?? ''),
        'office_hours'           => trim($_POST['office_hours'] ?? ''),
        'academic_session_label' => trim($_POST['academic_session_label'] ?? ''),
        'currency_symbol'        => trim($_POST['currency_symbol'] ?? 'Rs')
    ];

    if ($settings_to_update['school_name'] === '') {
        $errors[] = 'School Name cannot be empty.';
    }
    if ($settings_to_update['school_email'] !== '' && !filter_var($settings_to_update['school_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid school email address.';
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "INSERT INTO school_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($settings_to_update as $k => $v) {
                mysqli_stmt_bind_param($stmt, 'ss', $k, $v);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            logActivity('settings_updated', 'school_settings', 1, "School general settings updated");
            $_SESSION['flash_success'] = 'School settings updated successfully.';
            header('Location: settings.php');
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Database error while saving settings: ' . $e->getMessage();
        }
    }
}

// Fetch current settings
$settings = [];
$res = mysqli_query($conn, "SELECT setting_key, setting_value FROM school_settings");
while ($r = mysqli_fetch_assoc($res)) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">School Settings</h1>
        <p class="page-header__desc">Configure school identity, official contact details, receipt labels, and global defaults.</p>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert--success">
        <div class="alert__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span><?php echo htmlspecialchars($success_msg); ?></span>
        </div>
    </div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert alert--danger">
        <div class="alert__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert--danger">
        <div>
            <strong>Validation errors:</strong>
            <ul style="margin-left: 20px; margin-top: 6px;">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="card animate-in" style="padding:32px;max-width:800px;">
    <form method="POST" action="settings.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <h3 style="font-size:1.15rem;font-weight:700;color:var(--admin-heading);margin-bottom:18px;border-bottom:1px solid var(--admin-border);padding-bottom:10px;">
            School Identity & Branding
        </h3>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label">School Name *</label>
                <input type="text" name="school_name" class="form-control" value="<?php echo htmlspecialchars($settings['school_name'] ?? 'Brightway School'); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">School Tagline</label>
                <input type="text" name="school_tagline" class="form-control" value="<?php echo htmlspecialchars($settings['school_tagline'] ?? ''); ?>">
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label">Academic Session Label</label>
                <input type="text" name="academic_session_label" class="form-control" value="<?php echo htmlspecialchars($settings['academic_session_label'] ?? '2026 – 2027'); ?>" placeholder="e.g. 2026 – 2027">
            </div>
            <div class="form-group">
                <label class="form-label">Currency Symbol (Receipts & Reports)</label>
                <input type="text" name="currency_symbol" class="form-control" value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? 'Rs'); ?>" placeholder="Rs, $, etc.">
            </div>
        </div>

        <h3 style="font-size:1.15rem;font-weight:700;color:var(--admin-heading);margin-top:24px;margin-bottom:18px;border-bottom:1px solid var(--admin-border);padding-bottom:10px;">
            Contact Details & Receipt Header Info
        </h3>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label">Official Email</label>
                <input type="email" name="school_email" class="form-control" value="<?php echo htmlspecialchars($settings['school_email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Official Phone</label>
                <input type="text" name="school_phone" class="form-control" value="<?php echo htmlspecialchars($settings['school_phone'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Campus Address</label>
            <input type="text" name="school_address" class="form-control" value="<?php echo htmlspecialchars($settings['school_address'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Office Hours</label>
            <input type="text" name="office_hours" class="form-control" value="<?php echo htmlspecialchars($settings['office_hours'] ?? 'Monday – Friday, 8:00 AM – 4:00 PM'); ?>">
        </div>

        <div style="margin-top:24px;display:flex;justify-content:flex-end;">
            <button type="submit" class="btn-admin btn-admin--primary">
                Save School Settings
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/footer.php'; ?>
