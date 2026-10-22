-- ============================================================================
-- Complete School Management System Database Schema & Initial Data
-- Database: school_management_db
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `school_management_db`
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `school_management_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. INDEPENDENT / PARENT TABLES (No Foreign Keys)
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `academic_sessions`;
CREATE TABLE `academic_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_name` VARCHAR(50) NOT NULL UNIQUE,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_name` VARCHAR(50) NOT NULL UNIQUE,
  `numeric_grade` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_name` VARCHAR(100) NOT NULL,
  `subject_code` VARCHAR(20) NOT NULL UNIQUE,
  `subject_type` ENUM('core', 'elective', 'practical') NOT NULL DEFAULT 'core',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_code` VARCHAR(50) NOT NULL UNIQUE,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(25) NOT NULL,
  `designation` VARCHAR(100) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `qualification` VARCHAR(150) NULL,
  `joining_date` DATE NOT NULL,
  `basic_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `staff_photo` VARCHAR(255) NULL,
  `status` ENUM('active', 'on_leave', 'resigned', 'terminated') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `holidays`;
CREATE TABLE `holidays` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `description` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,
  `target_audience` ENUM('public', 'internal', 'all') NOT NULL DEFAULT 'public',
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `published_date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `website_content`;
CREATE TABLE `website_content` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `content_key` VARCHAR(100) NOT NULL UNIQUE,
  `section_name` VARCHAR(100) NOT NULL,
  `label` VARCHAR(150) NOT NULL,
  `content_value` TEXT NOT NULL,
  `content_type` ENUM('text', 'textarea', 'email', 'phone') NOT NULL DEFAULT 'text',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `website_images`;
CREATE TABLE `website_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `image_key` VARCHAR(100) NOT NULL UNIQUE,
  `section_name` VARCHAR(100) NOT NULL,
  `label` VARCHAR(150) NOT NULL,
  `image_path` VARCHAR(255) NULL,
  `fallback_path` VARCHAR(255) NOT NULL,
  `alt_text` VARCHAR(255) NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gallery_images`;
CREATE TABLE `gallery_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category` ENUM('campus', 'activities', 'events', 'classrooms') NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `alt_text` VARCHAR(255) NOT NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `contact_inquiries`;
CREATE TABLE `contact_inquiries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(25) NULL,
  `topic` VARCHAR(50) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('unread', 'read', 'in_progress', 'resolved') NOT NULL DEFAULT 'unread',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `school_settings`;
CREATE TABLE `school_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. DEPENDENT LEVEL 1 TABLES (Foreign Keys to Level 1)
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS `sections`;
CREATE TABLE `sections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `section_name` VARCHAR(20) NOT NULL,
  `capacity` INT NOT NULL DEFAULT 30,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_class_section` (`class_id`, `section_name`),
  CONSTRAINT `fk_sections_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `class_subjects`;
CREATE TABLE `class_subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `teacher_id` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_class_subject` (`class_id`, `subject_id`),
  CONSTRAINT `fk_class_subjects_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_class_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_class_subjects_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admission_no` VARCHAR(50) NOT NULL UNIQUE,
  `roll_no` VARCHAR(20) NULL,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `session_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `section_id` INT NOT NULL,
  `parent_name` VARCHAR(100) NOT NULL,
  `parent_phone` VARCHAR(25) NOT NULL,
  `parent_email` VARCHAR(100) NULL,
  `address` TEXT NOT NULL,
  `admission_date` DATE NOT NULL,
  `student_photo` VARCHAR(255) NULL,
  `status` ENUM('active', 'inactive', 'graduated', 'archived') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_students_session` FOREIGN KEY (`session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `fk_students_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `fk_students_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `admissions_inquiries`;
