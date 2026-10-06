<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for string-backed enums: value lists for validation and
 * {value,label} option lists that the web client renders in dropdowns.
 */
trait HasOptions
{
    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ] + (method_exists($case, 'meta') ? $case->meta() : []), self::cases());
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
