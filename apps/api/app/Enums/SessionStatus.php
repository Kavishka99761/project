<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Lifecycle of a study session timer.
 */
enum SessionStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Paused = 'paused';
    case OnBreak = 'on_break';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Studying',
            self::Paused => 'Paused',
            self::OnBreak => 'On break',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
