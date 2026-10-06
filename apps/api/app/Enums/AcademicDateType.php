<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Category of a date extracted from academic documents.
 */
enum AcademicDateType: string
{
    use HasOptions;

    case AssignmentDeadline = 'assignment_deadline';
    case Exam = 'exam';
    case ProjectMilestone = 'project_milestone';
    case Event = 'event';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AssignmentDeadline => 'Assignment deadline',
            self::Exam => 'Examination',
            self::ProjectMilestone => 'Project milestone',
            self::Event => 'Academic event',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> */
    public function meta(): array
    {
        return match ($this) {
            self::AssignmentDeadline => ['color' => '#f97316', 'icon' => 'clipboard-check'],
            self::Exam => ['color' => '#ef4444', 'icon' => 'pencil'],
            self::ProjectMilestone => ['color' => '#0ea5e9', 'icon' => 'flag'],
            self::Event => ['color' => '#8b5cf6', 'icon' => 'calendar-event'],
            self::Other => ['color' => '#64748b', 'icon' => 'calendar'],
        };
    }
}
