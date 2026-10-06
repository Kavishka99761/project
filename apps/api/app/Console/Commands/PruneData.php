<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Services\Platform\TrashService;
use Illuminate\Console\Command;

class PruneData extends Command
{
    protected $signature = 'edusmart:prune';

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
            ->where('created_at', '<', now()->subDays((int) config('edusmart.activity.retention_days', 365)))
            ->delete();

        $this->components->info("Purged {$purged} trashed record(s) and {$logs} old activity log(s).");

        return self::SUCCESS;
    }
}
