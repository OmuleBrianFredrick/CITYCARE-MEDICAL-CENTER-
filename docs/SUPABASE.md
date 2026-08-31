# CityCare Supabase Database Setup

CityCare uses Supabase as managed PostgreSQL infrastructure. Laravel remains the application, authentication, authorization, and migration layer; the Supabase publishable key and Supabase Auth are not required for the current server-rendered application.

Linking a GitHub repository in the Supabase dashboard does not connect Laravel or create its tables. The database becomes operational only after the runtime connection is configured and Laravel migrations are applied.

## 1. Connection model

Use the **Session Pooler** connection from the Supabase **Connect** panel:

- port `5432`
- SSL required
- database `postgres`
- a username in the form supplied by Supabase, commonly `postgres.PROJECT_REF`
- the database password set for the Supabase project

Do not use the transaction pooler on port `6543` as Laravel's primary connection because transaction mode does not support prepared statements. Direct connections are also valid where the host supports IPv6, but the Session Pooler is the portable choice for Render or Railway.

Store the URI only in an untracked `.env` or the hosting provider's encrypted secret store:

```env
DB_CONNECTION=pgsql
DB_URL=postgres://postgres.PROJECT_REF:DATABASE_PASSWORD@SESSION_POOLER_HOST:5432/postgres
DB_SCHEMA=citycare
DB_SSLMODE=require
```

If the password contains URI-reserved characters, use the exact encoded connection URI supplied by Supabase. Never commit a real URI, database password, service-role key, or access token.

## 2. Provision the application schema

Run these commands once from a trusted deployment environment with the production connection configured:

```bash
php artisan config:clear
php artisan citycare:database-prepare
php artisan migrate --force
```

`citycare:database-prepare` is idempotent. It creates the `citycare` PostgreSQL schema and removes access granted through PostgreSQL's `PUBLIC` role. Laravel's configured search path then places its migration table and all application tables in that schema.

The `citycare` schema must not be added to Supabase **API Settings > Exposed schemas**. The application accesses it only through the protected server-side database connection.

## 3. Provision the first administrator

For the first deployment only, set temporary values in the secret store or untracked `.env`:

```env
CITYCARE_ADMIN_EMAIL=initial-admin@example.org
CITYCARE_ADMIN_PASSWORD=replace-with-a-unique-password-of-at-least-12-characters
```

Then run:

```bash
php artisan db:seed --force
```

Verify that the administrator can sign in, remove both bootstrap values, and rebuild the configuration cache. All later workers are onboarded through CityCare's Staff administration invitation workflow. Do not create CityCare worker accounts in Supabase Auth.

## 4. Verification

Use production-safe, read-only checks:

```bash
php artisan migrate:status
php artisan schedule:list
php artisan config:cache
```

In Supabase Table Editor, application tables may not appear while the editor is scoped to `public`; select the `citycare` schema. The `public` schema being empty is expected.

The dashboard's **Last migration** card tracks Supabase CLI migration files, not Laravel's `citycare.migrations` table. It can therefore continue to show **No migrations** even when every Laravel migration has been applied successfully. Laravel migrations are the single source of truth for this project; do not maintain a second duplicate SQL migration history.

Never run `php artisan test`, `migrate:fresh`, demo seeders, or destructive reset commands against the Supabase production database.

## 5. Hosting later

Render or Railway are natural preview targets because they can run the complete PHP/Laravel process and scheduler. Vercel is not the default target for this server-rendered Laravel application. Hosting credentials should be configured as provider secrets; they do not belong in GitHub or `.env.example`.

Official references:

- [Supabase Laravel quickstart](https://supabase.com/docs/guides/getting-started/quickstarts/laravel)
- [Supabase PostgreSQL connection modes](https://supabase.com/docs/guides/database/connecting-to-postgres)
- [Supabase database migrations](https://supabase.com/docs/guides/deployment/database-migrations)
