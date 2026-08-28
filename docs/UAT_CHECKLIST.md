# CityCare Manual Browser UAT Checklist

This checklist is the remaining manual acceptance stage. Automated tests cover authorization, facility isolation, validation, transactions, lifecycle rules, migrations, and service behavior; UAT confirms that the rendered workflows are understandable and usable in real browsers.

## 1. Prepare the local demonstration environment

Use a disposable/local database only. Set a unique local base password of at least 12 characters in the untracked `.env`:

```env
APP_ENV=local
APP_DEBUG=true
CITYCARE_TEST_PASSWORD=replace-with-a-local-test-password
```

Then run:

```bash
php artisan migrate
php artisan db:seed --class=CityCareDemoDataSeeder
npm run build
php artisan serve
```

The role accounts and password suffixes are listed in [UI_TEST_ACCOUNTS.md](UI_TEST_ACCOUNTS.md). The connected sample records are described in [DEMO_WORKFLOW.md](DEMO_WORKFLOW.md).

## 2. Browser and responsive coverage

Complete the critical scenarios in:

- Chrome or Edge at a desktop width
- Firefox at a desktop width
- One mobile viewport near 390 × 844
- One tablet viewport near 768 × 1024

At each size, confirm navigation remains available, tables/forms do not lose actions, focus indicators are visible, status/errors are readable, and no content causes unusable horizontal overflow.

## 3. Shared authentication and shell

- [ ] Invalid credentials return a generic error without revealing whether an account exists.
- [ ] An inactive account cannot sign in.
- [ ] Successful sign-in opens the correct role workspace and assigned facility context.
- [ ] Sidebar/mobile navigation contains only permitted modules.
- [ ] Signing out invalidates the session and protected pages no longer open through Back/refresh.

## 4. Super Administrator

- [ ] Open Organization, switch between active facilities, and confirm departments/service points change with the selection.
- [ ] Update a facility profile and a typed global setting; refresh and confirm values persist.
- [ ] Open Staff administration, invite a staff member, copy the one-time setup link, and confirm reissue/revoke controls.
- [ ] In a private/incognito window, open the setup link, choose the account password, and confirm the link cannot be reused.
- [ ] Open Access control, adjust an allowed role permission, confirm navigation changes for that role, then restore the intended permission.
- [ ] Confirm protected `super-admin` and `patient` roles cannot be destructively rewritten.
- [ ] Review Audit and Reports, including facility filters, report execution, result view, and CSV export.

## 5. Operations Administrator

- [ ] Confirm the Organization page is locked to the assigned facility and global settings are read-only.
- [ ] Invite/edit/deactivate/reactivate ordinary staff in the assigned facility.
- [ ] Confirm another facility cannot be selected by editing the URL or form payload.
- [ ] Confirm Access control is not available.
- [ ] Review same-facility audit events and reports without seeing foreign-facility data.

## 6. Reception and patient records

- [ ] Register a patient and confirm server-side validation messages for missing/invalid fields.
- [ ] Search by patient name and medical-record number.
- [ ] Create an appointment using the live patient-search selector.
- [ ] Filter the appointment list, check a scheduled patient in, and confirm the queue/status updates.
- [ ] Cancel a separate appointment and confirm the reason/status is shown.
- [ ] Provision or manage a patient's portal invitation from the patient record.

## 7. Nursing and clinical care

- [ ] Nurse opens the checked-in patient, records vitals, and adds an authorized note.
- [ ] Doctor opens the encounter, records diagnosis, clinical note, treatment plan, and referral.
- [ ] Upload a permitted referral attachment, download it, and delete it with confirmation.
- [ ] Confirm the attachment cannot be opened from a copied public/storage URL.
- [ ] Create a laboratory order and prescription from the encounter.
- [ ] Close an encounter with a summary and confirm closed-state actions are no longer offered.

## 8. Laboratory

- [ ] Open the ordered laboratory queue and inspect patient/test details.
- [ ] Record a result, complete/verify the work as permitted, and confirm its final state.
- [ ] Confirm invalid repeated completion/cancellation actions are rejected cleanly.
- [ ] Confirm the patient portal shows only released/allowed result information.

## 9. Pharmacy

- [ ] Open the pending prescription queue and inspect formulation/instructions.
- [ ] Dispense an allowed quantity and confirm stock decreases exactly once.
- [ ] Attempt an excessive or repeated dispensing and confirm it is rejected.
- [ ] Confirm completed dispensing history remains visible.

## 10. Billing and cashier

- [ ] Open the billing queue and create/confirm encounter charges and an invoice.
- [ ] Record a partial payment and verify balance/status/receipt.
- [ ] Record the remaining payment and verify the paid state.
- [ ] Attempt an overpayment or duplicate submission and confirm no duplicate payment is created.
- [ ] Exercise an authorized void/refund/cancellation path and confirm audit/history visibility.

## 11. Inventory and procurement

- [ ] Review items, stores, stock balances, and low-stock indicators.
- [ ] Create a supplier and purchase order, add items, and submit it.
- [ ] Receive goods and confirm the order, receipt, balance, and movement history update together.
- [ ] Attempt a duplicate/excess receipt and confirm it is rejected.
- [ ] Confirm pharmacy dispensing is reflected in stock movement/balance views.

## 12. Patient portal

- [ ] Sign in as the demo patient and confirm only that patient's appointments, results, invoices, payments, and notifications appear.
- [ ] Mark one notification read, then mark all read, and confirm unread counts update.
- [ ] Confirm internal-only clinical or staff actions are absent.
- [ ] Attempt to open copied staff URLs and confirm access is denied.

## 13. Acceptance record

Record the test date, commit hash, browser/version, viewport/device, tester, result, and any issue reference for every failed item. Final acceptance requires:

- [ ] No critical or high-severity functional/security defects
- [ ] All role-critical scenarios completed
- [ ] Desktop, tablet, and mobile navigation accepted
- [ ] Production configuration and backup owner identified
- [ ] Scheduler operation verified
- [ ] Any deferred cosmetic issue documented with an owner
