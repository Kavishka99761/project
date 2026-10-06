<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Summary depth requested by the student.
 */
enum SummaryLength: string
{
    use HasOptions;

    case Short = 'short';
    case Medium = 'medium';
    case Detailed = 'detailed';

    public function label(): string
    {
        return match ($this) {
            self::Short => 'Short',
            self::Medium => 'Medium',
            self::Detailed => 'Detailed',
        };
    }
}
