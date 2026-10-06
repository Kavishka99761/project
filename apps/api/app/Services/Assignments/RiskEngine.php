<?php

namespace App\Services\Assignments;

use App\Enums\RiskLevel;
use App\Models\Assignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * JITHMI — deadline-miss risk model.
 *
 * 1. Capacity: study hours actually available between now and each deadline,
 *    from the student's weekly availability (partial days are pro-rated over
 *    the study window, 08:00–23:00 by default).
 * 2. Demand: assignments are served Earliest-Deadline-First, so an
 *    assignment's demand includes all remaining work due before it.
 * 3. Load ratio ρ = cumulative demand / capacity. ρ = 1 means the work left
 *    exactly fills the available time.
 * 4. Probability of missing the deadline:
 *        p = sigmoid( k·(ρ − m) + adjustments )
 *    with adjustments for last-day crunch, not having started, being behind
 *    the expected schedule, and recent progress pace.
 *
 * The engine is pure (no I/O) so the same code powers live risk, stored
 * history, ranking, the daily plan and "what-if" scenarios.
 */
class RiskEngine
{
    private const WEEKDAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    /**
     * Assess all of a student's active assignments together.
     *
     * @param  Collection<int, Assignment>  $assignments  active (not completed) assignments
     * @param  array<string, float>  $availability  weekday => hours
     * @param  array<int, float|null>  $velocity  assignment id => progress points per day (recent)
     * @return array<int, array<string, mixed>> keyed by assignment id
     */
    public function assess(Collection $assignments, array $availability, ?Carbon $now = null, float $extraDailyHours = 0.0, array $velocity = []): array
    {
        $now ??= now();
        $ordered = $assignments
            ->filter(fn (Assignment $a) => ! $a->isCompleted())
            ->sortBy([
                fn ($a, $b) => $a->deadline <=> $b->deadline,
                fn ($a, $b) => $b->priority->weight() <=> $a->priority->weight(),
            ])
            ->values();

        $results = [];
        $cumulative = 0.0;
        $earlierCount = 0;

        foreach ($ordered as $assignment) {
            $remaining = $assignment->remainingHours();
            $deadline = $assignment->deadline;
            $daysLeft = round(($deadline->getTimestamp() - $now->getTimestamp()) / 86400, 2);

            if ($daysLeft < 0) {
                $results[$assignment->id] = $this->overdue($assignment, $remaining, abs($daysLeft));
                // Recently overdue work still competes for time (late submission window).
                if (abs($daysLeft) <= 7) {
                    $cumulative += $remaining;
                    $earlierCount++;
                }

                continue;
            }

            $competing = $cumulative;
            $cumulative += $remaining;
            $capacity = $this->availableHours($now, $deadline, $availability, $extraDailyHours);
            $ratio = $cumulative / max($capacity, 0.25);

            $results[$assignment->id] = $this->score(
                $assignment, $now, $remaining, $competing, $earlierCount, $capacity, $ratio, $daysLeft, $velocity[$assignment->id] ?? null,
            );
            $earlierCount++;
        }

        return $this->rank($results, $ordered);
    }

    /**
     * Study hours available between two instants.
     *
     * @param  array<string, float>  $availability
     */
    public function availableHours(Carbon $from, Carbon $to, array $availability, float $extraDailyHours = 0.0): float
    {
        if ($to->lessThanOrEqualTo($from)) {
            return 0.0;
        }
        $startHour = (int) config('edusmart.risk.study_day_start', 8);
        $endHour = (int) config('edusmart.risk.study_day_end', 23);
        $windowSeconds = ($endHour - $startHour) * 3600;

        $hours = 0.0;
        $day = $from->copy()->startOfDay();
        $last = $to->copy()->startOfDay();
        $guard = 0;
        while ($day->lessThanOrEqualTo($last) && $guard++ < 800) {
            $daily = max(0.0, (float) ($availability[self::WEEKDAYS[$day->dayOfWeek]] ?? 0) + $extraDailyHours);
            if ($daily > 0) {
                $windowStart = $day->copy()->setTime($startHour, 0);
                $windowEnd = $day->copy()->setTime($endHour, 0);
                $segmentStart = $from->greaterThan($windowStart) ? $from : $windowStart;
                $segmentEnd = $to->lessThan($windowEnd) ? $to : $windowEnd;
                if ($segmentEnd->greaterThan($segmentStart)) {
                    $hours += $daily * ($segmentEnd->getTimestamp() - $segmentStart->getTimestamp()) / $windowSeconds;
                }
            }
            $day->addDay();
        }

        return round($hours, 2);
    }

