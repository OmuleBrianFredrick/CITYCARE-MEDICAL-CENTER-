# CityCare Medical Center — Application Architecture

## 1. Purpose

CityCare Medical Center is being developed as a complete, professional medical-center management platform rather than a minimal CRUD application. The system will cover the operational lifecycle from patient access and registration through clinical care, diagnostics, pharmacy, billing, payments, reporting, administration, and audit/security.

The architecture is intentionally modular so each business area can be developed, tested, secured, and evolved without creating a tightly coupled monolith.

## 2. Architecture Principles

1. **Domain-first development** — business rules are defined before controllers and UI are built.
2. **Role-aware access** — every protected capability is granted through explicit permissions, not hidden UI links alone.
3. **Auditability** — clinically and financially significant actions must be traceable.
4. **Least privilege** — staff receive only the access required for their responsibilities.
5. **Transactional integrity** — clinical, stock, billing, payment, and dispensing operations that change multiple records must use database transactions where appropriate.
6. **Reusable UI** — common layouts, forms, tables, status indicators, alerts, modals, and navigation patterns will be shared components.
7. **Responsive premium interface** — the UI will be designed for desktop, tablet, and mobile use with a medical visual language based primarily on white/cream, layered blue, and controlled yellow accents.
8. **Testable services** — important domain rules belong in services/actions/policies where they can be tested independently of HTTP controllers.
9. **Configuration over hard-coding** — environment-specific settings remain configurable and secrets never enter source control.
10. **Incremental delivery** — each completed phase leaves the repository in a runnable and verifiable state.

## 3. User and Access Architecture

The system will distinguish between **patients** and **internal staff**. Public patient-facing registration must never be treated as an employee account-creation mechanism.

### Internal roles

- **Super Administrator** — highest system authority; organization setup, security, role/permission administration, system configuration, and emergency administrative operations.
- **Administrator / Management** — operational management without unrestricted security authority.
- **Receptionist** — patient registration, appointment handling, check-in, queue management, and front-desk workflows.
- **Doctor / Clinician** — consultations, clinical notes, diagnoses, treatment plans, orders, prescriptions, referrals, and clinical follow-up.
- **Nurse / Clinical Support** — vitals, triage, nursing observations, patient preparation, and assigned clinical tasks.
- **Laboratory Staff** — laboratory orders, specimen workflow, results entry, verification, and release according to configured permissions.
- **Pharmacy Staff** — medication catalogue, prescriptions, dispensing, stock checks, and pharmacy transactions.
- **Cashier / Finance** — invoices, payments, receipts, balances, refunds where authorized, and financial reporting.
- **Records Officer** — medical-record administration and controlled records workflows.
- **Inventory / Stores Staff** — medical and general stock, suppliers, stock movements, purchase workflows, and inventory reporting.

The final permission matrix will be defined before implementation of protected modules. A role will not be used as a substitute for fine-grained authorization where a capability requires more precision.

### Patient access

Patients will have a separate access experience from staff. Patient accounts will support appropriate patient-facing capabilities such as profile management, appointment access, notifications, and permitted history/records views. Clinical information exposed to patients will be explicitly scoped rather than exposing internal records wholesale.

## 4. Core Business Modules

### 4.1 Organization and Administration

- facility profile
- departments and service points
- staff accounts
- roles and permissions
- operational settings
- notification settings
- system configuration

### 4.2 Patient Management

- patient registration
- patient identifiers / medical record number
- demographics and contact information
- emergency contacts
- next of kin
- patient status
- duplicate detection strategy
- patient search and profile

### 4.3 Appointments and Scheduling

- appointment booking
- provider schedules
- department/service schedules
- appointment status lifecycle
- rescheduling and cancellation
- reminders
- check-in
- no-show tracking

### 4.4 Reception and Queue Management

- daily arrival/check-in
- queue creation
- triage queue
- clinician queue
- priority handling
- queue status
- service-point assignment

### 4.5 Clinical Care

- encounter creation
- consultation notes
- chief complaint
- history
- examination
- vitals
- assessment
- diagnosis
- treatment plan
- follow-up
- clinical attachments where appropriate
- referrals

### 4.6 Laboratory

- test catalogue
- laboratory orders
- specimen workflow
- result entry
- result verification
- result release
- reference ranges and units where configured
- abnormal-result flags
- laboratory reporting

