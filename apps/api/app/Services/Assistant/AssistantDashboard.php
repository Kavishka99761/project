<?php

namespace App\Services\Assistant;

use App\Enums\AcademicDateStatus;
use App\Enums\KnowledgeCategory;
use App\Enums\KnowledgeStatus;
use App\Models\User;

/**
 * KAVISHKA — Academic Dashboard: knowledge base coverage, chatbot usage,
 * dates waiting for review and the upcoming academic calendar.
 */
class AssistantDashboard
{
    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $documents = $user->knowledgeDocuments()->get(['id', 'title', 'category', 'pages', 'chunk_count', 'status', 'created_at']);
        $questions = $user->chatMessages()->where('role', 'user');
        $answers = $user->chatMessages()->where('role', 'assistant');

        return [
            'stats' => [
                'documents' => $documents->count(),
                'indexed' => $documents->where('status', KnowledgeStatus::Indexed)->count(),
                'passages' => (int) $documents->sum('chunk_count'),
                'pages' => (int) $documents->sum('pages'),
                'conversations' => $user->conversations()->count(),
                'questions' => (clone $questions)->count(),
                'average_confidence' => ($avg = (clone $answers)->avg('confidence')) !== null ? (int) round($avg * 100) : null,
                'dates_pending' => $user->academicDates()->where('status', AcademicDateStatus::Pending)->count(),
                'dates_added' => $user->academicDates()->where('status', AcademicDateStatus::Added)->count(),
            ],
            'by_category' => collect(KnowledgeCategory::cases())->map(fn ($category) => [
                'category' => $category->value,
                'label' => $category->label(),
                'color' => $category->meta()['color'],
                'icon' => $category->meta()['icon'],
                'count' => $documents->where('category', $category)->count(),
            ])->values(),
            'recent_documents' => $documents->sortByDesc('created_at')->take(5)->values()->map(fn ($d) => [
                'id' => $d->id, 'title' => $d->title, 'category' => $d->category->value, 'status' => $d->status->value,
                'pages' => $d->pages, 'chunks' => $d->chunk_count,
            ]),
            'recent_conversations' => $user->conversations()->orderByDesc('last_message_at')->limit(5)->get(['id', 'title', 'message_count', 'last_message_at'])
                ->map(fn ($c) => ['id' => $c->id, 'title' => $c->title, 'messages' => $c->message_count, 'last_message_at' => $c->last_message_at?->toIso8601String()]),
            'pending_dates' => $user->academicDates()->with('document:id,title')->where('status', AcademicDateStatus::Pending)
                ->orderBy('date')->limit(6)->get()->map(fn ($d) => [
                    'id' => $d->id, 'title' => $d->title, 'date' => $d->date->toDateString(), 'type' => $d->type->value,
                    'confidence' => $d->confidence, 'document' => $d->document?->title,
                ]),
            'upcoming_events' => $user->calendarEvents()->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->limit(6)->get()
                ->map(fn ($e) => [
                    'id' => $e->id, 'title' => $e->title, 'type' => $e->type->value, 'starts_at' => $e->starts_at->toIso8601String(),
                    'all_day' => $e->all_day, 'reminder_minutes' => $e->reminder_minutes,
                ]),
            'suggested_questions' => [
                'What is the penalty for late submission?',
                'When is the final project proposal due?',
                'What is the minimum attendance requirement?',
                'How do I apply for mitigating circumstances?',
            ],
        ];
    }
}
