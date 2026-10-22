<?php
/**
 * admin/gallery.php
 * School Website Gallery Media Management
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Gallery Media Management';
$current_page = 'gallery';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Upload target directory
$upload_dir = __DIR__ . '/../uploads/gallery/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ─── POST HANDLERS ──────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
        header('Location: gallery.php');
        exit();
    }

    $op = $_POST['op'] ?? '';

    // UPLOAD IMAGE
    if ($op === 'upload') {
        $title = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? 'campus';
        $alt_text = trim($_POST['alt_text'] ?? $title);
        $display_order = (int)($_POST['display_order'] ?? 0);

        if ($title === '') $errors[] = 'Image title / caption is required.';
        if (!in_array($category, ['campus', 'activities', 'events', 'classrooms'], true)) {
            $category = 'campus';
        }

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please select a valid image file to upload.';
        } else {
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
                $new_filename = 'gallery_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destination = $upload_dir . $new_filename;
                $db_path = 'uploads/gallery/' . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $stmt = mysqli_prepare($conn, "INSERT INTO gallery_images (category, title, image_path, alt_text, display_order, is_published) VALUES (?, ?, ?, ?, ?, 1)");
                    mysqli_stmt_bind_param($stmt, 'ssssi', $category, $title, $db_path, $alt_text, $display_order);
                    if (mysqli_stmt_execute($stmt)) {
                        $new_id = mysqli_insert_id($conn);
                        logActivity('gallery_uploaded', 'gallery_images', $new_id, "Uploaded gallery image '{$title}' ({$category})");
                        $_SESSION['flash_success'] = 'Gallery photo successfully uploaded and published.';
                    } else {
                        $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $errors[] = 'Failed to move uploaded file. Check directory permissions.';
                }
            }
        }
        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
        }
        header('Location: gallery.php');
        exit();
    }

    // EDIT / REPLACE IMAGE
    if ($op === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? 'campus';
        $alt_text = trim($_POST['alt_text'] ?? $title);
        $display_order = (int)($_POST['display_order'] ?? 0);

        if ($title === '') $errors[] = 'Image title / caption is required.';
        if (!in_array($category, ['campus', 'activities', 'events', 'classrooms'], true)) {
            $category = 'campus';
        }

        $stmt = mysqli_prepare($conn, "SELECT image_path FROM gallery_images WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            $errors[] = 'Photo not found.';
        }

        $new_db_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $max_size = 5 * 1024 * 1024;
            if ($file['size'] > $max_size) {
                $errors[] = 'Replacement image file size exceeds the 5MB limit.';
            }
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_exts, true)) {
                $errors[] = 'Invalid file type. Only JPG, PNG, WEBP, and SVG formats are allowed.';
            }

            if (empty($errors)) {
                $new_filename = 'gallery_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destination = $upload_dir . $new_filename;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $new_db_path = 'uploads/gallery/' . $new_filename;
                    $old_full = __DIR__ . '/../' . $row['image_path'];
                    if (file_exists($old_full) && is_file($old_full) && strpos($row['image_path'], 'uploads/gallery/') !== false) {
                        @unlink($old_full);
                    }
                } else {
                    $errors[] = 'Failed to save uploaded replacement photo.';
                }
            }
        }

        if (empty($errors)) {
            if ($new_db_path) {
                $upd = mysqli_prepare($conn, "UPDATE gallery_images SET title = ?, category = ?, alt_text = ?, display_order = ?, image_path = ? WHERE id = ?");
                mysqli_stmt_bind_param($upd, 'sssisi', $title, $category, $alt_text, $display_order, $new_db_path, $id);
            } else {
                $upd = mysqli_prepare($conn, "UPDATE gallery_images SET title = ?, category = ?, alt_text = ?, display_order = ? WHERE id = ?");
                mysqli_stmt_bind_param($upd, 'sssii', $title, $category, $alt_text, $display_order, $id);
            }
            if (mysqli_stmt_execute($upd)) {
                logActivity('gallery_updated', 'gallery_images', $id, "Updated gallery photo #{$id} '{$title}'");
                $_SESSION['flash_success'] = 'Gallery photo updated successfully.';
            } else {
                $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($upd);
        } else {
            $_SESSION['flash_error'] = implode(' ', $errors);
        }
        header('Location: gallery.php');
        exit();
    }

    // DELETE IMAGE
    if ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conn, "SELECT image_path FROM gallery_images WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($row) {
            $full_path = __DIR__ . '/../' . $row['image_path'];
            if (file_exists($full_path) && is_file($full_path) && strpos($row['image_path'], 'uploads/gallery/') !== false) {
                @unlink($full_path);
            }
            $del = mysqli_prepare($conn, "DELETE FROM gallery_images WHERE id = ?");
            mysqli_stmt_bind_param($del, 'i', $id);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            logActivity('gallery_deleted', 'gallery_images', $id, "Deleted gallery photo #{$id}");
            $_SESSION['flash_success'] = 'Gallery item deleted.';
        }
        header('Location: gallery.php');
        exit();
    }
}

// Fetch images
$cat_filter = $_GET['cat'] ?? '';
$where = "WHERE 1=1";
$params = [];
$types = '';
if ($cat_filter && in_array($cat_filter, ['campus', 'activities', 'events', 'classrooms'], true)) {
    $where .= " AND category = ?";
    $params[] = $cat_filter;
    $types .= 's';
}

$query = "SELECT * FROM gallery_images $where ORDER BY display_order ASC, id DESC";
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$images = [];
while ($r = mysqli_fetch_assoc($res)) {
    $images[] = $r;
}
mysqli_stmt_close($stmt);

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Gallery Media Management</h1>
        <p class="page-header__desc">Upload and curate photographs displayed on the public school website gallery.</p>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert--success"><div class="alert__content"><span><?php echo htmlspecialchars($success_msg); ?></span></div></div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert alert--danger"><div class="alert__content"><span><?php echo htmlspecialchars($error_msg); ?></span></div></div>
<?php endif; ?>

<style>
.gallery-admin-layout {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    align-items: start;
}
.gallery-photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    padding: 20px;
}
@media (max-width: 900px) {
    .gallery-admin-layout {
        grid-template-columns: 1fr !important;
    }
}
@media (max-width: 480px) {
    .gallery-photo-grid {
        grid-template-columns: 1fr;
        padding: 12px;
    }
}
</style>

<div class="grid-2col gallery-admin-layout">
    <!-- Gallery Grid Panel -->
    <div class="panel animate-in">
        <div class="panel__header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <h3 class="panel__title">Curated Photos (<?php echo count($images); ?>)</h3>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <a href="gallery.php" class="btn-admin btn-admin--sm <?php echo empty($cat_filter) ? 'btn-admin--primary' : 'btn-admin--ghost'; ?>">All</a>
                <a href="gallery.php?cat=campus" class="btn-admin btn-admin--sm <?php echo $cat_filter === 'campus' ? 'btn-admin--primary' : 'btn-admin--ghost'; ?>">Campus</a>
                <a href="gallery.php?cat=activities" class="btn-admin btn-admin--sm <?php echo $cat_filter === 'activities' ? 'btn-admin--primary' : 'btn-admin--ghost'; ?>">Activities</a>
                <a href="gallery.php?cat=events" class="btn-admin btn-admin--sm <?php echo $cat_filter === 'events' ? 'btn-admin--primary' : 'btn-admin--ghost'; ?>">Events</a>
            </div>
        </div>

        <?php if (!empty($images)): ?>
            <div class="gallery-photo-grid">
                <?php foreach ($images as $img): 
                    $src = '../' . $img['image_path'];
                ?>
                <div class="card" style="padding:10px;border-radius:10px;background:var(--admin-input-bg);border:1px solid var(--admin-border);position:relative;">
                    <div style="height:140px;border-radius:6px;overflow:hidden;background:#0c1a26;position:relative;display:flex;align-items:center;justify-content:center;">
                        <img src="<?php echo htmlspecialchars($src); ?>" alt="<?php echo htmlspecialchars($img['alt_text']); ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
                    </div>
                    <div style="margin-top:10px;">
                        <div style="font-weight:700;font-size:0.9rem;color:var(--admin-heading);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($img['title']); ?>">
                            <?php echo htmlspecialchars($img['title']); ?>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
                            <span class="badge badge--info" style="font-size:0.7rem;"><?php echo ucfirst($img['category']); ?></span>
                            <div style="display:flex;gap:4px;align-items:center;">
                                <button type="button" class="action-btn action-btn--edit" onclick='openEditModal(<?php echo json_encode($img); ?>)' title="Change / Edit Photo" style="background:none;border:none;cursor:pointer;color:var(--admin-primary,#1ea7ec);">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <form method="POST" action="gallery.php" onsubmit="return confirm('Delete this photo?');" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="op" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $img['id']; ?>">
                                    <button type="submit" class="action-btn action-btn--delete" style="background:none;border:none;cursor:pointer;" title="Delete">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><p>No photos in this category yet.</p></div>
        <?php endif; ?>
    </div>

    <!-- Upload Form Card -->
    <div class="card animate-in" style="padding:24px;">
        <h3 style="font-size:1.1rem;font-weight:700;color:var(--admin-heading);margin-bottom:16px;">Upload Gallery Photo</h3>
        <form method="POST" action="gallery.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="op" value="upload">

            <div class="form-group">
                <label class="form-label">Photo Title / Caption *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Science Fair Exhibition" required>
            </div>

            <div class="form-group">
                <label class="form-label">Category *</label>
                <select name="category" class="form-control" required>
                    <option value="campus">Campus</option>
                    <option value="activities">Activities</option>
                    <option value="events">Events</option>
                    <option value="classrooms">Classrooms</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Choose File (JPG, PNG, WEBP, SVG) *</label>
                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp,image/svg+xml" required>
                <small style="color:var(--admin-text-muted);font-size:0.75rem;">Max file size: 5 MB</small>
            </div>

            <div class="form-group">
                <label class="form-label">Display Order</label>
                <input type="number" name="display_order" class="form-control" value="0">
            </div>

            <button type="submit" class="btn-admin btn-admin--primary" style="width:100%;margin-top:6px;">
                Upload & Publish
            </button>
        </form>
    </div>
</div>

<!-- Edit / Change Photo Modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:9999;align-items:center;justify-content:center;padding:16px;">
    <div class="card animate-in" style="max-width:500px;width:100%;padding:28px;background:var(--admin-card-bg,#142535);border:1px solid var(--admin-border);border-radius:14px;box-shadow:0 20px 50px rgba(0,0,0,0.5);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;border-bottom:1px solid var(--admin-border);padding-bottom:12px;">
            <h3 style="font-size:1.15rem;font-weight:700;color:var(--admin-heading);margin:0;">Edit / Change Photo</h3>
            <button type="button" onclick="closeEditModal()" style="background:none;border:none;color:var(--admin-text-muted);cursor:pointer;font-size:1.4rem;line-height:1;">&times;</button>
        </div>

        <form method="POST" action="gallery.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="op" value="edit">
            <input type="hidden" name="id" id="edit_id">

            <div style="margin-bottom:16px;">
                <label class="form-label" style="display:block;">Current Picture</label>
                <div style="height:150px;border-radius:8px;overflow:hidden;background:#0c1a26;display:flex;align-items:center;justify-content:center;border:1px solid var(--admin-border);">
                    <img id="edit_preview" src="" alt="Current photo" style="width:100%;height:100%;object-fit:cover;">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Photo Title / Caption *</label>
                <input type="text" name="title" id="edit_title" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Category *</label>
                <select name="category" id="edit_category" class="form-control" required>
                    <option value="campus">Campus</option>
                    <option value="activities">Activities</option>
                    <option value="events">Events</option>
                    <option value="classrooms">Classrooms</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Change / Replace Picture (Optional)</label>
                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp,image/svg+xml">
                <small style="color:var(--admin-text-muted);font-size:0.75rem;">Leave empty to keep the current picture. Select a new file to replace it.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Display Order</label>
                <input type="number" name="display_order" id="edit_order" class="form-control" value="0">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" onclick="closeEditModal()" class="btn-admin btn-admin--ghost">Cancel</button>
                <button type="submit" class="btn-admin btn-admin--primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(img) {
    document.getElementById('edit_id').value = img.id;
    document.getElementById('edit_title').value = img.title;
    document.getElementById('edit_category').value = img.category;
    document.getElementById('edit_order').value = img.display_order;
    document.getElementById('edit_preview').src = '../' + img.image_path;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
