# School Management System
## Software Requirements Specification (SRS)

**Version:** 1.0  
**Project Type:** Web-Based School Management System  
**Target Users:** Small and medium-sized private schools in Pakistan  
**Technology:** HTML, CSS, JavaScript, PHP, MySQLi  
**Architecture:** Single school database with role-based access control

---

## 1. Project Overview

The School Management System is a web-based application designed to manage the daily administrative and academic activities of a private school through one centralized system.

The system will allow authorized school staff to manage student records, admissions, classes, attendance, examinations, fees, staff, salaries, expenses, and reports.

The application will support three user roles:

1. School Admin / Principal
2. Accountant / Fee Manager
3. Receptionist / Admission Officer

Each role will have a separate login experience and specific permissions according to its responsibilities.

The system must be simple, secure, reliable, and suitable for schools that currently manage their records through registers, paper forms, or spreadsheets.

## 2. Project Objectives

The system must:

- Centralize school records in one database.
- Reduce manual paperwork and duplicate data entry.
- Improve the accuracy of fee collection and financial records.
- Simplify admissions and student registration.
- Maintain attendance, examination, and result records.
- Manage staff information and salary records.
- Generate useful reports and printable documents.
- Restrict access to confidential information.
- Provide an interface that non-technical school staff can use easily.
- Support future improvements without unnecessarily rebuilding existing modules.

## 3. Project Scope

### 3.1 Included Features

The system will include:

- Authentication and user account management.
- Role-based permissions.
- Admin dashboard.
- Student management.
- Admission management.
- Class, section, subject, and academic session management.
- Attendance management.
- Examination and result management.
- Fee structures, payments, receipts, and outstanding dues.
- Staff records and salary management.
- Income, expenses, and financial reporting.
- Printable reports and CSV exports.
- School information, announcements, holidays, and gallery management.
- School settings and account management.
- Database backup and recovery procedures.
- Activity logs for important administrative and financial actions.

### 3.2 Excluded Features for Version 1

The following are not required in the initial version:

- Separate student portal.
- Separate parent portal.
- Separate teacher portal.
- Online payment gateway.
- Mobile applications.
- Automated SMS or WhatsApp integration.
- AI chatbot.
- Multi-school management through the same customer-facing school portal.

These features must not be added unless they are approved as future requirements.

## 4. User Roles and Access Control

### 4.1 Role One: School Admin / Principal

The School Admin is the primary user and has complete administrative control over the school's system.

The Admin can manage all three roles' relevant activities, create staff accounts, assign permissions, and access all school records.

**Admin permissions:**

- Full access to student and admission records.
- Full academic management.
- Full fee and financial management.
- Full staff and salary management.
- Full reporting access.
- School website content management.
- School settings and account management.
- Creation, modification, and deactivation of user accounts.
- Review of important activity logs.
- Oversight of backup and recovery status.

### 4.2 Role Two: Accountant / Fee Manager

The Accountant is responsible for fee collection and authorized financial operations.

The Accountant must not receive unrestricted administrative access.

**Accountant permissions:**

- View necessary student information for fee collection.
- View assigned fee structures.
- Record full and partial fee payments.
- Generate and print payment receipts.
- View payment histories and outstanding dues.
- Apply authorized discounts or concessions according to the school's permission policy.
- Record authorized income and expenses.
- Record salary payments if explicitly permitted by the Admin.
- View and print permitted financial reports.
- Export authorized financial data.

**Restricted actions:**

- Cannot manage user accounts or permissions.
- Cannot change global school settings.
- Cannot modify examination results or attendance.
- Cannot silently delete or alter completed financial transactions.
- Cannot access unrelated confidential staff or student information.
- Cannot approve their own restricted financial adjustments if approval is required.

### 4.3 Role Three: Receptionist / Admission Officer

The Admission Officer manages student registration and admission-related activities.

**Admission Officer permissions:**

- Create new admission applications.
- Enter student and guardian information.
- Generate unique admission application or student numbers.
- View and update permitted admission records.
- Check existing records to prevent duplicate registrations.
- Select classes and sections configured by the Admin.
- Track pending, approved, rejected, and enrolled applications.
- Upload and print admission documents where supported.
- View admission summaries and print admission reports.
- Update approved basic student information within assigned permissions.

