<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\RiskAssessment;
use Illuminate\Support\Carbon;

/**
 * JITHMI — Deadline-miss risk engine.
 *
 * Computes a 0-100 risk score from the remaining workload versus the time left,
 * classifies it (Low/Medium/High/Critical) and records human-readable reasons.
 * This is the authoritative implementation; the web/mobile clients mirror it in
 * ES.calcRisk (frontend-web) and api/risk.ts (mobile-app) for instant UI feedback.
 */
class RiskCalculator
{
    /** Hours a student can reasonably study per day — used to normalise pressure. */
    private const DAILY_CAPACITY = 5.0;

    /**
     * @return array{score:int, level:string, hours_per_day:float, reasons:array<int,string>, days:int}
     */
    public function assess(Assignment $assignment): array
    {
        $days = max((int) Carbon::today()->diffInDays($assignment->deadline, false), 0);
        $remainingHours = max((float) $assignment->est_hours - (float) $assignment->done_hours, 0.0);
        $hoursPerDay = $days > 0 ? $remainingHours / $days : $remainingHours;

        if ($days === 0) {
            $score = $remainingHours > 0 ? 100 : 0;
        } else {
            $pressure = ($hoursPerDay / self::DAILY_CAPACITY) * 60;   // workload pressure
            $incomplete = (100 - (int) $assignment->progress) * 0.4;  // how unfinished it is
            $score = (int) min(100, round($pressure + $incomplete));
        }

        $level = $this->levelFor($score);
        $reasons = $this->reasons($days, $hoursPerDay, (int) $assignment->progress, $remainingHours);

        return [
            'score'         => $score,
            'level'         => $level,
            'hours_per_day' => round($hoursPerDay, 1),
            'reasons'       => $reasons,
            'days'          => $days,
        ];
    }

    /** Recompute and persist a risk snapshot for an assignment. */
    public function recalculate(Assignment $assignment): RiskAssessment
    {
        $result = $this->assess($assignment);

        return RiskAssessment::create([
            'assignment_id' => $assignment->id,
            'score'         => $result['score'],
            'level'         => $result['level'],
            'hours_per_day' => $result['hours_per_day'],
            'reasons'       => $result['reasons'],
            'calculated_at' => now(),
        ]);
    }

    /**
     * Rank a set of assignments by urgency (highest risk first).
     *
     * @param  \Illuminate\Support\Collection<int, Assignment>  $assignments
     * @return array<int, array{assignment: Assignment, risk: array}>
     */
    public function rank($assignments): array
    {
        $ranked = [];
        foreach ($assignments as $assignment) {
            $ranked[] = ['assignment' => $assignment, 'risk' => $this->assess($assignment)];
        }
        usort($ranked, fn ($a, $b) => $b['risk']['score'] <=> $a['risk']['score']);

        return $ranked;
    }

    private function levelFor(int $score): string
    {
        return match (true) {
            $score >= 75 => 'Critical',
            $score >= 50 => 'High',
            $score >= 25 => 'Medium',
            default      => 'Low',
        };
    }

    /** @return array<int,string> */
    private function reasons(int $days, float $hoursPerDay, int $progress, float $remainingHours): array
    {
        $reasons = [];
        if ($hoursPerDay > 3) {
            $reasons[] = sprintf('Needs ~%.1fh/day to finish', $hoursPerDay);
        }
        if ($days <= 3) {
            $reasons[] = sprintf('Only %d day(s) left', $days);
        }
        if ($progress < 40) {
            $reasons[] = sprintf('Progress is low (%d%%)', $progress);
        }
        if ($remainingHours <= 0) {
            $reasons[] = 'All estimated work completed';
        }
        if (empty($reasons)) {
            $reasons[] = 'On track — workload fits available time';
        }

        return $reasons;
    }
}
