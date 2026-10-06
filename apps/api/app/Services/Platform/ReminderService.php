<?php

namespace App\Services\Platform;

use App\Enums\AssignmentStatus;
use App\Enums\ModuleKey;
use App\Enums\NotificationType;
use App\Models\Assignment;
use App\Models\CalendarEvent;
use App\Models\StudyReminder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Sends every kind of due reminder as a notification:
 *   • calendar event reminders           (KAVISHKA — set reminder for academic event)
 *   • assignment due within 24 hours      (JITHMI)
 *   • assignment just became overdue      (JITHMI — overdue history)
 *   • recurring study-session reminders   (PASINDU)
 *
 * Runs every minute from the scheduler and, as a safety net in development
 * (no scheduler running), at most once a minute per student when the web
 * client polls notifications.
 */
class ReminderService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ActivityLogger $activity,
    ) {}

    /** Throttled per-user dispatch used by the notifications endpoint. */
    public function dispatchForUserThrottled(User $user): void
    {
        if (Cache::add('reminders:user:'.$user->id, true, 60)) {
            $this->dispatch($user);
        }
    }

    /** @return array{events: int, deadlines: int, overdue: int, study: int} */
    public function dispatch(?User $only = null): array
    {
        return [
            'events' => $this->eventReminders($only),
            'deadlines' => $this->deadlineReminders($only),
            'overdue' => $this->overdueChecks($only),
            'study' => $this->studyReminders($only),
        ];
    }

    private function eventReminders(?User $only): int
    {
        $events = CalendarEvent::query()->with('user')
            ->when($only, fn ($q) => $q->where('user_id', $only->id))
            ->whereNotNull('reminder_at')->whereNull('reminder_sent_at')
            ->where('reminder_at', '<=', now())
            ->where('starts_at', '>=', now()->subDay())
            ->limit(200)->get();

        foreach ($events as $event) {
            $when = $event->all_day ? $event->starts_at->format('l, d M') : $event->starts_at->format('l, d M · H:i');
            $this->notifications->send($event->user, ModuleKey::Assistant, 'Reminder: '.$event->title,
                $event->type->label().' — '.$when.' ('.$event->starts_at->diffForHumans().')',
                NotificationType::Reminder, '/calendar?date='.$event->starts_at->toDateString(), $event->type->meta()['icon']);
            $event->forceFill(['reminder_sent_at' => now()])->saveQuietly();
        }

        return $events->count();
    }

    private function deadlineReminders(?User $only): int
    {
        $assignments = Assignment::query()->with('user.settings')
            ->when($only, fn ($q) => $q->where('user_id', $only->id))
            ->where('status', '!=', AssignmentStatus::Completed)
            ->whereNull('reminder_sent_at')
            ->whereBetween('deadline', [now(), now()->addDay()])
            ->limit(200)->get();

        $sent = 0;
        foreach ($assignments as $assignment) {
            if ($assignment->user->settingsOrDefault()->notify_deadlines) {
                $this->notifications->send($assignment->user, ModuleKey::Assignments, 'Due soon: '.$assignment->title,
                    sprintf('Due %s — %d%% complete, about %.1f h of work left.', $assignment->deadline->diffForHumans(), $assignment->progress, $assignment->remainingHours()),
                    NotificationType::Warning, "/assignments/{$assignment->id}", 'alarm');
                $sent++;
            }
            $assignment->forceFill(['reminder_sent_at' => now()])->saveQuietly();
        }

        return $sent;
    }

    private function overdueChecks(?User $only): int
    {
        $assignments = Assignment::query()->with('user')
            ->when($only, fn ($q) => $q->where('user_id', $only->id))
            ->where('status', '!=', AssignmentStatus::Completed)
            ->where('deadline', '<', now())
            ->whereNull('overdue_notified_at')
            ->limit(200)->get();

        foreach ($assignments as $assignment) {
            $assignment->forceFill(['was_overdue' => true, 'overdue_notified_at' => now()])->saveQuietly();
            $this->notifications->send($assignment->user, ModuleKey::Assignments, 'Overdue: '.$assignment->title,
                'The deadline has passed with '.(100 - $assignment->progress).'% still to do. Submit as soon as you can.',
                NotificationType::Danger, "/assignments/{$assignment->id}", 'exclamation-octagon');
            $this->activity->log($assignment->user, ModuleKey::Assignments, 'assignments.overdue',
                "“{$assignment->title}” became overdue", $assignment);
        }

        return $assignments->count();
    }

    private function studyReminders(?User $only): int
    {
        $weekday = strtolower(now()->format('D'));
        $reminders = StudyReminder::query()->with('user')
            ->when($only, fn ($q) => $q->where('user_id', $only->id))
            ->where('is_active', true)
            ->get();

        $sent = 0;
        foreach ($reminders as $reminder) {
            if (! in_array($weekday, (array) $reminder->days, true)) {
                continue;
            }
            $due = Carbon::parse(now()->toDateString().' '.substr((string) $reminder->remind_time, 0, 5));
            // Only within two hours of the slot, and once per day.
            if ($due->isFuture() || $due->lessThan(now()->subHours(2)) || ($reminder->last_sent_at && $reminder->last_sent_at->greaterThanOrEqualTo($due))) {
                continue;
            }
            $reminder->forceFill(['last_sent_at' => now()])->saveQuietly();

            $user = $reminder->user;
            if (! $user->settingsOrDefault()->notify_study_reminders || $user->studySessions()->live()->exists()) {
                continue;
            }
            $this->notifications->send($user, ModuleKey::Study, $reminder->title,
                $reminder->message ?: 'It is time for your planned study session. Start the timer when you are ready.',
                NotificationType::Reminder, '/study?start=1'.($reminder->module_id ? '&module='.$reminder->module_id : ''), 'stopwatch');
            $sent++;
        }

        return $sent;
    }
}
