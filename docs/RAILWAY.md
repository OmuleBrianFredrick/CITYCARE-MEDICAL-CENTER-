# Railway deployment

CityCare runs on Railway as three services backed by the existing Supabase
PostgreSQL project:

- `citycare-web` serves the Laravel application through Railway's detected
  PHP-FPM/Caddy runtime and exposes `/up` as its health check.
- `citycare-worker` continuously processes the database queue.
- `citycare-reminders` runs the appointment-reminder command hourly as a
  short-lived Railway cron job.

The services use Supabase's IPv4 Session Pooler on port 5432 and the private
`citycare` schema. The web service mounts a persistent Railway volume at
`/app/storage/app/private` for protected clinical referral attachments.

## Required protected variables

Set these on all three services without committing them:

- `APP_KEY`: a dedicated production Laravel application key.
- `DB_PASSWORD`: the current Supabase database password.

After Railway generates the web domain, set `APP_URL` to its HTTPS URL on all
three services. The infrastructure definition uses `preserve()` for these
values so future plans do not disclose or replace them.

## Deployment behavior

Each service runs the idempotent schema preparation and Laravel migrations in
an isolated pre-deploy step. The application then caches Laravel's production
configuration. Logs are written to Railway through `stderr`; no secrets should
be printed to deployment logs.

Do not set `CITYCARE_ADMIN_EMAIL` or `CITYCARE_ADMIN_PASSWORD` in Railway. The
initial administrator already exists in Supabase and those one-time bootstrap
values have been removed.

For previews, use synthetic data only. Before processing real patient data,
complete the hosting, email, backup, monitoring, retention, and regulatory
compliance reviews required for the deployment jurisdiction.
