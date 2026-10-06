<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Deadline-miss risk band derived from the 0–100 risk percentage.
 */
enum RiskLevel: string
{
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public static function fromScore(int $score): self
    {
        $levels = config('edusmart.risk.levels');

        return match (true) {
            $score >= $levels['critical'] => self::Critical,
            $score >= $levels['high'] => self::High,
            $score >= $levels['medium'] => self::Medium,
            default => self::Low,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Ordinal used to detect escalation (low → critical). */
    public function rank(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Medium => 1,
            self::High => 2,
            self::Critical => 3,
        };
    }

    /** @return array<string, string> */
    public function meta(): array
    {
        return match ($this) {
            self::Low => ['color' => '#0ca30c', 'icon' => 'shield-check'],
            self::Medium => ['color' => '#fab219', 'icon' => 'shield'],
            self::High => ['color' => '#ec835a', 'icon' => 'shield-exclamation'],
            self::Critical => ['color' => '#d03b3b', 'icon' => 'shield-fill-exclamation'],
        };
    }
}
