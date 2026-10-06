<?php

namespace App\Http\Controllers\Api\V1\Study;

use App\Http\Controllers\Controller;
use App\Http\Requests\Study\StudyPlanRequest;
use App\Http\Resources\Study\StudyPlanResource;
use App\Models\StudyPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * PASINDU — planned study time (compared with actual study time).
 */
class StudyPlanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $from = Carbon::parse($request->input('from', now()->startOfWeek()->toDateString()));
        $to = Carbon::parse($request->input('to', $from->copy()->addDays(13)->toDateString()));

        $plans = $request->user()->studyPlans()->with(['module', 'assignment'])
            ->whereBetween('plan_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('plan_date')->get();

        // Attach actual minutes studied that day (same module when set).
        $sessions = $request->user()->studySessions()->completed()
            ->whereBetween('started_at', [$from->startOfDay(), $to->copy()->endOfDay()])->get(['started_at', 'module_id', 'actual_minutes']);
        foreach ($plans as $plan) {
            $plan->actual_minutes = $sessions
                ->filter(fn ($s) => $s->started_at->toDateString() === $plan->plan_date->toDateString() && (! $plan->module_id || $s->module_id === $plan->module_id))
                ->sum('actual_minutes');
        }

        return StudyPlanResource::collection($plans);
    }

    public function store(StudyPlanRequest $request): JsonResponse
    {
        $plan = $request->user()->studyPlans()->create($request->validated());
        activity()->action('study.plan_created')->describe("Planned {$plan->planned_minutes} min of study on ".$plan->plan_date->format('D d M'))->on($plan);

        return StudyPlanResource::make($plan->load(['module', 'assignment']))->response()->setStatusCode(201);
    }

    public function update(StudyPlanRequest $request, StudyPlan $plan): StudyPlanResource
    {
        $plan->update($request->validated());
        activity()->action('study.plan_updated')->describe('Updated the study plan for '.$plan->plan_date->format('D d M'))->on($plan);

        return StudyPlanResource::make($plan->load(['module', 'assignment']));
    }

    public function destroy(StudyPlan $plan): JsonResponse
    {
        $plan->delete();
        activity()->action('study.plan_deleted')->describe('Removed a study plan for '.$plan->plan_date->format('D d M'));

        return response()->json(['message' => 'Plan removed.']);
    }
}
