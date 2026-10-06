<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Common Platform Layer — central overview hub across the four modules.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        return response()->json($dashboard->build($request->user()));
    }
}
