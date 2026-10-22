<?php
/**
 * accountant/income_expense.php
 * Income & Expense Management — Accountant Portal
 * Only allows direct transactions (reference_type='direct').
 * Fee payments are NEVER counted here to prevent double-counting.
 * SRS Section 7.4
 */
require_once __DIR__ . '/auth_check.php';

$page_title   = 'Income & Expense Records';
$current_page = 'income_expense';

$errors      = [];
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$action     = $_GET['action']  ?? 'list';
$typeFilter = $_GET['type']    ?? '';
$dateStart  = $_GET['start']   ?? date('Y-m-01');
$dateEnd    = $_GET['end']     ?? date('Y-m-d');

// ─── POST HANDLER ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security token invalid.';
        header('Location: income_expense.php');
        exit();
    }

    $op = $_POST['op'] ?? '';

    if ($op === 'add' || $op === 'edit') {
        $type        = trim($_POST['type']           ?? '');
        $category    = trim($_POST['category']       ?? '');
        $amount      = (float)($_POST['amount']      ?? 0);
        $title       = trim($_POST['title']          ?? '');
        $description = trim($_POST['description']    ?? '');
        $pm          = $_POST['payment_method']      ?? 'cash';
        $date        = trim($_POST['date']           ?? date('Y-m-d'));

        if (!in_array($type, ['income','expense'], true)) $errors[] = 'Invalid transaction type.';
        if ($category === '')                               $errors[] = 'Category is required.';
        if ($amount <= 0)                                  $errors[] = 'Amount must be greater than zero.';
        if ($title === '')                                  $errors[] = 'Title is required.';

        if (empty($errors)) {
            if ($op === 'add') {
                $ins = mysqli_prepare($conn,
                    "INSERT INTO financial_transactions (trans_date, type, category, amount, title, description, payment_method, reference_type) VALUES (?, ?, ?, ?, ?, ?, ?, 'direct')");
                mysqli_stmt_bind_param($ins, 'sssdsss', $date, $type, $category, $amount, $title, $description, $pm);
                if (mysqli_stmt_execute($ins)) {
                    $new_id = mysqli_insert_id($conn);
                    logActivity('finance_added', 'financial_transactions', $new_id, "Accountant added {$type} '{$title}' Rs {$amount}");
                    $_SESSION['flash_success'] = ucfirst($type) . " record '{$title}' added successfully.";
                } else {
                    $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($ins);

            } elseif ($op === 'edit') {
                $edit_id = (int)($_POST['id'] ?? 0);
                $upd = mysqli_prepare($conn,
                    "UPDATE financial_transactions SET trans_date=?, type=?, category=?, amount=?, title=?, description=?, payment_method=?
                     WHERE id=? AND reference_type='direct'");
                mysqli_stmt_bind_param($upd, 'sssdsssi', $date, $type, $category, $amount, $title, $description, $pm, $edit_id);
                if (mysqli_stmt_execute($upd)) {
                    logActivity('finance_updated', 'financial_transactions', $edit_id, "Accountant updated {$type} '{$title}'");
                    $_SESSION['flash_success'] = ucfirst($type) . ' record updated successfully.';
                } else {
                    $_SESSION['flash_error'] = 'Database error: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($upd);
            }
            header('Location: income_expense.php' . ($typeFilter ? '?type='.urlencode($typeFilter) : ''));
            exit();
        }

    } elseif ($op === 'delete') {
        // Accountant may delete only direct (non-fee, non-salary) transactions
        $del_id = (int)($_POST['id'] ?? 0);
        $del = mysqli_prepare($conn, "DELETE FROM financial_transactions WHERE id=? AND reference_type='direct'");
        mysqli_stmt_bind_param($del, 'i', $del_id);
        if (mysqli_stmt_execute($del) && mysqli_stmt_affected_rows($del) > 0) {
            logActivity('finance_deleted', 'financial_transactions', $del_id, "Accountant deleted direct transaction #{$del_id}");
            $_SESSION['flash_success'] = 'Transaction deleted.';
        } else {
            $_SESSION['flash_error'] = 'Could not delete. Only direct income/expense records can be removed.';
        }
        mysqli_stmt_close($del);
        header('Location: income_expense.php' . ($typeFilter ? '?type='.urlencode($typeFilter) : ''));
        exit();
    }
}

// ─── Fetch record to edit ─────────────────────────────────────────
$edit_record = null;
if ($action === 'edit') {
    $eid = (int)($_GET['id'] ?? 0);
    $est = mysqli_prepare($conn, "SELECT * FROM financial_transactions WHERE id=? AND reference_type='direct' LIMIT 1");
    mysqli_stmt_bind_param($est, 'i', $eid);
    mysqli_stmt_execute($est);
    $edit_record = mysqli_fetch_assoc(mysqli_stmt_get_result($est));
    mysqli_stmt_close($est);
}

// ─── Fetch transactions list (direct only!) ──────────────────────
$where = "WHERE reference_type='direct'";
if ($typeFilter) $where .= " AND type='" . mysqli_real_escape_string($conn, $typeFilter) . "'";
$where .= " AND trans_date BETWEEN '" . mysqli_real_escape_string($conn, $dateStart) . "' AND '" . mysqli_real_escape_string($conn, $dateEnd) . "'";

$transactions = [];
$total_income   = 0;
$total_expense  = 0;
$res = mysqli_query($conn, "SELECT * FROM financial_transactions $where ORDER BY trans_date DESC, id DESC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $transactions[] = $row;
        if ($row['type'] === 'income')  $total_income  += (float)$row['amount'];
        if ($row['type'] === 'expense') $total_expense += (float)$row['amount'];
    }
}

include __DIR__ . '/header.php';
?>

<div class="page-header">
    <div class="page-header__left">
        <h1 class="page-header__title">Income &amp; Expenses</h1>
        <p class="page-header__desc">Record authorized direct income and school expenses. Fee payments are tracked separately.</p>
    </div>
    <div class="page-header__actions">
        <button type="button" class="btn-admin btn-admin--primary" onclick="toggleForm('add')">+ Add Record</button>
        <a href="reports.php?tab=financial" class="btn-admin btn-admin--ghost">View Reports</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong>Errors:</strong>
    <ul style="margin:6px 0 0 16px;"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>
<?php if ($success_msg): ?>
<div class="alert alert-success flash-auto-dismiss"><?php echo htmlspecialchars($success_msg); ?></div>
<?php endif; ?>
<?php if ($error_msg): ?>
<div class="alert alert-danger flash-auto-dismiss"><?php echo htmlspecialchars($error_msg); ?></div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="stats-grid animate-in" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
    <div class="stat-card">
        <div class="stat-card__label" style="color:#22c55e;">Direct Income</div>
        <div class="stat-card__value" style="color:#22c55e;"><?php echo $currency; ?> <?php echo number_format($total_income, 2); ?></div>
        <span class="stat-card__change">For selected period</span>
    </div>
    <div class="stat-card">
        <div class="stat-card__label" style="color:var(--admin-red);">Direct Expenses</div>
        <div class="stat-card__value" style="color:var(--admin-red);"><?php echo $currency; ?> <?php echo number_format($total_expense, 2); ?></div>
        <span class="stat-card__change">For selected period</span>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Net</div>
        <div class="stat-card__value" style="color:<?php echo ($total_income-$total_expense)>=0?'#22c55e':'var(--admin-red)'; ?>;"><?php echo $currency; ?> <?php echo number_format($total_income-$total_expense, 2); ?></div>
        <span class="stat-card__change">Income minus expenses</span>
    </div>
</div>

<!-- Add / Edit Form -->
<div class="panel animate-in" id="formPanel" style="display:<?php echo (!empty($errors)||$edit_record)?'block':'none'; ?>;">
    <div class="panel__header"><h3 class="panel__title"><?php echo $edit_record ? 'Edit Record' : 'Add New Record'; ?></h3>
        <button type="button" class="btn-admin btn-admin--sm btn-admin--ghost" onclick="toggleForm('add')">✕ Close</button>
    </div>
    <form method="POST" action="income_expense.php<?php echo $typeFilter?'?type='.urlencode($typeFilter):''; ?>" style="padding:16px 20px;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
        <input type="hidden" name="op"         value="<?php echo $edit_record ? 'edit' : 'add'; ?>">
        <?php if ($edit_record): ?><input type="hidden" name="id" value="<?php echo $edit_record['id']; ?>"><?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label">Type <span style="color:var(--admin-red);">*</span></label>
                <select name="type" class="form-control" required>
                    <option value="">— Select —</option>
                    <option value="income"  <?php echo ($edit_record['type']??$typeFilter)==='income'?'selected':''; ?>>Income</option>
                    <option value="expense" <?php echo ($edit_record['type']??$typeFilter)==='expense'?'selected':''; ?>>Expense</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Category <span style="color:var(--admin-red);">*</span></label>
                <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($edit_record['category']??''); ?>" placeholder="e.g. Rent, Stationery" required>
            </div>
            <div class="form-group">
                <label class="form-label">Title <span style="color:var(--admin-red);">*</span></label>
                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($edit_record['title']??''); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Amount (<?php echo $currency; ?>) <span style="color:var(--admin-red);">*</span></label>
                <input type="number" name="amount" class="form-control" step="0.01" min="0.01" value="<?php echo htmlspecialchars($edit_record['amount']??''); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($edit_record['trans_date']??date('Y-m-d')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Payment Method</label>
                <select name="payment_method" class="form-control">
                    <?php foreach (['cash'=>'Cash','bank_transfer'=>'Bank Transfer','cheque'=>'Cheque','online'=>'Online'] as $v=>$l): ?>
                    <option value="<?php echo $v; ?>" <?php echo ($edit_record['payment_method']??'cash')===$v?'selected':''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Optional details"><?php echo htmlspecialchars($edit_record['description']??''); ?></textarea>
        </div>

        <div style="display:flex;gap:12px;margin-top:4px;">
            <button type="submit" class="btn-admin btn-admin--primary"><?php echo $edit_record ? 'Update Record' : 'Add Record'; ?></button>
            <a href="income_expense.php<?php echo $typeFilter?'?type='.urlencode($typeFilter):''; ?>" class="btn-admin btn-admin--ghost">Cancel</a>
        </div>
    </form>
</div>

<!-- Filter Bar -->
<div class="filter-card animate-in">
    <form method="GET" action="income_expense.php" class="filter-form" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:.8rem;">Type</label>
            <select name="type" class="form-control">
                <option value="">All</option>
                <option value="income"  <?php echo $typeFilter==='income'?'selected':''; ?>>Income</option>
                <option value="expense" <?php echo $typeFilter==='expense'?'selected':''; ?>>Expense</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:.8rem;">From</label>
            <input type="date" name="start" class="form-control" value="<?php echo htmlspecialchars($dateStart); ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label" style="font-size:.8rem;">To</label>
            <input type="date" name="end" class="form-control" value="<?php echo htmlspecialchars($dateEnd); ?>">
        </div>
        <button type="submit" class="btn-admin btn-admin--primary">Filter</button>
        <a href="income_expense.php" class="btn-admin btn-admin--ghost">Reset</a>
    </form>
</div>

<!-- Transactions Table -->
<div class="panel animate-in">
    <div class="panel__header">
        <h3 class="panel__title">Transactions (<?php echo count($transactions); ?>)</h3>
        <small style="color:var(--admin-text-muted);">Fee payments excluded. Direct records only.</small>
    </div>

    <?php if (!empty($transactions)): ?>
    <div class="table-responsive">
        <table class="recent-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Title</th>
                    <th>Method</th>
                    <th style="text-align:right;">Amount</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $tx): ?>
            <tr>
                <td style="font-size:.85rem;"><?php echo date('M j, Y', strtotime($tx['trans_date'])); ?></td>
                <td>
                    <span class="badge badge--<?php echo $tx['type']==='income'?'success':'danger'; ?>">
                        <?php echo ucfirst($tx['type']); ?>
                    </span>
                </td>
                <td style="color:var(--admin-text-muted);"><?php echo htmlspecialchars($tx['category']); ?></td>
                <td style="font-weight:600;"><?php echo htmlspecialchars($tx['title']); ?>
                    <?php if ($tx['description']): ?>
                    <br><small style="font-weight:400;color:var(--admin-text-muted);"><?php echo htmlspecialchars(substr($tx['description'],0,60)); ?></small>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$tx['payment_method']))); ?></td>
                <td style="text-align:right;font-weight:700;color:<?php echo $tx['type']==='income'?'#22c55e':'var(--admin-red)'; ?>;">
                    <?php echo $currency; ?> <?php echo number_format($tx['amount'], 2); ?>
                </td>
                <td style="text-align:right;">
                    <a href="income_expense.php?action=edit&id=<?php echo $tx['id']; ?><?php echo $typeFilter?'&type='.urlencode($typeFilter):''; ?>" class="btn-admin btn-admin--sm btn-admin--secondary">Edit</a>
                    <form method="POST" action="income_expense.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                        <input type="hidden" name="op"   value="delete">
                        <input type="hidden" name="id"   value="<?php echo $tx['id']; ?>">
                        <button type="submit" class="btn-admin btn-admin--sm btn-admin--ghost" style="color:var(--admin-red);">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><p>No direct income or expense records for the selected period.</p></div>
    <?php endif; ?>
</div>

<script>
function toggleForm(mode) {
    var panel = document.getElementById('formPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    if (panel.style.display === 'block') panel.scrollIntoView({behavior:'smooth'});
}
<?php if ($action === 'edit' && $edit_record): ?>
document.getElementById('formPanel').style.display = 'block';
<?php endif; ?>
</script>

<?php include __DIR__ . '/footer.php'; ?>