**Restricted actions:**

- Cannot collect or modify fee transactions.
- Cannot view detailed financial reports.
- Cannot access salary records or expenses.
- Cannot change marks, results, or attendance.
- Cannot manage users or system settings.
- Cannot approve their own admission applications if Admin approval is required.

### 4.4 Permission Enforcement

Permissions must be enforced on the server side, not only by hiding buttons in the interface.

Every protected page, form submission, data export, and modification request must verify the user's authenticated session and required permission.

Unauthenticated users must be redirected to the login page. Unauthorized users must receive an appropriate access-denied response.

## 5. Functional Requirements: Authentication and User Management

### FR-01: Login

- Users must log in using their assigned credentials.
- Passwords must be stored using secure password hashing.
- Login errors must not reveal whether a username or password specifically was incorrect.
- Sessions must be protected against unauthorized access.
- Users must be able to log out securely.
- Inactive accounts must not be able to log in.
- Repeated failed login attempts should be appropriately limited or monitored.

### FR-02: User Management

The Admin must be able to:

- Create Accountant and Admission Officer accounts.
- Assign each account an authorized role.
- Activate or deactivate accounts.
- Reset passwords securely.
- View account status and relevant activity.
- Prevent unauthorized users from changing their own permissions.

User deactivation must preserve associated financial and academic records.

## 6. Functional Requirements: School Admin Modules

### 6.1 Admin Dashboard

The dashboard must provide a clear overview of school operations.

It should display:

- Total active students.
- Total staff members.
- Number of classes and sections.
- Today's attendance summary.
- Fee collection for the selected period.
- Outstanding fee balances.
- Recent admissions.
- Upcoming examinations and important school dates.
- Income and expense summaries.
- Important notifications or pending administrative tasks.

All totals must come from actual database records. The dashboard must not display hardcoded statistics.

### 6.2 Student Management

The Admin must be able to:

- Add new student records.
- View and search student profiles.
- Update student information.
- Assign admission numbers.
- Assign academic sessions, classes, and sections.
- Record guardian contact information.
- Track enrollment and leaving status.
- View attendance, fee, and academic history.
- Print student profiles and lists.
- Export permitted student lists.

Student records should not be permanently deleted if they have linked payments, attendance, examinations, or other historical records. Archiving or marking a student as inactive should be used where appropriate.

### 6.3 Admission Management

The Admin must be able to:

- Review new admission applications.
- Approve or reject applications.
- Record admission decisions.
- Convert an approved application into a student record.
- Prevent duplicate student creation.
- Assign the correct academic session, class, and section.
- Review submitted documents.
- Search and filter admission applications.
- Print admission forms and reports.

Conversion from an admission application into a student record must not create duplicate students or lose existing information.

### 6.4 Academic Management

The Admin must manage:

**Academic sessions**
- Create and manage academic years.
- Set the active academic session.
- Preserve historical academic records.

**Classes and sections**
- Create, edit, and deactivate classes.
- Create sections under the appropriate classes.
- Assign students to valid class-section combinations.
- Prevent invalid assignments.

**Subjects**
- Create and manage subjects.
- Assign subjects to appropriate classes.
- Maintain subject information for examination and result management.

**Student promotion**
- Promote eligible students to the next class or section.
- Preserve previous academic history.
- Prevent accidental overwriting of historical records.

### 6.5 Attendance Management

The Admin must be able to:

- Mark daily student attendance.
- Select a date, class, and section.
- Record present, absent, and other approved attendance statuses.
- Correct attendance according to permissions.
- Prevent duplicate attendance entries for the same student and date.
- View daily and monthly attendance summaries.
- Search attendance by student or class.
- Print attendance registers and reports.

Attendance reports must be calculated from stored attendance records.

### 6.6 Examination and Results Management

The Admin must be able to:

- Create examinations.
- Define examination dates or periods.
- Assign examinations to classes and subjects.
- Define maximum marks and passing marks.
- Enter and update student marks.
- Calculate totals, percentages, and grades according to configured rules.
- Identify pass and fail status.
- Generate student result cards.
- Generate class-wise result summaries.
- Print results and permitted reports.

The system must validate marks against the configured maximum marks and must not calculate results from missing or invalid marks without clearly indicating the missing data.

