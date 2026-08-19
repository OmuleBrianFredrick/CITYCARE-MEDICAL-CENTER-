# CityCare Medical Center

A full-scale medical-center management platform being developed with Laravel 13 for CityCare Medical Center. The project is intentionally designed as a modular, secure, professional system rather than a minimal CRUD application.

## Technology Foundation

- Laravel 13.26.1
- PHP 8.3+
- MySQL
- Africa/Kampala application timezone
- Blade-based server-rendered UI foundation
- Laravel sessions, cache and queues
- PHPUnit feature/unit testing
- GitHub as the source-control and collaboration platform

## Product Vision

CityCare will provide a unified operational platform covering patient access, reception, appointments, clinical care, diagnostics, pharmacy, billing, payments, insurance, inventory, procurement, reporting, notifications, administration, and security/audit workflows.

The architecture separates patient-facing access from internal staff access and uses explicit role/permission authorization for protected capabilities.

## Architecture

The living architecture specification is maintained in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

The principal domains are:

- Organization and administration
- Identity and access management
- Patient management
- Appointments and scheduling
- Reception and queue management
- Clinical encounters and records
- Laboratory
- Pharmacy
- Billing and finance
- Insurance
- Inventory and procurement
- Admissions and inpatient workflows where required
- Notifications
- Reporting and analytics
- Audit and security

## Identity and Access Foundation

CityCare distinguishes between patient accounts and internal staff accounts through `users.user_type`.

Initial system roles include:

- Super Administrator
- Administrator
- Receptionist
- Doctor / Clinician
- Nurse / Clinical Support
- Laboratory Staff
- Pharmacy Staff
- Cashier / Finance
- Records Officer
- Inventory / Stores Staff
- Patient

Authorization is permission-based. Roles are associated with reusable permissions through the role/permission schema. Protected routes can use the `permission:<permission-slug>` middleware rather than relying on UI visibility alone.

Staff profiles are separated from the base authentication record and can contain employee number, job title, employment status, phone, and joining date.

## Authentication Foundation

The current authentication layer includes:

- Email/password session authentication
- Secure session regeneration after successful login
- Explicit active/inactive account enforcement
- Logout with session invalidation and CSRF token regeneration
- Last-login timestamp tracking
- Named login rate limiting using email and IP address
- Generic invalid-credential responses
- Role and permission resolution through the authenticated user
- Permission middleware for protected application capabilities
- Premium responsive CityCare login and authenticated dashboard foundation

Multi-factor authentication/OTP will be introduced through the security policy appropriate to each account class rather than hard-coded as a universal login ceremony.

## Database

The local development environment uses the MySQL database:

`citycare_medical_center`

The database foundation currently contains Laravel framework tables plus the CityCare access-control schema. CityCare domain migrations will be introduced in controlled stages so relationships, constraints, authorization boundaries, and business rules can be validated as each module is built.

## UI Direction

The visual language is based on a premium medical-center experience:

- layered blue palette
- white/cream surfaces
- restrained yellow accents
- high readability and accessible contrast
- responsive layouts
- professional cards, tables, status badges and forms
- separate but related patient-facing and internal-workspace experiences

The current login and dashboard are foundation interfaces. They will evolve as the complete navigation and domain modules are introduced.

## Testing Strategy

Testing is layered:

1. Unit tests for isolated business logic.
2. Feature tests for HTTP workflows and authorization.
3. Database tests for relationships, constraints and transactional integrity.
4. Local browser testing for complete user journeys and visual/interaction QA.
5. GitHub CI for reproducible automated checks as the pipeline is established.

Current automated foundation coverage includes access-control seeding, role/permission resolution, patient/staff distinction, authentication, active-account enforcement, login throttling, authorization middleware, and logout behavior.

## Development Sequence

The project follows a controlled delivery sequence:

1. Foundation
2. Identity and Security
3. Organization and Departments
4. Patient Management
5. Appointments and Reception
6. Clinical Core
7. Laboratory
8. Pharmacy
9. Billing and Payments
10. Insurance
11. Inventory and Procurement
12. Admissions where required
13. Notifications and Reporting
14. Premium UI/UX refinement
15. Local browser QA
16. Security/performance hardening
17. Documentation and release readiness

A module is not considered complete merely because its pages render. It must have its data model, migrations, relationships, validation, authorization, business rules, UI states, tests, and applicable browser workflow verified.

## Current Project Status

### Completed

- Laravel 13 foundation created
- GitHub repository connected and synchronized
- CityCare application identity configured
- Africa/Kampala timezone configured
- MySQL configured and connected
- Public storage link established
- Framework migrations executed
- CityCare access-control schema implemented
- System roles and permissions seeded
- Staff profile foundation implemented
- Role/permission resolution implemented
- Authentication request validation implemented
- Login/logout workflow implemented
- Active-account enforcement implemented
- Login rate limiting implemented
- Permission middleware implemented
- Initial CityCare login interface implemented
- Initial authenticated dashboard implemented
- Authentication/access-control feature tests added
- Architecture specification added under `docs/ARCHITECTURE.md`

### Current Phase

**Phase 1.3 — Identity and Security**

Current chapter: **Authentication & Authorization Engine**.

Next work will expand the staff account lifecycle, password/security workflows, authorization policies, audit/security event foundation, and the complete authentication test matrix before moving into organization and department management.

## Local Setup

1. Clone the repository.
2. Install PHP dependencies:

```bash
composer install
```

3. Create `.env` from `.env.example` and configure local credentials.
4. Generate an application key:

```bash
php artisan key:generate
```

5. Create the `citycare_medical_center` MySQL database.
6. Run migrations and seeders:

```bash
php artisan migrate
php artisan db:seed
```

7. Link public storage:

```bash
php artisan storage:link
```

8. Run automated tests:

```bash
php artisan test
```

9. Start the local application:

```bash
php artisan serve
```

## Development Rule

Every phase should leave `main` runnable, documented, and testable. Changes are implemented in logical checkpoints and synchronized back to the GitHub repository before the project advances to the next major domain.
