<?php
/**
 * accountant/header.php
 * Reusable header for all Accountant Portal pages.
 * Includes HTML <head>, sidebar (finance-only nav), and topbar.
 */
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
}
$acc_name    = function_exists('getAdminName')    ? getAdminName()    : htmlspecialchars($_SESSION['admin_name'] ?? 'Accountant');
$acc_initial = function_exists('getAdminInitial') ? getAdminInitial() : strtoupper(substr($acc_name, 0, 1));
$today       = date('l, F j, Y');
$page_title  = $page_title ?? ucfirst($current_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> – Accountant Portal</title>
    <link rel="icon" href="../assets/images/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../admin/assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../admin/assets/css/admin.css'); ?>">
    <style>
        /* Accountant portal accent – teal/emerald override */
        :root {
            --admin-accent: #0d9488;
            --admin-accent-hover: #0f766e;
            --acc-badge-bg: rgba(13,148,136,.15);
        }
        .acc-portal-badge {
            display:inline-flex;align-items:center;gap:6px;
            background:var(--acc-badge-bg);color:var(--admin-accent);
            font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
            padding:3px 8px;border-radius:20px;border:1px solid rgba(13,148,136,.3);
        }
    </style>
    <script>
        (function(){
            try { if(localStorage.getItem('adminTheme')==='light') document.documentElement.classList.add('light-mode'); } catch(e){}
        })();
    </script>
</head>
<body class="admin-body">

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ─── ACCOUNTANT SIDEBAR ────────────────────────────────────── -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand__icon" style="background:linear-gradient(135deg,#0d9488,#065f46);">₨</div>
        <div class="sidebar-brand__text">
            Brightway
            <small>Accountant Portal</small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-nav__label">Overview</div>
        <a href="dashboard.php" class="sidebar-nav__link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>

        <div class="sidebar-nav__label">Fee Collection</div>
        <a href="fee_payments.php" class="sidebar-nav__link <?php echo $current_page === 'fee_payments' ? 'active' : ''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            Collect Fees
        </a>
        <a href="fee_dues.php" class="sidebar-nav__link <?php echo $current_page === 'fee_dues' ? 'active' : ''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Outstanding Dues
        </a>

        <div class="sidebar-nav__label">Finance</div>
        <a href="income_expense.php" class="sidebar-nav__link <?php echo $current_page === 'income_expense' ? 'active' : ''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            Income &amp; Expenses
        </a>
        <a href="salaries.php" class="sidebar-nav__link <?php echo $current_page === 'salaries' ? 'active' : ''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            Salary Payments
        </a>

        <div class="sidebar-nav__label">Reports</div>
        <a href="reports.php" class="sidebar-nav__link <?php echo $current_page === 'reports' ? 'active' : ''; ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Financial Reports
        </a>
    </nav>

    <div class="sidebar-bottom">
        <a href="../admin/logout.php" class="sidebar-nav__link" style="color:var(--admin-red);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
        </a>
        <div class="sidebar-user">
            <div class="sidebar-user__avatar" style="background:linear-gradient(135deg,#0d9488,#065f46);"><?php echo $acc_initial; ?></div>
            <div class="sidebar-user__info">
                <div class="sidebar-user__name"><?php echo $acc_name; ?></div>
                <div class="sidebar-user__role">
                    <span class="acc-portal-badge">Accountant</span>
                </div>
            </div>
        </div>
    </div>
</aside>

<!-- ─── MAIN ────────────────────────────────────────────────── -->
<div class="admin-main">
    <header class="admin-topbar">
        <div class="topbar-left">
            <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div class="topbar-title">
                <span class="topbar-greeting">Welcome, </span>
                <span><?php echo $acc_name; ?></span> &nbsp;<span class="acc-portal-badge">Fee Manager</span>
            </div>
        </div>
        <div class="topbar-right">
            <button class="topbar-btn" id="themeToggle" title="Toggle Light/Dark Mode" aria-label="Toggle theme">
                <svg class="theme-icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                <svg class="theme-icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
            <span class="topbar-date">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?php echo $today; ?>
            </span>
            <a href="../index.php" class="back-to-site" title="View public website">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                <span>View Site</span>
            </a>
        </div>
    </header>
    <main class="admin-content">
