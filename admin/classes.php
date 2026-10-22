<?php
/**
 * admin/classes.php
 * Classes & Sections Management Module
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Classes & Sections';
$current_page = 'classes';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$action = $_GET['action'] ?? 'list';
$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tab = $_GET['tab'] ?? 'classes'; // 'classes' or 'sections'

// ─── POST HANDLERS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token). Please try again.';
        header('Location: classes.php');
        exit();
    }

    $post_action = $_POST['action'] ?? '';

    // CREATE CLASS
    if ($post_action === 'create_class') {
        $class_name    = trim($_POST['class_name'] ?? '');
        $numeric_grade = isset($_POST['numeric_grade']) ? (int)$_POST['numeric_grade'] : 0;

        if ($class_name === '') {
            $errors[] = 'Class name is required (e.g. Grade 5, Primary).';
        } else {
            $chk = mysqli_prepare($conn, "SELECT id FROM classes WHERE class_name = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 's', $class_name);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) {
                $errors[] = "A class named '{$class_name}' already exists.";
            }
            mysqli_stmt_close($chk);
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO classes (class_name, numeric_grade) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'si', $class_name, $numeric_grade);
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                // Also create default Section A for convenience
                $sec_stmt = mysqli_prepare($conn, "INSERT INTO sections (class_id, section_name, capacity) VALUES (?, 'A', 30)");
                mysqli_stmt_bind_param($sec_stmt, 'i', $new_id);
                mysqli_stmt_execute($sec_stmt);
                mysqli_stmt_close($sec_stmt);

                mysqli_stmt_close($stmt);
                $_SESSION['flash_success'] = "Class '{$class_name}' created successfully with Section A.";
                header('Location: classes.php');
                exit();
            } else {
                $errors[] = 'Failed to create class: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }

    // UPDATE CLASS
    if ($post_action === 'update_class') {
        $id            = (int)($_POST['id'] ?? 0);
        $class_name    = trim($_POST['class_name'] ?? '');
        $numeric_grade = isset($_POST['numeric_grade']) ? (int)$_POST['numeric_grade'] : 0;

        if ($id <= 0) {
            $errors[] = 'Invalid class identifier.';
        }
        if ($class_name === '') {
            $errors[] = 'Class name is required.';
        } else {
            $chk = mysqli_prepare($conn, "SELECT id FROM classes WHERE class_name = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'si', $class_name, $id);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) {
                $errors[] = "Class name '{$class_name}' is already used by another class.";
            }
            mysqli_stmt_close($chk);
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "UPDATE classes SET class_name = ?, numeric_grade = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'sii', $class_name, $numeric_grade, $id);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $_SESSION['flash_success'] = "Class '{$class_name}' updated successfully.";
                header('Location: classes.php');
                exit();
            } else {
                $errors[] = 'Failed to update class: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }

    // SAFE DELETE CLASS
    if ($post_action === 'delete_class') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Check linked students
            $chk = mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE class_id = $id");
            $st_cnt = mysqli_fetch_assoc($chk)['c'] ?? 0;

            if ($st_cnt > 0) {
                $_SESSION['flash_error'] = "Cannot delete this class: {$st_cnt} student(s) are currently enrolled in it. Reassign or graduate students first.";
            } else {
                // Check class_subjects
                $chk2 = mysqli_query($conn, "SELECT COUNT(*) as c FROM class_subjects WHERE class_id = $id");
                $sub_cnt = mysqli_fetch_assoc($chk2)['c'] ?? 0;

                // Delete associated sections and class safely
                mysqli_query($conn, "DELETE FROM sections WHERE class_id = $id");
                mysqli_query($conn, "DELETE FROM class_subjects WHERE class_id = $id");
                $del = mysqli_query($conn, "DELETE FROM classes WHERE id = $id");
                if ($del) {
                    $_SESSION['flash_success'] = "Class deleted successfully.";
                } else {
                    $_SESSION['flash_error'] = "Failed to delete class: " . mysqli_error($conn);
                }
            }
        }
        header('Location: classes.php');
        exit();
    }
}

// ─── LOAD DATA FOR EDIT ──────────────────────────────────────────
$edit_class = null;
if ($action === 'edit' && $class_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM classes WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $class_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $edit_class = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$edit_class) {
        $_SESSION['flash_error'] = 'Class not found.';
        header('Location: classes.php');
        exit();
    }
}

// Fetch all classes with section counts and student counts
$classes_list = [];
$res = mysqli_query($conn, "SELECT c.*, 
    COUNT(DISTINCT sec.id) AS section_count,
    COUNT(DISTINCT st.id) AS student_count,
    GROUP_CONCAT(DISTINCT sec.section_name ORDER BY sec.section_name SEPARATOR ', ') AS section_names
    FROM classes c
    LEFT JOIN sections sec ON c.id = sec.class_id
    LEFT JOIN students st ON c.id = st.class_id
    GROUP BY c.id
    ORDER BY c.numeric_grade ASC, c.class_name ASC");
while ($r = mysqli_fetch_assoc($res)) {
    $classes_list[] = $r;
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Classes & Sections</h1>
        <p class="page-header__desc">Organize grade levels, classroom sections, and student allocations.</p>
    </div>
    <div class="page-header__actions">
        <a href="sections.php" class="btn-admin btn-admin--secondary">Manage All Sections →</a>
        <?php if ($action === 'edit'): ?>
            <a href="classes.php" class="btn-admin btn-admin--ghost">← Cancel Edit</a>
        <?php endif; ?>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="nav-tabs">
    <a href="classes.php" class="nav-tab nav-tab--active">Classes Overview</a>
    <a href="sections.php" class="nav-tab">Sections Management</a>
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
            <strong>Please resolve the following:</strong>
            <ul style="margin-left: 20px; margin-top: 6px;">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="dash-grid" style="grid-template-columns: 360px 1fr; align-items: start;">
    <!-- Add or Edit Class Form -->
    <div class="form-card animate-in">
        <div class="form-card__header">
            <h2 class="form-card__title"><?php echo $edit_class ? 'Edit Class' : 'Create New Class'; ?></h2>
        </div>

        <form method="POST" action="classes.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="<?php echo $edit_class ? 'update_class' : 'create_class'; ?>">
            <?php if ($edit_class): ?>
                <input type="hidden" name="id" value="<?php echo $edit_class['id']; ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="class_name">Class / Grade Name <span class="required">*</span></label>
                <input type="text" id="class_name" name="class_name" class="form-control" 
                       value="<?php echo htmlspecialchars($edit_class['class_name'] ?? ($_POST['class_name'] ?? '')); ?>" 
                       placeholder="e.g. Primary, Middle School, Grade 7" required>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label class="form-label" for="numeric_grade">Numeric Grade Order</label>
                <input type="number" id="numeric_grade" name="numeric_grade" class="form-control" 
                       value="<?php echo htmlspecialchars((string)($edit_class['numeric_grade'] ?? ($_POST['numeric_grade'] ?? 1))); ?>" 
                       min="0" max="20" required>
                <span class="form-hint">Used for chronological sorting (e.g. Kindergarten=0, Grade 1=1).</span>
            </div>

            <div class="form-actions" style="margin-top:0;padding-top:16px;">
                <?php if ($edit_class): ?>
                    <a href="classes.php" class="btn-admin btn-admin--secondary">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="btn-admin btn-admin--primary">
                    <?php echo $edit_class ? 'Save Changes' : 'Create Class'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Classes List Table Panel -->
    <div class="panel animate-in">
        <div class="panel__header">
            <h3 class="panel__title">Registered Classes (<?php echo count($classes_list); ?>)</h3>
            <a href="sections.php" class="panel__action">+ Manage Sections</a>
        </div>

        <?php if (!empty($classes_list)): ?>
            <div class="table-responsive">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Class Name</th>
                            <th>Grade Order</th>
                            <th>Sections</th>
                            <th>Enrolled</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes_list as $cls): ?>
                        <tr>
                            <td>
                                <strong style="font-size:0.95rem;color:var(--admin-heading);"><?php echo htmlspecialchars($cls['class_name']); ?></strong>
                            </td>
                            <td>
                                <span class="badge badge--info">Grade <?php echo $cls['numeric_grade']; ?></span>
                            </td>
                            <td>
                                <?php if ($cls['section_names']): ?>
                                    <span style="color:var(--admin-accent);font-weight:600;"><?php echo htmlspecialchars($cls['section_names']); ?></span>
                                    <small style="color:var(--admin-text-muted);">(<?php echo $cls['section_count']; ?>)</small>
                                <?php else: ?>
                                    <span style="color:var(--admin-text-muted);font-style:italic;">No sections</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-weight:600;color:var(--admin-heading);"><?php echo number_format($cls['student_count']); ?></span>
                                <small style="color:var(--admin-text-muted);">students</small>
                            </td>
                            <td style="text-align:right;">
                                <div class="table-actions" style="justify-content:flex-end;">
                                    <a href="sections.php?class_id=<?php echo $cls['id']; ?>" class="action-btn action-btn--view" title="Manage Class Sections">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                    </a>

                                    <a href="classes.php?action=edit&id=<?php echo $cls['id']; ?>" class="action-btn action-btn--edit" title="Edit Class">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>

                                    <?php if ($cls['student_count'] == 0): ?>
                                        <form method="POST" action="classes.php" style="display:inline;" onsubmit="return confirm('Delete class <?php echo htmlspecialchars($cls['class_name']); ?> and its empty sections?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="action" value="delete_class">
                                            <input type="hidden" name="id" value="<?php echo $cls['id']; ?>">
                                            <button type="submit" class="action-btn action-btn--delete" title="Delete Class">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="action-btn action-btn--disabled" disabled title="Cannot delete — has <?php echo $cls['student_count']; ?> enrolled student(s)">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No classes registered yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
