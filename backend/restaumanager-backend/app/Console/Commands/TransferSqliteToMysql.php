<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferSqliteToMysql extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:transfer-sqlite-to-mysql {--drop-existing} {--tables=*} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy data from database/database.sqlite into the MySQL connection preserving ids and relations.';

    public function handle(): int
    {
        $sqlitePath = database_path('database.sqlite');

        if (!file_exists($sqlitePath)) {
            $this->error("SQLite database not found at {$sqlitePath}");
            return 1;
        }

        // Register a temporary connection to the old sqlite database
        config(['database.connections.old_sqlite' => [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
            'prefix' => '',
        ]]);

        $old = DB::connection('old_sqlite');
        $new = DB::connection(config('database.default'));

        // get tables from sqlite_master
        $tables = collect($old->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')
            ->toArray();

        if ($this->option('dry-run')) {
            $this->info('Dry run: tables detected in SQLite:');
            foreach ($tables as $t) {
                $this->line(" - {$t}");
            }
            return 0;
        }

        $selected = $this->option('tables');
        if (!empty($selected)) {
            $tables = array_intersect($tables, $selected);
        }

        if (empty($tables)) {
            $this->info('No tables found to transfer.');
            return 0;
        }

        if ($this->option('drop-existing')) {
            $this->info('Truncating destination tables...');
            $new->statement('SET FOREIGN_KEY_CHECKS=0;');
            foreach ($tables as $table) {
                try {
                    $new->table($table)->truncate();
                } catch (\Throwable $e) {
                    Log::error("Could not truncate table {$table}: " . $e->getMessage());
                }
            }
            $new->statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $new->statement('SET FOREIGN_KEY_CHECKS=0;');

        $logPath = storage_path('logs/db_transfer_errors.log');
        foreach ($tables as $table) {
            $this->info("Transferring table: {$table}");
            $count = 0;
            try {
                $cursor = $old->table($table)->cursor();
                $batch = [];
                foreach ($cursor as $row) {
                    $arr = (array) $row;
                    $batch[] = $arr;
                    if (count($batch) >= 100) {
                        DB::table($table)->insertOrIgnore($batch);
                        $count += count($batch);
                        $batch = [];
                    }
                }
                if (!empty($batch)) {
                    DB::table($table)->insertOrIgnore($batch);
                    $count += count($batch);
                }

                // adjust auto_increment for MySQL
                try {
                    $max = DB::table($table)->max('id');
                    if ($max !== null) {
                        $ai = $max + 1;
                        DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$ai}");
                    }
                } catch (\Throwable $e) {
                    // some tables might not have id column
                }

                $this->info("Inserted approx {$count} rows into {$table}");
            } catch (\Throwable $e) {
                $msg = "Error transferring table {$table}: " . $e->getMessage();
                file_put_contents($logPath, $msg.PHP_EOL, FILE_APPEND);
                Log::error($msg);
                $this->error($msg);
            }
        }

        $new->statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('Transfer completed. Check ' . $logPath . ' for errors.');
        return 0;
    }
}