Grade boundaries and rounding rules must be configurable or explicitly documented.

### 6.7 Fee Structure and Fee Assignments

The Admin must be able to:

- Create fee categories.
- Define class-wise or session-wise fee structures.
- Configure monthly tuition fees and other approved charges.
- Assign fees to individual students.
- Apply authorized concessions and discounts.
- Record fee adjustments with appropriate reasons.
- Review outstanding balances.
- View student fee histories.

The system must distinguish between assigned fees, payments received, adjustments, and remaining balances.

### 6.8 Staff Management

The Admin must be able to:

- Add and update staff records.
- Record names, contact details, designations, joining dates, and employment status.
- Maintain teacher and non-teaching staff categories.
- Search and filter staff.
- View relevant employment history.
- Print staff lists and records.

Access to confidential staff information must be restricted to authorized users.

### 6.9 Salary Management

The Admin must be able to:

- Define or record staff salary amounts.
- Maintain monthly salary records.
- Record approved allowances and deductions.
- Record salary payments and payment dates.
- Track unpaid and partially paid salaries where applicable.
- Generate salary slips.
- View salary expenditure reports.
- Correct errors through controlled adjustments.

Salary calculations and payment records must remain consistent with financial transactions.

### 6.10 Income and Expense Management

The Admin must be able to:

- Define income and expense categories.
- Record school income.
- Record operational expenses.
- Maintain transaction dates, amounts, descriptions, and references.
- Review transaction histories.
- Filter transactions by date and category.
- View monthly and yearly summaries.
- Print financial reports.

Fee collections must not be counted twice as income when financial records are linked to the same payment.

### 6.11 Reports and Exports

The Admin must have access to:

- Student reports.
- Admission reports.
- Class and section lists.
- Attendance reports.
- Examination and result reports.
- Fee collection reports.
- Outstanding dues reports.
- Student fee ledgers.
- Staff reports.
- Salary payment reports.
- Income and expense reports.
- Financial summaries.

Reports must support relevant filters such as date range, academic session, class, section, and payment status.

CSV exports must contain the same authorized records and filters as the corresponding report.

### 6.12 School Website Content

The Admin must be able to manage available website content, including:

- School name, logo, and contact information.
- About-school information.
- Announcements.
- Holidays and important dates.
- Gallery images.
- Other existing editable website content.

Image uploads must be validated by file type and size. The system must not require the Admin to edit source code manually just to change a supported image.

Only existing, supported website pages and content areas should be integrated unless additional pages are explicitly approved.

### 6.13 School Settings

The Admin must be able to configure:

- School details and logo.
- Academic session.
- Relevant fee and result settings.
- Receipt and report information.
- Available classes and sections.
- User accounts and permissions.
- Other required school-specific settings.

Changes to settings must not silently damage historical records.

## 7. Functional Requirements: Accountant Modules

### 7.1 Accountant Dashboard

The dashboard must show authorized financial information, including:

- Today's fee collection.
- Monthly fee collection.
- Outstanding fee dues.
- Recent payments.
- Unpaid or partially paid fees.
- Recent expenses.
- Relevant pending financial tasks.

It must not expose unrelated administrative settings or confidential academic records.

### 7.2 Fee Collection

The Accountant must be able to:

- Search for students.
- View relevant fee assignments and outstanding balances.
- Record full payments.
- Record partial payments.
- Select an approved payment method.
- Record payment date and reference where applicable.
- Generate a unique receipt number.
- Print or reprint a receipt.
- View payment history.

The system must prevent duplicate submissions from creating duplicate payments.

A payment must not exceed the valid outstanding balance unless an explicitly supported advance-payment feature permits it.

### 7.3 Receipts and Corrections

Receipts must contain:

- School name and available logo.
- Receipt number.
- Student name and admission number.
- Class and section where applicable.
- Payment date.
- Amount received.
- Payment method.
- Fee details and remaining balance where applicable.
- Authorized school information.

Completed transactions must not be silently deleted or rewritten. Corrections must preserve an audit trail and correctly update balances and reports.

### 7.4 Financial Operations

Depending on the permissions granted by the Admin, the Accountant may:

