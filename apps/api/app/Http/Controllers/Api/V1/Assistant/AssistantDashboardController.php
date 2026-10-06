<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Http\Controllers\Controller;
use App\Services\Assistant\AssistantDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KAVISHKA — Academic Dashboard.
 */
class AssistantDashboardController extends Controller
{
    public function __invoke(Request $request, AssistantDashboard $dashboard): JsonResponse
    {
        return response()->json($dashboard->build($request->user()));
    }
}