### 4.7 Pharmacy

- medicine catalogue
- prescriptions
- prescription items
- dispensing workflow
- partial/full dispensing
- medication stock linkage
- dispensing records
- pharmacy reporting

### 4.8 Billing and Finance

- service catalogue
- charge generation
- invoices
- invoice items
- payments
- payment methods
- receipts
- outstanding balances
- refunds/adjustments with authorization
- daily financial summaries

### 4.9 Insurance

- insurance providers
- patient insurance profiles
- coverage information
- claim-related records
- authorization/reference information
- insurer billing workflows where required

### 4.10 Inventory and Procurement

- item catalogue
- categories
- units of measure
- suppliers
- purchase orders
- receiving
- stock movements
- batch/lot information where required
- expiry tracking
- reorder thresholds
- stock adjustments
- pharmacy/medical-store integration

### 4.11 Admissions and Inpatient Care

- admission request
- admission
- ward/bed assignment
- inpatient encounters
- nursing observations
- treatment/medication tracking
- transfers
- discharge planning
- discharge summary

This module will be enabled according to CityCare's actual operational requirements; its domain model will remain separated from outpatient workflows.

### 4.12 Reporting and Analytics

- operational dashboard
- patient statistics
- appointment statistics
- clinical activity summaries
- laboratory statistics
- pharmacy statistics
- inventory reports
- revenue/payment reports
- outstanding balances
- staff activity
- audit/security reporting

Reports must respect the same authorization rules as the underlying data.

### 4.13 Notifications

- appointment reminders
- patient-facing notifications
- staff notifications
- laboratory result notifications where permitted
- payment/receipt notifications
- system alerts
- configurable email/SMS integration boundary

External delivery providers will be abstracted behind application services rather than embedded directly in controllers.

### 4.14 Audit and Security

- authentication logs
- authorization-sensitive actions
- account activation/deactivation
- password/security events
- significant patient-record changes
- financial adjustments
- inventory adjustments
- administrative changes
- failed login/throttling records
- security dashboard/log review

## 5. Major Domain Relationships

The principal relationship direction is:

`Patient -> Appointments -> Encounters -> Clinical Records`

with supporting flows:

`Encounter -> Orders -> Laboratory Results`

`Encounter -> Prescription -> Dispensing -> Inventory Movement`

`Appointment/Encounter/Service -> Charges -> Invoice -> Payment`

`Patient -> Insurance Profile -> Claims/Billing References`

`Supplier -> Purchase Order -> Receiving -> Inventory Movement`

`Staff User -> Roles/Permissions -> Authorized Actions -> Audit Trail`

The actual database schema will be normalized around these relationships. We will avoid storing duplicated business state when it can be derived safely, while preserving historical records required for auditability.

## 6. Clinical Record Boundary

Clinical records are treated as a protected domain. Controllers should not directly expose arbitrary patient-record columns. Access will be mediated through policies/permissions and domain services.

Important clinical entities will maintain authorship and timestamps. Where records need correction, the preferred model is controlled amendment/history rather than destructive replacement.

## 7. Financial Integrity Boundary

Financial operations will be transactional and auditable.

Examples:

- creating an invoice with its line items
- recording a payment and updating the resulting balance
- issuing an authorized refund/adjustment
- generating pharmacy charges during dispensing

Money values will use fixed-precision database types and explicit currency handling. Financial history must not depend solely on mutable current totals.

## 8. Inventory Integrity Boundary

Stock changes will be represented by traceable movements rather than silent quantity edits.

Examples:

- receiving stock
- dispensing medicine
- issuing stock
- returning stock
- approved adjustments
- expiry/damage write-offs

Where stock availability matters, the operation will validate and update the relevant records atomically.

## 9. UI Architecture

The interface will use a premium medical-center visual system:

- white/cream surfaces
- layered blue primary palette
- restrained yellow accent for attention and status
- high readability and accessible contrast
- rounded but professional cards/panels
- clear status badges
- compact but readable data tables
- dashboard metrics
- responsive sidebar/navigation
- mobile-friendly workflows
- consistent empty/loading/error/success states

The application shell will contain:

- branded sidebar
- top navigation/header
- contextual page title and breadcrumbs
- notification area
- user/account menu
- responsive navigation
- reusable content panels

Patient-facing pages will have a distinct, warmer presentation from the internal clinical/administrative workspace while remaining part of the same design system.

