<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Calendar event category.
 */
enum EventType: string
{
    use HasOptions;

    case Deadline = 'deadline';
    case Exam = 'exam';
    case Milestone = 'milestone';
    case Lecture = 'lecture';
    case Study = 'study';
    case Holiday = 'holiday';
    case Event = 'event';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Deadline => 'Deadline',
            self::Exam => 'Exam',
            self::Milestone => 'Milestone',
            self::Lecture => 'Lecture',
            self::Study => 'Study',
            self::Holiday => 'Holiday',
            self::Event => 'Event',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> */
    public function meta(): array
    {
        return match ($this) {
            self::Deadline => ['color' => '#f97316', 'icon' => 'alarm'],
            self::Exam => ['color' => '#ef4444', 'icon' => 'pencil'],
            self::Milestone => ['color' => '#0ea5e9', 'icon' => 'flag'],
            self::Lecture => ['color' => '#3b82f6', 'icon' => 'easel'],
            self::Study => ['color' => '#14b8a6', 'icon' => 'stopwatch'],
            self::Holiday => ['color' => '#22c55e', 'icon' => 'sun'],
            self::Event => ['color' => '#8b5cf6', 'icon' => 'calendar-event'],
            self::Other => ['color' => '#64748b', 'icon' => 'calendar'],
        };
    }
}
