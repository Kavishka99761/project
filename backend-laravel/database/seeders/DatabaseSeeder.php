<?php

namespace Database\Seeders;

use App\Models\AcademicChunk;
use App\Models\AcademicDate;
use App\Models\AcademicDocument;
use App\Models\Assignment;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Document;
use App\Models\EngagementLog;
use App\Models\Module;
use App\Models\Notification;
use App\Models\StudySession;
use App\Models\User;
use App\Services\RetrievalService;
use App\Services\RiskCalculator;
use App\Services\SummaryGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds a complete, realistic demo dataset for one student across all four
 * modules. Derived data (summaries, risk snapshots, a grounded chat reply) is
 * produced by the real services so the API and the seeded DB always agree.
 *
 * Login: student@edusmart.lk / password
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reset (safe for a demo/dev database).
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }
        foreach ([
            'notifications', 'chat_messages', 'chat_conversations', 'engagement_logs',
            'study_sessions', 'risk_assessments', 'assignments', 'academic_dates',
            'academic_chunks', 'academic_documents', 'summaries', 'documents',
            'modules', 'personal_access_tokens', 'users',
        ] as $table) {
            DB::table($table)->truncate();
        }
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $summaryGen = app(SummaryGenerator::class);
        $risk = app(RiskCalculator::class);
        $retrieval = app(RetrievalService::class);

        /* ------------------------- User ------------------------- */
        $user = User::create([
            'name'                 => 'Demo Student',
            'email'                => 'student@edusmart.lk',
            'password'             => Hash::make('password'),
            'program'              => 'BSc (Hons) in Information Technology',
            'academic_year'        => 'Year 2 · Semester 1',
            'dark_mode'            => false,
            'daily_target_minutes' => 180,
            'email_verified_at'    => now(),
        ]);

        /* ------------------- Modules (subjects) ------------------ */
        /* Same catalogue, in the same order, as ES.modules in
         * frontend-web/assets/js/mock-data.js and demoModules in
         * mobile-app/src/data/demo.js. The mobile app flips between this API and its
         * bundled offline demo, so the modules it lists must not change when it does.
         * `color` is the hex value behind each layer's accent name
         * (bethmi #3b82f6, pasindu #14b8a6, kavishka #8b5cf6, jithmi #f97316). */
        $subjects = [
            ['code' => 'SE201', 'name' => 'Software Engineering',  'color' => '#3b82f6', 'icon' => 'diagram-3'],
            ['code' => 'DB202', 'name' => 'Database Systems',      'color' => '#f97316', 'icon' => 'database'],
            ['code' => 'PR210', 'name' => 'Programming (OOP)',     'color' => '#14b8a6', 'icon' => 'code-slash'],
            ['code' => 'AI301', 'name' => 'AI & Machine Learning', 'color' => '#8b5cf6', 'icon' => 'cpu'],
        ];
        $modules = collect($subjects)->map(fn ($s) => Module::create($s + ['user_id' => $user->id]));
        [$se, $dbms, $oop, $ai] = $modules;

        /* ------------- KAVISHKA: academic docs + chunks ---------- */
        $handbook = AcademicDocument::create([
            'user_id' => $user->id, 'category' => 'Handbook', 'title' => 'University Student Handbook 2026',
            'pages' => 48, 'is_indexed' => true,
        ]);
        $project = AcademicDocument::create([
            'user_id' => $user->id, 'category' => 'Project', 'title' => 'Final Year Project Guidelines',
            'pages' => 22, 'is_indexed' => true,
        ]);
        $regulation = AcademicDocument::create([
            'user_id' => $user->id, 'category' => 'Regulation', 'title' => 'Examination & Grading Regulations',
            'pages' => 16, 'is_indexed' => true,
        ]);

        $chunks = [
            [$handbook, 'Academic Calendar & Key Dates', 6,
                'The academic year is divided into two semesters. Semester 1 lectures run from January to May, with the examination period in June. Semester 2 runs from July to November with examinations in December. Continuous assessment submissions must be uploaded before the stated deadline; late submissions incur a penalty of 5% of the total marks per day.',
                ['academic calendar', 'semester', 'examination', 'deadline', 'late submission', 'penalty']],
            [$handbook, 'Grading & GPA', 12,
                'Each module is graded on a 4.0 scale. A continuous assessment component contributes 40% and the final examination contributes 60%. The grade point average (GPA) is calculated by weighting each module grade point by its credit value. A student must maintain a minimum GPA of 2.0 to remain in good academic standing.',
                ['grading', 'gpa', 'continuous assessment', 'credit', 'examination', 'standing']],
            [$handbook, 'Attendance Requirement', 9,
                'Students are required to maintain a minimum of 80% attendance for each module to be eligible to sit the final examination. Attendance is recorded per lecture and tutorial session. Students below the threshold must submit a medical or special consideration request to the faculty office.',
                ['attendance', 'examination', 'eligibility', 'medical', 'threshold']],
            [$project, 'Project Proposal', 3,
                'The final year project begins with an individual proposal submitted in the first two weeks of semester 1. The proposal must state the problem, objectives, scope, methodology and expected outcomes. Each student is assigned a supervisor who approves the proposal before development begins.',
                ['project', 'proposal', 'supervisor', 'objectives', 'methodology', 'scope']],
            [$project, 'Project Report & Viva', 15,
                'The final project report must follow the prescribed format: abstract, introduction, literature review, methodology, results, conclusion and references. The report is 10,000 words with a tolerance of plus or minus 10%, excluding references and appendices. It is submitted two weeks before the viva voce examination. Plagiarism above 20% similarity results in rejection of the report.',
                ['report', 'viva', 'plagiarism', 'literature review', 'references', 'submission', 'word count', 'length']],
            [$regulation, 'Examination Rules', 4,
                'Students must arrive fifteen minutes before the examination start time and present a valid student identity card. Electronic devices are not permitted in the examination hall. Any attempt at malpractice results in a zero mark for the paper and referral to the disciplinary board.',
                ['examination', 'rules', 'identity card', 'malpractice', 'disciplinary']],
            [$regulation, 'Special Consideration', 8,
                'A student affected by illness or bereavement may apply for special consideration within seven days of the assessment deadline. Supporting documentation such as a medical certificate must accompany the request. Approved applications may receive an extension or a deferred examination.',
                ['special consideration', 'extension', 'medical certificate', 'deferred', 'deadline']],
        ];
        foreach ($chunks as [$doc, $section, $page, $content, $keywords]) {
            AcademicChunk::create([
                'academic_document_id' => $doc->id, 'section' => $section, 'page' => $page,
                'content' => $content, 'keywords' => $keywords,
            ]);
        }

        /* ---------------- KAVISHKA: academic dates --------------- */
        $dates = [
            ['title' => 'Semester 1 Examinations Begin', 'event_date' => now()->addDays(21)->toDateString(), 'type' => 'Exam', 'reminder' => '3 days before', 'academic_document_id' => $handbook->id],
            ['title' => 'Final Year Project Proposal Due', 'event_date' => now()->addDays(9)->toDateString(), 'type' => 'Deadline', 'reminder' => '1 day before', 'academic_document_id' => $project->id],
            // +3 / +9 line these calendar events up with the assignment deadlines below,
            // so the Jithmi <-> Kavishka link is visible in the UI.
            ['title' => 'Continuous Assessment Submission Closes', 'event_date' => now()->addDays(3)->toDateString(), 'type' => 'Deadline', 'reminder' => '1 day before', 'academic_document_id' => $handbook->id],
            ['title' => 'Software Engineering Group Milestone', 'event_date' => now()->addDays(9)->toDateString(), 'type' => 'Milestone', 'reminder' => '2 days before', 'academic_document_id' => null],
        ];
        $academicDates = collect($dates)->map(fn ($d) => AcademicDate::create($d + ['user_id' => $user->id]));

        /* ------------------ BETHMI: documents -------------------- */
        $docs = [
            [$oop, 'OOP Lec 03 - Inheritance & Polymorphism', 'Inheritance', 'PDF', 14,
                'Inheritance allows a class to reuse the state and behaviour of a parent class. The child class extends the parent and may override inherited methods to provide specialised behaviour. Polymorphism lets a single interface represent different underlying forms, so a method call can resolve to the correct implementation at runtime. Method overriding changes behaviour in the subclass while method overloading provides multiple signatures in the same class. Abstract classes define a contract that concrete subclasses must fulfil. This promotes code reuse and a clear hierarchical design in object-oriented programs.'],
            [$dbms, 'DBMS Lec 05 - Normalization Forms', 'Normalization', 'PDF', 18,
                'Normalization is the process of organising columns and tables of a database to reduce redundancy and improve data integrity. First normal form requires atomic values and no repeating groups. Second normal form removes partial dependencies on a composite key. Third normal form removes transitive dependencies so every non-key column depends only on the primary key. Boyce-Codd normal form is a stricter version of third normal form. Proper normalization minimises update anomalies and produces a clean relational schema, although heavy normalization can add join cost at query time.'],
            [$ai, 'AI Lec 07 - Supervised vs Unsupervised Learning', 'Machine Learning', 'PDF', 11,
                'Supervised learning trains a model on labelled examples so it can predict an outcome for new data, and covers classification tasks such as spam detection as well as regression tasks such as price forecasting. Unsupervised learning finds structure in unlabelled data, using clustering to group similar records and dimensionality reduction to compress features. Overfitting happens when a model memorises the training data instead of learning the underlying pattern, which is why data is split into training, validation and test sets. Feature scaling, cross-validation and a confusion matrix are standard practice when evaluating a classifier.'],
            [$se, 'SE Lec 02 - Agile & Scrum Process', 'Agile', 'PDF', 16,
                'Agile software development emphasises iterative delivery, collaboration and responsiveness to change. Scrum is an agile framework that organises work into time-boxed sprints, typically two to four weeks long. The product owner maintains the product backlog and prioritises items by value. The scrum master facilitates ceremonies and removes impediments for the team. Daily stand-ups, sprint planning, sprint review and retrospective meetings provide structure. Agile methods favour working software over comprehensive documentation and customer collaboration over contract negotiation.'],
            [$oop, 'OOP Tutorial 02 - Interfaces & Abstract', 'Abstraction', 'Text', 6,
                'An interface declares method signatures without implementation and a class that implements it must provide every method. Interfaces support multiple inheritance of type and enable loose coupling between components. An abstract class can contain both abstract methods and concrete methods with shared state. Use an interface to define a capability across unrelated classes and an abstract class to share common behaviour within a hierarchy. Dependency injection relies on programming to an interface rather than a concrete class.'],
            [$dbms, 'DBMS Lec 09 - Indexing & Query Optimisation', 'Indexing', 'PDF', 20,
                'An index is a data structure that improves the speed of data retrieval operations on a database table at the cost of additional storage and slower writes. A B-tree index supports equality and range queries and keeps keys sorted. A hash index supports only equality lookups but is very fast for them. The query optimizer chooses an execution plan by estimating the cost of using available indexes versus a full table scan. Composite indexes follow the leftmost prefix rule. Proper indexing is essential for performance on large tables and should be guided by the actual query workload.'],
        ];
        $documents = collect($docs)->map(function ($d) use ($user) {
            /** @var Module $module */
            [$module, $title, $topic, $type, $pages, $text] = $d;

            return Document::create([
                'user_id' => $user->id, 'module_id' => $module->id, 'title' => $title,
                'topic' => $topic, 'type' => $type, 'pages' => $pages,
                'size_bytes' => random_int(120, 900) * 1024, 'file_path' => null,
                'extracted_text' => $text,
                'created_at' => now()->subDays(random_int(1, 20)),
            ]);
        });

        // Generate real summaries from the extracted text (Bethmi service).
        $documents->take(3)->each(function (Document $doc) use ($user, $summaryGen) {
            $lengths = ['Short', 'Medium', 'Detailed'];
            foreach (array_slice($lengths, 0, 2) as $len) {
                $r = $summaryGen->generate($doc->extracted_text, $len, $doc->title.' — '.$len.' summary');
                $user->summaries()->create([
                    'document_id' => $doc->id, 'title' => $r['title'], 'length_type' => $r['length_type'],
                    'body' => $r['body'], 'keywords' => $r['keywords'],
                ]);
            }
        });

        /* ----------------- JITHMI: assignments ------------------- */
        /* Titles, modules and inputs are the SAME four rows the web (mock-data.js)
         * and mobile (demo.js) datasets render, tuned so RiskCalculator yields the
         * full spread — Critical 90, High 55, Medium 40, Low 14. A demo that only
         * ever shows one level hides the entire point of the module, and matching
         * the titles matters because the mobile app flips between this API and its
         * offline bundle — different names would make that flip visible.
         * The fifth row is seeded-only and exercises the days = 0 / completed path.
         * Arithmetic per row:
         *   score = (remaining/days)/5*60 + (100-progress)*0.4   (DAILY_CAPACITY=5) */
        $assignmentDefs = [
            // 15h left / 3 days = 5.0h/day -> 60 + 30.0 = 90 -> Critical
            [$dbms, 'Database Project', now()->addDays(3)->toDateString(), 'High', 20.0, 5.0, 25, $academicDates[2]->id],
            // 10h left / 4 days = 2.5h/day -> 30 + 24.8 = 55 -> High
            [$oop, 'Web Assignment', now()->addDays(4)->toDateString(), 'High', 16.0, 6.0, 38, null],
            // 10h left / 9 days = 1.1h/day -> 13.3 + 26.8 = 40 -> Medium
            [$se, 'Research Report', now()->addDays(9)->toDateString(), 'Medium', 15.0, 5.0, 33, $academicDates[3]->id],
            // 4h left / 20 days = 0.2h/day -> 2.4 + 12.0 = 14 -> Low
            [$ai, 'ML Mini Project', now()->addDays(20)->toDateString(), 'Low', 12.0, 8.0, 70, null],
            // Overdue but fully submitted -> days = 0, no work left -> 0 (excluded from ranking)
            [$oop, 'Programming Practice Set', now()->subDays(2)->toDateString(), 'Medium', 5.0, 5.0, 100, null],
        ];
        $assignments = collect($assignmentDefs)->map(function ($a) use ($user) {
            /** @var Module $module */
            [$module, $title, $deadline, $priority, $est, $done, $progress, $academicDateId] = $a;

            return Assignment::create([
                'user_id' => $user->id, 'module_id' => $module->id, 'academic_date_id' => $academicDateId,
                'title' => $title, 'deadline' => $deadline, 'priority' => $priority,
                'est_hours' => $est, 'done_hours' => $done, 'progress' => $progress,
                'completed' => $progress >= 100,
            ]);
        });

        // Persist a real risk snapshot for each assignment (Jithmi service).
        $assignments->each(fn (Assignment $a) => $risk->recalculate($a));

        /* -------------- PASINDU: study sessions ------------------ */
        for ($i = 6; $i >= 1; $i--) {
            $day = now()->subDays($i)->setTime(random_int(8, 19), 0);
            $planned = [25, 45, 50, 30, 60][random_int(0, 4)];
            $actual = (int) round($planned * (random_int(70, 110) / 100));
            $session = StudySession::create([
                'user_id' => $user->id, 'module_id' => $modules->random()->id,
                'assignment_id' => null, 'document_id' => $documents->random()->id,
                'activity' => ['Revision', 'Practice', 'Reading', 'Assignment'][random_int(0, 3)],
                'planned_minutes' => $planned, 'actual_minutes' => $actual, 'status' => 'completed',
                'started_at' => $day, 'ended_at' => $day->copy()->addMinutes($actual),
            ]);
            EngagementLog::create([
                'user_id' => $user->id, 'study_session_id' => $session->id,
                'level' => ['Low', 'Moderate', 'Good'][random_int(0, 2)], 'percent' => random_int(55, 96),
                'source' => 'auto', 'logged_at' => $day->copy()->addMinutes((int) round($actual / 2)),
            ]);
        }
        // Today's completed session linked to the most urgent assignment.
        $urgent = $assignments->where('completed', false)->sortBy('deadline')->first();
        if ($urgent) {
            $todaySession = StudySession::create([
                'user_id' => $user->id, 'module_id' => $urgent->module_id,
                'assignment_id' => $urgent->id, 'document_id' => $documents->first()->id,
                'activity' => 'Assignment', 'planned_minutes' => 45, 'actual_minutes' => 40, 'status' => 'completed',
                'started_at' => now()->subHours(2), 'ended_at' => now()->subHours(1),
            ]);
            EngagementLog::create([
                'user_id' => $user->id, 'study_session_id' => $todaySession->id,
                'level' => 'Good', 'percent' => 88, 'source' => 'manual', 'logged_at' => now()->subHour(),
            ]);
        }

        /* ----------- KAVISHKA: a grounded chat conversation ------ */
        $conversation = ChatConversation::create([
            'user_id' => $user->id, 'title' => 'When do semester exams start?',
        ]);
        $question = 'When do the semester 1 examinations start and what is the attendance requirement?';
        ChatMessage::create([
            'conversation_id' => $conversation->id, 'role' => 'user', 'content' => $question, 'created_at' => now()->subMinutes(10),
        ]);
        $allChunks = AcademicChunk::with('document')->get();
        $result = $retrieval->answer($question, $allChunks);
        ChatMessage::create([
            'conversation_id' => $conversation->id, 'role' => 'bot', 'content' => $result['answer'],
            'source_chunk_id' => $result['chunk']?->id, 'created_at' => now()->subMinutes(9),
        ]);

        /* --------------- Common: notifications ------------------- */
        $notes = [
            ['jithmi', 'Critical risk: Database Project', 'Due in 3 days and only 25% complete. Start a focus session today.'],
            ['kavishka', 'New date extracted', 'Continuous Assessment Submission Closes was added to your calendar.'],
            ['pasindu', 'Daily target reached', 'You studied 150 minutes today — great consistency!'],
            ['bethmi', 'Summary ready', 'A new summary of AI Lec 07 - Supervised vs Unsupervised Learning is available.'],
        ];
        foreach ($notes as $i => [$source, $title, $message]) {
            Notification::create([
                'user_id' => $user->id, 'module_source' => $source, 'title' => $title,
                'message' => $message, 'is_read' => $i > 1, 'created_at' => now()->subMinutes(($i + 1) * 30),
            ]);
        }

        $this->command?->info('EDU-SMART demo data seeded. Login: student@edusmart.lk / password');
    }
}
