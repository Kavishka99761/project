<?php

namespace App\Services\Study;

use App\Enums\EngagementLevel;
use App\Enums\SessionStatus;
use App\Models\EngagementLog;
use App\Models\StudySession;

/**
 * PASINDU — engagement monitoring.
 *
 * Automatic samples arrive from the browser every minute with the signals of
 * that window (visible/focused time, interaction count, idle seconds). The
 * score blends tab focus with activity; manual concentration reports (1–5)
 * are recorded as-is and weigh into the next automatic samples. Level
 * changes (e.g. Focused → Distracted) are detected and recorded as events.
 */
class EngagementService
{
    /**
     * @param  array{visible_ratio?: float, focus_ratio?: float, interactions?: int, idle_seconds?: int, window_seconds?: int, tab_switches?: int}  $signals
     * @return array{log: EngagementLog, changed: bool, previous: ?string}
     */
    public function recordAutomatic(StudySession $session, array $signals): array
    {
        $score = $this->scoreFromSignals($signals);

        // Blend with a recent manual report so self-assessment matters.
        $manual = $session->engagementLogs()
            ->where('source', 'manual')
            ->where('logged_at', '>=', now()->subMinutes(10))
            ->latest('logged_at')
            ->value('score');
        if ($manual !== null) {
            $score = (int) round(0.6 * $score + 0.4 * $manual);
        }

        return $this->store($session, $score, 'auto', null, $signals, null);
    }

    /** @return array{log: EngagementLog, changed: bool, previous: ?string} */
    public function recordManual(StudySession $session, int $concentration, ?string $note = null): array
    {
        $score = [1 => 15, 2 => 35, 3 => 58, 4 => 78, 5 => 95][$concentration] ?? 58;

        return $this->store($session, $score, 'manual', $concentration, null, $note);
    }

    /**
     * 0–100 engagement for one sampling window:
     *   70 % focus  — share of the window the study tab was visible and focused
     *   30 % activity — interaction density, discounted by idle time
     * minus a small penalty for frequent tab switching.
     */
    public function scoreFromSignals(array $signals): int
    {
        $window = max(1, (int) ($signals['window_seconds'] ?? 60));
        $focus = (float) ($signals['focus_ratio'] ?? $signals['visible_ratio'] ?? 1.0);
        $idle = min($window, (int) ($signals['idle_seconds'] ?? 0));
        $interactions = (int) ($signals['interactions'] ?? 0);

        $activity = min(1.0, $interactions / max(4, $window / 10)) * (1 - $idle / $window);
        // Reading quietly is fine: a visible tab with modest idleness still counts.
        if ($focus > 0.9 && $idle < $window * 0.8) {
            $activity = max($activity, 0.6);
        }

        $score = 100 * (0.7 * max(0, min(1, $focus)) + 0.3 * $activity);
        $score -= min(20, 4 * (int) ($signals['tab_switches'] ?? 0));

        return (int) max(0, min(100, round($score)));
    }

    public function averageFor(StudySession $session): ?int
    {
        $average = $session->engagementLogs()->avg('score');

        return $average === null ? null : (int) round((float) $average);
    }

    /** @return array{log: EngagementLog, changed: bool, previous: ?string} */
    private function store(StudySession $session, int $score, string $source, ?int $concentration, ?array $signals, ?string $note): array
    {
        if (! in_array($session->status, [SessionStatus::Active, SessionStatus::Paused, SessionStatus::OnBreak], true)) {
            throw new \DomainException('Engagement can only be recorded for a running session.');
        }

        $previous = $session->engagementLogs()->latest('logged_at')->first();
        $level = EngagementLevel::fromScore($score);

        $log = $session->engagementLogs()->create([
            'user_id' => $session->user_id,
            'score' => $score,
            'level' => $level,
            'source' => $source,
            'concentration' => $concentration,
            'signals' => $signals,
            'note' => $note,
            'logged_at' => now(),
        ]);

        $changed = $previous !== null && $previous->level !== $level;
        if ($changed) {
            $session->events()->create([
                'user_id' => $session->user_id,
                'type' => 'engagement_changed',
                'payload' => ['from' => $previous->level->value, 'to' => $level->value, 'score' => $score],
                'occurred_at' => now(),
            ]);
        }

        return ['log' => $log, 'changed' => $changed, 'previous' => $previous?->level->value];
    }
}
