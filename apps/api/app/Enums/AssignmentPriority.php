<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Student-assigned importance of an assignment. The weight feeds the urgency
 * ranking (it never changes the deadline-miss probability itself).
 */
enum AssignmentPriority: string
{
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** 0–100 contribution to the urgency score. */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 25,
            self::Medium => 50,
            self::High => 75,
            self::Urgent => 100,
        };
    }

    /** @return array<string, string> */
    public function meta(): array
    {
        return match ($this) {
            self::Low => ['color' => '#22c55e', 'icon' => 'arrow-down'],
            self::Medium => ['color' => '#eab308', 'icon' => 'dash'],
            self::High => ['color' => '#f97316', 'icon' => 'arrow-up'],
            self::Urgent => ['color' => '#ef4444', 'icon' => 'exclamation-lg'],
        };
    }
}
