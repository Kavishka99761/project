<?php

namespace App\Console\Commands;

use App\Services\Platform\ReminderService;
use Illuminate\Console\Command;

class DispatchReminders extends Command
{
    protected $signature = 'edusmart:reminders';

    protected $description = 'Send due calendar, deadline, overdue and study-session reminders';

    public function handle(ReminderService $reminders): int
    {
        $sent = $reminders->dispatch();
        $this->components->info(sprintf(
            'Reminders sent — events: %d, deadlines: %d, overdue: %d, study: %d',
            $sent['events'], $sent['deadlines'], $sent['overdue'], $sent['study'],
        ));

        return self::SUCCESS;
    }
}
