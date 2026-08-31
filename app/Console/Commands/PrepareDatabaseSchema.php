<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareDatabaseSchema extends Command
{
    protected $signature = 'citycare:database-prepare
        {--schema= : PostgreSQL schema to create; defaults to DB_SCHEMA}';

    protected $description = 'Create and protect the PostgreSQL schema used by CityCare migrations';

    public function handle(): int
    {
        $connectionName = (string) config('database.default');
        $connection = DB::connection($connectionName);

        if ($connection->getDriverName() !== 'pgsql') {
            $this->error('CityCare schema preparation requires a PostgreSQL connection.');

            return self::FAILURE;
        }

        $configuredSearchPath = (string) config("database.connections.{$connectionName}.search_path", '');
        $schema = trim((string) ($this->option('schema') ?: explode(',', $configuredSearchPath)[0]));

        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $schema)) {
            $this->error('The schema must be a lowercase PostgreSQL identifier using letters, numbers, or underscores.');

            return self::INVALID;
        }

        $quotedSchema = '"'.$schema.'"';

        $connection->unprepared("CREATE SCHEMA IF NOT EXISTS {$quotedSchema}");
        $connection->unprepared("REVOKE ALL ON SCHEMA {$quotedSchema} FROM PUBLIC");

        $this->info("PostgreSQL schema [{$schema}] is ready and is not accessible through the PUBLIC role.");

        return self::SUCCESS;
    }
}
