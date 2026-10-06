<?php

namespace Database\Seeders;

use App\Enums\AcademicDateStatus;
use App\Enums\AcademicDateType;
use App\Enums\AssignmentPriority;
use App\Enums\AssignmentStatus;
use App\Enums\EngagementLevel;
use App\Enums\EventType;
use App\Enums\KnowledgeCategory;
use App\Enums\ModuleKey;
use App\Enums\NotificationType;
use App\Enums\RiskLevel;
use App\Enums\SessionStatus;
use App\Enums\StudyActivity;
use App\Enums\StudyAidType;
use App\Enums\SummaryLength;
use App\Models\AcademicDate;
use App\Models\ActivityLog;
use App\Models\Assignment;
use App\Models\AssignmentProgressLog;
use App\Models\Document;
use App\Models\EngagementLog;
use App\Models\Module;
use App\Models\RiskAssessment;
use App\Models\StudySession;
use App\Models\StudySessionEvent;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Assignments\AssignmentService;
use App\Services\Assignments\RiskService;
use App\Services\Assistant\AcademicDateService;
use App\Services\Assistant\ChatService;
use App\Services\Assistant\KnowledgeService;
use App\Services\Learning\DocumentService;
use App\Services\Learning\StudyAidService;
use App\Services\Learning\SummaryService;
use App\Services\Platform\NotificationService;
use App\Services\Platform\ReminderService;
use App\Services\Study\SessionFeedbackBuilder;
use Database\Seeders\Demo\DemoContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds a complete, realistic demo semester for one student.
 *
 * Nothing is faked where a real pipeline exists: lecture notes are rendered
 * to PDF/Word/text and uploaded through the extractor and summariser, the
 * knowledge base is indexed and scanned for dates, chatbot answers come from
 * the retrieval engine, and risk comes from the risk model. Only the past
 * (four weeks of study sessions and history) is synthesised.
 *
 *   Login: student@edusmart.lk / password
 */
class DemoSeeder extends Seeder
{
    private User $user;

    /** @var array<string, Module> */
    private array $modules = [];

    /** @var array<string, Document> */
    private array $documents = [];

    /** @var array<string, Assignment> */
    private array $assignments = [];

    public function run(): void
    {
        mt_srand(2026);
        config(['edusmart.firebase.enabled' => false]); // realtime mirror not needed while seeding

        $this->resetDemoUser();
        $this->createUser();
        $this->createModules();
        $this->seedLearning();
        $this->seedKnowledgeBase();
        $this->seedAssignments();
        $this->seedStudyHistory();
        $this->seedPlansAndReminders();
        $this->seedConversations();
        $this->seedCalendar();
        $this->finaliseRisk();
        $this->seedNotifications();
        $this->seedActivityHistory();

        $this->user->settings->update(['auto_summarize' => true]);
        DemoContent::cleanup();

        $this->command?->info('Demo semester seeded for student@edusmart.lk / password');
    }

    /* ------------------------------------------------------------------ */

    private function resetDemoUser(): void
    {
        if ($existing = User::where('email', 'student@edusmart.lk')->first()) {
            foreach (['documents', 'knowledge'] as $area) {
                Storage::disk(config('edusmart.uploads.disk'))->deleteDirectory("{$area}/{$existing->id}");
            }
            $existing->tokens()->delete();
            $existing->delete();
        }
    }

    private function createUser(): void
    {
        $this->user = User::create([
            'name' => 'Sachini Fernando',
            'email' => 'student@edusmart.lk',
            'password' => 'password',
            'student_id' => 'SE/2024/0142',
            'university' => 'Faculty of Computing',
            'program' => 'BSc (Hons) in Software Engineering',
            'academic_year' => 'Year 2 · Semester 1',
            'phone' => '+94 71 234 5678',
            'bio' => 'Second-year software engineering student. Using EDU-SMART to keep notes, deadlines and study time in one place.',
            'email_verified_at' => now()->subDays(30),
            'last_login_at' => now()->subHours(2),
            'last_login_ip' => '127.0.0.1',
        ]);
        $this->user->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();
        $this->user->settings()->create(array_merge(UserSetting::defaults(), [
            'auto_summarize' => false,
            'availability' => ['mon' => 4, 'tue' => 4, 'wed' => 4, 'thu' => 4, 'fri' => 3, 'sat' => 6, 'sun' => 5],
            'daily_goal_minutes' => 150,
            'weekly_goal_minutes' => 900,
            'focus_minutes' => 45,
            'short_break_minutes' => 8,
        ]));
        $this->user->load('settings');
    }

