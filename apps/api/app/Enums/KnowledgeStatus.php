<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Processing pipeline state of a knowledge document.
 */
enum KnowledgeStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Processing = 'processing';
    case Indexed = 'indexed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Indexed => 'Indexed',
            self::Failed => 'Failed',
        };
    }
}
