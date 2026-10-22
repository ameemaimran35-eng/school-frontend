<?php
/**
 * admin/website_content.php
 * Public Website Dynamic Content Management
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Website Content CMS';
$current_page = 'website_content';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ─── POST HANDLER: UPDATE CONTENT ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
        header('Location: website_content.php');
        exit();
    }

    $content_values = $_POST['content'] ?? [];
    if (!empty($content_values)) {
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "UPDATE website_content SET content_value = ? WHERE id = ?");
            foreach ($content_values as $id => $val) {
                $c_id = (int)$id;
                $c_val = trim($val);
                mysqli_stmt_bind_param($stmt, 'si', $c_val, $c_id);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            logActivity('website_content_updated', 'website_content', 0, "Updated website content text elements");
            $_SESSION['flash_success'] = 'Website content elements updated successfully.';
            header('Location: website_content.php');
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch all content items grouped by section
$items = [];
$res = mysqli_query($conn, "SELECT * FROM website_content ORDER BY section_name ASC, id ASC");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $items[$r['section_name']][] = $r;
    }
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Website Content CMS</h1>
        <p class="page-header__desc">Update editable copy, headlines, and contact elements on the public school website.</p>
    </div>
    <div class="page-header__actions" style="display:flex;gap:10px;">
        <a href="website_images.php" class="btn-admin btn-admin--primary">Manage Website Images 📸</a>
        <a href="../index.php" target="_blank" class="btn-admin btn-admin--secondary">View Live Website ↗</a>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert--success"><div class="alert__content"><span><?php echo htmlspecialchars($success_msg); ?></span></div></div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert alert--danger"><div class="alert__content"><span><?php echo htmlspecialchars($error_msg); ?></span></div></div>
<?php endif; ?>

<?php if (!empty($items)): ?>
    <form method="POST" action="website_content.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <?php foreach ($items as $sec_name => $sec_items): ?>
            <div class="card animate-in" style="padding:28px;margin-bottom:24px;">
                <h3 style="font-size:1.15rem;font-weight:700;color:var(--admin-heading);margin-bottom:18px;border-bottom:1px solid var(--admin-border);padding-bottom:10px;text-transform:capitalize;">
                    Section: <?php echo htmlspecialchars(str_replace('_', ' ', $sec_name)); ?>
                </h3>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:20px;">
                    <?php foreach ($sec_items as $it): ?>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" style="font-weight:600;"><?php echo htmlspecialchars($it['label']); ?></label>
                            <?php if ($it['content_type'] === 'textarea'): ?>
                                <textarea name="content[<?php echo $it['id']; ?>]" class="form-control" rows="3"><?php echo htmlspecialchars($it['content_value']); ?></textarea>
                            <?php else: ?>
                                <input type="<?php echo $it['content_type'] === 'email' ? 'email' : ($it['content_type'] === 'phone' ? 'tel' : 'text'); ?>" 
                                       name="content[<?php echo $it['id']; ?>]" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($it['content_value']); ?>">
                            <?php endif; ?>
                            <small style="color:var(--admin-text-muted);font-size:0.75rem;">Key: <code><?php echo htmlspecialchars($it['content_key']); ?></code></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div style="position:sticky;bottom:20px;display:flex;justify-content:flex-end;z-index:10;">
            <button type="submit" class="btn-admin btn-admin--primary" style="box-shadow:0 8px 24px rgba(0,0,0,0.3);padding:12px 28px;font-size:1rem;">
                Save All Content Changes
            </button>
        </div>
    </form>
<?php else: ?>
    <div class="empty-state"><p>No editable content elements found in database.</p></div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
