<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Learning material format (drives the viewer and the extractor).
 */
enum DocumentKind: string
{
    use HasOptions;

    case Pdf = 'pdf';
    case Word = 'word';
    case Slides = 'slides';
    case Text = 'text';
    case Markdown = 'markdown';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Word => 'Word',
            self::Slides => 'Slides',
            self::Text => 'Text',
            self::Markdown => 'Markdown',
            self::Note => 'Lecture note',
        };
    }

    /** @return array<string, string> */
    public function meta(): array
    {
        return match ($this) {
            self::Pdf => ['color' => '#ef4444', 'icon' => 'file-earmark-pdf'],
            self::Word => ['color' => '#2563eb', 'icon' => 'file-earmark-word'],
            self::Slides => ['color' => '#ea580c', 'icon' => 'file-earmark-slides'],
            self::Text => ['color' => '#64748b', 'icon' => 'file-earmark-text'],
            self::Markdown => ['color' => '#0f766e', 'icon' => 'markdown'],
            self::Note => ['color' => '#7c3aed', 'icon' => 'journal-text'],
        };
    }
}
