<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Services\Firebase\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Firebase realtime layer — exchanges the Sanctum session for a Firebase
 * custom token so the browser can subscribe to its own Firestore data.
 */
class FirebaseController extends Controller
{
    public function __invoke(Request $request, FirebaseService $firebase): JsonResponse
    {
        activity()->skip();

        return response()->json($firebase->clientConfig($request->user()));
    }
}