    private function createModules(): void
    {
        $catalogue = [
            'DB' => ['CS2012', 'Database Systems', '#2a78d6', 'database', 'Dr. Nadeesha Perera', 20, 'Relational modelling, SQL, transactions and indexing on Microsoft SQL Server.'],
            'WEB' => ['CS2034', 'Web Application Development', '#eb6834', 'globe2', 'Mr. Ruwan Jayasinghe', 20, 'Front-end and back-end web development, HTTP and REST APIs.'],
            'OOP' => ['CS2045', 'Object-Oriented Programming', '#1baf7a', 'box', 'Dr. Anushka Silva', 20, 'Classes, inheritance, polymorphism and design principles in Java.'],
            'SE' => ['CS2021', 'Software Engineering', '#eda100', 'diagram-3', 'Ms. Dilani Wickramasinghe', 20, 'Process models, requirements, UML and agile delivery.'],
            'RES' => ['CS2050', 'Research Methods', '#e87ba4', 'search', 'Prof. Kumara Bandara', 10, 'Research design, literature reviews and academic writing.'],
        ];
        $order = 0;
        foreach ($catalogue as $key => [$code, $name, $color, $icon, $lecturer, $credits, $description]) {
            $this->modules[$key] = $this->user->modules()->create([
                'code' => $code, 'name' => $name, 'color' => $color, 'icon' => $icon, 'lecturer' => $lecturer,
                'credits' => $credits, 'semester' => 'Semester 1', 'description' => $description, 'sort_order' => $order++,
            ]);
        }
    }

    /* ---------------------------- BETHMI ------------------------------ */

    private function seedLearning(): void
    {
        $service = app(DocumentService::class);
        $library = [
            // key, source, format, module, topic, days ago
            ['normalization', 'learning/database-normalization.md', 'pdf', 'DB', 'Normalization', 20],
            ['joins', 'learning/sql-joins-subqueries.md', 'docx', 'DB', 'SQL queries', 16],
            ['oop05', 'learning/oop-lecture-05-inheritance-polymorphism.md', 'pdf', 'OOP', 'Inheritance', 13],
            ['encapsulation', 'learning/encapsulation-abstraction.md', 'note', 'OOP', 'Encapsulation', 11],
            ['sdlc', 'learning/sdlc-models.md', 'pdf', 'SE', 'Process models', 9],
            ['scrum', 'learning/agile-scrum.md', 'docx', 'SE', 'Agile', 7],
            ['rest', 'learning/http-rest-web-apis.md', 'pdf', 'WEB', 'REST APIs', 5],
            ['litreview', 'learning/literature-review-guide.md', 'txt', 'RES', 'Literature review', 3],
        ];

        foreach ($library as [$key, $source, $format, $module, $topic, $daysAgo]) {
            $markdown = DemoContent::markdown($source);
            $attributes = ['title' => DemoContent::title($markdown), 'topic' => $topic, 'module_id' => $this->modules[$module]->id];

            $document = $format === 'note'
                ? $service->createNote($this->user, $attributes + ['content' => $markdown])
                : $service->upload($this->user, DemoContent::upload($source, $format), $attributes);

            $created = now()->subDays($daysAgo)->setTime(mt_rand(9, 21), mt_rand(0, 59));
            $document->forceFill([
                'created_at' => $created,
                'updated_at' => $created,
                'extracted_at' => $created,
                'last_opened_at' => $daysAgo < 10 ? now()->subDays(mt_rand(0, $daysAgo)) : null,
                'is_favorite' => in_array($key, ['normalization', 'oop05'], true),
            ])->saveQuietly();
            $this->documents[$key] = $document;
        }

        $summaries = app(SummaryService::class);
        $plan = [
            ['normalization', SummaryLength::Short], ['normalization', SummaryLength::Medium], ['normalization', SummaryLength::Detailed],
            ['joins', SummaryLength::Medium], ['oop05', SummaryLength::Short], ['oop05', SummaryLength::Medium],
            ['encapsulation', SummaryLength::Medium], ['sdlc', SummaryLength::Medium], ['scrum', SummaryLength::Short],
            ['rest', SummaryLength::Detailed], ['litreview', SummaryLength::Medium],
        ];
        foreach ($plan as [$key, $length]) {
            $summary = $summaries->generateAndSave($this->documents[$key], $length);
            $when = $this->documents[$key]->created_at->copy()->addMinutes(mt_rand(5, 600));
            $summary->forceFill(['created_at' => $when, 'updated_at' => $when, 'is_favorite' => $key === 'normalization' && $length === SummaryLength::Detailed])->saveQuietly();
        }

        $aids = app(StudyAidService::class);
        foreach ([['normalization', StudyAidType::Flashcards], ['normalization', StudyAidType::Quiz], ['oop05', StudyAidType::Mindmap], ['oop05', StudyAidType::Quiz], ['rest', StudyAidType::Flashcards]] as [$key, $type]) {
            $aid = $aids->generateAndSave($this->documents[$key], $type);
            $when = $this->documents[$key]->created_at->copy()->addDay();
            $aid->forceFill(['created_at' => $when->min(now()), 'updated_at' => $when->min(now())])->saveQuietly();
        }
    }

    /* --------------------------- KAVISHKA ----------------------------- */

