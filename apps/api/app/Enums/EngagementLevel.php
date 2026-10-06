<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Concentration band for a 0–100 engagement score.
 */
enum EngagementLevel: string
{
    use HasOptions;

    case High = 'high';
    case Moderate = 'moderate';
    case Low = 'low';

    public static function fromScore(int $score): self
    {
        $bands = config('edusmart.study.engagement');

        return match (true) {
            $score >= $bands['high'] => self::High,
            $score >= $bands['moderate'] => self::Moderate,
            default => self::Low,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::High => 'Focused',
            self::Moderate => 'Moderate',
            self::Low => 'Distracted',
        };
    }

    /** @return array<string, string> */
    public function meta(): array
    {
        return match ($this) {
            self::High => ['color' => '#0ca30c', 'icon' => 'lightning-charge'],
            self::Moderate => ['color' => '#fab219', 'icon' => 'activity'],
            self::Low => ['color' => '#d03b3b', 'icon' => 'cloud-drizzle'],
        };
    }
}
