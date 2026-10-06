<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What the student is doing during a study session.
 */
enum StudyActivity: string
{
    use HasOptions;

    case Reading = 'reading';
    case Revision = 'revision';
    case Practice = 'practice';
    case Assignment = 'assignment';
    case LectureReview = 'lecture_review';
    case Project = 'project';
    case ExamPrep = 'exam_prep';
    case NoteTaking = 'note_taking';
    case Research = 'research';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Reading => 'Reading',
            self::Revision => 'Revision',
            self::Practice => 'Practice questions',
            self::Assignment => 'Assignment work',
            self::LectureReview => 'Lecture review',
            self::Project => 'Project work',
            self::ExamPrep => 'Exam preparation',
            self::NoteTaking => 'Note taking',
            self::Research => 'Research',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> */
    public function meta(): array
    {
        return match ($this) {
            self::Reading => ['icon' => 'book'],
            self::Revision => ['icon' => 'arrow-repeat'],
            self::Practice => ['icon' => 'pencil-square'],
            self::Assignment => ['icon' => 'clipboard-check'],
            self::LectureReview => ['icon' => 'easel'],
            self::Project => ['icon' => 'kanban'],
            self::ExamPrep => ['icon' => 'mortarboard'],
            self::NoteTaking => ['icon' => 'journal-text'],
            self::Research => ['icon' => 'search'],
            self::Other => ['icon' => 'three-dots'],
        };
    }
}
