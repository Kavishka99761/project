<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Assignment lifecycle.
 */
enum AssignmentStatus: string
{
    use HasOptions;

    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
        };
    }
}
