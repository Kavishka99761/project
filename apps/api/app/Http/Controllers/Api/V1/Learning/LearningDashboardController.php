<?php

namespace App\Http\Controllers\Api\V1\Learning;

use App\Http\Controllers\Controller;
use App\Services\Learning\LearningDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BETHMI — Learning Dashboard.
 */
class LearningDashboardController extends Controller
{
    public function __invoke(Request $request, LearningDashboard $dashboard): JsonResponse
    {
        return response()->json($dashboard->build($request->user()));
    }
}
