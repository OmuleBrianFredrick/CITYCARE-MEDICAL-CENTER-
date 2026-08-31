# CityCare Deployment and Operations Runbook

This runbook covers a controlled single-node deployment and the additional requirements for reverse-proxy or multi-node hosting. Run every database command against a verified backup and exact target database. Never run `migrate:fresh` outside an isolated test environment.

## 1. Runtime requirements

- PHP 8.3 or newer with `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `pdo_pgsql`, `pgsql`, `session`, `tokenizer`, and `xml`
- Composer 2
- Node.js 22.13 through 22.x and npm 10.9 or newer for asset builds
- PostgreSQL 17-compatible database (Supabase PostgreSQL is the production target)
- A web server whose document root is the repository's `public` directory
- Write access for the web/PHP process to `storage` and `bootstrap/cache`
- HTTPS for every non-local deployment

Build frontend assets in CI or on the release host. A host serving a prebuilt artifact does not need Node.js at runtime.

## 2. Production environment

Copy `.env.example` to an untracked `.env`, then set at least:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://citycare.example.org
APP_TIMEZONE=Africa/Kampala

DB_CONNECTION=pgsql
DB_URL=postgres://postgres.PROJECT_REF:replace-with-a-secret@SESSION_POOLER_HOST:5432/postgres
DB_SCHEMA=citycare
DB_SSLMODE=require

LOG_LEVEL=warning
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CITYCARE_TEST_PASSWORD=
CITYCARE_CLINICAL_ATTACHMENTS_DISK=local
```

Generate `APP_KEY` once with `php artisan key:generate`. Back it up securely and retain it across releases; changing it invalidates encrypted application data and sessions. Use `APP_PREVIOUS_KEYS` during a controlled key rotation.

When a trusted reverse proxy terminates HTTPS, set `TRUSTED_PROXIES` to its exact comma-separated IP/CIDR allow-list. Do not use a broad wildcard on an untrusted network.

The default `local` clinical-attachment disk stores protected referral documents under `storage/app/private`. It is suitable only when that directory is persistent and backed up. Configure a durable private disk for multi-node or ephemeral hosting. To use the supplied `s3` disk, add `league/flysystem-aws-s3-v3` to the application with Composer during development, commit the updated Composer files, deploy that locked dependency, and then configure the private bucket. The application rejects the public disk for these documents.

## 3. First deployment

1. Create the PostgreSQL database and application role. For Supabase, copy the Session Pooler URI from **Connect**, not the transaction-pooler URI. Keep the connection URI only in the host's secret store or untracked `.env`.
2. Install and build the release:

```bash
composer install --no-dev --prefer-dist --no-interaction --classmap-authoritative
npm ci --ignore-scripts --no-audit --no-fund
npm run build
```

3. Generate the application key if this is a new environment:

```bash
php artisan key:generate
```

4. Create and protect the private application schema, then apply migrations:

```bash
php artisan citycare:database-prepare
php artisan migrate --force
```

5. Provision roles, the organization foundation, and the first super administrator. Temporarily set a unique administrator email and a password of at least 12 characters:

```env
CITYCARE_ADMIN_EMAIL=initial-admin@example.org
CITYCARE_ADMIN_PASSWORD=replace-with-a-unique-secret
```

Then run:

```bash
php artisan db:seed --force
```

Verify that the account can sign in, remove both bootstrap values from `.env`, and rebuild the configuration cache. Later staff accounts should be created through the protected Staff administration invitation workflow.

The demo seeders are blocked in production. Never deploy `CITYCARE_TEST_PASSWORD` or run `CityCareDemoDataSeeder` against real data.

6. Cache the production configuration:

```bash
php artisan optimize
```

7. Start the scheduler described below and verify `GET /up` returns a successful response.

`php artisan storage:link` is needed only for deliberately public files. It does not expose or serve protected clinical referral attachments.

## 4. Web server and filesystem

- Point Apache/Nginx/IIS to `<release>/public`, never to the repository root.
- Deny direct access to `.env`, `storage`, database backups, logs, and source-control metadata.
- Give the application process write access only to `storage` and `bootstrap/cache`.
- Configure TLS, secure headers, request-size limits, and log rotation at the web-server layer.
- Keep the deployment user and database user separate from privileged system/root accounts.

For local XAMPP testing, configure an Apache virtual host with its `DocumentRoot` set to `D:/xampp/htdocs/CITYCARE-MEDICAL-CENTER-/public`.

## 5. Scheduler and queues

Appointment reminders are scheduled hourly in `routes/console.php`. Laravel's scheduler must be invoked every minute.

Linux cron:

```cron
* * * * * cd /var/www/citycare && php artisan schedule:run >> /dev/null 2>&1
```

For supervised Windows/XAMPP testing, run `php artisan schedule:work`, or configure Windows Task Scheduler to execute `php artisan schedule:run` every minute from the project directory.

No CityCare job currently implements `ShouldQueue`; database notifications are written synchronously. A queue worker is therefore not required today. If asynchronous jobs are introduced, supervise `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` and restart workers after each deployment.

## 6. Routine release procedure

1. Confirm CI and the release commit.
2. Back up the PostgreSQL database and protected attachment storage.
3. Put the application in maintenance mode: `php artisan down --retry=60`.
4. Deploy the immutable release files.
5. Run `composer install --no-dev --prefer-dist --no-interaction --classmap-authoritative`.
6. Run `npm ci --ignore-scripts --no-audit --no-fund && npm run build`, or publish the CI-built assets.
7. Run `php artisan citycare:database-prepare` and `php artisan migrate --force`.
8. Run `php artisan optimize` and restart PHP/queue processes as applicable.
9. Run the smoke checks below.
10. Bring the application back with `php artisan up`.

Do not run the base seeder as an automatic deployment step. It is intended for first-time foundation/bootstrap provisioning; ongoing roles and settings are managed in the application.

## 7. Release and health checks

Run the following pre-release checks in CI or on a development/test host that has development dependencies installed and uses a dedicated disposable test database:

```bash
composer validate --strict
composer audit --locked --no-interaction
npm audit --package-lock-only --audit-level=high
vendor/bin/pint --test
npm run build
php artisan test
```

Never point `php artisan test` at the production database; database-oriented tests intentionally create and reset test data. After deploying with `composer install --no-dev`, use only production-safe checks on the release host:

```bash
php artisan migrate:status
php artisan schedule:list
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

After deployment, verify:

- `/up` returns HTTP 200.
- HTTPS redirects and secure session cookies behave correctly.
- A super administrator can sign in and sees the intended facility.
- A normal staff account is restricted to its assigned facility.
- A patient can sign in to the portal without seeing another patient's data.
- A private referral attachment can be downloaded only by authorized same-facility clinical staff.
- Scheduler logs show the reminder command running without overlap.

Run the manual scenarios in [UAT_CHECKLIST.md](UAT_CHECKLIST.md) before final sign-off.

## 8. Backup and rollback

- Use Supabase managed backups where available and take a tested logical PostgreSQL backup before every schema deployment.
- Back up the configured clinical-attachment disk together with the database so metadata and files remain consistent.
- Retain the previous release artifact and its dependency locks.
- If application code fails before a migration, redeploy the previous artifact and clear/rebuild caches.
- If an irreversible migration has run, restore the verified database and attachment backup before redeploying the previous artifact. Do not improvise with `migrate:fresh` or broad manual deletes.
- Record the failed release, error logs, affected time window, and recovery actions in the operational incident log.
