# CityCare Demo Workflow

`CityCareDemoDataSeeder` creates an idempotent local data set for cross-role browser testing. It does not store credentials; see [UI test accounts](UI_TEST_ACCOUNTS.md) for the local-only login procedure.

## Ready-to-test scenarios

1. Reception can find **Amina Nakato** (`CC-DEMO-0001`), whose appointment is checked in today. It is paired with the active encounter `DEMO-ENC-ACTIVE`.
2. Nursing and clinical roles can review the active encounter, triage vital signs, working diagnosis, notes, treatment plan, and pending referral.
3. Laboratory sees `DEMO-LAB-OPEN`, an ordered malaria test, alongside a completed historical CBC result.
4. Pharmacy sees a pending amoxicillin prescription. The pharmacy stock balance is intentionally below its reorder level, while a historical paracetamol dispensing demonstrates stock consumption.
5. Cashier sees an outstanding active consultation invoice and a historical partially paid laboratory invoice.
6. Inventory sees a completed purchase order, goods receipt, receipt movements, and two low-stock lines.
7. Reports have active definitions for clinical, laboratory, pharmacy, billing, and inventory summaries. Audit records link the seeded patient and active encounter to their responsible roles.

The data covers live queues and completed historical work without bypassing the application service layer for receiving, dispensing, charges, invoices, or payments. Automated coverage verifies a clean seed and a second seed pass remain stable.
