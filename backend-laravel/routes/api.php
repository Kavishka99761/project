<?php

use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\StudySessionController;
use App\Http\Controllers\Api\SummaryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EDU-SMART API Routes
|--------------------------------------------------------------------------
| All routes are prefixed with /api (see bootstrap/app.php). Public routes:
| health, register, login. Everything else requires a Sanctum bearer token.
|
| Grouped by the module that owns the data:
|   Common   : auth, modules, notifications, dashboard, calendar
|   Bethmi   : documents, summaries
|   Pasindu  : study sessions, engagement, analytics
|   Kavishka : assistant (knowledge base, chat, academic dates)
|   Jithmi   : assignments, risk, recommendation, what-if
*/

Route::get('/health', fn () => response()->json([
    'status'  => 'ok',
    'service' => 'edu-smart-api',
    'time'    => now()->toIso8601String(),
]));

/* ------------------------------ Public ------------------------------ */
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

/* --------------------------- Authenticated -------------------------- */
Route::middleware('auth:sanctum')->group(function () {

    // Common Platform Layer
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/calendar', [CalendarController::class, 'index']);
    Route::apiResource('modules', ModuleController::class);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{notification}', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    // BETHMI — Learning Materials
    Route::get('/documents/{document}/keywords', [DocumentController::class, 'keywords']);
    Route::post('/documents/{document}/summaries', [SummaryController::class, 'store']);
    Route::apiResource('documents', DocumentController::class);
    Route::get('/summaries', [SummaryController::class, 'index']);
    Route::get('/summaries/{summary}', [SummaryController::class, 'show']);
    Route::delete('/summaries/{summary}', [SummaryController::class, 'destroy']);

    // PASINDU — Study & Engagement
    Route::get('/study/current', [StudySessionController::class, 'current']);
    Route::get('/study/analytics', [StudySessionController::class, 'analytics']);
    Route::get('/study/sessions', [StudySessionController::class, 'index']);
    Route::post('/study/sessions', [StudySessionController::class, 'store']);
    Route::patch('/study/sessions/{session}', [StudySessionController::class, 'update']);
    Route::post('/study/sessions/{session}/engagement', [StudySessionController::class, 'logEngagement']);

    // KAVISHKA — Academic Assistant
    Route::get('/assistant/knowledge', [AssistantController::class, 'knowledge']);
    Route::post('/assistant/knowledge', [AssistantController::class, 'storeKnowledge']);
    Route::delete('/assistant/knowledge/{doc}', [AssistantController::class, 'destroyKnowledge']);
    Route::get('/assistant/conversations', [AssistantController::class, 'conversations']);
    Route::get('/assistant/conversations/{conversation}/messages', [AssistantController::class, 'messages']);
    Route::post('/assistant/chat', [AssistantController::class, 'chat']);
    Route::get('/assistant/dates', [AssistantController::class, 'dates']);
    Route::post('/assistant/dates', [AssistantController::class, 'storeDate']);
    Route::put('/assistant/dates/{date}', [AssistantController::class, 'updateDate']);
    Route::delete('/assistant/dates/{date}', [AssistantController::class, 'destroyDate']);

    // JITHMI — Assignment Risk
    Route::get('/assignments/rank', [AssignmentController::class, 'rank']);
    Route::get('/assignments/recommendation', [AssignmentController::class, 'recommendation']);
    Route::post('/assignments/whatif', [AssignmentController::class, 'whatIf']);
    Route::apiResource('assignments', AssignmentController::class);
});
