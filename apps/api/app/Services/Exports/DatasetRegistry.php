<?php

namespace App\Services\Exports;

use App\Enums\ModuleKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Every dataset the student can download, with its columns. Column values
 * are closures so formats share one definition: PDF, Excel and CSV render
 * the columns; JSON adds the richer `json` payload where one is defined.
 */
class DatasetRegistry
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $date = fn ($value) => $value?->format('Y-m-d H:i');
        $day = fn ($value) => $value?->format('Y-m-d');

        return [
            /* ---------------- Platform ---------------- */
            'profile' => [
                'label' => 'Profile & settings', 'module' => ModuleKey::Platform, 'date_column' => null,
                'query' => fn (User $u) => $u->newQuery()->whereKey($u->id)->with('settings'),
                'columns' => [
                    'Name' => fn ($r) => $r->name, 'Email' => fn ($r) => $r->email, 'Student ID' => fn ($r) => $r->student_id,
                    'University' => fn ($r) => $r->university, 'Programme' => fn ($r) => $r->program, 'Academic year' => fn ($r) => $r->academic_year,
                    'Phone' => fn ($r) => $r->phone, 'Daily goal (min)' => fn ($r) => $r->settings?->daily_goal_minutes,
                    'Weekly goal (min)' => fn ($r) => $r->settings?->weekly_goal_minutes, 'Theme' => fn ($r) => $r->settings?->theme,
                    'Member since' => fn ($r) => $date($r->created_at),
                ],
                'json' => fn ($r) => $r->only(['id', 'name', 'email', 'student_id', 'university', 'program', 'academic_year', 'phone', 'bio', 'created_at']) + ['settings' => $r->settings?->toArray()],
            ],
            'modules' => [
                'label' => 'Modules', 'module' => ModuleKey::Platform, 'date_column' => 'created_at',
                'query' => fn (User $u) => $u->modules()->orderBy('code'),
                'columns' => [
                    'Code' => fn ($r) => $r->code, 'Name' => fn ($r) => $r->name, 'Credits' => fn ($r) => $r->credits,
                    'Semester' => fn ($r) => $r->semester, 'Lecturer' => fn ($r) => $r->lecturer, 'Active' => fn ($r) => $r->is_active ? 'Yes' : 'No',
                ],
            ],
            'notifications' => [
                'label' => 'Notifications', 'module' => ModuleKey::Platform, 'date_column' => 'created_at',
                'query' => fn (User $u) => $u->userNotifications()->latest(),
                'columns' => [
                    'Date' => fn ($r) => $date($r->created_at), 'Module' => fn ($r) => $r->module?->label(), 'Type' => fn ($r) => $r->type?->label(),
                    'Title' => fn ($r) => $r->title, 'Message' => fn ($r) => $r->message, 'Read' => fn ($r) => $r->read_at ? 'Yes' : 'No',
                ],
            ],
            'activity' => [
                'label' => 'Activity log (audit trail)', 'module' => ModuleKey::Platform, 'date_column' => 'created_at',
                'query' => fn (User $u) => $u->activityLogs()->latest('created_at'),
                'columns' => [
                    'Date' => fn ($r) => $date($r->created_at), 'Module' => fn ($r) => $r->module?->label(), 'Action' => fn ($r) => $r->action,
                    'Description' => fn ($r) => $r->description, 'Method' => fn ($r) => $r->method, 'Status' => fn ($r) => $r->status_code,
                    'Duration (ms)' => fn ($r) => $r->duration_ms, 'IP address' => fn ($r) => $r->ip_address,
                ],
                'json' => fn ($r) => $r->toArray(),
            ],
            'calendar' => [
                'label' => 'Calendar events', 'module' => ModuleKey::Platform, 'date_column' => 'starts_at',
                'query' => fn (User $u) => $u->calendarEvents()->with('module:id,code')->orderBy('starts_at'),
                'columns' => [
                    'Title' => fn ($r) => $r->title, 'Type' => fn ($r) => $r->type->label(), 'Starts' => fn ($r) => $date($r->starts_at),
                    'Ends' => fn ($r) => $date($r->ends_at), 'All day' => fn ($r) => $r->all_day ? 'Yes' : 'No', 'Module' => fn ($r) => $r->module?->code,
                    'Location' => fn ($r) => $r->location, 'Reminder (min)' => fn ($r) => $r->reminder_minutes, 'Source' => fn ($r) => $r->source,
                ],
            ],
            'searches' => [
                'label' => 'Search history', 'module' => ModuleKey::Platform, 'date_column' => 'created_at',
                'query' => fn (User $u) => $u->searchHistory()->latest('created_at'),
                'columns' => ['Date' => fn ($r) => $date($r->created_at), 'Query' => fn ($r) => $r->query, 'Scope' => fn ($r) => $r->scope, 'Results' => fn ($r) => $r->results_count],
            ],
            'exports' => [
                'label' => 'Export history', 'module' => ModuleKey::Platform, 'date_column' => 'created_at',
                'query' => fn (User $u) => $u->exportLogs()->latest('created_at'),
                'columns' => ['Date' => fn ($r) => $date($r->created_at), 'Dataset' => fn ($r) => $r->dataset, 'Format' => fn ($r) => strtoupper($r->format), 'Rows' => fn ($r) => $r->row_count, 'File' => fn ($r) => $r->file_name],
            ],

            /* ---------------- Bethmi · Learning ---------------- */
            'documents' => [
                'label' => 'Learning documents', 'module' => ModuleKey::Learning, 'date_column' => 'created_at',
                'query' => fn (User $u) => $u->documents()->with('module:id,code')->latest(),
                'columns' => [
                    'Title' => fn ($r) => $r->title, 'Module' => fn ($r) => $r->module?->code, 'Topic' => fn ($r) => $r->topic,
                    'Type' => fn ($r) => $r->kind->label(), 'File' => fn ($r) => $r->original_name, 'Pages' => fn ($r) => $r->pages,
                    'Words' => fn ($r) => $r->word_count, 'Size (KB)' => fn ($r) => round($r->size_bytes / 1024, 1),
                    'Extraction' => fn ($r) => $r->extraction_status->label(), 'Keywords' => fn ($r) => implode(', ', array_slice(array_column((array) $r->keywords, 'term'), 0, 6)),
                    'Uploaded' => fn ($r) => $date($r->created_at),
                ],
            ],
            'summaries' => [
                'label' => 'Saved summaries', 'module' => ModuleKey::Learning, 'date_column' => 'created_at',
                'query' => fn (User $u) => $u->summaries()->with('document:id,title')->latest(),
                'columns' => [
                    'Title' => fn ($r) => $r->title, 'Document' => fn ($r) => $r->document?->title, 'Length' => fn ($r) => $r->length->label(),
                    'Words' => fn ($r) => $r->word_count, 'Summary' => fn ($r) => $r->content,
                    'Keywords' => fn ($r) => implode(', ', array_column((array) $r->keywords, 'term')), 'Created' => fn ($r) => $date($r->created_at),
                ],
                'json' => fn ($r) => $r->only(['id', 'title', 'length', 'method', 'content', 'bullet_points', 'keywords', 'key_concepts', 'highlights', 'word_count', 'created_at'])
                    + ['document' => $r->document?->title],
            ],
            'study_aids' => [
                'label' => 'Flashcards, quizzes & mind maps', 'module' => ModuleKey::Learning, 'date_column' => 'created_at',
                'query' => fn (User $u) => $u->studyAids()->with('document:id,title')->latest(),
                'columns' => ['Title' => fn ($r) => $r->title, 'Type' => fn ($r) => $r->type->label(), 'Items' => fn ($r) => $r->item_count, 'Document' => fn ($r) => $r->document?->title, 'Created' => fn ($r) => $date($r->created_at)],
                'json' => fn ($r) => $r->only(['id', 'title', 'type', 'content', 'item_count', 'created_at']),
            ],

            /* ---------------- Pasindu · Study ---------------- */
            'study_sessions' => [
                'label' => 'Study sessions', 'module' => ModuleKey::Study, 'date_column' => 'started_at',
                'query' => fn (User $u) => $u->studySessions()->with(['module:id,code', 'assignment:id,title'])->latest('started_at'),
                'columns' => [
                    'Started' => fn ($r) => $date($r->started_at), 'Ended' => fn ($r) => $date($r->ended_at), 'Module' => fn ($r) => $r->module?->code,
                    'Activity' => fn ($r) => $r->activity->label(), 'Goal' => fn ($r) => $r->goal, 'Planned (min)' => fn ($r) => $r->planned_minutes,
                    'Actual (min)' => fn ($r) => $r->actual_minutes, 'Breaks (min)' => fn ($r) => (int) round($r->break_seconds / 60),
                    'Pauses' => fn ($r) => $r->pause_count, 'Engagement %' => fn ($r) => $r->avg_engagement, 'Focus score' => fn ($r) => $r->focus_score,
                    'Assignment' => fn ($r) => $r->assignment?->title, 'Status' => fn ($r) => $r->status->label(),
                ],
            ],
            'engagement' => [
                'label' => 'Engagement history', 'module' => ModuleKey::Study, 'date_column' => 'logged_at',
                'query' => fn (User $u) => $u->engagementLogs()->latest('logged_at'),
                'columns' => [
                    'Time' => fn ($r) => $date($r->logged_at), 'Session' => fn ($r) => $r->study_session_id, 'Score' => fn ($r) => $r->score,
                    'Level' => fn ($r) => $r->level->label(), 'Source' => fn ($r) => ucfirst($r->source), 'Concentration (1–5)' => fn ($r) => $r->concentration, 'Note' => fn ($r) => $r->note,
                ],
            ],
            'study_plans' => [
                'label' => 'Study plans', 'module' => ModuleKey::Study, 'date_column' => 'plan_date',
                'query' => fn (User $u) => $u->studyPlans()->with('module:id,code')->orderByDesc('plan_date'),
                'columns' => ['Date' => fn ($r) => $day($r->plan_date), 'Title' => fn ($r) => $r->title, 'Module' => fn ($r) => $r->module?->code, 'Planned (min)' => fn ($r) => $r->planned_minutes, 'Done' => fn ($r) => $r->is_done ? 'Yes' : 'No'],
            ],
            'study_reminders' => [
                'label' => 'Study reminders', 'module' => ModuleKey::Study, 'date_column' => null,
                'query' => fn (User $u) => $u->studyReminders()->orderBy('remind_time'),
                'columns' => ['Title' => fn ($r) => $r->title, 'Time' => fn ($r) => substr((string) $r->remind_time, 0, 5), 'Days' => fn ($r) => implode(', ', (array) $r->days), 'Active' => fn ($r) => $r->is_active ? 'Yes' : 'No'],
            ],

            /* ---------------- Kavishka · Assistant ---------------- */
            'knowledge' => [
                'label' => 'Knowledge base documents', 'module' => ModuleKey::Assistant, 'date_column' => 'created_at',
                'query' => fn (User $u) => $u->knowledgeDocuments()->latest(),
                'columns' => [
                    'Title' => fn ($r) => $r->title, 'Category' => fn ($r) => $r->category->label(), 'File' => fn ($r) => $r->original_name,
                    'Pages' => fn ($r) => $r->pages, 'Passages' => fn ($r) => $r->chunk_count, 'Status' => fn ($r) => $r->status->label(),
                    'Indexed' => fn ($r) => $date($r->indexed_at), 'Uploaded' => fn ($r) => $date($r->created_at),
                ],
            ],
            'chat_history' => [
                'label' => 'Chatbot conversation history', 'module' => ModuleKey::Assistant, 'date_column' => 'created_at',
                'query' => fn (User $u) => $u->chatMessages()->with('conversation:id,title')->orderBy('chat_conversation_id')->orderBy('created_at'),
                'columns' => [
                    'Conversation' => fn ($r) => $r->conversation?->title, 'Time' => fn ($r) => $date($r->created_at), 'Role' => fn ($r) => ucfirst($r->role),
                    'Message' => fn ($r) => $r->content,
                    'Sources' => fn ($r) => implode('; ', array_map(fn ($s) => '['.$s['n'].'] '.$s['document'].($s['page'] ? ' p.'.$s['page'] : ''), array_filter((array) $r->sources, fn ($s) => $s['cited'] ?? true))),
                    'Confidence' => fn ($r) => $r->confidence !== null ? round($r->confidence * 100).'%' : null,
                ],
                'json' => fn ($r) => $r->only(['id', 'chat_conversation_id', 'role', 'content', 'sources', 'confidence', 'created_at']) + ['conversation' => $r->conversation?->title],
            ],
            'academic_dates' => [
                'label' => 'Extracted academic dates', 'module' => ModuleKey::Assistant, 'date_column' => 'date',
                'query' => fn (User $u) => $u->academicDates()->with('document:id,title')->orderBy('date'),
                'columns' => [
                    'Date' => fn ($r) => $day($r->date), 'Time' => fn ($r) => $r->time ? substr((string) $r->time, 0, 5) : null, 'Title' => fn ($r) => $r->title,
                    'Type' => fn ($r) => $r->type->label(), 'Status' => fn ($r) => $r->status->label(), 'Confidence' => fn ($r) => round($r->confidence * 100).'%',
                    'Source' => fn ($r) => $r->document?->title, 'Page' => fn ($r) => $r->page_number, 'Context' => fn ($r) => $r->context,
                ],
            ],

            /* ---------------- Jithmi · Assignments ---------------- */
            'assignments' => [
                'label' => 'Assignments', 'module' => ModuleKey::Assignments, 'date_column' => 'deadline',
                'query' => fn (User $u) => $u->assignments()->with('module:id,code')->orderBy('deadline'),
                'columns' => [
                    'Title' => fn ($r) => $r->title, 'Module' => fn ($r) => $r->module?->code, 'Deadline' => fn ($r) => $date($r->deadline),
                    'Priority' => fn ($r) => $r->priority->label(), 'Estimated (h)' => fn ($r) => $r->estimated_hours, 'Done (h)' => fn ($r) => $r->completed_hours,
                    'Progress %' => fn ($r) => $r->progress, 'Status' => fn ($r) => $r->status->label(), 'Risk %' => fn ($r) => $r->risk_score,
                    'Risk level' => fn ($r) => $r->risk_level?->label(), 'Rank' => fn ($r) => $r->priority_rank, 'Was overdue' => fn ($r) => $r->was_overdue ? 'Yes' : 'No',
                    'Completed' => fn ($r) => $date($r->completed_at),
                ],
            ],
            'progress_logs' => [
                'label' => 'Assignment progress history', 'module' => ModuleKey::Assignments, 'date_column' => 'created_at',
                'query' => fn (User $u) => \App\Models\AssignmentProgressLog::query()->where('user_id', $u->id)->with('assignment:id,title')->latest('created_at'),
                'columns' => [
                    'Date' => fn ($r) => $date($r->created_at), 'Assignment' => fn ($r) => $r->assignment?->title, 'Progress before' => fn ($r) => $r->progress_before,
                    'Progress after' => fn ($r) => $r->progress_after, 'Hours added' => fn ($r) => $r->hours_added, 'Source' => fn ($r) => str_replace('_', ' ', $r->source), 'Note' => fn ($r) => $r->note,
                ],
            ],
            'risk_history' => [
                'label' => 'Risk assessment history', 'module' => ModuleKey::Assignments, 'date_column' => 'calculated_at',
                'query' => fn (User $u) => $u->riskAssessments()->with('assignment:id,title')->latest('calculated_at'),
                'columns' => [
                    'Calculated' => fn ($r) => $date($r->calculated_at), 'Assignment' => fn ($r) => $r->assignment?->title, 'Risk %' => fn ($r) => $r->score,
                    'Level' => fn ($r) => $r->level->label(), 'Remaining (h)' => fn ($r) => $r->remaining_hours, 'Available (h)' => fn ($r) => $r->available_hours,
                    'Load ratio' => fn ($r) => $r->load_ratio, 'Trigger' => fn ($r) => str_replace('_', ' ', $r->trigger),
                    'Top reason' => fn ($r) => $r->reasons[0]['text'] ?? null,
                ],
            ],
            'submissions' => [
                'label' => 'Submission history', 'module' => ModuleKey::Assignments, 'date_column' => 'submitted_at',
                'query' => fn (User $u) => \App\Models\AssignmentSubmission::query()->where('user_id', $u->id)->with('assignment:id,title')->latest('submitted_at'),
                'columns' => [
                    'Submitted' => fn ($r) => $date($r->submitted_at), 'Assignment' => fn ($r) => $r->assignment?->title, 'Deadline' => fn ($r) => $date($r->deadline_at),
                    'On time' => fn ($r) => $r->is_late ? 'Late' : 'On time', 'Minutes late' => fn ($r) => $r->minutes_late ?: null, 'Note' => fn ($r) => $r->note,
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function query(string $key, User $user, ?string $from = null, ?string $to = null): Builder|Relation
    {
        $dataset = $this->get($key) ?? throw new \InvalidArgumentException("Unknown dataset [{$key}].");
        $query = ($dataset['query'])($user);
        if ($dataset['date_column'] && $from) {
            $query->where($dataset['date_column'], '>=', $from);
        }
        if ($dataset['date_column'] && $to) {
            $query->where($dataset['date_column'], '<=', $to.' 23:59:59');
        }

        return $query;
    }
}
