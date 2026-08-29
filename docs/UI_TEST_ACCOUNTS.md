# CityCare Local Demo Accounts

These accounts are for supervised local development and UI acceptance only. They use `.test` addresses and must never be used as real accounts.

Set a local-only base password in your untracked `.env` file:

```env
CITYCARE_TEST_PASSWORD=replace-with-a-local-test-password-of-at-least-12-characters
```

Every account receives a distinct password derived from that base. For example, if the local base is `my-local-password`, the doctor signs in with `my-local-password-doctor`. The base password is never committed to source control.

Create only the role accounts and their staff department/service-point contexts:

```bash
php artisan db:seed --class=CityCareDemoAccountSeeder
```

For a complete idempotent local demonstration environment - including patients, appointments, clinical records, laboratory queues, prescriptions, billing, stock, procurement, reports, and audit events - run:

```bash
php artisan db:seed --class=CityCareDemoDataSeeder
```

| Role | Email | Password suffix |
|---|---|---|
| Super Administrator | `admin@citycare.test` | `-super-admin` |
| Operations Administrator | `administrator@citycare.test` | `-administrator` |
| Receptionist | `reception@citycare.test` | `-reception` |
| Doctor / Clinician | `doctor@citycare.test` | `-doctor` |
| Nurse / Clinical Support | `nurse@citycare.test` | `-nurse` |
| Laboratory Staff | `laboratory@citycare.test` | `-laboratory` |
| Pharmacy Staff | `pharmacy@citycare.test` | `-pharmacy` |
| Cashier / Finance | `cashier@citycare.test` | `-cashier` |
| Records Officer | `records@citycare.test` | `-records` |
| Inventory / Stores Staff | `inventory@citycare.test` | `-inventory` |
| Patient | `patient@citycare.test` | `-patient` |

Rerunning the demo-data seeder converges on the same records: it does not duplicate invoices, payments, purchase receipts, stock movements, or demo accounts. It is deliberately separate from the ordinary `DatabaseSeeder`, so a normal application seed does not add demo accounts or local credentials.
