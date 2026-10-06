<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Enums\AcademicDateStatus;
use App\Enums\AcademicDateType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\AcademicDateRequest;
use App\Http\Resources\Assignments\AssignmentResource;
use App\Http\Resources\Assistant\AcademicDateResource;
use App\Http\Resources\Platform\CalendarEventResource;
use App\Models\AcademicDate;
use App\Models\KnowledgeDocument;
use App\Services\Assignments\RiskService;
use App\Services\Assistant\AcademicDateService;
use App\Services\Documents\DocumentStorage;
use App\Services\Documents\TextExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * KAVISHKA — Academic Date Extraction & Calendar Integration: review the
 * deadlines / exams / milestones / events found in documents, add them to
 * the calendar with a reminder, or hand a deadline to JITHMI as an
 * assignment.
 */
class AcademicDateController extends Controller
{
    public function __construct(private readonly AcademicDateService $dates) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(AcademicDateStatus::class)],
            'type' => ['nullable', Rule::enum(AcademicDateType::class)],
            'knowledge_document_id' => ['nullable', 'integer'],
            'upcoming' => ['nullable', 'boolean'],
        ]);

        return AcademicDateResource::collection($request->user()->academicDates()->with(['document', 'assignment'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['knowledge_document_id'] ?? null, fn ($q, $id) => $q->where('knowledge_document_id', $id))
            ->when($request->boolean('upcoming'), fn ($q) => $q->whereDate('date', '>=', today()))
            ->orderBy('date')->get());
    }

    /** Scan a knowledge document (again) for important dates. */
    public function extract(KnowledgeDocument $knowledge, TextExtractor $extractor, DocumentStorage $storage): JsonResponse
    {
        $pages = $extractor->extract($storage->absolutePath((string) $knowledge->file_path), (string) $knowledge->extension)->pages;
        $found = $this->dates->extractFromDocument($knowledge, $pages);
        activity()->action('academic_dates.extracted')->describe("Extracted {$found} new date(s) from “{$knowledge->title}”")->on($knowledge);

        return response()->json(['message' => $found ? "Found {$found} new date(s)." : 'No new dates found.', 'found' => $found]);
    }

    public function update(AcademicDateRequest $request, AcademicDate $academicDate): AcademicDateResource
    {
        $academicDate->update($request->validated());
        activity()->action('academic_dates.updated')->describe("Corrected extracted date “{$academicDate->title}”")->on($academicDate);

        return AcademicDateResource::make($academicDate->load(['document', 'assignment']));
    }

    public function addToCalendar(Request $request, AcademicDate $academicDate): JsonResponse
    {
        $reminder = $request->validate(['reminder_minutes' => ['nullable', 'integer', 'between:0,40320']])['reminder_minutes'] ?? null;
        $event = $this->dates->addToCalendar($academicDate, $reminder);
        activity()->action('academic_dates.added_to_calendar')
            ->describe("Added “{$academicDate->title}” (".$academicDate->date->format('d M Y').') to the calendar')->on($academicDate);

        return response()->json([
            'date' => AcademicDateResource::make($academicDate->refresh()->load(['document', 'assignment'])),
            'event' => CalendarEventResource::make($event->load('module')),
        ], 201);
    }

    /** Add every pending date (optionally of selected types) to the calendar. */
    public function addAll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'types' => ['nullable', 'array'],
            'types.*' => [Rule::enum(AcademicDateType::class)],
            'reminder_minutes' => ['nullable', 'integer', 'between:0,40320'],
        ]);

        $dates = $request->user()->academicDates()->where('status', AcademicDateStatus::Pending)
            ->when($data['ids'] ?? null, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->when($data['types'] ?? null, fn ($q, $types) => $q->whereIn('type', $types))
            ->get();
        foreach ($dates as $date) {
            $this->dates->addToCalendar($date, $data['reminder_minutes'] ?? null);
        }
        activity()->action('academic_dates.bulk_added')->describe("Added {$dates->count()} extracted date(s) to the calendar");

        return response()->json(['message' => "{$dates->count()} date(s) added to your calendar.", 'added' => $dates->count()]);
    }

    public function dismiss(AcademicDate $academicDate): AcademicDateResource
    {
        $academicDate->update(['status' => AcademicDateStatus::Dismissed]);
        activity()->action('academic_dates.dismissed')->describe("Dismissed extracted date “{$academicDate->title}”")->on($academicDate);

        return AcademicDateResource::make($academicDate->load(['document', 'assignment']));
    }

    public function restore(AcademicDate $academicDate): AcademicDateResource
    {
        $academicDate->update(['status' => $academicDate->calendar_event_id ? AcademicDateStatus::Added : AcademicDateStatus::Pending]);

        return AcademicDateResource::make($academicDate->load(['document', 'assignment']));
    }

    /** Integration KAVISHKA → JITHMI: track the deadline as an assignment. */
    public function createAssignment(Request $request, AcademicDate $academicDate, RiskService $risk): JsonResponse
    {
        abort_if($academicDate->assignment()->exists(), 422, 'An assignment already exists for this date.');
        $data = $request->validate([
            'estimated_hours' => ['nullable', 'numeric', 'between:0.5,500'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'module_id' => ['nullable', 'integer', Rule::exists('modules', 'id')->where('user_id', $request->user()->id)],
        ]);

        $assignment = $this->dates->createAssignment($academicDate, array_filter($data, fn ($v) => $v !== null));
        $risk->recalculate($request->user(), 'created', $assignment);
        activity()->action('academic_dates.assignment_created')
            ->describe("Created assignment “{$assignment->title}” from a deadline found in ".($academicDate->document?->title ?? 'a document'))->on($assignment);

        return response()->json(AssignmentResource::make($assignment->refresh()->load(['module', 'academicDate'])), 201);
    }

    public function destroy(AcademicDate $academicDate): JsonResponse
    {
        $academicDate->delete();
        activity()->action('academic_dates.deleted')->describe("Deleted extracted date “{$academicDate->title}”");

        return response()->json(['message' => 'Date removed.']);
    }
}