    private function seedKnowledgeBase(): void
    {
        $service = app(KnowledgeService::class);
        $sources = [
            ['knowledge/student-handbook.md', 'pdf', KnowledgeCategory::Handbook, null, 14],
            ['knowledge/final-year-project-guidelines.md', 'pdf', KnowledgeCategory::ProjectGuideline, null, 13],
            ['knowledge/examination-regulations.md', 'pdf', KnowledgeCategory::Regulation, null, 12],
            ['knowledge/cs2012-module-handbook.md', 'docx', KnowledgeCategory::ModuleDocument, 'DB', 16],
        ];

        foreach ($sources as [$source, $format, $category, $module, $daysAgo]) {
            $markdown = DemoContent::markdown($source);
            $document = $service->upload($this->user, DemoContent::upload($source, $format), [
                'category' => $category->value,
                'title' => DemoContent::title($markdown),
                'module_id' => $module ? $this->modules[$module]->id : null,
            ]);
            $created = now()->subDays($daysAgo)->setTime(mt_rand(10, 18), mt_rand(0, 59));
            $document->forceFill(['created_at' => $created, 'processed_at' => $created, 'indexed_at' => $created])->saveQuietly();
            AcademicDate::where('knowledge_document_id', $document->id)->update(['created_at' => $created, 'updated_at' => $created]);
        }

        // Review: add upcoming deadlines, exams and milestones to the calendar,
        // leave the rest for the student to review on the Academic dates page.
        $dates = app(AcademicDateService::class);
        $upcoming = $this->user->academicDates()->orderBy('date')->get();
        $added = 0;
        foreach ($upcoming as $date) {
            $important = in_array($date->type, [AcademicDateType::AssignmentDeadline, AcademicDateType::Exam, AcademicDateType::ProjectMilestone], true);
            if ($date->date->isPast()) {
                $date->update(['status' => AcademicDateStatus::Dismissed]);
            } elseif ($important && $added < 7) {
                $dates->addToCalendar($date, $date->type === AcademicDateType::Exam ? 10080 : 1440);
                $added++;
            }
        }
    }

    /* ---------------------------- JITHMI ------------------------------ */

    private function seedAssignments(): void
    {
        $service = app(AssignmentService::class);
        $dates = app(AcademicDateService::class);

        // KAVISHKA → JITHMI: the group project deadline found in the module handbook.
        $projectDate = $this->user->academicDates()->where('type', AcademicDateType::AssignmentDeadline)
            ->where('context', 'like', '%group database project%')->first();
        $projectDeadline = today()->addDays(3)->setTime(23, 59);
        if ($projectDate) {
            $db = $dates->createAssignment($projectDate, [
                'title' => 'Database Project', 'module_id' => $this->modules['DB']->id, 'estimated_hours' => 20,
                'priority' => AssignmentPriority::High, 'weight_percent' => 25, 'type' => 'project',
                'description' => 'Group project: design, implement and document a SQL Server database (ER diagram, normalised schema, T-SQL scripts, stored procedures, 3,000-word report).',
            ]);
        } else {
            $db = $service->create($this->user, ['title' => 'Database Project', 'module_id' => $this->modules['DB']->id, 'deadline' => $projectDeadline,
                'estimated_hours' => 20, 'priority' => AssignmentPriority::High, 'weight_percent' => 25, 'type' => 'project']);
        }
        $this->assignments['db'] = $db;

        $definitions = [
            'web' => ['Web Assignment', 'WEB', today()->addDays(6)->setTime(23, 59), 16, AssignmentPriority::Medium, 30, 'coursework',
                'Build a responsive single-page web app that consumes a REST API, with authentication and form validation.', 8],
            'research' => ['Research Report', 'RES', today()->addDays(12)->setTime(17, 0), 12, AssignmentPriority::Medium, 25, 'report',
                'A 2,500-word literature review on a computing topic of your choice using at least 15 peer-reviewed sources.', 10],
            'oop' => ['OOP Lab Portfolio', 'OOP', today()->addDays(16)->setTime(23, 59), 8, AssignmentPriority::Low, 15, 'lab',
                'Portfolio of six lab exercises on inheritance, interfaces and polymorphism with unit tests.', 7],
            'sprint' => ['Sprint Review Presentation', 'SE', today()->addDays(21)->setTime(10, 0), 6, AssignmentPriority::Medium, 20, 'presentation',
                'Present the sprint backlog, burndown chart and increment demo for the team project.', 4],
            'uml' => ['UML Case Study', 'SE', today()->subDays(2)->setTime(23, 59), 10, AssignmentPriority::High, 20, 'coursework',
                'Use-case, class and sequence diagrams for the library management case study.', 20],
        ];
        foreach ($definitions as $key => [$title, $module, $deadline, $hours, $priority, $weight, $type, $description, $createdDaysAgo]) {
            $this->assignments[$key] = $service->create($this->user, [
                'title' => $title, 'module_id' => $this->modules[$module]->id, 'deadline' => $deadline, 'estimated_hours' => $hours,
                'priority' => $priority, 'weight_percent' => $weight, 'type' => $type, 'description' => $description,
            ]);
            $this->backdate($this->assignments[$key], $createdDaysAgo);
        }
        $this->backdate($db, 16);

        // Completed work (submission history: two on time, one late).
        $completed = [
            ['SQL Query Worksheet', 'DB', today()->subDays(6)->setTime(23, 59), 5, now()->subDays(7)->setTime(20, 15), 18],
            ['Landing Page Prototype', 'WEB', today()->subDays(10)->setTime(23, 59), 8, today()->subDays(9)->setTime(2, 40), 21],
            ['Research Ethics Form', 'RES', today()->subDays(3)->setTime(17, 0), 2, now()->subDays(5)->setTime(11, 5), 14],
        ];
        foreach ($completed as [$title, $module, $deadline, $hours, $submitted, $createdDaysAgo]) {
            $assignment = $service->create($this->user, ['title' => $title, 'module_id' => $this->modules[$module]->id, 'deadline' => $deadline,
                'estimated_hours' => $hours, 'priority' => AssignmentPriority::Medium, 'type' => 'coursework']);
            $this->backdate($assignment, $createdDaysAgo);
            $assignment->forceFill(['completed_hours' => $hours])->saveQuietly();
            $service->complete($assignment, $submitted, 'Submitted on the VLE');
            AssignmentProgressLog::where('assignment_id', $assignment->id)->update(['created_at' => $submitted]);
            $assignment->submissions()->update(['created_at' => $submitted]);
        }
    }

