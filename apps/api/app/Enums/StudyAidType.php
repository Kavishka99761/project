<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * NotebookLM-style study aids generated from a document.
 */
enum StudyAidType: string
{
    use HasOptions;

    case Flashcards = 'flashcards';
    case Quiz = 'quiz';
    case Mindmap = 'mindmap';

    public function label(): string
    {
        return match ($this) {
            self::Flashcards => 'Flashcards',
            self::Quiz => 'Quiz',
            self::Mindmap => 'Mind map',
        };
    }

    /** @return array<string, string> */
    public function meta(): array
    {
        return match ($this) {
            self::Flashcards => ['color' => '#f59e0b', 'icon' => 'card-text'],
            self::Quiz => ['color' => '#10b981', 'icon' => 'patch-question'],
            self::Mindmap => ['color' => '#8b5cf6', 'icon' => 'diagram-3'],
        };
    }
}
