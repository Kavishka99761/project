<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks  (run `php artisan schedule:work` in development, or a
| one-minute cron / Windows Task Scheduler entry for `schedule:run`)
|--------------------------------------------------------------------------
*/

// Calendar reminders, assignments due soon / overdue, study-session reminders.
Schedule::command('acadealert:reminders')->everyMinute()->withoutOverlapping();

// Time passing changes deadline risk — refresh it for everyone every hour.
Schedule::command('acadealert:recalculate-risk')->hourly()->withoutOverlapping();

// Housekeeping.
Schedule::command('acadealert:prune')->dailyAt('03:15');
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('03:30');
