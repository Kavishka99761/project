<?php

namespace App\Services\Platform;

use App\Models\Assignment;
use App\Models\CalendarEvent;
use App\Models\Document;
use App\Models\KnowledgeDocument;
use App\Models\Summary;
use App\Models\User;
use App\Services\Assignments\RiskService;
use Illuminate\Database\Eloquent\Model;

/**
 * Notion-style trash: deleted documents, summaries, knowledge documents,
 * assignments and calendar events stay recoverable until emptied.
 */
class TrashService
{
    /** type => [model, title column, label, icon] */
    public const TYPES = [
        'documents' => [Document::class, 'title', 'Learning document', 'journal-richtext'],
        'summaries' => [Summary::class, 'title', 'Summary', 'card-text'],
        'knowledge' => [KnowledgeDocument::class, 'title', 'Knowledge document', 'book-half'],
        'assignments' => [Assignment::class, 'title', 'Assignment', 'clipboard-check'],
        'events' => [CalendarEvent::class, 'title', 'Calendar event', 'calendar-event'],
    ];

    public function __construct(private readonly RiskService $risk) {}

    /** @return list<array<string, mixed>> */
    public function list(User $user): array
    {
        $items = [];
        foreach (self::TYPES as $type => [$model, $titleColumn, $label, $icon]) {
            foreach ($model::onlyTrashed()->where('user_id', $user->id)->latest('deleted_at')->limit(200)->get() as $record) {
                $items[] = [
                    'type' => $type,
                    'type_label' => $label,
                    'icon' => $icon,
                    'id' => $record->id,
                    'title' => $record->{$titleColumn},
                    'deleted_at' => $record->deleted_at->toIso8601String(),
                    'purge_at' => $record->deleted_at->copy()->addDays(30)->toIso8601String(),
                ];
            }
        }
        usort($items, fn ($a, $b) => strcmp($b['deleted_at'], $a['deleted_at']));

        return $items;
    }

    public function find(User $user, string $type, int $id): Model
    {
        [$model] = self::TYPES[$type] ?? throw new \InvalidArgumentException('Unknown trash type.');

        return $model::onlyTrashed()->where('user_id', $user->id)->findOrFail($id);
    }

    public function restore(User $user, string $type, int $id): Model
    {
        $record = $this->find($user, $type, $id);
        $record->restore();
        if ($record instanceof Assignment) {
            $this->risk->recalculate($user, 'restored', $record);
        }

        return $record;
    }

    public function purge(User $user, string $type, int $id): void
    {
        $this->find($user, $type, $id)->forceDelete();
    }

    public function empty(User $user): int
    {
        $count = 0;
        foreach (self::TYPES as [$model]) {
            foreach ($model::onlyTrashed()->where('user_id', $user->id)->get() as $record) {
                $record->forceDelete();
                $count++;
            }
        }

        return $count;
    }
}
