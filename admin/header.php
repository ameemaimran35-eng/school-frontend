<?php
/**
 * admin/header.php
 * Reusable header for all admin pages.
 * Includes HTML <head>, opening body, sidebar navigation, and top bar.
 * Assumes authentication has already been performed before inclusion.
 */
// Determine current page for active sidebar highlighting. Override by setting $current_page before include.
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
}

// Safely initialize admin display name and avatar initial
if (!isset($admin_name) || empty($admin_name)) {
    $admin_name = function_exists('getAdminName')
        ? getAdminName()
        : htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin');
}
if (!isset($admin_initial) || empty($admin_initial)) {
    $admin_initial = function_exists('getAdminInitial')
        ? getAdminInitial()
        : strtoupper(substr($admin_name, 0, 1));
}
if (!isset($today) || empty($today)) {
    $today = date('l, F j, Y');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($current_page); ?> – Admin Panel</title>
    <link rel="icon" href="../assets/images/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo file_exists(__DIR__ . '/assets/css/admin.css') ? filemtime(__DIR__ . '/assets/css/admin.css') : time(); ?>">
    <script>
        (function() {
            try {
                if (localStorage.getItem('adminTheme') === 'light') {
                    document.documentElement.classList.add('light-mode');
                }
            } catch(e) {}
        })();
    </script>
</head>
<body class="admin-body">

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ─── SIDEBAR ─────────────────────────────────────────────── -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand__icon">B</div>
        <div class="sidebar-brand__text">
            Brightway
            <small>School Admin</small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-nav__label">Main</div>
        <a href="dashboard.php" class="sidebar-nav__link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
        <div class="sidebar-nav__label">Academics</div>
        <a href="students.php" class="sidebar-nav__link <?php echo $current_page === 'students' ? 'active' : ''; ?>">Students</a>
        <a href="staff.php" class="sidebar-nav__link <?php echo $current_page === 'staff' ? 'active' : ''; ?>">Staff</a>
        <a href="classes.php" class="sidebar-nav__link <?php echo $current_page === 'classes' ? 'active' : ''; ?>">Classes & Sections</a>
        <a href="sessions.php" class="sidebar-nav__link <?php echo $current_page === 'sessions' ? 'active' : ''; ?>">Academic Sessions</a>
        <a href="subjects.php" class="sidebar-nav__link <?php echo $current_page === 'subjects' ? 'active' : ''; ?>">Subjects</a>
        <a href="attendance.php" class="sidebar-nav__link <?php echo $current_page === 'attendance' ? 'active' : ''; ?>">Attendance</a>
        <a href="exams.php" class="sidebar-nav__link <?php echo $current_page === 'exams' ? 'active' : ''; ?>">Exams & Marks</a>
        <div class="sidebar-nav__label">Finance</div>
        <a href="fees.php" class="sidebar-nav__link <?php echo $current_page === 'fees' ? 'active' : ''; ?>">Fees Structure</a>
        <a href="fee_payments.php" class="sidebar-nav__link <?php echo $current_page === 'fee_payments' ? 'active' : ''; ?>">Fee Payments</a>
        <a href="fee_dues.php" class="sidebar-nav__link <?php echo $current_page === 'fee_dues' ? 'active' : ''; ?>">Fee Dues</a>
        <a href="income_expense.php" class="sidebar-nav__link <?php echo $current_page === 'income_expense' ? 'active' : ''; ?>">Income & Expenses</a>
        <a href="staff_salary_payments.php" class="sidebar-nav__link <?php echo $current_page === 'salary' ? 'active' : ''; ?>">Salaries</a>
        <div class="sidebar-nav__label">Website CMS</div>
        <a href="gallery.php" class="sidebar-nav__link <?php echo $current_page === 'gallery' ? 'active' : ''; ?>">Gallery</a>
        <a href="website_images.php" class="sidebar-nav__link <?php echo $current_page === 'website_images' ? 'active' : ''; ?>">Website Images</a>
        <a href="announcements.php" class="sidebar-nav__link <?php echo $current_page === 'announcements' ? 'active' : ''; ?>">Announcements</a>
        <a href="holidays.php" class="sidebar-nav__link <?php echo $current_page === 'holidays' ? 'active' : ''; ?>">Holidays</a>
        <a href="admissions.php" class="sidebar-nav__link <?php echo $current_page === 'admissions' ? 'active' : ''; ?>">Admissions</a>
        <a href="contact_inquiries.php" class="sidebar-nav__link <?php echo $current_page === 'contact_inquiries' ? 'active' : ''; ?>">Contact Inquiries</a>
        <a href="website_content.php" class="sidebar-nav__link <?php echo $current_page === 'website_content' ? 'active' : ''; ?>">Website Copy</a>
        <div class="sidebar-nav__label">Administration</div>
        <a href="reports.php" class="sidebar-nav__link <?php echo $current_page === 'reports' ? 'active' : ''; ?>">Reports & Exports</a>
        <a href="users.php" class="sidebar-nav__link <?php echo $current_page === 'users' ? 'active' : ''; ?>">User Accounts</a>
        <a href="activity_logs.php" class="sidebar-nav__link <?php echo $current_page === 'activity_logs' ? 'active' : ''; ?>">Audit Logs</a>
        <a href="backup.php" class="sidebar-nav__link <?php echo $current_page === 'backup' ? 'active' : ''; ?>">Backup & Restore</a>
        <a href="settings.php" class="sidebar-nav__link <?php echo $current_page === 'settings' ? 'active' : ''; ?>">School Settings</a>
    </nav>
    <div class="sidebar-bottom">
        <a href="logout.php" class="sidebar-nav__link" style="color:var(--admin-red);">Logout</a>
        <div class="sidebar-user">
            <div class="sidebar-user__avatar"><?php echo $admin_initial; ?></div>
            <div class="sidebar-user__info">
                <div class="sidebar-user__name"><?php echo $admin_name; ?></div>
                <div class="sidebar-user__role">Administrator</div>
            </div>
        </div>
    </div>
</aside>

<!-- ─── MAIN ───────────────────────────────────────────────── -->
<div class="admin-main">
    <header class="admin-topbar">
        <div class="topbar-left">
            <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div class="topbar-title"><span class="topbar-greeting">Welcome back, </span><span><?php echo $admin_name; ?></span> 👋</div>
        </div>
        <div class="topbar-right">
            <button class="topbar-btn" id="themeToggle" title="Toggle Light/Dark Mode" aria-label="Toggle light or dark theme">
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
