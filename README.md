# CityCare Medical Center

CityCare Medical Center is a Laravel-based clinic management platform developed to centralise patient, appointment, clinical, laboratory, pharmacy, billing, inventory, procurement, reporting, and administrative workflows.

The project was developed as a project-based academic submission and has been taken through structured implementation, security hardening, data-integrity testing, and final automated regression testing. The repository is maintained so that the application remains runnable, testable, and suitable for supervised local demonstration.

## 1. Technology Foundation

- Laravel 13.26.1
- PHP 8.3+
- MySQL
- Blade server-rendered UI
- Laravel sessions, cache, queues, validation and middleware
- Eloquent ORM and migrations
- PHPUnit feature/unit testing
- GitHub for source control
- Africa/Kampala application timezone

Local environment configuration is supplied through `.env`. Secret-bearing local files are intentionally excluded from version control.

## 2. Project Purpose

The system addresses the operational problems described in the academic project brief, including appointment coordination, patient-record management, clinical documentation, payment tracking, inventory operations, reporting, and controlled access to sensitive information.

The application is organised around role-appropriate workflows so users are granted access to functions relevant to their responsibilities rather than relying only on interface-level hiding.

## 3. Major Functional Areas

### Patient Management

- Patient registration and records
- Patient search and registry views
- Patient portal administration foundation
- Facility-scoped patient access
- Patient lifecycle and validation rules

### Appointments and Reception

- Appointment workspace
- Appointment creation and validation
- Availability and collision protection
- Check-in, completion and cancellation workflows
- Appointment filtering and patient search

### Clinical Care

- Clinical encounters
- Vitals
- Clinical notes and finalisation
- Diagnoses
- Treatment plans
- Referrals and attachments
- Encounter lifecycle controls

### Laboratory

- Diagnostic order workflows
- Result recording
- Order cancellation and lifecycle validation
- Permission-controlled laboratory operations

### Pharmacy

- Prescription management
- Dispensing workflows
- Inventory-linked dispensing
- Quantity and lifecycle validation
- Facility-scoped medication access

### Billing and Payments

- Billable services and service pricing
- Charges
- Invoices and invoice line items
- Partial and full payments
- Overpayment prevention
- Invoice cancellation and charge release
- Idempotent charge generation
- Transactional financial operations

### Inventory and Procurement

- Stores and stock balances
- Procurement and purchase orders
- Goods receiving
- Stock movements
- Pharmacy stock consumption
- Facility and store integrity checks
- Concurrency protection for stock updates

### Reporting and Audit

- Report definitions and execution
- Report run lifecycle
- CSV export workflow
- Facility and date scoped reporting
- Audit log viewing

### Administration and Organisation

- Facility configuration
- Departments
- Service points
- System settings
- Organisation administration permissions

## 4. Authentication and Authorization

The application provides session-based authentication with explicit account-state enforcement and permission middleware.

Key controls include:

- Secure session regeneration after authentication
- Active/inactive account enforcement
- Login throttling
- Logout with session invalidation and CSRF-token regeneration
- Role and permission resolution
- Permission-based route protection
- Facility-level access checks for protected operational data
- Controlled staff invitation lifecycle

The implementation distinguishes authentication from authorization. Protected routes are checked through middleware and permission boundaries rather than being secured only by hiding interface elements.

## 5. Database Design

The database is implemented through Laravel migrations and Eloquent models with primary keys, foreign keys, scoped relationships, unique constraints, and lifecycle rules appropriate to the application domains.

Important integrity measures include:

- Facility-scoped operational records
- Foreign-key relationships between related domain records
- Unique inventory balance per store and inventory item
- Validation of cross-facility relationships
- Transactional operations for multi-step financial and inventory workflows
- Row locking where concurrent updates could otherwise cause inconsistent state

The local database used during development is `citycare_medical_center`.

## 6. Models, Relationships and Business Logic

Domain models use Eloquent relationships for common one-to-many, many-to-one and related entity associations. Important business rules are implemented through service classes and transactional workflows where appropriate rather than being placed only in views.

The project includes dedicated service-layer logic for areas such as:

- Appointments
- Billing
- Inventory and procurement
- Clinical workflows
- Organisation configuration
- Patient portal management
- Reporting

Model assignment boundaries and request-validation boundaries are intentionally separated so server-managed fields are not automatically accepted as client-controlled input.

## 7. CRUD and HTTP Workflows

Core domains provide create, read, update or lifecycle-management workflows through controllers, request validation, Eloquent models and Blade views.

