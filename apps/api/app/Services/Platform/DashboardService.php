<?php

namespace App\Services\Platform;

use App\Enums\AcademicDateStatus;
use App\Enums\AssignmentStatus;
use App\Models\AssignmentProgressLog;
use App\Models\User;
use App\Services\Assignments\AssignmentDashboard;
use App\Services\Study\StudyAnalyticsService;

/**
 * Common Platform Layer — the central hub that ties the four module
 * dashboards together, including the live integration flow:
 *
 *   KAVISHKA extracts a deadline → JITHMI tracks + prioritises it →
 *   PASINDU studies it with BETHMI's material → JITHMI's risk updates.
 */
class DashboardService
{
    public function __construct(
        private readonly AssignmentDashboard $assignments,
        private readonly StudyAnalyticsService $study,
        private readonly CalendarService $calendar,
    ) {}

    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $risk = $this->assignments->build($user);
        $streak = $this->study->streaks($user);
        $settings = $user->settingsOrDefault();

        $todayMinutes = (int) $user->studySessions()->completed()->whereDate('started_at', today())->sum('actual_minutes');
        $live = $user->studySessions()->live()->first();
        if ($live) {
            $todayMinutes += (int) floor($live->elapsedFocusSeconds() / 60);
        }
        $weekMinutes = (int) $user->studySessions()->completed()->where('started_at', '>=', today()->startOfWeek())->sum('actual_minutes');

        return [
            'greeting' => $this->greeting(),
            'date' => now()->toIso8601String(),
            'modules' => [
                'learning' => [
                    'documents' => $user->documents()->count(),
                    'summaries' => $user->summaries()->count(),
                    'study_aids' => $user->studyAids()->count(),
                    'latest' => $user->documents()->latest()->first(['id', 'title', 'kind', 'created_at']),
                ],
                'study' => [
                    'today_minutes' => $todayMinutes,
                    'daily_goal' => $settings->daily_goal_minutes,
                    'week_minutes' => $weekMinutes,
                    'weekly_goal' => $settings->weekly_goal_minutes,
                    'streak' => $streak['current'],
                    'live_session_id' => $live?->id,
                ],
                'assistant' => [
                    'documents' => $user->knowledgeDocuments()->count(),
                    'conversations' => $user->conversations()->count(),
                    'questions' => $user->chatMessages()->where('role', 'user')->count(),
                    'pending_dates' => $user->academicDates()->where('status', AcademicDateStatus::Pending)->count(),
                ],
                'assignments' => [
                    'active' => $risk['counts']['active'],
                    'overdue' => $risk['counts']['overdue'],
                    'critical' => $risk['counts']['critical'],
                    'completed' => $risk['counts']['completed'],
                ],
            ],
            'priority' => array_slice($risk['priority'], 0, 4),
            'recommendation' => $risk['recommendation'],
            'workload' => $risk['workload'],
            'upcoming' => $this->calendar->upcoming($user, 14, 6),
            'integration' => $this->integration($user),
            'recent_activity' => $user->activityLogs()->whereNotIn('method', ['GET'])->latest('created_at')->limit(8)->get()
                ->map(fn ($a) => [
                    'id' => $a->id, 'module' => $a->module?->value, 'action' => $a->action,
                    'description' => $a->description, 'created_at' => $a->created_at?->toIso8601String(),
                ]),
            'unread_notifications' => $user->userNotifications()->unread()->count(),
        ];
    }

    /** How data is flowing between the four modules (live counters). */
    private function integration(User $user): array
    {
        return [
            ['from' => 'assistant', 'to' => 'assignments', 'label' => 'Deadlines extracted → assignments',
                'count' => $user->assignments()->whereNotNull('academic_date_id')->count()],
            ['from' => 'assistant', 'to' => 'platform', 'label' => 'Academic dates → calendar',
                'count' => $user->academicDates()->where('status', AcademicDateStatus::Added)->count()],
            ['from' => 'assignments', 'to' => 'study', 'label' => 'Priority tasks studied',
                'count' => $user->studySessions()->whereNotNull('assignment_id')->count()],
            ['from' => 'learning', 'to' => 'study', 'label' => 'Sessions using learning material',
                'count' => $user->studySessions()->whereNotNull('document_id')->count()],
            ['from' => 'study', 'to' => 'assignments', 'label' => 'Hours logged to assignments',
                'count' => round((float) AssignmentProgressLog::query()->where('user_id', $user->id)->where('source', 'study_session')->sum('hours_added'), 1)],
            ['from' => 'assignments', 'to' => 'assignments', 'label' => 'Risk recalculations',
                'count' => $user->riskAssessments()->count()],
        ];
    }

    private function greeting(): string
    {
        return match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }
}