CREATE TABLE `admissions_inquiries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `child_name` VARCHAR(100) NOT NULL,
  `grade_applying` VARCHAR(50) NOT NULL,
  `message` TEXT NULL,
  `status` ENUM('pending', 'reviewing', 'interviewed', 'accepted', 'rejected', 'enrolled') NOT NULL DEFAULT 'pending',
  `converted_student_id` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_admissions_student` FOREIGN KEY (`converted_student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `fee_structures`;
CREATE TABLE `fee_structures` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `fee_title` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `due_date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_fee_structures_session` FOREIGN KEY (`session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `fk_fee_structures_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `exams`;
CREATE TABLE `exams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT NOT NULL,
  `exam_name` VARCHAR(100) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `term` VARCHAR(50) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_exams_session` FOREIGN KEY (`session_id`) REFERENCES `academic_sessions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `staff_salaries`;
CREATE TABLE `staff_salaries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `salary_month` TINYINT NOT NULL,
  `salary_year` SMALLINT NOT NULL,
  `basic_amount` DECIMAL(10,2) NOT NULL,
  `allowances` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `deductions` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `net_salary` DECIMAL(10,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_method` ENUM('cash', 'bank_transfer', 'cheque') NOT NULL DEFAULT 'bank_transfer',
  `status` ENUM('paid', 'pending') NOT NULL DEFAULT 'paid',
  `transaction_ref` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_staff_salary_period` (`staff_id`, `salary_month`, `salary_year`),
  CONSTRAINT `fk_salaries_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. DEPENDENT LEVEL 2 TABLES (Foreign Keys to Level 1 and Level 2)
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS `student_fee_assignments`;
CREATE TABLE `student_fee_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `fee_structure_id` INT NOT NULL,
  `assigned_amount` DECIMAL(10,2) NOT NULL,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `net_amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_student_fee` (`student_id`, `fee_structure_id`),
  CONSTRAINT `fk_fee_assign_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fee_assign_structure` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `fee_payments`;
CREATE TABLE `fee_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `receipt_no` VARCHAR(50) NOT NULL UNIQUE,
  `student_fee_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('cash', 'bank_transfer', 'cheque', 'online') NOT NULL DEFAULT 'cash',
  `payment_date` DATE NOT NULL,
  `reference_no` VARCHAR(100) NULL,
  `remarks` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payments_fee_assign` FOREIGN KEY (`student_fee_id`) REFERENCES `student_fee_assignments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `section_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `status` ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
  `remarks` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_student_daily_attendance` (`student_id`, `attendance_date`),
  CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `fk_attendance_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `exam_schedules`;
CREATE TABLE `exam_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `exam_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `max_marks` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  `pass_marks` DECIMAL(5,2) NOT NULL DEFAULT 40.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_exam_class_subject` (`exam_id`, `class_id`, `subject_id`),
  CONSTRAINT `fk_exam_sched_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_sched_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `fk_exam_sched_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `marks`;
CREATE TABLE `marks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_schedule_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `marks_obtained` DECIMAL(5,2) NOT NULL,
  `grade` VARCHAR(5) NOT NULL,
  `remarks` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_student_exam_marks` (`exam_schedule_id`, `student_id`),
  CONSTRAINT `fk_marks_schedule` FOREIGN KEY (`exam_schedule_id`) REFERENCES `exam_schedules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_marks_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. FINANCIAL TRANSACTIONS LEDGER (Guaranteed Non-Duplication)
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS `financial_transactions`;
CREATE TABLE `financial_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `trans_date` DATE NOT NULL,
  `type` ENUM('income', 'expense') NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `payment_method` ENUM('cash', 'bank_transfer', 'cheque', 'online') NOT NULL DEFAULT 'cash',
  `reference_type` ENUM('fee_payment', 'salary_payment', 'direct') NOT NULL DEFAULT 'direct',
  `reference_id` INT NULL,
  `receipt_invoice_no` VARCHAR(100) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_financial_ref` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `timetables`;
CREATE TABLE `timetables` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `section_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `teacher_id` INT NULL,
  `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `room_no` VARCHAR(50) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_tt_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tt_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tt_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tt_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- SEED DATA PRESERVING ALL EXISTING CONTENT, STYLES, AND SVG ASSETS
-- ============================================================================

-- School Settings
INSERT INTO `school_settings` (`setting_key`, `setting_value`) VALUES
('school_name', 'Brightway School'),
('school_tagline', 'Where curious minds grow into confident futures.'),
('school_email', 'info@brightwayschool.edu'),
('school_phone', '+1 (555) 234-5678'),
('school_address', '124 Education Avenue, Learning District, City 10020'),
('office_hours', 'Monday – Friday, 8:00 AM – 4:00 PM'),
('academic_session_label', '2026 – 2027'),
('currency_symbol', '$');

-- Academic Sessions
INSERT INTO `academic_sessions` (`session_name`, `start_date`, `end_date`, `is_current`) VALUES
('2026-2027', '2026-08-01', '2027-05-31', 1),
('2027-2028', '2027-08-01', '2028-05-31', 0);

-- Classes
INSERT INTO `classes` (`class_name`, `numeric_grade`) VALUES
('Early Years', 0),
('Primary', 1),
('Middle School', 6),
('Secondary', 9);

-- Sections
INSERT INTO `sections` (`class_id`, `section_name`, `capacity`) VALUES
(1, 'A', 25),
(2, 'A', 30),
(2, 'B', 30),
(3, 'A', 30),
(4, 'A', 35);

-- Core Subjects
INSERT INTO `subjects` (`subject_name`, `subject_code`, `subject_type`) VALUES
('Mathematics', 'MTH-101', 'core'),
('English Language', 'ENG-101', 'core'),
('General Science', 'SCI-101', 'core'),
('Computer Studies', 'CMP-101', 'core'),
('Social Studies', 'SOC-101', 'core'),
('Art & Design', 'ART-101', 'elective'),
('Physical Education', 'PHE-101', 'practical'),
('Music', 'MUS-101', 'elective'),
('Moral & Life Skills', 'MLS-101', 'core');

-- Sample Initial Staff
INSERT INTO `staff` (`employee_code`, `first_name`, `last_name`, `gender`, `email`, `phone`, `designation`, `department`, `qualification`, `joining_date`, `basic_salary`, `staff_photo`, `status`) VALUES
('STF-001', 'Eleanor', 'Vance', 'Female', 'e.vance@brightway.edu', '+1 (555) 010-0001', 'Principal', 'Administration', 'M.Ed. Educational Leadership', '2020-01-15', 5500.00, 'assets/images/teacher.svg', 'active'),
('STF-002', 'Marcus', 'Chen', 'Male', 'm.chen@brightway.edu', '+1 (555) 010-0002', 'Head of Academics', 'Academics', 'M.Sc. Mathematics', '2021-03-01', 4200.00, 'assets/images/student-2.svg', 'active'),
('STF-003', 'Sarah', 'Jenkins', 'Female', 's.jenkins@brightway.edu', '+1 (555) 010-0003', 'Head of Student Life', 'Student Affairs', 'B.A. Child Psychology', '2021-08-15', 3800.00, 'assets/images/student-5.svg', 'active'),
('STF-004', 'David', 'Miller', 'Male', 'd.miller@brightway.edu', '+1 (555) 010-0004', 'Admissions Officer', 'Admissions', 'B.B.A. Communications', '2022-02-10', 3400.00, 'assets/images/student-3.svg', 'active');

-- Associate sample subjects with classes
INSERT INTO `class_subjects` (`class_id`, `subject_id`, `teacher_id`) VALUES
(1, 1, 3),
(1, 2, 3),
(2, 1, 2),
(2, 2, 3),
(2, 3, 2),
(3, 1, 2),
(3, 2, 3),
(3, 4, 2),
(4, 1, 2),
(4, 3, 2),
(4, 4, 2);

-- Sample Students
INSERT INTO `students` (`admission_no`, `roll_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `session_id`, `class_id`, `section_id`, `parent_name`, `parent_phone`, `parent_email`, `address`, `admission_date`, `student_photo`, `status`) VALUES
('BW-2026-0001', '01', 'Liam', 'Carter', 'Male', '2018-05-14', 1, 2, 2, 'Robert Carter', '+1 (555) 301-4455', 'robert.carter@example.com', '45 Elm Street, City', '2026-08-01', 'assets/images/student-1.svg', 'active'),
('BW-2026-0002', '02', 'Sophia', 'Patel', 'Female', '2017-09-21', 1, 2, 2, 'Anil Patel', '+1 (555) 301-8899', 'anil.patel@example.com', '88 Maple Lane, City', '2026-08-01', 'assets/images/student-2.svg', 'active'),
('BW-2026-0003', '01', 'Noah', 'Harris', 'Male', '2014-11-05', 1, 3, 4, 'Grace Harris', '+1 (555) 301-7711', 'grace.harris@example.com', '12 Pine Ridge, City', '2026-08-01', 'assets/images/student-3.svg', 'active');

-- Website Text Content (Pre-populates existing copy identically)
INSERT INTO `website_content` (`content_key`, `section_name`, `label`, `content_value`, `content_type`) VALUES
('home_hero_title', 'Home Hero', 'Hero Headline', 'Where curious minds grow into confident futures.', 'text'),
('home_hero_subtitle', 'Home Hero', 'Hero Subtitle', 'A caring, forward-looking school for all grades. Empowering every learner from their first steps to graduation.', 'textarea'),
('home_hero_badge', 'Home Hero', 'Hero Badge Notice', 'Admissions open for 2026 – 2027', 'text'),
('home_about_eyebrow', 'Home About', 'About Section Eyebrow', 'About us', 'text'),
('home_about_title', 'Home About', 'About Section Title', 'A school where learning grows with every student.', 'text'),
('home_about_lead', 'Home About', 'About Section Paragraph', 'At Brightway School, education goes beyond traditional memorization. We provide an inspiring environment where every student develops a genuine passion for discovery, resilience, and creative thinking.', 'textarea'),
('about_page_mission', 'About Page', 'Mission Statement', 'To foster inclusive, curious, and empathetic learners equipped with critical thinking skills to lead purposeful lives.', 'textarea'),
('about_page_vision', 'About Page', 'Vision Statement', 'To be a beacon of progressive, compassionate learning that inspires children to build thoughtful, forward-looking communities.', 'textarea');

-- Website Managed Image Slots (ALL fallback_paths explicitly point to existing SVGs!)
INSERT INTO `website_images` (`image_key`, `section_name`, `label`, `image_path`, `fallback_path`, `alt_text`) VALUES
('hero_arch_1', 'Home Hero', 'Hero Left Student Arch', NULL, 'assets/images/student-1.svg', 'Student holding a folder'),
('hero_arch_2', 'Home Hero', 'Hero Center Student Arch', NULL, 'assets/images/student-2.svg', 'Student giving a thumbs up'),
('hero_arch_3', 'Home Hero', 'Hero Right Student Arch', NULL, 'assets/images/student-3.svg', 'Student with headphones and a notebook'),
('about_teacher_home', 'Home About', 'Home About Section Photo', NULL, 'assets/images/about-teacher.svg', 'Teacher giving a thumbs up'),
('about_inset_home', 'Home About', 'Home About Tour Inset', NULL, 'assets/images/gallery/classroom-1.svg', 'Classroom preview'),
('about_page_main', 'About Page', 'About Page Teacher Photo', NULL, 'assets/images/about-teacher.svg', 'Teacher giving a thumbs up'),
('about_page_campus', 'About Page', 'About Page Campus Facility', NULL, 'assets/images/gallery/campus-2.svg', 'School campus lawn'),
('program_early_years', 'Programs', 'Early Years Learning Arch', NULL, 'assets/images/gallery/classroom-1.svg', 'Early Years learning environment'),
('program_primary', 'Programs', 'Primary Learning Arch', NULL, 'assets/images/gallery/activity-1.svg', 'Primary learning environment'),
('program_middle', 'Programs', 'Middle School Learning Arch', NULL, 'assets/images/gallery/classroom-2.svg', 'Middle School learning environment'),
('program_secondary', 'Programs', 'Secondary Learning Arch', NULL, 'assets/images/gallery/campus-2.svg', 'Secondary learning environment'),
('admissions_photo', 'Admissions', 'Admissions Process Photo', NULL, 'assets/images/student-1.svg', 'Student holding an application folder');

-- Gallery Seed Images (All 12 existing SVGs from gallery.html pre-populated!)
INSERT INTO `gallery_images` (`category`, `title`, `image_path`, `alt_text`, `display_order`, `is_published`) VALUES
('campus', 'Campus building', 'assets/images/gallery/campus-1.svg', 'Campus building (placeholder illustration)', 1, 1),
('activities', 'Outdoor activity', 'assets/images/gallery/activity-1.svg', 'Outdoor activity (placeholder illustration)', 2, 1),
('events', 'Stage event', 'assets/images/gallery/event-1.svg', 'Stage event (placeholder illustration)', 3, 1),
('classrooms', 'Classroom lesson', 'assets/images/gallery/classroom-1.svg', 'Classroom lesson (placeholder illustration)', 4, 1),
('campus', 'Campus lawn', 'assets/images/gallery/campus-2.svg', 'Campus lawn (placeholder illustration)', 5, 1),
('events', 'Celebration', 'assets/images/gallery/event-2.svg', 'Celebration (placeholder illustration)', 6, 1),
('activities', 'Sports time', 'assets/images/gallery/activity-2.svg', 'Sports time (placeholder illustration)', 7, 1),
('classrooms', 'Group learning', 'assets/images/gallery/classroom-2.svg', 'Group learning (placeholder illustration)', 8, 1),
('campus', 'Campus entrance', 'assets/images/gallery/campus-3.svg', 'Campus entrance (placeholder illustration)', 9, 1),
('events', 'School assembly', 'assets/images/gallery/event-3.svg', 'School assembly (placeholder illustration)', 10, 1),
('classrooms', 'Reading time', 'assets/images/gallery/classroom-3.svg', 'Reading time (placeholder illustration)', 11, 1),
('activities', 'Playground', 'assets/images/gallery/activity-3.svg', 'Playground (placeholder illustration)', 12, 1);

-- Sample Holidays
INSERT INTO `holidays` (`title`, `start_date`, `end_date`, `description`) VALUES
('Winter Break', '2026-12-20', '2027-01-05', 'School closed for winter recess.'),
('Spring Holiday', '2027-03-22', '2027-03-29', 'Spring break vacation.');

-- Sample Announcements
INSERT INTO `announcements` (`title`, `content`, `target_audience`, `is_published`, `published_date`) VALUES
('Admissions Open for Academic Year 2026-2027', 'Applications are now open for Early Years through Secondary levels. Schedule a campus visit or apply online.', 'public', 1, '2026-08-01'),
('Annual Science Fair Registration', 'Students in Grades 4 to 10 can submit their experiment proposals by October 30.', 'all', 1, '2026-09-15');
