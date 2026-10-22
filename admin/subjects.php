<?php
/**
 * admin/subjects.php
 * Subjects Management Module
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Subjects Management';
$current_page = 'subjects';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$action = $_GET['action'] ?? 'list';
$subject_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ─── POST HANDLERS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token). Please try again.';
        header('Location: subjects.php');
        exit();
    }
    $post_action = $_POST['action'] ?? '';

    // CREATE SUBJECT
    if ($post_action === 'create') {
        $subject_name = trim($_POST['subject_name'] ?? '');
        $subject_code = trim($_POST['subject_code'] ?? '');
        $subject_type = $_POST['subject_type'] ?? 'core';
        if ($subject_name === '') $errors[] = 'Subject name is required.';
        if ($subject_code === '') $errors[] = 'Subject code is required.';
        if (empty($errors)) {
            $chk = mysqli_prepare($conn, "SELECT id FROM subjects WHERE subject_code = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 's', $subject_code);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) $errors[] = "A subject with code '{$subject_code}' already exists.";
            mysqli_stmt_close($chk);
        }
        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO subjects (subject_name, subject_code, subject_type) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sss', $subject_name, $subject_code, $subject_type);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_success'] = "Subject '{$subject_name}' created successfully.";
                header('Location: subjects.php');
                exit();
            } else {
                $errors[] = 'Failed to create subject: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }

    // UPDATE SUBJECT
    if ($post_action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $subject_name = trim($_POST['subject_name'] ?? '');
        $subject_code = trim($_POST['subject_code'] ?? '');
        $subject_type = $_POST['subject_type'] ?? 'core';
        $class_ids = $_POST['class_ids'] ?? [];
        if ($id <= 0) $errors[] = 'Invalid subject identifier.';
        if ($subject_name === '') $errors[] = 'Subject name is required.';
        if ($subject_code === '') $errors[] = 'Subject code is required.';
        if (empty($errors)) {
            $chk = mysqli_prepare($conn, "SELECT id FROM subjects WHERE subject_code = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'si', $subject_code, $id);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) $errors[] = "Subject code '{$subject_code}' is already used by another subject.";
            mysqli_stmt_close($chk);
        }
        if (empty($errors)) {
            mysqli_begin_transaction($conn);
            $stmt = mysqli_prepare($conn, "UPDATE subjects SET subject_name = ?, subject_code = ?, subject_type = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'sssi', $subject_name, $subject_code, $subject_type, $id);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            // Update class_links
            $del = mysqli_prepare($conn, "DELETE FROM class_subjects WHERE subject_id = ?");
            mysqli_stmt_bind_param($del, 'i', $id);
            $ok = $ok && mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            if (!empty($class_ids) && $ok) {
                $ins = mysqli_prepare($conn, "INSERT INTO class_subjects (class_id, subject_id) VALUES (?, ?)");
                foreach ($class_ids as $cid) {
                    $cid = (int)$cid;
                    mysqli_stmt_bind_param($ins, 'ii', $cid, $id);
                    $ok = $ok && mysqli_stmt_execute($ins);
                }
                mysqli_stmt_close($ins);
            }
            if ($ok) {
                mysqli_commit($conn);
                $_SESSION['flash_success'] = "Subject '{$subject_name}' updated successfully.";
                header('Location: subjects.php');
                exit();
            } else {
                mysqli_rollback($conn);
                $errors[] = 'Failed to update subject: ' . mysqli_error($conn);
            }
        }
    }

    // DELETE SUBJECT
    if ($post_action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $dep_chk = mysqli_query($conn, "SELECT COUNT(*) AS c FROM class_subjects WHERE subject_id = $id");
            $dep_cnt = mysqli_fetch_assoc($dep_chk)['c'] ?? 0;
            if ($dep_cnt > 0) {
                $_SESSION['flash_error'] = "Cannot delete subject: it is linked to {$dep_cnt} class(es). Unlink first.";
            } else {
                $del = mysqli_prepare($conn, "DELETE FROM subjects WHERE id = ?");
                mysqli_stmt_bind_param($del, 'i', $id);
                if (mysqli_stmt_execute($del)) {
                    $_SESSION['flash_success'] = "Subject deleted successfully.";
                } else {
                    $_SESSION['flash_error'] = "Delete failed: " . mysqli_error($conn);
                }
                mysqli_stmt_close($del);
            }
        }
        header('Location: subjects.php');
        exit();
    }
}

// ─── LOAD DATA FOR EDIT ──────────────────────────────────────────
$edit_subject = null;
if ($action === 'edit' && $subject_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM subjects WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $subject_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $edit_subject = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$edit_subject) {
        $_SESSION['flash_error'] = 'Subject not found.';
        header('Location: subjects.php');
        exit();
    }
    $assoc_res = mysqli_query($conn, "SELECT class_id FROM class_subjects WHERE subject_id = {$subject_id}");
    $associated_classes = [];
    while ($row = mysqli_fetch_assoc($assoc_res)) $associated_classes[] = $row['class_id'];
}

// Fetch all subjects with class count
$subjects_list = [];
$res = mysqli_query($conn, "SELECT s.*, COUNT(cs.class_id) AS class_count FROM subjects s LEFT JOIN class_subjects cs ON s.id = cs.subject_id GROUP BY s.id ORDER BY s.subject_name ASC");
while ($r = mysqli_fetch_assoc($res)) $subjects_list[] = $r;

// Fetch all classes for association dropdowns
$classes = [];
$res = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY numeric_grade ASC, class_name ASC");
while ($row = mysqli_fetch_assoc($res)) $classes[] = $row;

include __DIR__ . '/header.php';
?>
<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Subjects Management</h1>
        <p class="page-header__desc">Create, edit and assign subjects to classes.</p>
    </div>
    <div class="page-header__actions">
        <?php if ($action === 'edit'): ?>
            <a href="subjects.php" class="btn-admin btn-admin--ghost">← Cancel Edit</a>
        <?php endif; ?>
    </div>
</div>
<?php if ($success_msg): ?>
    <div class="alert alert--success"><div class="alert__content"><span><?php echo htmlspecialchars($success_msg); ?></span></div></div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert alert--danger"><div class="alert__content"><span><?php echo htmlspecialchars($error_msg); ?></span></div></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert--danger"><div><strong>Please resolve the following:</strong><ul style="margin-left:20px;margin-top:6px;">
        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
    </ul></div></div>
<?php endif; ?>
<div class="dash-grid" style="grid-template-columns: 360px 1fr; align-items: start;">
    <div class="form-card animate-in">
        <div class="form-card__header"><h2 class="form-card__title"><?php echo $edit_subject ? 'Edit Subject' : 'Add New Subject'; ?></h2></div>
        <form method="POST" action="subjects.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="<?php echo $edit_subject ? 'update' : 'create'; ?>">
            <?php if ($edit_subject): ?><input type="hidden" name="id" value="<?php echo $edit_subject['id']; ?>"><?php endif; ?>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="subject_name">Subject Name <span class="required">*</span></label>
                <input type="text" id="subject_name" name="subject_name" class="form-control" value="<?php echo htmlspecialchars($edit_subject['subject_name'] ?? ($_POST['subject_name'] ?? '')); ?>" placeholder="e.g. Mathematics" required>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="subject_code">Subject Code <span class="required">*</span></label>
                <input type="text" id="subject_code" name="subject_code" class="form-control" value="<?php echo htmlspecialchars($edit_subject['subject_code'] ?? ($_POST['subject_code'] ?? '')); ?>" placeholder="e.g. MTH-101" required>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="subject_type">Subject Type</label>
                <select id="subject_type" name="subject_type" class="form-control" required>
                    <?php $types = ['core','elective','practical']; foreach ($types as $t): ?>
                        <option value="<?php echo $t; ?>" <?php echo (isset($edit_subject['subject_type']) && $edit_subject['subject_type'] === $t) || ($_POST['subject_type'] ?? '') === $t ? 'selected' : ''; ?>><?php echo ucfirst($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="class_ids">Assign to Classes (Ctrl/Cmd for multiple)</label>
                <select id="class_ids" name="class_ids[]" class="form-control" multiple size="5">
                    <?php foreach ($classes as $cls): ?>
                        <option value="<?php echo $cls['id']; ?>" <?php
                            $selected = false;
                            if ($edit_subject) $selected = in_array($cls['id'], $associated_classes ?? []);
                            else $selected = in_array($cls['id'], $_POST['class_ids'] ?? []);
                            echo $selected ? 'selected' : '';
                        ?>><?php echo htmlspecialchars($cls['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint">Hold Ctrl/Cmd to select multiple classes.</small>
            </div>
            <div class="form-actions" style="margin-top:0;padding-top:16px;">
                <?php if ($edit_subject): ?>
                    <a href="subjects.php" class="btn-admin btn-admin--secondary">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="btn-admin btn-admin--primary"><?php echo $edit_subject ? 'Save Changes' : 'Create Subject'; ?></button>
            </div>
        </form>
    </div>
    <div class="panel animate-in">
        <div class="panel__header"><h3 class="panel__title">Registered Subjects (<?php echo count($subjects_list); ?>)</h3></div>
        <?php if (!empty($subjects_list)): ?>
            <div class="table-responsive"><table class="recent-table"><thead><tr><th>Name</th><th>Code</th><th>Type</th><th>Classes Assigned</th><th style="text-align:right;">Actions</th></tr></thead><tbody>
                <?php foreach ($subjects_list as $sub): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($sub['subject_name']); ?></strong></td>
                        <td><span class="badge badge--info"><?php echo htmlspecialchars($sub['subject_code']); ?></span></td>
                        <td><span class="badge badge--secondary"><?php echo htmlspecialchars($sub['subject_type']); ?></span></td>
                        <td><?php echo $sub['class_count']; ?> class(es)</td>
                        <td style="text-align:right;">
                            <div class="table-actions" style="justify-content:flex-end;">
                                <a href="subjects.php?action=edit&id=<?php echo $sub['id']; ?>" class="action-btn action-btn--edit" title="Edit Subject"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                                <?php if ($sub['class_count'] == 0): ?>
                                    <form method="POST" action="subjects.php" style="display:inline;" onsubmit="return confirm('Delete subject <?php echo htmlspecialchars($sub['subject_name']); ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                                        <button type="submit" class="action-btn action-btn--delete" title="Delete Subject"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="action-btn action-btn--disabled" disabled title="Cannot delete — assigned to <?php echo $sub['class_count']; ?> class(es)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <div class="empty-state"><p>No subjects registered yet.</p></div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
