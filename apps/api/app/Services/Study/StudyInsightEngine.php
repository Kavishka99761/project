<?php

namespace App\Services\Study;

use App\Enums\AssignmentStatus;
use App\Enums\RiskLevel;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * PASINDU — personalised study suggestions derived from the student's own
 * analytics, cross-checked with assignment risk (JITHMI integration).
 */
class StudyInsightEngine
{
    /**
     * @param  array<string, mixed>  $analytics
     * @return list<array{id: string, type: string, icon: string, title: string, message: string}>
     */
    public function generate(User $user, array $analytics, Collection $sessions): array
    {
        $insights = [];

        // 1. Peak focus window.
        $best = $analytics['preferred_periods']['windows'][0] ?? null;
        if ($best && $best['sessions'] >= 3) {
            $insights[] = $this->insight('peak-time', 'success', 'clock-history', 'Your peak focus time',
                "You focus best between {$best['label']} (average engagement {$best['engagement']}%). Schedule your hardest topics in this window.");
        }

        // 2. Weekly goal pacing.
        $week = $analytics['week'];
        $daysLeft = 7 - (int) now()->dayOfWeekIso + 1;
        if ($week['goal'] > 0 && $week['minutes'] < $week['goal']) {
            $perDay = (int) ceil(($week['goal'] - $week['minutes']) / max(1, $daysLeft));
            $insights[] = $this->insight('weekly-goal', $week['percent'] >= 60 ? 'info' : 'warning', 'bullseye', 'Weekly goal',
                sprintf('%s of your %s weekly goal done. About %d min per day for the next %d day(s) gets you there.',
                    $this->hours($week['minutes']), $this->hours($week['goal']), $perDay, $daysLeft));
        } elseif ($week['goal'] > 0) {
            $insights[] = $this->insight('weekly-goal', 'success', 'trophy', 'Weekly goal reached',
                'You already hit your weekly study goal — anything more is a bonus. Remember to rest too.');
        }

        // 3. Planned vs actual.
        $rate = $analytics['stats']['completion_rate'];
        if ($rate !== null && $analytics['stats']['sessions'] >= 3) {
            if ($rate < 80) {
                $insights[] = $this->insight('planned-actual', 'warning', 'hourglass-split', 'Sessions end early',
                    "You complete about {$rate}% of your planned study time. Try shorter blocks (25–30 min) that you can finish, then chain them.");
            } elseif ($rate > 125) {
                $insights[] = $this->insight('planned-actual', 'info', 'hourglass-top', 'You study longer than planned',
                    "Sessions run about {$rate}% of plan. Plan longer sessions so your schedule stays realistic.");
            }
        }

        // 4. Long sessions vs engagement.
        $long = $sessions->filter(fn ($s) => $s->actual_minutes > 60 && $s->avg_engagement !== null);
        $short = $sessions->filter(fn ($s) => $s->actual_minutes <= 60 && $s->avg_engagement !== null);
        if ($long->count() >= 2 && $short->count() >= 2) {
            $drop = (int) round($short->avg('avg_engagement') - $long->avg('avg_engagement'));
            if ($drop >= 8) {
                $insights[] = $this->insight('long-sessions', 'warning', 'battery-half', 'Long sessions drain focus',
                    "Your engagement is {$drop} points lower in sessions over an hour. Add a break every 45–50 minutes.");
            }
        }

        // 5. Risky assignment in an under-studied module (JITHMI integration).
        $risky = $user->assignments()->with('module')
            ->where('status', '!=', AssignmentStatus::Completed)
            ->whereIn('risk_level', [RiskLevel::High->value, RiskLevel::Critical->value])
            ->orderByDesc('risk_score')->first();
        if ($risky) {
            $moduleMinutes = (int) $sessions->filter(fn ($s) => $s->module_id && $s->module_id === $risky->module_id
                && $s->started_at->greaterThanOrEqualTo(now()->subDays(7)))->sum('actual_minutes');
            $insights[] = $this->insight('risky-assignment', 'danger', 'exclamation-triangle', 'Focus where the risk is',
                sprintf('“%s” is at %d%% risk (%s) and got %s of study this week. Make it your next session.',
                    $risky->title, $risky->risk_score, $risky->risk_level?->label(), $moduleMinutes ? $this->hours($moduleMinutes) : 'no time'));
        }

        // 6. Streak.
        $streak = $analytics['streak'];
        if ($streak['current'] >= 3) {
            $insights[] = $this->insight('streak', 'success', 'fire', "{$streak['current']}-day streak",
                $streak['studied_today'] ? 'You studied today — keep the chain going tomorrow.' : 'Study today to extend your streak.');
        } elseif ($streak['current'] === 0 && $streak['best'] > 0) {
            $insights[] = $this->insight('streak', 'info', 'arrow-repeat', 'Restart your streak',
                "Your best streak is {$streak['best']} days. A 20-minute session today starts a new one.");
        }

        // 7. Most engaging activity.
        $activities = collect($analytics['by_activity'])->filter(fn ($a) => $a['engagement'] !== null && $a['sessions'] >= 2);
        if ($activities->count() >= 2) {
            $top = $activities->sortByDesc('engagement')->first();
            $insights[] = $this->insight('best-activity', 'info', 'stars', 'What works for you',
                "{$top['label']} keeps you most engaged ({$top['engagement']}%). Mix it into revision of harder topics.");
        }

        // 8. Consistency.
        $active = $analytics['stats']['active_days'];
        if ($analytics['range_days'] >= 14 && $active < $analytics['range_days'] * 0.4) {
            $insights[] = $this->insight('consistency', 'tip', 'calendar-check', 'Little and often',
                "You studied on {$active} of the last {$analytics['range_days']} days. Shorter daily sessions beat occasional long ones for retention.");
        }

        return $insights;
    }

    private function insight(string $id, string $type, string $icon, string $title, string $message): array
    {
        return compact('id', 'type', 'icon', 'title', 'message');
    }

    private function hours(int $minutes): string
    {
        return $minutes >= 60 ? round($minutes / 60, 1).' h' : $minutes.' min';
    }
}
