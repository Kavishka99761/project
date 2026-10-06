<?php

namespace App\Services\Study;

use App\Enums\EngagementLevel;
use App\Models\StudySession;

/**
 * PASINDU — end-of-session feedback: what was achieved, how focused it was,
 * how it compares with the student's usual sessions and what to try next.
 */
class SessionFeedbackBuilder
{
    /**
     * @param  array<string, mixed>|null  $assignmentUpdate
     * @return array<string, mixed>
     */
    public function build(StudySession $session, ?array $assignmentUpdate = null): array
    {
        $actual = $session->actual_minutes;
        $planned = max(1, $session->planned_minutes);
        $completion = (int) round($actual / $planned * 100);
        $engagement = $session->avg_engagement;
        $focusScore = (int) round(0.7 * ($engagement ?? 65) + 0.3 * min(100, $completion));

        $history = $session->user->studySessions()->completed()
            ->whereKeyNot($session->id)
            ->where('started_at', '>=', now()->subDays(30))
            ->get(['actual_minutes', 'avg_engagement', 'focus_score']);
        $avgMinutes = $history->avg('actual_minutes');
        $avgFocus = $history->whereNotNull('focus_score')->avg('focus_score');

        $logs = $session->engagementLogs()->orderBy('logged_at')->get(['score', 'level', 'logged_at']);
        $longestHigh = 0;
        $run = 0;
        foreach ($logs as $log) {
            $run = $log->level === EngagementLevel::High ? $run + 1 : 0;
            $longestHigh = max($longestHigh, $run);
        }

        $tips = [];
        if ($completion < 70) {
            $tips[] = 'You stopped well before your plan — try a shorter planned time next session so it feels achievable.';
        } elseif ($completion > 130) {
            $tips[] = 'You studied much longer than planned. Plan longer sessions, or add breaks to stay fresh.';
        }
        if ($engagement !== null && $engagement < 50) {
            $tips[] = 'Engagement was low. Silence notifications, close unrelated tabs and try the focus mode.';
        }
        if ($session->pause_count >= 3) {
            $tips[] = "You paused {$session->pause_count} times. Batch interruptions into your planned breaks.";
        }
        if ($actual >= 50 && $session->break_count === 0) {
            $tips[] = 'Long stretch without a break — short breaks every 25–50 minutes keep concentration high.';
        }
        if ($tips === []) {
            $tips[] = 'Great rhythm. Keep the same setup for your next session.';
        }

        if ($actual < 5) {
            $focusScore = min($focusScore, 30);
            $tips = ['Very short session. Even 25 focused minutes makes a real difference — try a full Pomodoro next time.'];
        }

        $headline = match (true) {
            $actual < 5 => "Short session recorded — {$actual} focused minute".($actual === 1 ? '' : 's').'.',
            $focusScore >= 80 => "Excellent session — {$actual} focused minutes with strong concentration.",
            $focusScore >= 60 => "Good session — {$actual} focused minutes.",
            default => "Session recorded — {$actual} minutes. Let's aim for better focus next time.",
        };

        return [
            'headline' => $headline,
            'focus_score' => $focusScore,
            'actual_minutes' => $actual,
            'planned_minutes' => $session->planned_minutes,
            'completion_percent' => $completion,
            'break_minutes' => (int) round($session->break_seconds / 60),
            'pauses' => $session->pause_count,
            'breaks' => $session->break_count,
            'average_engagement' => $engagement,
            'engagement_level' => $engagement !== null ? EngagementLevel::fromScore($engagement)->value : null,
            'longest_focused_streak_minutes' => $longestHigh,
            'compared_to_average' => [
                'minutes' => $avgMinutes ? (int) round(($actual - $avgMinutes) / max(1, $avgMinutes) * 100) : null,
                'focus' => $avgFocus ? (int) round($focusScore - $avgFocus) : null,
            ],
            'assignment' => $assignmentUpdate,
            'tips' => $tips,
        ];
    }
}
