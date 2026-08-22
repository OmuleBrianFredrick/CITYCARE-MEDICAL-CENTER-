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

Staff profiles are separated from the base authentication record and can contain employee number, job title, employment status, phone, joining date, department, and service point.

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
- Employee invitation foundation with secure token hashing and lifecycle states
- Premium responsive CityCare login and authenticated dashboard foundation

Multi-factor authentication/OTP will be introduced through the security policy appropriate to each account class rather than hard-coded as a universal login ceremony.

## Organization Foundation

The organization domain now provides the core structures required for the medical center itself:

- Facility profile and identity configuration
- Departments
- Service points
- Typed system settings
- Operational/notification configuration storage
- Department and service-point assignment capability on staff profiles
- Organization view/manage permissions
- Default CityCare facility, department and service-point seed data
- Protected organization administration workspace

The facility configuration establishes the CityCare regional defaults, including Uganda, Africa/Kampala, UGX and the premium blue/cream/yellow visual palette.

## Database

The local development environment uses the MySQL database:

`citycare_medical_center`

CityCare domain migrations are introduced in controlled stages so relationships, constraints, authorization boundaries, and business rules can be validated as each module is built.

## Billing Foundation — Phase 11.1

The financial domain foundation includes:

- Billable services/items scoped to facilities
- Versioned service pricing with effective dates and currency
- Patient- and encounter-linked charges
- Invoice/bill records with lifecycle status
- Invoice line items with historical price snapshots
- Discount and adjustment amounts at charge, line-item, and invoice level
- Fixed-precision subtotals, totals, paid amounts, and outstanding balances
- Payment records with method and lifecycle status
- Receipt and transaction references
- Staff/cashier/accounting-user authorship links for financial actions
- Facility relationships and referential-integrity constraints
- Laravel migrations, Eloquent models, relationships, and model factories

## Billing Service Layer — Phase 11.2

`App\Services\BillingService` now owns the core financial business rules while controllers remain thin.

Implemented rules include:

- Validate active staff and active patients before billing operations
- Validate facility ownership and active billable services
- Validate service-price ownership, activity, positive amount, currency and effective-date window
- Add one or multiple billable charges
- Validate positive quantities and non-negative discounts
- Calculate charge subtotal and final total deterministically
- Reject discounts greater than the subtotal and negative resulting totals
- Support idempotency keys for duplicate charge-generation protection
- Reject invalid, closed, cancelled, cross-patient, or cross-facility encounters
- Create invoices transactionally from pending charges
- Prevent invoicing an already invoiced/voided charge
- Preserve charge price snapshots in invoice line items
- Calculate invoice subtotal, discounts, adjustments, total and balance due
- Record completed payments transactionally
- Support partial and full payment
- Prevent payment amounts above the outstanding balance
- Transition invoices from issued → partially paid → paid
- Generate unique invoice and receipt references
- Cancel unpaid invoices with an explicit reason and release their charges back to pending
- Prevent cancellation after payment has been recorded
- Recalculate eligible invoice totals transactionally without allowing balances to become inconsistent

## UI Direction

The visual language is based on a premium medical-center experience:

- layered blue palette
- white/cream surfaces
- restrained yellow accents
- high readability and accessible contrast
- responsive layouts
- professional cards, tables, status badges and forms
- separate but related patient-facing and internal-workspace experiences

The login, dashboard, and organization administration workspace establish the initial internal visual system. It will evolve as the complete navigation and domain modules are introduced.

## Testing Strategy

Testing is layered:

1. Unit tests for isolated business logic.
2. Feature tests for HTTP workflows and authorization.
3. Database tests for relationships, constraints and transactional integrity.
4. Local browser testing for complete user journeys and visual/interaction QA.
5. GitHub CI for reproducible automated checks as the pipeline is established.

Phase 11.2 includes dedicated `BillingServiceTest` coverage for charge calculations, active-state enforcement, closed encounters, idempotency, invoice creation, duplicate invoicing, partial/full payments, overpayment prevention, and invoice cancellation.

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
- Employee invitation database/model foundation implemented
- Employee invitation service/security foundation implemented
- Pending employee password lifecycle supported
- Organization schema implemented
- Facility, department, service-point and system-setting models implemented
- Organization configuration service implemented
- Organization permissions added to the access matrix
- Default CityCare organization seed data implemented
- Protected organization administration routes implemented
- Premium organization administration workspace implemented
- Clinical core implemented and integrated
- Laboratory workflow implemented and regression-tested
- Pharmacy workflow implemented and regression-tested
- Billing & Payments Phase 11.1 financial database/model foundation implemented
- Billing & Payments Phase 11.2 service layer implemented
- Billing service feature tests added

### Current Phase

**Phase 11 — Billing & Payments**

Current chapter: **11.2 — Billing service layer**.

Implemented in 11.2: transactional charge generation, service/price validation, idempotent charge creation, invoice generation, financial calculations, partial/full payments, overpayment prevention, invoice lifecycle transitions, cancellation rules, active staff/patient enforcement, encounter-state enforcement, and dedicated billing service tests.

Next chapter: **11.3 — Permissions & access control**, where the financial permission matrix will be introduced without unnecessary permission duplication.

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
