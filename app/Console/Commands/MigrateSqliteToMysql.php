<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateSqliteToMysql extends Command
{
    protected $signature = 'migrate:sqlite-to-mysql 
                            {--source=sqlite : Source connection name}
                            {--destination=mysql : Destination connection name}
                            {--chunk=500 : Batch size for inserts}
                            {--truncate : Truncate destination tables before import}';

    protected $description = 'Safely migrate all data from SQLite to MySQL without changing IDs or columns';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $source = (string) $this->option('source');
        $destination = (string) $this->option('destination');
        $chunkSize = max((int) $this->option('chunk'), 1);

        $sourceDb = DB::connection($source);
        $destinationDb = DB::connection($destination);

        if (! $this->hasSqliteDriver($sourceDb->getDriverName())) {
            $this->error("Source connection [{$source}] is not SQLite.");
            return self::FAILURE;
        }

        if ($destinationDb->getDriverName() !== 'mysql') {
            $this->error("Destination connection [{$destination}] is not MySQL.");
            return self::FAILURE;
        }

        $tables = $this->discoverSourceTables($source);

        if ($tables->isEmpty()) {
            $this->warn('No source tables found to migrate.');
            return self::SUCCESS;
        }

        $orderedTables = $this->orderTablesForMigration($tables);

        $this->info("Starting migration: {$source} -> {$destination}");
        $this->line("Tables to migrate: {$orderedTables->count()}");

        $destinationDb->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($orderedTables as $table) {
                $this->migrateTable(
                    sourceConnection: $source,
                    destinationConnection: $destination,
                    table: $table,
                    chunkSize: $chunkSize,
                    truncateBeforeImport: (bool) $this->option('truncate'),
                );
            }
        } catch (Throwable $e) {
            $this->error("Migration failed: {$e->getMessage()}");
            return self::FAILURE;
        } finally {
            $destinationDb->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info('SQLite -> MySQL migration completed successfully.');

        return self::SUCCESS;
    }

    private function hasSqliteDriver(string $driver): bool
    {
        return in_array($driver, ['sqlite', 'sqlite3'], true);
    }

    private function discoverSourceTables(string $connection): Collection
    {
        $rows = DB::connection($connection)
            ->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

        return collect($rows)
            ->map(fn (object $row): string => $row->name)
            ->reject(fn (string $table): bool => $table === 'migrations')
            ->values();
    }

    private function orderTablesForMigration(Collection $tables): Collection
    {
        // Hand-prioritize principal tables that are commonly referenced.
        $preferredOrder = [
            'users',
            'universities',
            'faculties',
            'departments',
            'semesters',
            'classes',
            'students',
            'lecturers',
            'venues',
            'courses',
            'attendance_weeks',
            'attendance_sessions',
            'attendances',
        ];

        $preferred = collect($preferredOrder)->filter(fn (string $table): bool => $tables->contains($table));
        $remaining = $tables->reject(fn (string $table): bool => $preferred->contains($table))->sort()->values();

        return $preferred->merge($remaining)->values();
    }

    private function migrateTable(
        string $sourceConnection,
        string $destinationConnection,
        string $table,
        int $chunkSize,
        bool $truncateBeforeImport,
    ): void {
        $sourceDb = DB::connection($sourceConnection);
        $destinationDb = DB::connection($destinationConnection);

        $sourceCount = $sourceDb->table($table)->count();
        $this->line("Migrating [{$table}] ({$sourceCount} rows)...");

        if ($truncateBeforeImport) {
            $destinationDb->table($table)->truncate();
        }

        $columns = collect($sourceDb->select("PRAGMA table_info('{$table}')"))
            ->map(fn (object $column): string => $column->name)
            ->values()
            ->all();

        $offset = 0;
        while (true) {
            $rows = $sourceDb->table($table)->offset($offset)->limit($chunkSize)->get();
            if ($rows->isEmpty()) {
                break;
            }

            $payload = $rows
                ->map(function (object $row) use ($columns): array {
                    $record = [];
                    foreach ($columns as $column) {
                        $record[$column] = $row->{$column} ?? null;
                    }
                    return $record;
                })
                ->all();

            $destinationDb->table($table)->insert($payload);
            $offset += $chunkSize;
        }

        $destinationCount = $destinationDb->table($table)->count();
        $status = $sourceCount === $destinationCount ? 'OK' : 'MISMATCH';

        $this->line(" - Verification {$status}: source={$sourceCount}, destination={$destinationCount}");

        if ($sourceCount !== $destinationCount) {
            throw new \RuntimeException("Row count mismatch detected on table [{$table}]");
        }

        if ($this->hasAutoIncrementPrimaryKey($sourceConnection, $table)) {
            $maxId = (int) ($destinationDb->table($table)->max('id') ?? 0);
            $nextId = $maxId + 1;
            $destinationDb->statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$nextId}");
        }
    }

    private function hasAutoIncrementPrimaryKey(string $sourceConnection, string $table): bool
    {
        $columns = DB::connection($sourceConnection)->select("PRAGMA table_info('{$table}')");
        foreach ($columns as $column) {
            if ($column->name === 'id' && (int) $column->pk === 1) {
                return true;
            }
        }

        return false;
    }
}
