<?php
/**
 * admin/website_images.php
 * Website Section Images & Banners Management
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Website Images CMS';
$current_page = 'website_images';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Upload target directory
$upload_dir = __DIR__ . '/../uploads/website/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ─── POST HANDLER ───────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
        header('Location: website_images.php');
        exit();
    }

    $op = $_POST['op'] ?? '';

    // RESET TO DEFAULT IMAGE
    if ($op === 'reset') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conn, "SELECT image_path, label FROM website_images WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($row) {
            if (!empty($row['image_path'])) {
                $full_path = __DIR__ . '/../' . $row['image_path'];
                if (file_exists($full_path) && is_file($full_path) && strpos($row['image_path'], 'uploads/website/') !== false) {
                    @unlink($full_path);
                }
            }
            $upd = mysqli_prepare($conn, "UPDATE website_images SET image_path = NULL WHERE id = ?");
            mysqli_stmt_bind_param($upd, 'i', $id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            logActivity('website_image_reset', 'website_images', $id, "Reset image '{$row['label']}' to default");
            $_SESSION['flash_success'] = "Image '{$row['label']}' reset to default template illustration.";
        }
        header('Location: website_images.php');
        exit();
    }

    // UPDATE / UPLOAD IMAGE
    if ($op === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $alt_text = trim($_POST['alt_text'] ?? '');

        $stmt = mysqli_prepare($conn, "SELECT image_path, label FROM website_images WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            $_SESSION['flash_error'] = 'Image slot not found.';
            header('Location: website_images.php');
            exit();
        }

        $new_db_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $max_size = 5 * 1024 * 1024; // 5 MB
            if ($file['size'] > $max_size) {
                $errors[] = 'Image file size exceeds the 5MB limit.';
            }

            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_exts, true)) {
                $errors[] = 'Invalid file type. Only JPG, PNG, WEBP, and SVG formats are allowed.';
            }

            if (empty($errors)) {
                $new_filename = 'web_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $new_db_path = 'uploads/website/' . $new_filename;
                    // Delete old custom file if exists
                    if (!empty($row['image_path'])) {
                        $old_full = __DIR__ . '/../' . $row['image_path'];
                        if (file_exists($old_full) && is_file($old_full) && strpos($row['image_path'], 'uploads/website/') !== false) {
                            @unlink($old_full);
                        }
                    }
                } else {
                    $errors[] = 'Failed to move uploaded file. Check directory write permissions.';
                }
            }
        }

        if (empty($errors)) {
            if ($new_db_path) {
                $upd = mysqli_prepare($conn, "UPDATE website_images SET image_path = ?, alt_text = ? WHERE id = ?");
                mysqli_stmt_bind_param($upd, 'ssi', $new_db_path, $alt_text, $id);
            } else {
                $upd = mysqli_prepare($conn, "UPDATE website_images SET alt_text = ? WHERE id = ?");
                mysqli_stmt_bind_param($upd, 'si', $alt_text, $id);
            }
            if (mysqli_stmt_execute($upd)) {
                logActivity('website_image_updated', 'website_images', $id, "Updated website image '{$row['label']}'");
                $_SESSION['flash_success'] = "Image for '{$row['label']}' updated successfully.";
            } else {
                $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($upd);
        } else {
            $_SESSION['flash_error'] = implode(' ', $errors);
        }

        header('Location: website_images.php');
        exit();
    }
}

// Fetch all website images grouped by section
$sections = [];
$res = mysqli_query($conn, "SELECT * FROM website_images ORDER BY section_name ASC, id ASC");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $sections[$r['section_name']][] = $r;
    }
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Website Images & Banners</h1>
        <p class="page-header__desc">Update photographs, student illustrations, and section banners displayed across the public school website.</p>
    </div>
    <div class="page-header__actions" style="display:flex;gap:10px;">
        <a href="website_content.php" class="btn-admin btn-admin--ghost">Website Copy CMS</a>
        <a href="../index.php" target="_blank" class="btn-admin btn-admin--secondary">View Live Website ↗</a>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert--success"><div class="alert__content"><span><?php echo htmlspecialchars($success_msg); ?></span></div></div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert alert--danger"><div class="alert__content"><span><?php echo htmlspecialchars($error_msg); ?></span></div></div>
<?php endif; ?>

<?php if (!empty($sections)): ?>
    <?php foreach ($sections as $sec_name => $sec_images): ?>
        <div class="card animate-in" style="padding:28px;margin-bottom:28px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--admin-border);padding-bottom:12px;">
                <h3 style="font-size:1.2rem;font-weight:700;color:var(--admin-heading);margin:0;">
                    <?php echo htmlspecialchars($sec_name); ?>
                </h3>
                <span class="badge badge--info"><?php echo count($sec_images); ?> Image Slots</span>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:24px;">
                <?php foreach ($sec_images as $img): 
                    $has_custom = !empty($img['image_path']) && file_exists(__DIR__ . '/../' . $img['image_path']);
                    $display_src = '../' . ($has_custom ? $img['image_path'] : $img['fallback_path']);
                ?>
                <div class="card" style="padding:18px;border-radius:12px;background:var(--admin-input-bg,#0c1a26);border:1px solid var(--admin-border);display:flex;flex-direction:column;justify-content:space-between;">
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                            <div>
                                <h4 style="font-size:1rem;font-weight:700;color:var(--admin-heading);margin:0 0 4px 0;">
                                    <?php echo htmlspecialchars($img['label']); ?>
                                </h4>
                                <small style="color:var(--admin-text-muted);font-size:0.75rem;">Slot: <code><?php echo htmlspecialchars($img['image_key']); ?></code></small>
                            </div>
                            <?php if ($has_custom): ?>
                                <span class="badge badge--success" style="font-size:0.7rem;">Custom Upload</span>
                            <?php else: ?>
                                <span class="badge badge--secondary" style="font-size:0.7rem;">Default Template</span>
                            <?php endif; ?>
                        </div>

                        <!-- Current Preview Box -->
                        <div style="height:170px;border-radius:8px;overflow:hidden;background:#061019;display:flex;align-items:center;justify-content:center;border:1px solid var(--admin-border);margin-bottom:14px;">
                            <img src="<?php echo htmlspecialchars($display_src); ?>" alt="<?php echo htmlspecialchars($img['alt_text']); ?>" style="width:100%;height:100%;object-fit:contain;padding:8px;">
                        </div>
                    </div>

                    <!-- Upload / Replace Form -->
                    <form method="POST" action="website_images.php" enctype="multipart/form-data" style="margin-top:8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="op" value="update">
                        <input type="hidden" name="id" value="<?php echo $img['id']; ?>">

                        <div class="form-group" style="margin-bottom:10px;">
                            <label class="form-label" style="font-size:0.8rem;margin-bottom:4px;">Alt / Description Text</label>
                            <input type="text" name="alt_text" class="form-control" style="font-size:0.85rem;padding:6px 10px;" value="<?php echo htmlspecialchars($img['alt_text']); ?>" required>
                        </div>

                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label" style="font-size:0.8rem;margin-bottom:4px;">Upload New Picture</label>
                            <input type="file" name="image" class="form-control" style="font-size:0.85rem;padding:6px 10px;" accept="image/jpeg,image/png,image/webp,image/svg+xml">
                            <small style="color:var(--admin-text-muted);font-size:0.7rem;">JPG, PNG, WEBP, SVG (Max 5MB)</small>
                        </div>

                        <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;margin-top:12px;">
                            <button type="submit" class="btn-admin btn-admin--primary btn-admin--sm" style="flex:1;">
                                Update Picture
                            </button>
                            <?php if ($has_custom): ?>
                                <button type="button" class="btn-admin btn-admin--ghost btn-admin--sm" onclick="resetImage(<?php echo $img['id']; ?>, '<?php echo htmlspecialchars(addslashes($img['label'])); ?>')" title="Reset to default illustration" style="color:var(--admin-red,#ef4444);">
                                    Reset
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="empty-state"><p>No website image slots configured in database.</p></div>
<?php endif; ?>

<!-- Hidden form for reset action -->
<form id="resetForm" method="POST" action="website_images.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <input type="hidden" name="op" value="reset">
    <input type="hidden" name="id" id="reset_id">
</form>

<script>
function resetImage(id, label) {
    if (confirm('Are you sure you want to reset "' + label + '" back to default template illustration?')) {
        document.getElementById('reset_id').value = id;
        document.getElementById('resetForm').submit();
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
