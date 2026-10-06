<?php

namespace App\Http\Controllers\Api\V1\Learning;

use App\Enums\StudyAidType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Learning\StudyAidResource;
use App\Models\Document;
use App\Models\StudyAid;
use App\Services\Learning\StudyAidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * BETHMI — NotebookLM-style study aids: flashcards, quizzes, mind maps.
 */
class StudyAidController extends Controller
{
    public function __construct(private readonly StudyAidService $aids) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return StudyAidResource::collection($request->user()->studyAids()->with('document')
            ->when($request->integer('document_id'), fn ($q, $id) => $q->where('document_id', $id))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->latest()->get());
    }

    public function generate(Request $request, Document $document): JsonResponse
    {
        $type = StudyAidType::from($request->validate(['type' => ['required', Rule::enum(StudyAidType::class)]])['type']);
        $aid = $this->aids->generateAndSave($document, $type);
        activity()->action('study_aids.generated')
            ->describe("Generated {$type->label()} ({$aid->item_count} items) from “{$document->title}”")->on($aid);

        return StudyAidResource::make($aid->load('document'))->response()->setStatusCode(201);
    }

    public function show(StudyAid $studyAid): StudyAidResource
    {
        return StudyAidResource::make($studyAid->load('document'));
    }

    /** Record a quiz attempt or flashcard review in the audit trail. */
    public function attempt(Request $request, StudyAid $studyAid): JsonResponse
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1'],
        ]);
        activity()->action('study_aids.attempted')
            ->describe(sprintf('Practised “%s” — %d/%d (%d%%)', $studyAid->title, $data['score'], $data['total'], round($data['score'] / $data['total'] * 100)))
            ->on($studyAid)->with($data);

        return response()->json(['message' => 'Result recorded.']);
    }

    public function destroy(StudyAid $studyAid): JsonResponse
    {
        $studyAid->delete();
        activity()->action('study_aids.deleted')->describe("Deleted “{$studyAid->title}”");

        return response()->json(['message' => 'Study aid deleted.']);
    }
}
