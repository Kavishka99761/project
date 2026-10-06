<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Feature module that owns an action, notification or dataset.
 */
enum ModuleKey: string
{
    use HasOptions;

    case Platform = 'platform';
    case Learning = 'learning';
    case Study = 'study';
    case Assistant = 'assistant';
    case Assignments = 'assignments';

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Platform',
            self::Learning => 'Learning Materials',
            self::Study => 'Study & Engagement',
            self::Assistant => 'Academic Assistant',
            self::Assignments => 'Assignments & Risk',
        };
    }

    /** @return array<string, string> */
    public function meta(): array
    {
        return match ($this) {
            self::Platform => ['color' => '#898781', 'icon' => 'grid-1x2'],
            self::Learning => ['color' => '#2a78d6', 'icon' => 'journal-richtext'],
            self::Study => ['color' => '#1baf7a', 'icon' => 'stopwatch'],
            self::Assistant => ['color' => '#4a3aa7', 'icon' => 'robot'],
            self::Assignments => ['color' => '#eb6834', 'icon' => 'clipboard-data'],
        };
    }
}
