<?php
/**
 * receptionist/auth_check.php
 * Shared authentication & role enforcement for Receptionist Portal.
 * Include at the very top of every receptionist page (before any output).
 */
require_once __DIR__ . '/../admin/auth.php';

// Must be logged in
requireAdminLogin();

// Must be receptionist or admin (admin can view all portals)
requireRole(['receptionist', 'admin']);

// Helpers
$school_name = getSetting('school_name', 'Brightway School');