    /**
     * Earliest-deadline-first study plan for the next $days days.
     *
     * @param  Collection<int, Assignment>  $assignments
     * @param  array<int, array<string, mixed>>  $risk  output of assess()
     * @return list<array{date: string, label: string, available_hours: float, planned_hours: float, allocations: list<array<string, mixed>>}>
     */
    public function plan(Collection $assignments, array $risk, array $availability, int $days = 7, ?Carbon $now = null, float $extraDailyHours = 0.0): array
    {
        $now ??= now();
        $active = $assignments
            ->filter(fn (Assignment $a) => ! $a->isCompleted() && isset($risk[$a->id]))
            ->filter(fn (Assignment $a) => $a->deadline->greaterThan($now->copy()->subDays(7)))
            ->sortBy([
                fn ($a, $b) => max($a->deadline, $now) <=> max($b->deadline, $now),
                fn ($a, $b) => ($risk[$b->id]['urgency'] ?? 0) <=> ($risk[$a->id]['urgency'] ?? 0),
            ])
            ->values();

        $remaining = $active->mapWithKeys(fn (Assignment $a) => [$a->id => $a->remainingHours()])->all();
        $plan = [];
        for ($i = 0; $i < $days; $i++) {
            $dayStart = $now->copy()->startOfDay()->addDays($i);
            $from = $i === 0 ? $now->copy() : $dayStart;
            $hours = $this->availableHours($from, $dayStart->copy()->endOfDay(), $availability, $extraDailyHours);
            $left = $hours;
            $allocations = [];

            foreach ($active as $assignment) {
                if ($left < 0.25 || ($remaining[$assignment->id] ?? 0) < 0.25) {
                    continue;
                }
                // Overdue items are still worked on; future items until their deadline day.
                if ($assignment->deadline->lessThan($dayStart) && ! $assignment->deadline->lessThan($now)) {
                    continue;
                }
                $take = floor(min($remaining[$assignment->id], $left) * 4) / 4;
                if ($take < 0.25) {
                    continue;
                }
                $remaining[$assignment->id] -= $take;
                $left -= $take;
                $allocations[] = [
                    'assignment_id' => $assignment->id,
                    'title' => $assignment->title,
                    'module' => $assignment->module?->code,
                    'color' => $assignment->module?->color,
                    'hours' => $take,
                    'risk_level' => $risk[$assignment->id]['level']->value,
                ];
            }

            $plan[] = [
                'date' => $dayStart->toDateString(),
                'label' => $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $dayStart->format('l')),
                'available_hours' => round($hours, 2),
                'planned_hours' => round($hours - $left, 2),
                'allocations' => $allocations,
            ];
        }

