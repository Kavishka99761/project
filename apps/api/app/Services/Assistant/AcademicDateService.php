<?php

namespace App\Services\Assistant;

use App\Enums\AcademicDateStatus;
use App\Enums\AcademicDateType;
use App\Enums\AssignmentPriority;
use App\Enums\EventType;
use App\Models\AcademicDate;
use App\Models\Assignment;
use App\Models\CalendarEvent;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * KAVISHKA — academic date extraction and calendar integration.
 *
 * Integration points:
 *   → Common calendar   addToCalendar() creates a CalendarEvent with a reminder
 *   → JITHMI            createAssignment() turns a detected deadline into a
 *                       tracked assignment (linked through academic_date_id)
 */
class AcademicDateService
{
    public function __construct(private readonly DateExtractor $extractor) {}

    /**
     * @param  list<string>  $pages
     * @return int number of new dates found
     */
    public function extractFromDocument(KnowledgeDocument $document, array $pages): int
    {
        $reference = Carbon::parse($document->created_at ?? now());
        $candidates = $this->extractor->extract($pages, $reference);

        $existing = AcademicDate::query()
            ->where('user_id', $document->user_id)
            ->get(['date', 'type', 'title'])
            ->map(fn ($d) => $d->date->toDateString().'|'.$d->type->value.'|'.mb_strtolower($d->title))
            ->flip();

        $created = 0;
        foreach ($candidates as $candidate) {
            $key = $candidate['date'].'|'.$candidate['type']->value.'|'.mb_strtolower($candidate['title']);
            if (isset($existing[$key])) {
                continue;
            }
            AcademicDate::forceCreate([
                'user_id' => $document->user_id,
                'knowledge_document_id' => $document->id,
                'title' => mb_substr($candidate['title'], 0, 200),
                'date' => $candidate['date'],
                'time' => $candidate['time'],
                'type' => $candidate['type'],
                'confidence' => $candidate['confidence'],
                'context' => $candidate['context'],
                'matched_text' => $candidate['matched_text'],
                'page_number' => $candidate['page'],
                'status' => AcademicDateStatus::Pending,
            ]);
            $created++;
        }

        return $created;
    }

    public function addToCalendar(AcademicDate $date, ?int $reminderMinutes = null): CalendarEvent
    {
        return DB::transaction(function () use ($date, $reminderMinutes) {
            if ($date->calendar_event_id && ($existing = CalendarEvent::find($date->calendar_event_id))) {
                return $existing;
            }

            $type = $this->eventType($date->type);
            $time = $date->time ? substr((string) $date->time, 0, 5) : null;
            $startsAt = Carbon::parse($date->date->toDateString().' '.($time ?? '00:00'));
            $reminder = $reminderMinutes ?? $date->user->settingsOrDefault()->default_reminder_minutes;

            $event = CalendarEvent::forceCreate([
                'user_id' => $date->user_id,
                'source' => 'academic_date',
                'title' => $date->title,
                'description' => $date->context
                    ? $date->context.($date->document ? "\n\nSource: {$date->document->title}".($date->page_number ? ", p. {$date->page_number}" : '') : '')
                    : null,
                'type' => $type,
                'starts_at' => $startsAt,
                'all_day' => $time === null,
                'color' => $type->meta()['color'] ?? null,
                'reminder_minutes' => $reminder,
            ]);

            $date->update(['calendar_event_id' => $event->id, 'status' => AcademicDateStatus::Added]);

            return $event;
        });
    }

    /** JITHMI integration — track a detected deadline as an assignment. */
    public function createAssignment(AcademicDate $date, array $overrides = []): Assignment
    {
        $time = $date->time ? substr((string) $date->time, 0, 5) : '23:59';

        return Assignment::forceCreate(array_merge([
            'user_id' => $date->user_id,
            'module_id' => $date->document?->module_id,
            'academic_date_id' => $date->id,
            'title' => $date->title,
            'description' => $date->context,
            'type' => $date->type === AcademicDateType::ProjectMilestone ? 'project' : 'coursework',
            'deadline' => Carbon::parse($date->date->toDateString().' '.$time),
            'priority' => $date->type === AcademicDateType::Exam ? AssignmentPriority::High : AssignmentPriority::Medium,
            'estimated_hours' => 8,
        ], $overrides));
    }

    public function eventType(AcademicDateType $type): EventType
    {
        return match ($type) {
            AcademicDateType::AssignmentDeadline => EventType::Deadline,
            AcademicDateType::Exam => EventType::Exam,
            AcademicDateType::ProjectMilestone => EventType::Milestone,
            AcademicDateType::Event => EventType::Event,
            AcademicDateType::Other => EventType::Other,
        };
    }
}
