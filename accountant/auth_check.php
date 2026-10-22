<?php
/**
 * accountant/auth_check.php
 * Shared authentication & role enforcement for Accountant Portal.
 * Include at the very top of every accountant page (before any output).
 */
require_once __DIR__ . '/../admin/auth.php';

// Must be logged in
requireAdminLogin();

// Must be accountant or admin (admin can view all portals)
requireRole(['accountant', 'admin']);

// Helpers
$currency    = getSetting('currency_symbol', 'Rs');
$school_name = getSetting('school_name', 'Brightway School');
