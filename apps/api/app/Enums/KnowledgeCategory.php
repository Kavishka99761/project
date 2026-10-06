<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Type of institutional document in the assistant's knowledge base.
 */
enum KnowledgeCategory: string
{
    use HasOptions;

    case Handbook = 'handbook';
    case ProjectGuideline = 'project_guideline';
    case Regulation = 'regulation';
    case ModuleDocument = 'module_document';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Handbook => 'Module handbook',
            self::ProjectGuideline => 'Project guideline',
            self::Regulation => 'University regulation',
            self::ModuleDocument => 'Module document',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> */
    public function meta(): array
    {
        return match ($this) {
            self::Handbook => ['color' => '#8b5cf6', 'icon' => 'book-half'],
            self::ProjectGuideline => ['color' => '#0ea5e9', 'icon' => 'kanban'],
            self::Regulation => ['color' => '#ef4444', 'icon' => 'bank'],
            self::ModuleDocument => ['color' => '#14b8a6', 'icon' => 'file-earmark-text'],
            self::Other => ['color' => '#64748b', 'icon' => 'folder2'],
        };
    }
}
