<?php

namespace App\Console\Commands;

use App\Services\Platform\DataAudit\DataAuditInstaller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Manage the SQL Server row-level audit triggers.
 *
 *   php artisan acadealert:audit status      which tables are audited, row counts
 *   php artisan acadealert:audit install     (re)create every trigger
 *   php artisan acadealert:audit uninstall   drop the triggers (log is kept)
 *   php artisan acadealert:audit recent      the latest 20 changes as a table
 */
class DataAudit extends Command
{
    protected $signature = 'acadealert:audit {action=status : status|install|uninstall|recent} {--limit=20}';

    protected $description = 'Install, inspect or remove the SQL Server data-change audit triggers';

    public function handle(DataAuditInstaller $installer): int
    {
        if (! $installer->supported()) {
            $this->components->warn('The database audit needs SQL Server (DB_CONNECTION=sqlsrv).');

            return self::FAILURE;
        }
        if (! $installer->logTableExists()) {
            $this->components->error('dbo.data_change_log is missing — run `php artisan migrate` first.');

            return self::FAILURE;
        }

        return match ($this->argument('action')) {
            'install' => $this->install($installer),
            'uninstall' => $this->uninstall($installer),
            'recent' => $this->recent(),
            default => $this->status($installer),
        };
    }

    private function install(DataAuditInstaller $installer): int
    {
        $tables = $installer->install();
        $this->components->info('Audit triggers installed on '.count($tables).' tables.');
        $this->line('  '.implode(', ', $tables));

        return self::SUCCESS;
    }

    private function uninstall(DataAuditInstaller $installer): int
    {
        $count = $installer->uninstall();
        $this->components->warn("Dropped {$count} audit trigger(s). Changes are no longer recorded until you run `acadealert:audit install`.");

        return self::SUCCESS;
    }

    private function status(DataAuditInstaller $installer): int
    {
        $triggers = $installer->installedTriggers();
        $counts = collect(DB::select('SELECT table_name, operation, COUNT(*) AS total FROM dbo.data_change_log GROUP BY table_name, operation'))
            ->groupBy('table_name');

        $rows = collect($triggers)->map(function ($trigger) use ($counts) {
            $table = substr($trigger, strlen(DataAuditInstaller::TRIGGER_PREFIX));
            $byOp = collect($counts[$table] ?? [])->pluck('total', 'operation');

            return [$table, $trigger, (int) ($byOp['I'] ?? 0), (int) ($byOp['U'] ?? 0), (int) ($byOp['D'] ?? 0)];
        });

        $this->table(['Table', 'Trigger', 'Inserts', 'Updates', 'Deletes'], $rows);
        $this->components->info(count($triggers).' tables audited · '.DB::table('data_change_log')->count().' changes recorded.');

        return self::SUCCESS;
    }

    private function recent(): int
    {
        $rows = DB::table('vw_data_changes')->orderByDesc('id')->limit((int) $this->option('limit'))
            ->get(['id', 'changed_at', 'operation', 'table_name', 'record_id', 'changed_columns', 'changed_by', 'source'])
            ->map(fn ($r) => [$r->id, substr((string) $r->changed_at, 0, 19), $r->operation, $r->table_name, $r->record_id,
                mb_strimwidth((string) $r->changed_columns, 0, 40, '…'), $r->changed_by, $r->source]);

        $this->table(['#', 'When', 'Operation', 'Table', 'Record', 'Changed columns', 'By', 'Source'], $rows);

        return self::SUCCESS;
    }
}
