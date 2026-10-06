<?php

namespace App\Console\Commands;

use App\Enums\AssignmentStatus;
use App\Models\User;
use App\Services\Assignments\RiskService;
use Illuminate\Console\Command;

class RecalculateRisk extends Command
{
    protected $signature = 'edusmart:recalculate-risk {--user= : Only this user id}';

    protected $description = 'Recalculate deadline-miss risk for every student with active assignments';

    public function handle(RiskService $risk): int
    {
        $users = User::query()
            ->when($this->option('user'), fn ($q, $id) => $q->whereKey($id))
            ->whereHas('assignments', fn ($q) => $q->where('status', '!=', AssignmentStatus::Completed))
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $count += count($risk->recalculate($user, 'scheduled'));
        }
        $this->components->info("Recalculated risk for {$count} assignment(s) across {$users->count()} student(s).");

        return self::SUCCESS;
    }
}
