<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Review state of an extracted date.
 */
enum AcademicDateStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Added = 'added';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Needs review',
            self::Added => 'Added to calendar',
            self::Dismissed => 'Dismissed',
        };
    }
}