        return $plan;
    }

    /**
     * Minimum constant daily study rate that meets every deadline (EDF).
     *
     * @param  Collection<int, Assignment>  $assignments
     */
    public function recommendedDailyHours(Collection $assignments, ?Carbon $now = null): float
    {
        $now ??= now();
        $rate = 0.0;
        $cumulative = 0.0;
        foreach ($assignments->filter(fn ($a) => ! $a->isCompleted())->sortBy('deadline') as $assignment) {
            $cumulative += $assignment->remainingHours();
            $days = max(0.5, ($assignment->deadline->getTimestamp() - $now->getTimestamp()) / 86400);
            $rate = max($rate, $cumulative / $days);
        }

        return round(min($rate, 16.0), 1);
    }

    private function score(
        Assignment $assignment,
        Carbon $now,
        float $remaining,
        float $competing,
        int $earlierCount,
        float $capacity,
        float $ratio,
        float $daysLeft,
        ?float $velocity,
    ): array {
        $k = (float) config('edusmart.risk.steepness', 6.5);
        $m = (float) config('edusmart.risk.midpoint', 0.9);
        $progress = (int) $assignment->progress;

        $logit = $k * ($ratio - $m);
        $factors = ['load' => round($k * ($ratio - $m), 3)];

        if ($daysLeft < 1 && $remaining > 0) {
            $logit += 0.8;
            $factors['crunch'] = 0.8;
        }

        $created = $assignment->created_at ?? $now->copy()->subDays(14);
        $window = max(1, ($assignment->deadline->getTimestamp() - $created->getTimestamp()) / 86400);
        $elapsed = max(0, min(1, ($now->getTimestamp() - $created->getTimestamp()) / 86400 / $window));
        $expectedProgress = (int) round($elapsed * 100);
        if ($progress === 0 && $elapsed > 0.5) {
            $logit += 0.6;
            $factors['not_started'] = 0.6;
        } elseif ($progress < $expectedProgress - 30) {
            $logit += 0.4;
            $factors['behind_schedule'] = 0.4;
        }

        $requiredVelocity = (100 - $progress) / max($daysLeft, 0.5);
        if ($velocity !== null && $progress < 100) {
            if ($velocity >= $requiredVelocity) {
                $logit -= 0.5;
                $factors['good_pace'] = -0.5;
            } elseif ($velocity < 0.5 * $requiredVelocity && $daysLeft < 14) {
                $logit += 0.5;
                $factors['slow_pace'] = 0.5;
            }
        }

        $probability = $remaining <= 0.01 ? 0.03 : 1 / (1 + exp(-$logit));
        $probability = max(0.01, min(0.99, $probability));
        $score = (int) round($probability * 100);
        $level = RiskLevel::fromScore($score);

        $requiredPerDay = $remaining / max($daysLeft, 1);
        $availablePerDay = $capacity / max($daysLeft, 1);

        return [
            'assignment_id' => $assignment->id,
            'score' => $score,
            'probability' => round($probability, 4),
            'level' => $level,
            'remaining_hours' => round($remaining, 2),
            'competing_hours' => round($competing, 2),
            'available_hours' => round($capacity, 2),
            'load_ratio' => round($ratio, 3),
            'days_left' => round($daysLeft, 2),
            'required_hours_per_day' => round($requiredPerDay, 2),
            'available_hours_per_day' => round($availablePerDay, 2),
            'recommended_hours_per_day' => round(min($requiredPerDay * 1.15, 12), 1),
            'expected_progress' => $expectedProgress,
            'velocity' => $velocity !== null ? round($velocity, 2) : null,
            'required_velocity' => round($requiredVelocity, 2),
            'factors' => $factors,
            'reasons' => $this->reasons($assignment, $remaining, $competing, $earlierCount, $capacity, $ratio, $daysLeft, $expectedProgress, $elapsed, $velocity, $requiredVelocity),
            'overdue' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function overdue(Assignment $assignment, float $remaining, float $daysAgo): array
    {
        $ago = $daysAgo < 1 ? round($daysAgo * 24).' hour(s)' : round($daysAgo).' day(s)';

        return [
            'assignment_id' => $assignment->id,
            'score' => 100,
            'probability' => 1.0,
            'level' => RiskLevel::Critical,
            'remaining_hours' => round($remaining, 2),
            'competing_hours' => 0.0,
            'available_hours' => 0.0,
            'load_ratio' => 99.0,
            'days_left' => round(-$daysAgo, 2),
            'required_hours_per_day' => round($remaining, 2),
            'available_hours_per_day' => 0.0,
            'recommended_hours_per_day' => round(min($remaining, 6), 1),
            'expected_progress' => 100,
            'velocity' => null,
            'required_velocity' => null,
            'factors' => ['overdue' => 99],
            'reasons' => [
                ['type' => 'danger', 'text' => "The deadline passed {$ago} ago with ".(100 - $assignment->progress).'% still to do.'],
                ['type' => 'info', 'text' => 'Submit as soon as possible or contact your lecturer about mitigating circumstances.'],
            ],
            'overdue' => true,
        ];
    }

    /** @return list<array{type: string, text: string}> */
    private function reasons(
        Assignment $assignment,
        float $remaining,
        float $competing,
        int $earlierCount,
        float $capacity,
        float $ratio,
        float $daysLeft,
        int $expectedProgress,
        float $elapsed,
        ?float $velocity,
        float $requiredVelocity,
    ): array {
        $reasons = [];
        $progress = (int) $assignment->progress;
        $demand = $remaining + $competing;

        if ($remaining <= 0.01) {
            return [['type' => 'success', 'text' => 'All estimated work is done — mark the assignment as completed when submitted.']];
        }

        if ($ratio > 1) {
            $reasons[] = ['type' => 'danger', 'text' => sprintf('Needs %.1f h of work but only %.1f h of study time is available before the deadline.', $demand, $capacity)];
        } elseif ($ratio > 0.7) {
            $reasons[] = ['type' => 'warning', 'text' => sprintf('Takes %d%% of your available study time before the deadline (%.1f h of %.1f h).', round($ratio * 100), $demand, $capacity)];
        } else {
            $reasons[] = ['type' => 'success', 'text' => sprintf('Workload fits — it needs only %d%% of your available study time.', max(1, round($ratio * 100)))];
        }

        if ($competing > 0 && $earlierCount > 0) {
            $reasons[] = ['type' => 'warning', 'text' => sprintf('%d assignment(s) due earlier need %.1f h of your time first.', $earlierCount, $competing)];
        }

        if ($daysLeft < 1) {
            $reasons[] = ['type' => 'danger', 'text' => sprintf('Due in about %d hour(s).', max(1, (int) round($daysLeft * 24)))];
        } elseif ($daysLeft <= 3) {
            $reasons[] = ['type' => 'warning', 'text' => sprintf('Only %d day(s) left.', (int) ceil($daysLeft))];
        }

        if ($progress === 0 && $elapsed > 0.5) {
            $reasons[] = ['type' => 'danger', 'text' => sprintf('Not started yet, with %d%% of the available time already gone.', round($elapsed * 100))];
        } elseif ($progress < $expectedProgress - 30) {
            $reasons[] = ['type' => 'warning', 'text' => sprintf('Progress is %d%% but about %d%% would be expected by now.', $progress, $expectedProgress)];
        }

        if ($velocity !== null) {
            if ($velocity >= $requiredVelocity) {
                $reasons[] = ['type' => 'success', 'text' => sprintf('Your recent pace (%.1f%%/day) is enough to finish on time.', $velocity)];
            } elseif ($velocity < 0.5 * $requiredVelocity && $daysLeft < 14) {
                $reasons[] = ['type' => 'warning', 'text' => sprintf('Recent pace (%.1f%%/day) is slower than the %.1f%%/day needed.', $velocity, $requiredVelocity)];
            }
        }

        $requiredPerDay = $remaining / max($daysLeft, 1);
        $reasons[] = ['type' => 'info', 'text' => sprintf('Plan about %.1f h per day to finish this assignment on time.', $requiredPerDay)];

        return $reasons;
    }

    /**
     * Urgency score and priority rank (1 = work on this first).
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function rank(array $results, Collection $ordered): array
    {
        foreach ($ordered as $assignment) {
            if (! isset($results[$assignment->id])) {
                continue;
            }
            $days = max(0.0, (float) $results[$assignment->id]['days_left']);
            $proximity = 100 * exp(-$days / 7);
            $weight = min(100, ((float) ($assignment->weight_percent ?? 0)) * 2);
            $results[$assignment->id]['urgency'] = round(
                0.55 * $results[$assignment->id]['score'] + 0.25 * $proximity + 0.15 * $assignment->priority->weight() + 0.05 * $weight,
                2,
            );
        }

        uasort($results, fn ($a, $b) => $b['urgency'] <=> $a['urgency']);
        $rank = 1;
        foreach ($results as &$result) {
            $result['rank'] = $rank++;
        }
        unset($result);

        return $results;
    }
}