- Record authorized income and expenses.
- Record salary payments.
- View relevant transaction details.
- Print financial summaries.
- Export authorized reports.

Restricted actions must require Admin authorization or an approved adjustment process.

### 7.5 Financial Reports

The Accountant must be able to generate:

- Daily fee collection reports.
- Monthly fee collection reports.
- Outstanding dues reports.
- Student-wise fee ledgers.
- Payment method summaries.
- Expense reports.
- Authorized salary payment reports.
- Financial summaries for a selected period.

Reports must reconcile with actual stored transactions and must not double-count linked payments.

## 8. Functional Requirements: Admission Officer Modules

### 8.1 Admission Dashboard

The dashboard must display:

- Total admission applications.
- Pending applications.
- Approved applications.
- Rejected applications.
- Recently registered students.
- Admissions requiring further action.

All figures must reflect actual records.

### 8.2 Admission Registration

The Admission Officer must be able to:

- Create an admission application.
- Enter required student details.
- Enter guardian contact details.
- Record the requested class and section.
- Record the application date.
- Upload supported admission documents.
- Save an incomplete application when appropriate.
- Update permitted application fields.
- Print an admission form.

Required fields must be validated before submission.

### 8.3 Duplicate Prevention

Before creating an application or student record, the system should check relevant existing identifiers and information.

The system must not rely solely on a student's name to identify duplicates because multiple students may share the same name.

### 8.4 Admission Status and Approval

The system must support clearly defined admission statuses:

- Pending.
- Approved.
- Rejected.
- Enrolled.

Only authorized users may approve or reject applications if approval is required by the school.

Once an application is approved and converted into a student record, the system must preserve the relationship between the application and the created student.

### 8.5 Student Record Access

The Admission Officer may view and update only permitted student information.

Financial transactions, examination marks, attendance records, salaries, user permissions, and confidential settings must remain inaccessible unless specific additional permissions are approved.

### 8.6 Admission Reports

The Admission Officer must be able to:

- View new admissions for a selected period.
- Filter applications by status.
- Search applications.
- Print admission lists.
- Export authorized admission reports.

## 9. Data Management Requirements

The database must maintain consistent relationships between relevant records.

Core data entities should include:

- Users and roles.
- Students and guardians.
- Admission applications.
- Academic sessions.
- Classes, sections, and subjects.
- Attendance records.
- Examinations and marks.
- Fee categories and student fee assignments.
- Fee payments and receipts.
- Staff and salary records.
- Financial transactions.
- Website content and gallery images.
- Audit logs.

The final database design must be based on the actual implementation and existing schema. Existing tables must be inspected before any schema changes are proposed.

Data relationships must use appropriate constraints and validation. Important financial and academic records must not be accidentally removed through cascading deletions.

## 10. Security Requirements

- Passwords must be securely hashed.
- Database queries involving user input must use prepared statements.
- Forms that change data must have appropriate CSRF protection.
- User input and file uploads must be validated.
- Output must be escaped appropriately to reduce XSS risks.
- Authentication and authorization must be checked on protected server-side operations.
- Sessions must be securely managed.
- Sensitive information must not be exposed in error messages.
- Important account, permission, financial, and academic changes must be logged.
- Production configuration must not expose database credentials.
- Access to backups must be restricted.
- Data export functions must enforce role permissions.

## 11. Non-Functional Requirements

### 11.1 Usability

- The interface must use clear labels and simple navigation.
- Forms must display understandable validation messages.
- Common tasks should require minimal steps.
- The design must suit non-technical school office staff.

### 11.2 Responsiveness

- The application must work on common desktop and laptop browsers.
- Important operations must remain usable on mobile screens.
- Tables, forms, menus, and printable documents must be tested at different screen sizes.

### 11.3 Reliability

- Successful changes must be saved to the database.
- Failed operations must not leave partial or inconsistent financial records.
- Multi-step financial operations should use database transactions where appropriate.
- Errors must be logged safely without exposing confidential data.

### 11.4 Performance

- Common lists must support pagination or another appropriate way of handling larger datasets.
- Search and filtering must work efficiently.
- Database queries must avoid unnecessary repeated processing.

### 11.5 Maintainability

