<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RiskCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Common Platform Layer — the shared calendar.
 * Merges KAVISHKA's academic dates with JITHMI's assignment deadlines into one
 * chronological event feed (this is the data-exchange integration point that the
 * calendar.html view renders).
 */
class CalendarController extends Controller
{
    public function __construct(private readonly RiskCalculator $risk) {}

    /** GET /api/calendar?from=YYYY-MM-DD&to=YYYY-MM-DD */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $from = $request->date('from') ?? Carbon::today()->subDays(7);
        $to = $request->date('to') ?? Carbon::today()->addMonths(2);

        $events = [];

        // Kavishka — academic dates.
        $dates = $user->academicDates()
            ->whereBetween('event_date', [$from, $to])
            ->orderBy('event_date')
            ->get();

        foreach ($dates as $d) {
            $events[] = [
                'id'      => 'date-'.$d->id,
                'title'   => $d->title,
                'date'    => Carbon::parse($d->event_date)->toDateString(),
                'type'    => $d->type,          // Deadline|Milestone|Exam|Event
                'source'  => 'kavishka',
                'meta'    => ['reminder' => $d->reminder, 'academic_date_id' => $d->id],
            ];
        }

        // Jithmi — assignment deadlines (with live risk level).
        $assignments = $user->assignments()
            ->whereBetween('deadline', [$from, $to])
            ->orderBy('deadline')
            ->get();

        foreach ($assignments as $a) {
            $assessment = $this->risk->assess($a);
            $events[] = [
                'id'      => 'assignment-'.$a->id,
                'title'   => $a->title.' (due)',
                'date'    => Carbon::parse($a->deadline)->toDateString(),
                'type'    => $a->completed ? 'Completed' : 'Assignment',
                'source'  => 'jithmi',
                'meta'    => [
                    'assignment_id' => $a->id,
                    'risk_level'    => $assessment['level'],
                    'risk_score'    => $assessment['score'],
                    'completed'     => (bool) $a->completed,
                ],
            ];
        }

        usort($events, fn ($x, $y) => strcmp($x['date'], $y['date']));

        return response()->json([
            'from'   => Carbon::parse($from)->toDateString(),
            'to'     => Carbon::parse($to)->toDateString(),
            'events' => $events,
        ]);
    }
}
