<?php

namespace App\Services\Platform;

use App\Enums\AssignmentStatus;
use App\Enums\EventType;
use App\Enums\RiskLevel;
use App\Models\Assignment;
use App\Models\CalendarEvent;
use App\Models\StudyPlan;
use App\Models\StudySession;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Common Services — Calendar API. Merges the student's calendar events
 * (manual + academic dates from KAVISHKA), assignment deadlines (JITHMI),
 * study plans and completed study sessions (PASINDU) into one feed.
 */
class CalendarService
{
    /**
     * @param  list<string>  $types
     * @param  list<string>  $sources  event|assignment|study_plan|study_session
     * @return list<array<string, mixed>>
     */
    public function feed(User $user, Carbon $from, Carbon $to, array $types = [], array $sources = ['event', 'assignment', 'study_plan']): array
    {
        $items = [];

        if (in_array('event', $sources, true)) {
            $events = $user->calendarEvents()->with('module:id,code,color')
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('starts_at', [$from, $to])
                        ->orWhere(fn ($q) => $q->where('starts_at', '<', $from)->where('ends_at', '>=', $from));
                })
                ->when($types, fn ($q) => $q->whereIn('type', $types))
                ->get();
            foreach ($events as $event) {
                $items[] = $this->fromEvent($event);
            }
        }

        if (in_array('assignment', $sources, true) && (! $types || in_array(EventType::Deadline->value, $types, true))) {
            $assignments = $user->assignments()->with('module:id,code,color')->whereBetween('deadline', [$from, $to])->get();
            foreach ($assignments as $assignment) {
                $items[] = $this->fromAssignment($assignment);
            }
        }

        if (in_array('study_plan', $sources, true) && (! $types || in_array(EventType::Study->value, $types, true))) {
            $plans = $user->studyPlans()->with('module:id,code,color')
                ->whereBetween('plan_date', [$from->toDateString(), $to->toDateString()])->get();
            foreach ($plans as $plan) {
                $items[] = $this->fromPlan($plan);
            }
        }

        if (in_array('study_session', $sources, true)) {
            $sessions = $user->studySessions()->completed()->with('module:id,code,color')
                ->whereBetween('started_at', [$from, $to])->get();
            foreach ($sessions as $session) {
                $items[] = $this->fromSession($session);
            }
        }

        usort($items, fn ($a, $b) => strcmp($a['start'], $b['start']));

        return $items;
    }

    /** @return list<array<string, mixed>> */
    public function upcoming(User $user, int $days = 14, int $limit = 8): array
    {
        $items = $this->feed($user, now(), now()->addDays($days));

        return array_slice(array_values(array_filter($items, fn ($i) => $i['source'] !== 'study_plan' || $i['start'] >= now()->toDateString())), 0, $limit);
    }

    /** @return array<string, mixed> */
    public function fromEvent(CalendarEvent $event): array
    {
        return [
            'id' => 'event-'.$event->id,
            'source' => 'event',
            'event_id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'type' => $event->type->value,
            'type_label' => $event->type->label(),
            'start' => $event->starts_at->toIso8601String(),
            'end' => $event->ends_at?->toIso8601String(),
            'all_day' => $event->all_day,
            'location' => $event->location,
            'color' => $event->color ?? $event->module?->color ?? $event->type->meta()['color'],
            'module' => $event->module ? ['id' => $event->module->id, 'code' => $event->module->code] : null,
            'reminder_minutes' => $event->reminder_minutes,
            'origin' => $event->source,
            'editable' => true,
        ];
    }

    private function fromAssignment(Assignment $assignment): array
    {
        $level = $assignment->status === AssignmentStatus::Completed ? null : $assignment->risk_level;

        return [
            'id' => 'assignment-'.$assignment->id,
            'source' => 'assignment',
            'assignment_id' => $assignment->id,
            'title' => 'Due: '.$assignment->title,
            'description' => $assignment->description,
            'type' => EventType::Deadline->value,
            'type_label' => 'Assignment deadline',
            'start' => $assignment->deadline->toIso8601String(),
            'end' => null,
            'all_day' => false,
            'color' => $level?->meta()['color'] ?? ($assignment->status === AssignmentStatus::Completed ? '#22c55e' : RiskLevel::Medium->meta()['color']),
            'module' => $assignment->module ? ['id' => $assignment->module->id, 'code' => $assignment->module->code] : null,
            'risk_level' => $level?->value,
            'risk_score' => $assignment->risk_score,
            'completed' => $assignment->status === AssignmentStatus::Completed,
            'url' => '/assignments/'.$assignment->id,
            'editable' => false,
        ];
    }

    private function fromPlan(StudyPlan $plan): array
    {
        return [
            'id' => 'plan-'.$plan->id,
            'source' => 'study_plan',
            'plan_id' => $plan->id,
            'title' => ($plan->title ?: 'Study plan').' · '.$plan->planned_minutes.' min',
            'type' => EventType::Study->value,
            'type_label' => 'Planned study',
            'start' => $plan->plan_date->toDateString(),
            'end' => null,
            'all_day' => true,
            'color' => $plan->module?->color ?? EventType::Study->meta()['color'],
            'module' => $plan->module ? ['id' => $plan->module->id, 'code' => $plan->module->code] : null,
            'done' => $plan->is_done,
            'url' => '/study/plans',
            'editable' => false,
        ];
    }

    private function fromSession(StudySession $session): array
    {
        return [
            'id' => 'session-'.$session->id,
            'source' => 'study_session',
            'session_id' => $session->id,
            'title' => "Studied {$session->actual_minutes} min · ".$session->activity->label(),
            'type' => EventType::Study->value,
            'type_label' => 'Study session',
            'start' => $session->started_at->toIso8601String(),
            'end' => $session->ended_at?->toIso8601String(),
            'all_day' => false,
            'color' => $session->module?->color ?? EventType::Study->meta()['color'],
            'module' => $session->module ? ['id' => $session->module->id, 'code' => $session->module->code] : null,
            'url' => '/study/sessions/'.$session->id,
            'editable' => false,
        ];
    }
}