    private function backdate(Assignment $assignment, int $daysAgo): void
    {
        $created = now()->subDays($daysAgo)->setTime(mt_rand(9, 20), mt_rand(0, 59));
        $assignment->forceFill(['created_at' => $created, 'updated_at' => $created])->saveQuietly();
        RiskAssessment::where('assignment_id', $assignment->id)->delete();
    }

    /* ---------------------------- PASINDU ----------------------------- */

    private function seedStudyHistory(): void
    {
        $restDays = [26, 22, 19, 15, 12, 6];
        $moduleWeights = ['DB' => 32, 'WEB' => 22, 'OOP' => 17, 'SE' => 14, 'RES' => 15];
        $moduleDocs = collect($this->documents)->groupBy(fn ($d) => $d->module_id);
        $hoursByAssignment = [];
        $feedback = app(SessionFeedbackBuilder::class);

        for ($daysAgo = 27; $daysAgo >= 0; $daysAgo--) {
            if (in_array($daysAgo, $restDays, true)) {
                continue;
            }
            $day = today()->subDays($daysAgo);
            $count = $day->isWeekend() ? mt_rand(2, 3) : mt_rand(1, 2);
            if ($daysAgo === 0) {
                $count = now()->hour >= 11 ? 1 : 0; // one morning session today
            }

            $usedHours = [];
            for ($i = 0; $i < $count; $i++) {
                $slot = $daysAgo === 0 ? 'morning' : $this->pick(['evening' => 60, 'morning' => 20, 'afternoon' => 20]);
                $hour = match ($slot) {
                    'morning' => mt_rand(8, 9),
                    'afternoon' => mt_rand(14, 16),
                    default => mt_rand(19, 21),
                };
                while (in_array($hour, $usedHours, true)) {
                    $hour++;
                }
                $usedHours[] = $hour;
                $start = $day->copy()->setTime($hour, mt_rand(0, 45));
                if ($start->isFuture()) {
                    continue;
                }

                $moduleKey = $this->pick($moduleWeights);
                $module = $this->modules[$moduleKey];
                $assignment = collect($this->assignments)->first(fn (Assignment $a) => $a->module_id === $module->id
                    && ! $a->isCompleted() && $a->created_at->lessThan($start) && $a->deadline->greaterThan($start));
                $activity = $assignment && mt_rand(1, 100) <= 60
                    ? ($assignment->type === 'project' ? StudyActivity::Project : StudyActivity::Assignment)
                    : $this->pick([StudyActivity::Revision->value => 30, StudyActivity::Reading->value => 25, StudyActivity::Practice->value => 20,
                        StudyActivity::LectureReview->value => 15, StudyActivity::NoteTaking->value => 10], true);
                $linkAssignment = in_array($activity, [StudyActivity::Assignment, StudyActivity::Project], true) ? $assignment : null;
                $document = ! $linkAssignment && mt_rand(1, 100) <= 55
                    ? ($moduleDocs->get($module->id)?->first(fn ($d) => $d->created_at->lessThan($start)))
                    : null;

                $planned = $this->pick(['25' => 20, '45' => 25, '50' => 30, '60' => 15, '90' => 10]);
                $factor = $slot === 'evening' ? mt_rand(85, 118) / 100 : mt_rand(70, 108) / 100;
                $actual = (int) max(12, min(110, round($planned * $factor)));
                $breaks = intdiv($actual, 45);
                $pauses = mt_rand(0, 2);
                $breakSeconds = $breaks * 300;
                $end = $start->copy()->addMinutes($actual)->addSeconds($breakSeconds + $pauses * 90);

                $session = $this->user->studySessions()->create([
                    'module_id' => $module->id,
                    'assignment_id' => $linkAssignment?->id,
                    'document_id' => $document?->id,
                    'activity' => $activity,
                    'goal' => $linkAssignment ? 'Work on '.$linkAssignment->title : ($document ? 'Study '.$document->title : null),
                    'planned_minutes' => (int) $planned,
                    'status' => SessionStatus::Completed,
                    'started_at' => $start,
                    'ended_at' => $end,
                    'focus_seconds' => $actual * 60,
                    'break_seconds' => $breakSeconds,
                    'pause_count' => $pauses,
                    'break_count' => $breaks,
                    'actual_minutes' => $actual,
                    'rating' => mt_rand(3, 5),
                    'mood' => ['great', 'good', 'good', 'okay', 'tired'][mt_rand(0, 4)],
                ]);
                $session->forceFill(['created_at' => $start, 'updated_at' => $end])->saveQuietly();

                $this->seedEngagement($session, $slot, $start, $actual);
                $this->seedSessionEvents($session, $start, $actual, $pauses, $breaks);

                $session->avg_engagement = (int) round(EngagementLog::where('study_session_id', $session->id)->avg('score') ?? 70);
                $session->feedback = $feedback->build($session);
                $session->focus_score = $session->feedback['focus_score'];
                $session->saveQuietly();

                if ($linkAssignment) {
                    $hoursByAssignment[$linkAssignment->id] = ($hoursByAssignment[$linkAssignment->id] ?? 0) + $actual / 60;
                    AssignmentProgressLog::create([
                        'assignment_id' => $linkAssignment->id, 'user_id' => $this->user->id, 'study_session_id' => $session->id,
                        'progress_before' => 0, 'progress_after' => 0, 'hours_added' => round($actual / 60, 2),
                        'completed_hours_after' => round($hoursByAssignment[$linkAssignment->id], 2), 'source' => 'study_session',
                        'note' => $actual.' min '.mb_strtolower($activity->label()).' session',
                    ])->forceFill(['created_at' => $end])->saveQuietly();
                }
            }
        }

        // Final, consistent progress for each active assignment (progress logs replayed in order).
        $targets = ['db' => 42, 'web' => 28, 'research' => 35, 'oop' => 15, 'sprint' => 0, 'uml' => 70];
        foreach ($targets as $key => $progress) {
            $assignment = $this->assignments[$key]->refresh();
            $studied = round($hoursByAssignment[$assignment->id] ?? 0, 2);
            $expectedHours = round((float) $assignment->estimated_hours * $progress / 100 * 0.85, 2);
            if ($progress > 0 && $studied < $expectedHours) {
                // Work done outside tracked sessions (group work, labs).
                AssignmentProgressLog::create([
                    'assignment_id' => $assignment->id, 'user_id' => $this->user->id, 'progress_before' => 0, 'progress_after' => 0,
                    'hours_added' => round($expectedHours - $studied, 2), 'completed_hours_after' => $expectedHours, 'source' => 'manual',
                    'note' => 'Worked on it outside timed sessions',
                ])->forceFill(['created_at' => now()->subDays(mt_rand(3, 9))->setTime(mt_rand(10, 21), 0)])->saveQuietly();
                $studied = $expectedHours;
            }
            $logs = AssignmentProgressLog::where('assignment_id', $assignment->id)->orderBy('created_at')->get();
            $running = 0;
            $steps = max(1, $logs->count());
            foreach ($logs as $index => $log) {
                $after = (int) round($progress * ($index + 1) / $steps);
                $log->forceFill(['progress_before' => $running, 'progress_after' => $after])->saveQuietly();
                $running = $after;
            }
            if ($logs->isEmpty() && $progress > 0) {
                AssignmentProgressLog::create([
                    'assignment_id' => $assignment->id, 'user_id' => $this->user->id, 'progress_before' => 0, 'progress_after' => $progress,
                    'hours_added' => 0, 'completed_hours_after' => 0, 'source' => 'manual', 'note' => 'Initial progress update',
                ])->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();
            }
            $assignment->forceFill([
                'progress' => $progress,
                'completed_hours' => $studied,
                'status' => $progress > 0 ? AssignmentStatus::InProgress : AssignmentStatus::NotStarted,
                'started_at' => $progress > 0 ? $assignment->created_at->copy()->addDays(2) : null,
            ])->saveQuietly();
        }
    }

