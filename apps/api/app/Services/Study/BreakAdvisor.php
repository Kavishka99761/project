<?php

namespace App\Services\Study;

use App\Enums\EngagementLevel;
use App\Enums\SessionStatus;
use App\Models\StudySession;
use App\Models\User;

/**
 * PASINDU — break management.
 *
 *  • Break recommendations from continuous focus time (Pomodoro-style cycles
 *    with a long break every N cycles) and from falling engagement.
 *  • Recommended focused-study period, personalised from the student's own
 *    history: how long their engagement typically stays high.
 */
class BreakAdvisor
{
    /**
     * @return array{recommend_break: bool, urgency: string, minutes: int, kind: string, message: string, continuous_focus_minutes: int, next_break_in_minutes: int, focus_period: array<string, mixed>}
     */
    public function advise(StudySession $session): array
    {
        $settings = $session->user->settingsOrDefault();
        $focusPeriod = $this->focusPeriod($session->user);
        $focusLength = $focusPeriod['minutes'];

        // Continuous focus since the last pause/break.
        $continuous = $session->status === SessionStatus::Active && $session->last_resumed_at
            ? (int) floor($session->last_resumed_at->diffInSeconds(now()) / 60)
            : 0;
        $cyclesDone = $session->break_count;
        $longBreakDue = ($cyclesDone + 1) % max(1, $settings->sessions_before_long_break) === 0;
        $breakMinutes = $longBreakDue ? $settings->long_break_minutes : $settings->short_break_minutes;
        $kind = $longBreakDue ? 'long' : 'short';

        $recent = $session->engagementLogs()->latest('logged_at')->limit(2)->pluck('level');
        $lowStreak = $recent->count() === 2 && $recent->every(fn ($l) => $l === EngagementLevel::Low);

        if ($session->status !== SessionStatus::Active) {
            return $this->result(false, 'none', $breakMinutes, $kind, $session->status === SessionStatus::OnBreak
                ? 'Enjoy your break — stretch, hydrate and look away from the screen.'
                : 'Session paused. Resume when you are ready.', $continuous, $focusLength, $focusPeriod);
        }
        if ($lowStreak) {
            return $this->result(true, 'high', max(5, $breakMinutes), $kind,
                'Your engagement has been low for two checks in a row. A short break now will help you refocus.',
                $continuous, $focusLength, $focusPeriod);
        }
        if ($continuous >= $focusLength) {
            return $this->result(true, $continuous >= $focusLength + 15 ? 'high' : 'medium', $breakMinutes, $kind,
                sprintf('You have focused for %d minutes. Take a %d-minute %s break.', $continuous, $breakMinutes, $kind),
                $continuous, $focusLength, $focusPeriod);
        }
        if ($session->elapsedFocusSeconds() >= $session->planned_minutes * 60) {
            return $this->result(false, 'info', $breakMinutes, $kind,
                'You reached your planned study time. Wrap up, or keep going if you are in the flow.',
                $continuous, $focusLength, $focusPeriod);
        }

        return $this->result(false, 'none', $breakMinutes, $kind,
            sprintf('Stay focused — next break in about %d minutes.', max(1, $focusLength - $continuous)),
            $continuous, $focusLength, $focusPeriod);
    }

    /**
     * Personal optimal focus length: the median minute at which engagement
     * first drops below "high" in past sessions, bounded to 20–90 minutes.
     *
     * @return array{minutes: int, break_minutes: int, source: string, explanation: string}
     */
    public function focusPeriod(User $user): array
    {
        $settings = $user->settingsOrDefault();
        $sessions = $user->studySessions()->completed()
            ->where('started_at', '>=', now()->subDays(60))
            ->where('actual_minutes', '>=', 15)
            ->with(['engagementLogs' => fn ($q) => $q->where('source', 'auto')->orderBy('logged_at')])
            ->latest('started_at')
            ->limit(40)
            ->get();

        $drops = [];
        foreach ($sessions as $session) {
            foreach ($session->engagementLogs as $log) {
                if ($log->score < config('edusmart.study.engagement.high')) {
                    $drops[] = max(5, (int) $session->started_at->diffInMinutes($log->logged_at));
                    break;
                }
            }
        }

        if (count($drops) < 4) {
            return [
                'minutes' => $settings->focus_minutes,
                'break_minutes' => $settings->short_break_minutes,
                'source' => 'settings',
                'explanation' => "Using your {$settings->focus_minutes}/{$settings->short_break_minutes} focus rhythm. Complete a few more tracked sessions for a personalised recommendation.",
            ];
        }

        sort($drops);
        $median = $drops[(int) floor(count($drops) / 2)];
        $minutes = (int) max(20, min(90, round($median / 5) * 5));
        $break = $minutes >= 60 ? 15 : ($minutes >= 40 ? 10 : 5);

        return [
            'minutes' => $minutes,
            'break_minutes' => $break,
            'source' => 'history',
            'explanation' => "Your focus typically stays high for about {$median} minutes, so {$minutes}-minute focus blocks with {$break}-minute breaks suit you best.",
        ];
    }

    private function result(bool $recommend, string $urgency, int $minutes, string $kind, string $message, int $continuous, int $focusLength, array $focusPeriod): array
    {
        return [
            'recommend_break' => $recommend,
            'urgency' => $urgency,
            'minutes' => $minutes,
            'kind' => $kind,
            'message' => $message,
            'continuous_focus_minutes' => $continuous,
            'next_break_in_minutes' => max(0, $focusLength - $continuous),
            'focus_period' => $focusPeriod,
        ];
    }
}
