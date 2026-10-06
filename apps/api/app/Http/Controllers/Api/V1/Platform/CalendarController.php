<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CalendarEventRequest;
use App\Http\Resources\Platform\CalendarEventResource;
use App\Models\CalendarEvent;
use App\Services\Platform\CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Common Services — Calendar API. The feed merges events, academic dates
 * (KAVISHKA), assignment deadlines (JITHMI) and study plans (PASINDU).
 */
class CalendarController extends Controller
{
    public function __construct(private readonly CalendarService $calendar) {}

    public function feed(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string'],
            'sources' => ['nullable', 'array'],
            'sources.*' => ['in:event,assignment,study_plan,study_session'],
        ]);
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->startOfMonth()->subWeek();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfMonth()->addWeek();
        abort_if($from->diffInDays($to) > 400, 422, 'Choose a range of at most 400 days.');

        return response()->json([
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'items' => $this->calendar->feed($request->user(), $from, $to, $data['types'] ?? [], $data['sources'] ?? ['event', 'assignment', 'study_plan']),
            'types' => EventType::options(),
        ]);
    }

    public function upcoming(Request $request): JsonResponse
    {
        return response()->json($this->calendar->upcoming($request->user(), $request->integer('days', 14), $request->integer('limit', 10)));
    }

    public function store(CalendarEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        $event = $request->user()->calendarEvents()->create($data + [
            'type' => $data['type'] ?? EventType::Event,
            'reminder_minutes' => array_key_exists('reminder_minutes', $data) ? $data['reminder_minutes'] : $request->user()->settingsOrDefault()->default_reminder_minutes,
        ]);
        activity()->action('calendar.created')->describe("Added calendar event “{$event->title}” on ".$event->starts_at->format('d M Y'))->on($event);

        return CalendarEventResource::make($event->load('module'))->response()->setStatusCode(201);
    }

    public function show(CalendarEvent $event): CalendarEventResource
    {
        return CalendarEventResource::make($event->load(['module', 'academicDate']));
    }

    public function update(CalendarEventRequest $request, CalendarEvent $event): CalendarEventResource
    {
        $event->update($request->validated());
        activity()->action('calendar.updated')->describe("Edited calendar event “{$event->title}”")->on($event);

        return CalendarEventResource::make($event->load('module'));
    }

    /** Set or clear the reminder of an event. */
    public function reminder(Request $request, CalendarEvent $event): CalendarEventResource
    {
        $data = $request->validate(['reminder_minutes' => ['nullable', 'integer', 'between:0,40320']]);
        $event->update(['reminder_minutes' => $data['reminder_minutes'] ?? null]);
        activity()->action('calendar.reminder_set')->describe($event->reminder_minutes === null
            ? "Removed the reminder for “{$event->title}”"
            : "Set a reminder {$event->reminder_minutes} min before “{$event->title}”")->on($event);

        return CalendarEventResource::make($event->load('module'));
    }

    public function destroy(CalendarEvent $event): JsonResponse
    {
        $event->delete();
        activity()->action('calendar.deleted')->describe("Deleted calendar event “{$event->title}”")->on($event);

        return response()->json(['message' => 'Event moved to trash.']);
    }
}
