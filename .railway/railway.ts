import {
    defineRailway,
    github,
    group,
    preserve,
    project,
    service,
    volume,
} from "railway/iac";

const repository = github("OmuleBrianFredrick/CITYCARE-MEDICAL-CENTER-", {
    branch: "main",
});

const buildCommand = "npm run build";
const prepareDatabase = [
    "php artisan citycare:database-prepare",
    "php artisan migrate --force --isolated",
    "php artisan optimize",
].join(" && ");

const commonEnvironment = {
    APP_NAME: "CityCare Medical Center",
    APP_ENV: "production",
    APP_KEY: preserve(),
    APP_DEBUG: "false",
    APP_URL: preserve(),
    APP_LOCALE: "en",
    APP_FALLBACK_LOCALE: "en",
    APP_TIMEZONE: "Africa/Kampala",
    APP_MAINTENANCE_DRIVER: "database",
    TRUSTED_PROXIES: "*",
    BCRYPT_ROUNDS: "12",

    LOG_CHANNEL: "stderr",
    LOG_STDERR_FORMATTER: "Monolog\\Formatter\\JsonFormatter",
    LOG_LEVEL: "warning",

    DB_CONNECTION: "pgsql",
    DB_HOST: "aws-0-eu-central-1.pooler.supabase.com",
    DB_PORT: "5432",
    DB_DATABASE: "postgres",
    DB_USERNAME: "postgres.wvldswreeuknmcbynoqr",
    DB_PASSWORD: preserve(),
    DB_SCHEMA: "citycare",
    DB_SSLMODE: "require",

    SESSION_DRIVER: "database",
    SESSION_LIFETIME: "120",
    SESSION_ENCRYPT: "false",
    SESSION_SECURE_COOKIE: "true",
    SESSION_HTTP_ONLY: "true",
    SESSION_SAME_SITE: "lax",

    CACHE_STORE: "database",
    QUEUE_CONNECTION: "database",
    FILESYSTEM_DISK: "local",
    CITYCARE_CLINICAL_ATTACHMENTS_DISK: "local",

    MAIL_MAILER: "log",
    MAIL_FROM_ADDRESS: "noreply@citycare.test",
    MAIL_FROM_NAME: "CityCare Medical Center",
};

export default defineRailway(() => {
    const clinicalAttachments = volume("clinical-attachments", {
        region: "europe-west4",
        sizeMB: 1024,
    });

    const web = service("citycare-web", {
        source: repository,
        build: buildCommand,
        preDeploy: prepareDatabase,
        healthcheck: "/up",
        healthcheckTimeout: 300,
        regions: { "europe-west4": 1 },
        deploy: {
            restartPolicyType: "ALWAYS",
            drainingSeconds: 30,
        },
        env: commonEnvironment,
        volumeMounts: {
            "/app/storage/app/private": clinicalAttachments,
        },
    });

    const worker = service("citycare-worker", {
        source: repository,
        build: buildCommand,
        preDeploy: prepareDatabase,
        start: "php artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600 --no-interaction",
        regions: { "europe-west4": 1 },
        deploy: {
            restartPolicyType: "ALWAYS",
        },
        env: commonEnvironment,
    });

    const reminders = service("citycare-reminders", {
        source: repository,
        build: buildCommand,
        preDeploy: prepareDatabase,
        start: "php artisan citycare:send-appointment-reminders --no-interaction",
        regions: { "europe-west4": 1 },
        deploy: {
            cronSchedule: "0 * * * *",
            restartPolicyType: "NEVER",
        },
        env: commonEnvironment,
    });

    const application = group("CityCare application", [web, worker, reminders]);
    const storage = group("Durable storage", [clinicalAttachments]);

    return project("CITYCARE-MEDICAL-CENTER", {
        resources: [application, storage],
    });
});
