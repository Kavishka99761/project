<?php

namespace App\Http\Controllers\Api\V1\Study;

use App\Http\Controllers\Controller;
use App\Services\Study\StudyAnalyticsService;
use App\Services\Study\StudyDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PASINDU — Study dashboard and productivity analytics.
 */
class StudyAnalyticsController extends Controller
{
    public function dashboard(Request $request, StudyDashboard $dashboard): JsonResponse
    {
        return response()->json($dashboard->build($request->user()));
    }

    public function analytics(Request $request, StudyAnalyticsService $analytics): JsonResponse
    {
        $request->validate(['days' => ['nullable', 'integer', 'in:7,14,30,60,90,180,365']]);
        $days = $request->integer('days') ?: 30;

        return response()->json($analytics->analytics($request->user(), $days));
    }
}
