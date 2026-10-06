<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Outcome of server-side text extraction.
 */
enum ExtractionStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Completed = 'completed';
    case Empty = 'empty';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Empty => 'No text found',
            self::Failed => 'Failed',
        };
    }
}