The project uses named routes, grouped middleware, resource-oriented controller actions and explicit permissions for state-changing operations. Destructive or irreversible operations are protected through lifecycle validation and explicit confirmation workflows where applicable.

## 8. Search, Filtering and Reporting

Operational lists support search and filtering where the underlying workflow requires it, including patient records, appointments, and operational reports.

Reporting functionality includes report execution, run tracking and CSV export. Reports are constrained by operational scope so sensitive information is not treated as globally visible data.

## 9. API / AJAX Requirement Review

The academic brief requires at least one JSON API or AJAX-based enhancement, such as dynamic appointment-slot loading, instant patient search, or doctor availability checking. A repository review did not identify a dedicated `/api` route or an obvious AJAX/JSON workflow in `routes/web.php`.

This requirement is therefore **not claimed as completed in this documentation**. It should be confirmed during browser/UI testing against any existing JavaScript interactions, or implemented explicitly if the final submission requires a demonstrable API/AJAX feature.

## 10. UI and UX Direction

The interface follows a consistent medical-centre visual direction using:

- Blue-focused visual hierarchy
- White/cream surfaces
- Restrained accent colours
- Responsive layouts
- Tables, cards, forms and status indicators
- Separate internal and patient-facing workflow concepts

The application includes a responsive login and authenticated dashboard foundation together with operational workspaces for the implemented modules.

The next validation stage is local browser and user-journey testing on the actual interface.

## 11. Security and Reliability Hardening

The project completed a dedicated hardening cycle before UI testing.

Security work included:

- Private handling of sensitive clinical attachments
- Facility-level object access controls
- Session and authentication security review
- Request validation and mass-assignment review
- Sensitive-data exposure review
- Route authorization and least-privilege verification

Reliability work included:

- Database integrity checks
- Transaction and rollback protection
- Concurrency and race-condition controls
- Lifecycle and state-transition validation
- Failure-recovery and idempotency tests

## 12. Automated Testing Status

Automated testing is layered across unit, feature, database-integrity, authorization, lifecycle and transactional behaviour.

The final Phase 14 regression was executed on `main` after the hardening branches were merged.

**Final result:**

- **252 tests passed**
- **953 assertions**
- **0 failures**
- **0 errors**

The application was also checked after cache clearing and migration verification. No pending migrations remained at the final verification point.

Automated tests establish the current regression baseline, but they do not replace browser-based usability and visual testing.

## 13. Academic Project Alignment

The examination brief for **BCS 3303 Advanced Application Development & Database Design** requires a Laravel Clinic Appointment and Patient Management System with application setup, Blade layout, responsive navigation, reusable UI elements, database design, Eloquent relationships, controllers and business logic, CRUD workflows, route organisation, authentication, role-based access, search/filtering/pagination, reporting, at least one API/AJAX feature, clean code, and project documentation.

The repository demonstrates substantial coverage of these areas, including the application foundation, Blade-based interface, patient and appointment workflows, clinical operations, billing, pharmacy, laboratory, inventory, reporting, authentication, permissions, database relationships and project documentation.

The **API/AJAX requirement remains the principal item that should not be represented as completed without additional evidence**. This README deliberately records that limitation instead of overstating compliance.

The examination brief also requires project documentation including setup steps, major features and screenshots or a brief description of system modules. This README supplies setup guidance and module descriptions. Screenshots can be added after the browser-testing stage if required for the final academic submission package.

## 14. Local Setup

1. Clone the repository.
2. Install PHP dependencies:

```bash
composer install
```

3. Create `.env` from `.env.example` and configure local database credentials.
4. Generate the application key:

```bash
php artisan key:generate
```

5. Create the MySQL database `citycare_medical_center`.
6. Run migrations and seed data as appropriate:

```bash
php artisan migrate
php artisan db:seed
```

7. Create the public storage link:

```bash
php artisan storage:link
```

8. Clear application caches when required:

```bash
php artisan optimize:clear
```

9. Run automated tests:

```bash
php artisan test
```

10. Start the local application:

```bash
php artisan serve
```

## 15. Development and Submission Notes

- Do not commit `.env` or other secret-bearing local configuration.
- Keep production credentials outside the repository.
- Keep generated dependencies and build artefacts out of source control unless explicitly required.
- Use feature branches for material changes and verify the branch before merging to `main`.
- Keep automated tests green before advancing between major project checkpoints.
- Complete browser and user-journey testing before final academic packaging.

## 16. Project Status

**Current status: implementation and automated hardening completed; browser/UI testing is the next validation stage.**

The `main` branch contains the merged implementation and has passed the final automated regression baseline described above.