- Existing project conventions must be followed.
- Reusable layouts and common validation logic should be reused.
- Duplicate modules and unnecessary files should not be created.
- Changes must be documented.
- The application must remain understandable for future maintenance.

## 12. Backup and Recovery Requirements

The system must provide a documented, reliable backup and recovery procedure.

- Database backups must be created through a safe, authorized process.
- Backup files must be stored in an appropriately restricted location.
- A restore procedure must be documented and tested.
- Backup status must not be reported as successful without evidence.
- A successful backup must be verified for usability.
- Recovery testing must use a safe test environment.

A backup feature is not considered complete merely because a backup button exists.

## 13. Testing and Acceptance Criteria

A module is complete only when its important workflows have been tested.

### 13.1 Admin Acceptance Tests

- Admin can log in and access authorized modules.
- Admin can create and update a student.
- Admin can configure classes and sections.
- Admin can manage attendance and examinations.
- Admin can review fee records and financial reports.
- Admin can manage staff and salary records.
- Admin can create and restrict user accounts.
- Unauthorized requests are blocked.

### 13.2 Accountant Acceptance Tests

- Accountant can log in to the correct dashboard.
- Accountant can record a valid full or partial payment.
- A unique receipt is generated and can be printed.
- Outstanding balances update correctly.
- Duplicate submissions do not create duplicate payments.
- Financial reports match the stored transactions.
- Unauthorized academic and administrative operations are blocked.

### 13.3 Admission Officer Acceptance Tests

- Admission Officer can create an application.
- Required-field validation works.
- Duplicate registrations are appropriately prevented.
- Application statuses update correctly.
- Approved applications can be converted without duplicate student records.
- Admission reports show the correct records.
- Unauthorized financial and academic operations are blocked.

### 13.4 General Acceptance Tests

- Login, logout, and session handling work correctly.
- Role permissions are verified on the server.
- Database changes persist after page reload.
- Important totals reconcile with their underlying records.
- Printing and exports work correctly.
- Mobile layouts remain usable.
- Backup restoration has been verified.
- No unresolved critical security or data-integrity issues remain.

## 14. Implementation Strategy

Implementation must follow a role-by-role approach.

### Stage 1: School Admin

Inspect and verify the existing system first. Complete only the missing Admin functionality, correct confirmed defects, and test all relevant workflows.

The stage must not be marked complete until its acceptance tests pass or any remaining limitations are explicitly documented.

### Stage 2: Accountant

After the Admin stage is verified, implement the Accountant's login, dashboard, authorized financial functions, restrictions, and tests.

Existing fee and finance functionality must be reused wherever appropriate.

### Stage 3: Admission Officer

After the Accountant stage is verified, implement the Admission Officer's login, dashboard, admission workflows, restrictions, and tests.

Existing admission and student-management functionality must be reused wherever appropriate.

### Final Integration

After all three roles have passed their individual tests:

- Verify that all roles work together correctly.
- Verify that permissions do not overlap incorrectly.
- Recheck financial and academic data consistency.
- Run regression tests to ensure existing features still work.
- Produce a final implementation and testing report.

## 15. Implementation Rules

- Inspect existing code and database structure before changing anything.
- Do not rebuild working modules unnecessarily.
- Do not invent new requirements beyond this SRS without approval.
- Do not replace the established technology stack without approval.
- Do not create separate teacher, student, or parent portals.
- Do not introduce fake data into production records.
- Do not claim that a feature works unless it has been tested.
- Do not create unnecessary backup or duplicate files inside the project.
- Do not silently delete or overwrite existing data.
- Report blockers, failed tests, incomplete features, and required manual steps honestly.

## 16. Definition of Done

The School Management System will be considered ready for a controlled school pilot only when:

1. The three specified roles work correctly.
2. Role permissions are enforced on the server.
3. Required modules function against the database.
4. Fee, receipt, salary, and financial calculations have been verified.
5. Academic and admission workflows have been verified.
6. Critical security and data-integrity issues are resolved.
7. Backup and restore procedures have been tested.
8. Important reports and printable documents work correctly.
9. The interface is usable on supported devices.
10. Actual test results and remaining limitations are documented.

Passing these criteria makes the system eligible for a controlled pilot; it does not automatically guarantee production readiness or commercial success.

---

**End of SRS — Version 1.0**
