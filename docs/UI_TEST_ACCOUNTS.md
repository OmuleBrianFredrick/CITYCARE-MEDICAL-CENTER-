# CityCare UI Test Accounts

These accounts are intended for supervised local UI and role-behaviour testing only. They use `.test` email addresses and must not be used as real accounts.

Set the shared password locally in `.env` using:

```env
CITYCARE_TEST_PASSWORD=replace-with-a-local-test-password-of-at-least-12-characters
```

Run the seeder only in the local test environment:

```bash
php artisan db:seed --class=UiTestAccountsSeeder
```

| Role | Email |
|---|---|
| Super Administrator | `admin@citycare.test` |
| Receptionist | `reception@citycare.test` |
| Doctor / Clinician | `doctor@citycare.test` |
| Nurse / Clinical Support | `nurse@citycare.test` |
| Laboratory Staff | `laboratory@citycare.test` |
| Pharmacy Staff | `pharmacy@citycare.test` |
| Cashier / Finance | `cashier@citycare.test` |
| Records Officer | `records@citycare.test` |
| Inventory / Stores Staff | `inventory@citycare.test` |
| Patient | `patient@citycare.test` |

The seeder hashes the local test password before persistence and synchronizes each account to its existing CityCare role. Never commit a real password to the repository.
