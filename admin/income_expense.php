<?php
/**
 * admin/income_expense.php
 * Manage income and expense records (CRUD) tied to financial_transactions.
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();

$page_title = 'Income & Expense Management';
$current_page = 'income_expense';

$errors = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$action = $_GET['action'] ?? 'list';
$typeFilter = $_GET['type'] ?? '';

// ─── POST HANDLERS (Must run before header.php to allow redirection) ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
        header('Location: income_expense.php' . ($typeFilter ? '?type=' . urlencode($typeFilter) : ''));
        exit();
    }

    $op = $_POST['op'] ?? '';

    if ($op === 'add' || $op === 'edit') {
        $type = $_POST['type'] ?? '';
        $category = trim($_POST['category'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $date = $_POST['date'] ?? date('Y-m-d');

        if (!in_array($type, ['income', 'expense'])) $errors[] = 'Invalid transaction type.';
        if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';
        if ($category === '') $errors[] = 'Category is required.';
        if ($title === '') $errors[] = 'Title is required.';

        if (empty($errors)) {
            if ($op === 'add') {
                $stmt = mysqli_prepare($conn, "INSERT INTO financial_transactions (trans_date, type, category, amount, title, description, payment_method, reference_type) VALUES (?, ?, ?, ?, ?, ?, ?, 'direct')");
                mysqli_stmt_bind_param($stmt, 'sssdsss', $date, $type, $category, $amount, $title, $description, $payment_method);
                if (mysqli_stmt_execute($stmt)) {
                    $new_id = mysqli_insert_id($conn);
                    logActivity('finance_added', 'financial_transactions', $new_id, "Added direct {$type} '{$title}' amount {$amount}");
                    $_SESSION['flash_success'] = ucfirst($type) . ' record added successfully.';
                } else {
                    $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = mysqli_prepare($conn, "UPDATE financial_transactions SET trans_date=?, type=?, category=?, amount=?, title=?, description=?, payment_method=? WHERE id=? AND reference_type='direct'");
                mysqli_stmt_bind_param($stmt, 'sssdsssi', $date, $type, $category, $amount, $title, $description, $payment_method, $id);
                if (mysqli_stmt_execute($stmt)) {
                    logActivity('finance_updated', 'financial_transactions', $id, "Updated direct {$type} '{$title}' amount {$amount}");
                    $_SESSION['flash_success'] = ucfirst($type) . ' record updated successfully.';
                } else {
                    $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
            header('Location: income_expense.php' . ($typeFilter ? '?type=' . urlencode($typeFilter) : ''));
            exit();
        }
    } elseif ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Only allow deleting direct transactions to preserve fee & salary ledger integrity
        $stmt = mysqli_prepare($conn, "DELETE FROM financial_transactions WHERE id = ? AND reference_type = 'direct'");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                logActivity('finance_deleted', 'financial_transactions', $id, "Deleted direct financial transaction #{$id}");
                $_SESSION['flash_success'] = 'Transaction record deleted.';
            } else {
                $_SESSION['flash_error'] = 'Cannot delete automated ledger records (e.g. linked fee payments or salaries).';
            }
        } else {
            $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
        header('Location: income_expense.php' . ($typeFilter ? '?type=' . urlencode($typeFilter) : ''));
        exit();
    }
}

// ─── QUERY TRANSACTIONS & SUMMARY STATS ─────────────────────────
$where = "WHERE 1=1";
$params = [];
$types = '';

if ($typeFilter && in_array($typeFilter, ['income', 'expense'], true)) {
    $where .= " AND type = ?";
    $params[] = $typeFilter;
    $types .= 's';
}

$query = "SELECT * FROM financial_transactions $where ORDER BY trans_date DESC, id DESC LIMIT 100";
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$transactions = [];
while ($r = mysqli_fetch_assoc($res)) {
    $transactions[] = $r;
}
mysqli_stmt_close($stmt);

// Overall monthly totals
$res = mysqli_query($conn, "SELECT 
    IFNULL(SUM(CASE WHEN type='income' THEN amount END), 0) AS total_income,
    IFNULL(SUM(CASE WHEN type='expense' THEN amount END), 0) AS total_expense
    FROM financial_transactions 
    WHERE YEAR(trans_date) = YEAR(CURDATE()) AND MONTH(trans_date) = MONTH(CURDATE())");
$monthly_totals = mysqli_fetch_assoc($res);

// Edit item lookup
$edit_item = null;
if ($action === 'edit') {
    $edit_id = (int)($_GET['id'] ?? 0);
    $stmt = mysqli_prepare($conn, "SELECT * FROM financial_transactions WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $edit_id);
    mysqli_stmt_execute($stmt);
    $edit_item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Income & Expense Management</h1>
        <p class="page-header__desc">Track operational income, school expenses, and reconcile ledger accounts.</p>
    </div>
    <div class="page-header__actions">
        <a href="income_expense.php?action=add&type=income" class="btn-admin btn-admin--primary">+ Record Income</a>
        <a href="income_expense.php?action=add&type=expense" class="btn-admin btn-admin--secondary" style="color:var(--admin-red);">+ Record Expense</a>
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

<!-- Monthly Financial Overview Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card animate-in">
        <div class="stat-card__label" style="color:var(--admin-accent);">Income This Month</div>
        <div class="stat-card__value" style="color:var(--admin-accent);">Rs <?php echo number_format($monthly_totals['total_income'], 2); ?></div>
        <span class="stat-card__change stat-card__change--up">Incoming cash flow</span>
    </div>
    <div class="stat-card animate-in">
        <div class="stat-card__label" style="color:var(--admin-red);">Expenses This Month</div>
        <div class="stat-card__value" style="color:var(--admin-red);">Rs <?php echo number_format($monthly_totals['total_expense'], 2); ?></div>
        <span class="stat-card__change stat-card__change--down">Operational costs</span>
    </div>
    <div class="stat-card animate-in">
        <div class="stat-card__label">Net Operating Balance</div>
        <?php $net = $monthly_totals['total_income'] - $monthly_totals['total_expense']; ?>
        <div class="stat-card__value" style="color:<?php echo $net >= 0 ? 'var(--admin-accent)' : 'var(--admin-red)'; ?>;">
            Rs <?php echo number_format($net, 2); ?>
        </div>
        <span class="stat-card__change stat-card__change--neutral"><?php echo date('F Y'); ?></span>
    </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): 
    $form_type = $edit_item['type'] ?? ($_GET['type'] ?? 'income');
?>
    <!-- Entry / Edit Form Card -->
    <div class="card animate-in" style="padding:24px;max-width:700px;margin-bottom:24px;">
        <h3 style="font-size:1.15rem;font-weight:700;color:var(--admin-heading);margin-bottom:16px;">
            <?php echo $action === 'edit' ? 'Edit Financial Entry' : 'Record New ' . ucfirst($form_type); ?>
        </h3>
        <form method="POST" action="income_expense.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="op" value="<?php echo $action === 'edit' ? 'edit' : 'add'; ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo (int)$edit_item['id']; ?>">
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label">Transaction Type *</label>
                    <select name="type" class="form-control" required>
                        <option value="income" <?php echo $form_type === 'income' ? 'selected' : ''; ?>>Income</option>
                        <option value="expense" <?php echo $form_type === 'expense' ? 'selected' : ''; ?>>Expense</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Transaction Date *</label>
                    <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($edit_item['trans_date'] ?? date('Y-m-d')); ?>" required>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label">Title / Narration *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Laboratory Equipment Purchase" value="<?php echo htmlspecialchars($edit_item['title'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount (Rs) *</label>
                    <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($edit_item['amount'] ?? ''); ?>" required>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <input type="text" name="category" class="form-control" placeholder="e.g. Utilities, Stationery, Maintenance" value="<?php echo htmlspecialchars($edit_item['category'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Mode</label>
                    <select name="payment_method" class="form-control">
                        <option value="cash" <?php echo ($edit_item['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="bank_transfer" <?php echo ($edit_item['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="cheque" <?php echo ($edit_item['payment_method'] ?? '') === 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                        <option value="online" <?php echo ($edit_item['payment_method'] ?? '') === 'online' ? 'selected' : ''; ?>>Online</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description / Remarks</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Optional notes or invoice references"><?php echo htmlspecialchars($edit_item['description'] ?? ''); ?></textarea>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <a href="income_expense.php" class="btn-admin btn-admin--ghost">Cancel</a>
                <button type="submit" class="btn-admin btn-admin--primary">
                    <?php echo $action === 'edit' ? 'Update Entry' : 'Save Transaction'; ?>
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="filter-card animate-in">
    <div style="display:flex;gap:10px;align-items:center;">
        <span style="font-weight:600;font-size:0.85rem;color:var(--admin-text-muted);">Filter:</span>
        <a href="income_expense.php" class="btn-admin btn-admin--sm <?php echo empty($typeFilter) ? 'btn-admin--primary' : 'btn-admin--ghost'; ?>">All Transactions</a>
        <a href="income_expense.php?type=income" class="btn-admin btn-admin--sm <?php echo $typeFilter === 'income' ? 'btn-admin--primary' : 'btn-admin--ghost'; ?>">Income Only</a>
        <a href="income_expense.php?type=expense" class="btn-admin btn-admin--sm <?php echo $typeFilter === 'expense' ? 'btn-admin--primary' : 'btn-admin--ghost'; ?>">Expenses Only</a>
    </div>
</div>

<!-- Ledger Table Panel -->
<div class="panel animate-in">
    <div class="panel__header">
        <h3 class="panel__title">Financial Ledger (Recent Transactions)</h3>
        <span style="color:var(--admin-text-muted);font-size:0.85rem;">Guaranteed non-duplicated financial log</span>
    </div>

    <?php if (!empty($transactions)): ?>
        <div class="table-responsive">
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Narration / Title</th>
                        <th>Payment Mode</th>
                        <th style="text-align:right;">Amount</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): 
                        $is_inc = ($t['type'] === 'income');
                    ?>
                    <tr>
                        <td style="font-size:0.85rem;color:var(--admin-text-muted);">
                            <?php echo date('M j, Y', strtotime($t['trans_date'])); ?>
                        </td>
                        <td>
                            <span class="badge badge--<?php echo $is_inc ? 'active' : 'danger'; ?>">
                                <?php echo ucfirst($t['type']); ?>
                            </span>
                        </td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($t['category']); ?></td>
                        <td>
                            <div style="font-weight:600;color:var(--admin-heading);"><?php echo htmlspecialchars($t['title']); ?></div>
                            <?php if ($t['description']): ?>
                                <small style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($t['description']); ?></small>
                            <?php endif; ?>
                            <?php if ($t['receipt_invoice_no']): ?>
                                <small style="color:var(--admin-accent);font-weight:600;">(Ref: <?php echo htmlspecialchars($t['receipt_invoice_no']); ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.85rem;color:var(--admin-text-muted);"><?php echo ucfirst(str_replace('_', ' ', $t['payment_method'])); ?></td>
                        <td style="text-align:right;font-weight:700;color:<?php echo $is_inc ? 'var(--admin-accent)' : 'var(--admin-red)'; ?>;">
                            <?php echo $is_inc ? '+' : '-'; ?> Rs <?php echo number_format($t['amount'], 2); ?>
                        </td>
                        <td style="text-align:right;">
                            <?php if ($t['reference_type'] === 'direct'): ?>
                                <div class="table-actions" style="justify-content:flex-end;">
                                    <a href="income_expense.php?action=edit&id=<?php echo $t['id']; ?>" class="action-btn action-btn--edit" title="Edit">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>
                                    <form method="POST" action="income_expense.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this record?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="op" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="action-btn action-btn--delete" style="background:none;border:none;cursor:pointer;" title="Delete">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span style="font-size:0.75rem;color:var(--admin-text-muted);">Automated</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>No financial transactions found.</p>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