## 10. Authentication and Security Strategy

The security foundation will include:

- secure password hashing
- login throttling
- session regeneration
- logout invalidation
- account activation/deactivation
- role and permission enforcement
- policy-based authorization
- CSRF protection through Laravel defaults
- security headers
- audit logging
- controlled password reset
- appropriate staff verification/OTP strategy
- patient authentication separated from staff authorization

The exact OTP/MFA policy will be finalized with the role matrix. It will not be assumed that every patient or every staff member requires the same authentication ceremony.

## 11. Application Layers

The preferred request flow is:

`Route -> Middleware -> Controller -> Form Request / Validation -> Policy / Authorization -> Service / Action -> Model / Repository boundary -> Database -> Resource/View`

Controllers should remain thin. Complex business rules belong in services/actions and policies.

Notifications, queued work, external integrations, and reporting queries will have explicit boundaries rather than being embedded in large controllers.

## 12. Database Strategy

The application will use MySQL in the XAMPP local environment and production-compatible MySQL-compatible configuration.

Database implementation will be staged:

1. framework foundation
2. identity and authorization schema
3. organization schema
4. patient schema
5. appointment/reception schema
6. clinical schema
7. laboratory/pharmacy schema
8. billing/payment/insurance schema
9. inventory/procurement schema
10. admissions/referrals/notifications/audit schema
11. indexes, constraints, foreign keys, and performance review

Every migration must be reversible where practical and must preserve referential integrity.

## 13. Testing Strategy

Testing will be layered:

### Unit tests

Domain rules, calculations, services, authorization rules, and pure application logic.

### Feature tests

HTTP workflows such as registration, login, appointment creation, consultation, laboratory ordering, dispensing, billing, payments, and staff administration.

### Database tests

Constraints, relationships, transactions, uniqueness, stock/balance integrity, and historical records.

### Browser testing

Performed primarily in the local development environment as requested, covering real user journeys and visual/interaction behavior.

### CI

GitHub Actions will run the reliable automated test suite and quality checks that can be reproduced in the repository environment.

## 14. Development Sequence

The project will proceed in controlled phases:

1. **Foundation** — Laravel, GitHub, environment, MySQL, storage, baseline tests.
2. **Identity and Security** — users, roles, permissions, authentication, audit foundation.
3. **Organization** — facility, departments, service points, staff administration.
4. **Patient Management** — registration, profiles, identifiers, search.
5. **Appointments and Reception** — scheduling, check-in, queue.
6. **Clinical Core** — encounters, vitals, diagnoses, treatment plans, referrals.
7. **Laboratory** — orders, specimens, results, verification.
8. **Pharmacy** — medicines, prescriptions, dispensing, stock integration.
9. **Billing and Payments** — services, invoices, payments, receipts, balances.
10. **Insurance** — coverage and insurer workflows.
11. **Inventory and Procurement** — suppliers, purchase orders, stock, expiry and movement controls.
12. **Admissions** — inpatient workflows if required by the final operational scope.
13. **Notifications and Reporting** — alerts, reminders, dashboards, reports.
14. **Premium UI / UX refinement** — full responsive polish across all modules.
15. **Browser QA** — end-to-end local workflows and visual testing.
16. **Hardening** — security, validation, performance, error handling, accessibility.
17. **Documentation and release readiness** — README, setup guide, operational notes, test matrix, deployment preparation.

## 15. Definition of Done

A module is not considered complete merely because its pages load.

A module is complete when:

- its data model is defined
- migrations and constraints exist
- relationships are correct
- authorization is enforced
- validation is implemented
- business rules are implemented
- services/actions are covered where needed
- controllers/routes are wired
- UI is complete and responsive
- success/error/empty/loading states are handled
- audit requirements are addressed
- automated tests pass
- browser workflow has been verified locally where applicable
- documentation is updated

## 16. Current Status

### Completed

- Laravel 13 foundation created
- Git repository initialized
- GitHub repository connected
- Laravel foundation pushed to `main`
- CityCare application identity configured
- Africa/Kampala timezone configured
- MySQL configured
- `citycare_medical_center` database connected
- framework migrations executed
- public storage link created

### Current phase

**Phase 1.3 — Full Application Architecture & Domain Design**

The next implementation work begins with the identity/authorization architecture and its database model before moving into patient and operational modules.
