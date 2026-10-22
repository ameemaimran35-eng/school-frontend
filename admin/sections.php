<?php
/**
 * admin/sections.php
 * Sections Management Module
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();
requireAdminOnlyAccess();

$page_title = 'Sections Management';
$current_page = 'classes'; // Active sidebar item is "Classes & Sections"

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$action = $_GET['action'] ?? 'list';
$section_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$filter_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// Fetch all classes for dropdowns and filters
$classes = [];
$res = mysqli_query($conn, "SELECT id, class_name, numeric_grade FROM classes ORDER BY numeric_grade ASC, class_name ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $classes[] = $row;
}

// ─── POST HANDLERS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token). Please try again.';
        header('Location: sections.php');
        exit();
    }

    $post_action = $_POST['action'] ?? '';

    // CREATE SECTION
    if ($post_action === 'create') {
        $class_id     = (int)($_POST['class_id'] ?? 0);
        $section_name = strtoupper(trim($_POST['section_name'] ?? ''));
        $capacity     = max(1, (int)($_POST['capacity'] ?? 30));

        if ($class_id <= 0) {
            $errors[] = 'Please select a valid class.';
        }
        if ($section_name === '') {
            $errors[] = 'Section name is required (e.g. A, B, Green).';
        }

        if (empty($errors)) {
            // Check uniqueness in class
            $chk = mysqli_prepare($conn, "SELECT id FROM sections WHERE class_id = ? AND section_name = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'is', $class_id, $section_name);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) {
                $errors[] = "Section '{$section_name}' already exists in the selected class.";
            }
            mysqli_stmt_close($chk);
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO sections (class_id, section_name, capacity) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'isi', $class_id, $section_name, $capacity);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $_SESSION['flash_success'] = "Section '{$section_name}' created successfully.";
                header("Location: sections.php?class_id={$class_id}");
                exit();
            } else {
                $errors[] = 'Failed to create section: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }

    // UPDATE SECTION
    if ($post_action === 'update') {
        $id           = (int)($_POST['id'] ?? 0);
        $class_id     = (int)($_POST['class_id'] ?? 0);
        $section_name = strtoupper(trim($_POST['section_name'] ?? ''));
        $capacity     = max(1, (int)($_POST['capacity'] ?? 30));

        if ($id <= 0) {
            $errors[] = 'Invalid section identifier.';
        }
        if ($class_id <= 0) {
            $errors[] = 'Class must be selected.';
        }
        if ($section_name === '') {
            $errors[] = 'Section name is required.';
        }

        if (empty($errors)) {
            $chk = mysqli_prepare($conn, "SELECT id FROM sections WHERE class_id = ? AND section_name = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'isi', $class_id, $section_name, $id);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            if (mysqli_stmt_num_rows($chk) > 0) {
                $errors[] = "Section '{$section_name}' already exists in this class.";
            }
            mysqli_stmt_close($chk);
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "UPDATE sections SET class_id = ?, section_name = ?, capacity = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'isii', $class_id, $section_name, $capacity, $id);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $_SESSION['flash_success'] = "Section '{$section_name}' updated successfully.";
                header("Location: sections.php?class_id={$class_id}");
                exit();
            } else {
                $errors[] = 'Failed to update section: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }

    // SAFE DELETE SECTION
    if ($post_action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Check linked students
            $chk = mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE section_id = $id");
            $st_cnt = mysqli_fetch_assoc($chk)['c'] ?? 0;

            if ($st_cnt > 0) {
                $_SESSION['flash_error'] = "Cannot delete this section: {$st_cnt} student(s) are currently enrolled in it. Reassign students first.";
            } else {
                $stmt = mysqli_prepare($conn, "DELETE FROM sections WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['flash_success'] = "Section deleted successfully.";
                } else {
                    $_SESSION['flash_error'] = "Delete failed: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        }
        header('Location: sections.php');
        exit();
    }
}

// ─── LOAD DATA FOR EDIT ──────────────────────────────────────────
$edit_section = null;
if ($action === 'edit' && $section_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM sections WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $section_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $edit_section = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$edit_section) {
        $_SESSION['flash_error'] = 'Section not found.';
        header('Location: sections.php');
        exit();
    }
}

// Fetch sections list with enrolled count
$where_sql = $filter_class_id > 0 ? "WHERE s.class_id = $filter_class_id" : "";
$sections_list = [];
$res = mysqli_query($conn, "SELECT s.*, 
    c.class_name, c.numeric_grade,
    COUNT(st.id) AS enrolled_count
    FROM sections s
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN students st ON s.id = st.section_id
    $where_sql
    GROUP BY s.id
    ORDER BY c.numeric_grade ASC, c.class_name ASC, s.section_name ASC");
while ($r = mysqli_fetch_assoc($res)) {
    $sections_list[] = $r;
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Sections Management</h1>
        <p class="page-header__desc">Manage classroom divisions, class associations, and seating capacity.</p>
    </div>
    <div class="page-header__actions">
        <a href="classes.php" class="btn-admin btn-admin--secondary">← Classes Overview</a>
        <?php if ($action === 'edit'): ?>
            <a href="sections.php<?php echo $filter_class_id > 0 ? "?class_id={$filter_class_id}" : ''; ?>" class="btn-admin btn-admin--ghost">Cancel Edit</a>
        <?php endif; ?>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="nav-tabs">
    <a href="classes.php" class="nav-tab">Classes Overview</a>
    <a href="sections.php" class="nav-tab nav-tab--active">Sections Management</a>
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
    <!-- Add or Edit Section Form -->
    <div class="form-card animate-in">
        <div class="form-card__header">
            <h2 class="form-card__title"><?php echo $edit_section ? 'Edit Section' : 'Create Section'; ?></h2>
        </div>

        <form method="POST" action="sections.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="<?php echo $edit_section ? 'update' : 'create'; ?>">
            <?php if ($edit_section): ?>
                <input type="hidden" name="id" value="<?php echo $edit_section['id']; ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="class_id">Assign to Class <span class="required">*</span></label>
                <select id="class_id" name="class_id" class="form-control" required>
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $cls): ?>
                        <option value="<?php echo $cls['id']; ?>" 
                            <?php echo (int)($edit_section['class_id'] ?? ($filter_class_id ?: ($_POST['class_id'] ?? 0))) === (int)$cls['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cls['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" for="section_name">Section Name <span class="required">*</span></label>
                <input type="text" id="section_name" name="section_name" class="form-control" 
                       value="<?php echo htmlspecialchars($edit_section['section_name'] ?? ($_POST['section_name'] ?? '')); ?>" 
                       placeholder="e.g. A, B, Red, Blue" maxlength="20" required>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label class="form-label" for="capacity">Seating Capacity</label>
                <input type="number" id="capacity" name="capacity" class="form-control" 
                       value="<?php echo htmlspecialchars((string)($edit_section['capacity'] ?? ($_POST['capacity'] ?? 30))); ?>" 
                       min="1" max="100" required>
            </div>

            <div class="form-actions" style="margin-top:0;padding-top:16px;">
                <?php if ($edit_section): ?>
                    <a href="sections.php" class="btn-admin btn-admin--secondary">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="btn-admin btn-admin--primary">
                    <?php echo $edit_section ? 'Update Section' : 'Create Section'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Sections Table Panel -->
    <div class="panel animate-in">
        <div class="panel__header" style="flex-wrap:wrap;gap:12px;">
            <h3 class="panel__title">Class Sections (<?php echo count($sections_list); ?>)</h3>
            <form method="GET" action="sections.php" style="display:flex;align-items:center;gap:8px;">
                <select name="class_id" class="form-control" style="height:32px;padding:0 24px 0 10px;font-size:0.8rem;" onchange="this.form.submit()">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $filter_class_id === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (!empty($sections_list)): ?>
            <div class="table-responsive">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Enrolled / Capacity</th>
                            <th>Utilization</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sections_list as $sec): 
                            $utilization = round(($sec['enrolled_count'] / max(1, $sec['capacity'])) * 100);
                            $util_badge = $utilization >= 100 ? 'badge--danger' : ($utilization >= 80 ? 'badge--warning' : 'badge--active');
                        ?>
                        <tr>
                            <td>
                                <strong style="color:var(--admin-heading);"><?php echo htmlspecialchars($sec['class_name']); ?></strong>
                            </td>
                            <td>
                                <span class="badge badge--info" style="font-size:0.82rem;padding:4px 10px;">
                                    Section <?php echo htmlspecialchars($sec['section_name']); ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color:var(--admin-heading);"><?php echo $sec['enrolled_count']; ?></strong>
                                <span style="color:var(--admin-text-muted);">/ <?php echo $sec['capacity']; ?> seats</span>
                            </td>
                            <td>
                                <span class="badge <?php echo $util_badge; ?>">
                                    <?php echo $utilization; ?>% full
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div class="table-actions" style="justify-content:flex-end;">
                                    <a href="sections.php?action=edit&id=<?php echo $sec['id']; ?>" class="action-btn action-btn--edit" title="Edit Section">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>

                                    <?php if ($sec['enrolled_count'] == 0): ?>
                                        <form method="POST" action="sections.php" style="display:inline;" onsubmit="return confirm('Delete Section <?php echo htmlspecialchars($sec['section_name']); ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $sec['id']; ?>">
                                            <button type="submit" class="action-btn action-btn--delete" title="Delete Section">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="action-btn action-btn--disabled" disabled title="Cannot delete — has <?php echo $sec['enrolled_count']; ?> enrolled student(s)">
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
                <p>No sections found for the selected criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
