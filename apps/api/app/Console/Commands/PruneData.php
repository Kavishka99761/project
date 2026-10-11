<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\DataChange;
use App\Services\Platform\DataAudit\DataAuditInstaller;
use App\Services\Platform\TrashService;
use Illuminate\Console\Command;

class PruneData extends Command
{
    protected $signature = 'acadealert:prune';

    protected $description = 'Purge trash older than 30 days and activity logs past the retention period';

    public function handle(): int
    {
        $purged = 0;
        foreach (TrashService::TYPES as [$model]) {
            foreach ($model::onlyTrashed()->where('deleted_at', '<', now()->subDays(30))->get() as $record) {
                $record->forceDelete();
                $purged++;
            }
        }

        $logs = ActivityLog::query()
            ->where('created_at', '<', now()->subDays((int) config('acadealert.activity.retention_days', 365)))
            ->delete();

        $changes = 0;
        if (app(DataAuditInstaller::class)->logTableExists()) {
            $changes = DataChange::query()
                ->where('changed_at', '<', now()->subDays((int) config('acadealert.activity.data_change_retention_days', 365)))
                ->delete();
        }

        $this->components->info("Purged {$purged} trashed record(s), {$logs} old activity log(s) and {$changes} old data change(s).");

        return self::SUCCESS;
    }
}
