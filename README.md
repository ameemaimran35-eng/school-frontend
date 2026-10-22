# School Management System

A web-based School Management System designed for small and medium-sized private schools in Pakistan. Built with **HTML, CSS, Vanilla JavaScript, Core PHP, and MySQLi**.

Full specifications and requirements are documented in [SRS.md](file:///c:/xampp/htdocs/school-management-system/SRS.md) (Version 1.0).

---

## Architecture & User Roles

The system uses a single school database with server-side role-based access control (RBAC) supporting three user roles:
1. **School Admin / Principal** — Full administrative, academic, financial, staff, and system control.
2. **Accountant / Fee Manager** — Fee collections, receipts, dues tracking, authorized income/expenses, and financial reporting.
3. **Receptionist / Admission Officer** — Student registration, inquiries, admission workflow, and duplicate checks.

---

## Setup & Running Locally

1. Place the project folder in your local web server root (e.g., `C:/xampp/htdocs/school-management-system`).
2. Start **Apache** and **MySQL** in XAMPP.
3. Import the database schema from `database/school_management_db.sql`.
4. Configure database credentials in `config/database.php`.
5. Access the public website at `http://localhost/school-management-system/` and the admin/portal at `http://localhost/school-management-system/admin/login.php`.