    private function seedEngagement(StudySession $session, string $slot, Carbon $start, int $actual): void
    {
        $base = match ($slot) {
            'evening' => 84,
            'morning' => 78,
            default => 66,
        };
        $rows = [];
        for ($minute = 5; $minute <= $actual; $minute += 5) {
            $decline = max(0, $minute - 40) * 0.85;
            $score = (int) max(12, min(99, round($base - $decline + mt_rand(-9, 7) - (mt_rand(1, 100) <= 8 ? 25 : 0))));
            $rows[] = [
                'study_session_id' => $session->id, 'user_id' => $this->user->id, 'score' => $score,
                'level' => EngagementLevel::fromScore($score)->value, 'source' => 'auto', 'concentration' => null,
                'signals' => json_encode(['window_seconds' => 300, 'focus_ratio' => round(min(1, $score / 95), 2), 'interactions' => mt_rand(8, 60), 'idle_seconds' => mt_rand(0, 90), 'tab_switches' => $score < 50 ? mt_rand(2, 6) : mt_rand(0, 1)]),
                'note' => null, 'logged_at' => $start->copy()->addMinutes($minute),
            ];
        }
        if (mt_rand(1, 100) <= 35 && $actual > 15) {
            $concentration = mt_rand(3, 5);
            $rows[] = [
                'study_session_id' => $session->id, 'user_id' => $this->user->id, 'score' => [3 => 58, 4 => 78, 5 => 95][$concentration],
                'level' => EngagementLevel::fromScore([3 => 58, 4 => 78, 5 => 95][$concentration])->value, 'source' => 'manual',
                'concentration' => $concentration, 'signals' => null, 'note' => $concentration === 5 ? 'In the zone' : null,
                'logged_at' => $start->copy()->addMinutes(intdiv($actual, 2)),
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            EngagementLog::insert($chunk);
        }
    }

    private function seedSessionEvents(StudySession $session, Carbon $start, int $actual, int $pauses, int $breaks): void
    {
        $events = [['started', $start, ['planned_minutes' => $session->planned_minutes]]];
        for ($b = 1; $b <= $breaks; $b++) {
            $at = $start->copy()->addMinutes($b * 45);
            $events[] = ['break_started', $at, ['minutes' => 5]];
            $events[] = ['break_ended', $at->copy()->addMinutes(5), []];
        }
        for ($p = 1; $p <= $pauses; $p++) {
            $at = $start->copy()->addMinutes(mt_rand(8, max(9, $actual - 5)));
            $events[] = ['paused', $at, []];
            $events[] = ['resumed', $at->copy()->addSeconds(90), []];
        }
        $events[] = ['stopped', $start->copy()->addMinutes($actual), ['focus_minutes' => $actual]];

        StudySessionEvent::insert(array_map(fn ($e) => [
            'study_session_id' => $session->id, 'user_id' => $this->user->id, 'type' => $e[0],
            'payload' => $e[2] ? json_encode($e[2]) : null, 'occurred_at' => $e[1],
        ], $events));
    }

    private function seedPlansAndReminders(): void
    {
        $weekStart = today()->startOfWeek();
        $plans = [
            [0, 'DB', 'db', 120, 'Database Project — stored procedures'], [1, 'WEB', 'web', 90, 'Web Assignment — API integration'],
            [2, 'RES', 'research', 60, 'Read 3 papers for the literature review'], [3, 'DB', 'db', 120, 'Database Project — report'],
            [4, 'OOP', 'oop', 45, 'OOP lab 3 and 4'], [5, 'WEB', 'web', 150, 'Web Assignment — testing'], [6, 'SE', null, 60, 'Revise SDLC models'],
        ];
        foreach ($plans as [$offset, $module, $assignment, $minutes, $title]) {
            $date = $weekStart->copy()->addDays($offset);
            $this->user->studyPlans()->create([
                'plan_date' => $date->toDateString(), 'module_id' => $this->modules[$module]->id,
                'assignment_id' => $assignment ? $this->assignments[$assignment]->id : null,
                'planned_minutes' => $minutes, 'title' => $title, 'is_done' => $date->lessThan(today()),
            ]);
        }

        $this->user->studyReminders()->create(['title' => 'Evening study block', 'message' => 'Your planned evening session starts now — 50 minutes of focus, then a break.',
            'remind_time' => '19:00', 'days' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'is_active' => true]);
        $this->user->studyReminders()->create(['title' => 'Weekend deep work', 'module_id' => $this->modules['DB']->id,
            'message' => 'Two focused hours on the Database Project.', 'remind_time' => '10:00', 'days' => ['sat', 'sun'], 'is_active' => true]);
    }

    /* --------------------------- KAVISHKA chat ------------------------- */

    private function seedConversations(): void
    {
        $chat = app(ChatService::class);
        $threads = [
            [5, ['What is the penalty for late submission?', 'Can I get an extension if I am sick?', 'How long can an extension be?']],
            [3, ['When is the final year project proposal due?', 'How long should the dissertation be?']],
            [1, ['What happens if I arrive late to an exam?', 'What is the minimum attendance requirement?']],
        ];

        foreach ($threads as [$daysAgo, $questions]) {
            $conversation = null;
            $time = now()->subDays($daysAgo)->setTime(mt_rand(13, 21), mt_rand(0, 50));
            foreach ($questions as $question) {
                $result = $chat->ask($this->user, $question, $conversation);
                $conversation = $result['conversation'];
                $result['question']->forceFill(['created_at' => $time, 'updated_at' => $time])->saveQuietly();
                $result['answer']->forceFill(['created_at' => $time->copy()->addSeconds(2), 'updated_at' => $time->copy()->addSeconds(2), 'feedback' => 'helpful'])->saveQuietly();
                $time->addMinutes(mt_rand(1, 4));
            }
            $conversation->forceFill(['created_at' => $time->copy()->subMinutes(10), 'last_message_at' => $time])->saveQuietly();
        }
    }

    private function seedCalendar(): void
    {
        $nextMonday = today()->next(Carbon::MONDAY);
        $events = [
            ['CS2012 Lecture — Indexing & Query Optimisation', EventType::Lecture, $nextMonday->copy()->setTime(9, 0), 120, 'Lecture Hall 3', 'DB', 30],
            ['Web project group meeting', EventType::Event, today()->addDay()->setTime(16, 0), 60, 'Library study room 2', 'WEB', 60],
            ['Research Methods tutorial', EventType::Lecture, today()->addDays(2)->setTime(13, 0), 60, 'Room B204', 'RES', 30],
            ['CS2045 OOP lab — Interfaces', EventType::Lecture, today()->addDays(3)->setTime(10, 0), 120, 'Computer Lab 1', 'OOP', 30],
            ['Personal tutor meeting', EventType::Event, today()->addDays(5)->setTime(11, 30), 30, 'Faculty Office', null, 1440],
        ];
        foreach ($events as [$title, $type, $start, $minutes, $location, $module, $reminder]) {
            $this->user->calendarEvents()->create([
                'title' => $title, 'type' => $type, 'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes($minutes),
                'location' => $location, 'module_id' => $module ? $this->modules[$module]->id : null,
                'color' => $module ? $this->modules[$module]->color : null, 'reminder_minutes' => $reminder,
            ]);
        }
    }

    /* ---------------------- JITHMI risk history ----------------------- */

    private function finaliseRisk(): void
    {
        $risk = app(RiskService::class);
        $current = $risk->recalculate($this->user, 'scheduled');

        // Plausible history: risk drifting toward today's value over two weeks.
        foreach ($this->assignments as $assignment) {
            $assignment->refresh();
            if (! isset($current[$assignment->id])) {
                continue;
            }
            RiskAssessment::where('assignment_id', $assignment->id)->delete();
            $final = $current[$assignment->id]['score'];
            $days = min(14, (int) $assignment->created_at->diffInDays(now()));
            $startScore = max(5, min(95, $final + ($assignment->deadline->isPast() ? -45 : mt_rand(-35, -8))));
            $rows = [];
            for ($d = $days; $d >= 1; $d--) {
                $t = 1 - $d / max(1, $days);
                $score = (int) max(1, min(99, round($startScore + ($final - $startScore) * $t + mt_rand(-4, 4))));
                $rows[] = $this->riskRow($assignment, $score, now()->subDays($d)->setTime(mt_rand(8, 22), mt_rand(0, 59)), $d % 3 === 0 ? 'progress_changed' : 'scheduled', $current[$assignment->id]);
            }
            $rows[] = $this->riskRow($assignment, $final, now(), 'scheduled', $current[$assignment->id]);
            foreach (array_chunk($rows, 50) as $chunk) {
                RiskAssessment::insert($chunk);
            }
        }

        app(ReminderService::class)->dispatch($this->user);
    }

    private function riskRow(Assignment $assignment, int $score, Carbon $at, string $trigger, array $result): array
    {
        return [
            'assignment_id' => $assignment->id, 'user_id' => $this->user->id, 'score' => $score,
            'level' => RiskLevel::fromScore($score)->value, 'probability' => round($score / 100, 4),
            'remaining_hours' => $result['remaining_hours'], 'available_hours' => min(99999, $result['available_hours']),
            'required_hours_per_day' => min(9999, $result['required_hours_per_day']), 'available_hours_per_day' => min(9999, $result['available_hours_per_day']),
            'days_left' => max(-99999, $result['days_left']), 'load_ratio' => min(9999, $result['load_ratio']),
            'factors' => json_encode($result['factors']), 'reasons' => json_encode($result['reasons']),
            'trigger' => $trigger, 'calculated_at' => $at,
        ];
    }

    /* ----------------------- Notifications & audit --------------------- */

    private function seedNotifications(): void
    {
        $notifications = app(NotificationService::class);
        $items = [
            [ModuleKey::Platform, 'Welcome to EDU-SMART, Sachini!', 'Add your modules, upload lecture notes and track your deadlines in one place.', NotificationType::Success, '/modules', 30, true],
            [ModuleKey::Learning, 'Summary ready: Database Normalization', 'A detailed summary with key concepts and revision notes is ready.', NotificationType::Success, '/learning/summaries', 19, true],
            [ModuleKey::Study, 'You are on a study streak!', 'Five days in a row — keep the chain going today.', NotificationType::Info, '/study/analytics', 1, false],
            [ModuleKey::Study, 'Time for your evening study block', 'Your planned evening session starts now — 50 minutes of focus, then a break.', NotificationType::Reminder, '/study', 0, false],
        ];
        foreach ($items as [$module, $title, $message, $type, $url, $daysAgo, $read]) {
            $notification = $notifications->send($this->user, $module, $title, $message, $type, $url);
            $when = now()->subDays($daysAgo)->subMinutes(mt_rand(5, 300));
            $notification?->forceFill(['created_at' => $when, 'updated_at' => $when, 'read_at' => $read ? $when->copy()->addHour() : null])->saveQuietly();
        }

        // Older system notifications (indexing, past completions) are already read.
        $this->user->userNotifications()->where('module', ModuleKey::Assistant)->update(['read_at' => now()->subDays(5), 'created_at' => now()->subDays(12)]);
        $this->user->userNotifications()->where('title', 'like', 'Completed:%')->update(['read_at' => now()->subDays(4), 'created_at' => now()->subDays(6)]);
    }

    private function seedActivityHistory(): void
    {
        ActivityLog::where('user_id', $this->user->id)->delete();
        $rows = [];
        $add = function (Carbon $at, ModuleKey $module, string $action, string $description, ?object $subject = null, string $method = 'POST', int $status = 201) use (&$rows) {
            $rows[] = [
                'user_id' => $this->user->id, 'module' => $module->value, 'action' => $action, 'description' => mb_substr($description, 0, 495),
                'subject_type' => $subject ? class_basename($subject) : null, 'subject_id' => $subject?->getKey(),
                'properties' => null, 'method' => $method, 'route' => null, 'status_code' => $status, 'duration_ms' => mt_rand(18, 420),
                'ip_address' => '127.0.0.1', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/141.0', 'created_at' => $at,
            ];
        };

        for ($d = 28; $d >= 0; $d--) {
            if ($d % 2 === 0 || $d < 6) {
                $add(now()->subDays($d)->setTime(mt_rand(7, 9), mt_rand(0, 59)), ModuleKey::Platform, 'auth.login', 'Signed in from Chrome on Windows', $this->user, 'POST', 200);
            }
        }
        foreach ($this->modules as $module) {
            $add($module->created_at->copy()->subDays(29), ModuleKey::Platform, 'modules.registered', "Registered module {$module->code} — {$module->name}", $module);
        }
        foreach ($this->user->documents()->get() as $document) {
            $add($document->created_at, ModuleKey::Learning, 'documents.uploaded', sprintf('Uploaded “%s” (%s, %d pages, %s words)', $document->title, $document->kind->label(), $document->pages, number_format($document->word_count)), $document);
        }
        foreach ($this->user->summaries()->get() as $summary) {
            $add($summary->created_at, ModuleKey::Learning, 'summaries.generated', "Generated and saved a {$summary->length->label()} summary — “{$summary->title}”", $summary);
        }
        foreach ($this->user->studyAids()->get() as $aid) {
            $add($aid->created_at, ModuleKey::Learning, 'study_aids.generated', "Generated {$aid->type->label()} ({$aid->item_count} items)", $aid);
        }
        foreach ($this->user->knowledgeDocuments()->get() as $doc) {
            $add($doc->created_at, ModuleKey::Assistant, 'knowledge.uploaded', sprintf('Added %s “%s” to the knowledge base (%d passages indexed)', mb_strtolower($doc->category->label()), $doc->title, $doc->chunk_count), $doc);
        }
        foreach ($this->user->academicDates()->where('status', AcademicDateStatus::Added)->get() as $date) {
            $add($date->updated_at->copy()->addMinutes(20), ModuleKey::Assistant, 'academic_dates.added_to_calendar', "Added “{$date->title}” ({$date->date->format('d M Y')}) to the calendar", $date);
        }
        foreach ($this->user->chatMessages()->where('role', 'user')->get() as $message) {
            $add($message->created_at, ModuleKey::Assistant, 'assistant.asked', 'Asked “'.$message->content.'”', null, 'POST', 201);
        }
        foreach ($this->user->assignments()->get() as $assignment) {
            $add($assignment->created_at, ModuleKey::Assignments, 'assignments.created', "Added assignment “{$assignment->title}” due ".$assignment->deadline->format('d M Y H:i'), $assignment);
        }
        foreach (AssignmentProgressLog::where('user_id', $this->user->id)->with('assignment:id,title')->get() as $log) {
            $add($log->created_at, ModuleKey::Assignments, 'assignments.progress_recorded', "Recorded progress on “{$log->assignment?->title}”: {$log->progress_before}% → {$log->progress_after}%", $log->assignment);
        }
        foreach ($this->user->studySessions()->get() as $session) {
            $add($session->started_at, ModuleKey::Study, 'study.session_started', "Started a {$session->planned_minutes}-min ".mb_strtolower($session->activity->label()).' session', $session);
            $add($session->ended_at, ModuleKey::Study, 'study.session_completed', "Completed a study session — {$session->actual_minutes} of {$session->planned_minutes} planned minutes, focus score {$session->focus_score}", $session, 'POST', 200);
        }

        usort($rows, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);
        foreach (array_chunk($rows, 100) as $chunk) {
            ActivityLog::insert($chunk);
        }
    }

    /**
     * Weighted random choice.
     *
     * @param  array<string, int>  $weights
     */
    private function pick(array $weights, bool $activity = false): mixed
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $activity ? StudyActivity::from((string) $value) : $value;
            }
        }

        return array_key_first($weights);
    }
}
