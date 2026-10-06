<?php

use App\Http\Controllers\Api\V1\Assignments\AssignmentController;
use App\Http\Controllers\Api\V1\Assignments\AssignmentHistoryController;
use App\Http\Controllers\Api\V1\Assignments\RiskController;
use App\Http\Controllers\Api\V1\Assistant\AcademicDateController;
use App\Http\Controllers\Api\V1\Assistant\AssistantDashboardController;
use App\Http\Controllers\Api\V1\Assistant\ChatController;
use App\Http\Controllers\Api\V1\Assistant\KnowledgeController;
use App\Http\Controllers\Api\V1\Learning\DocumentController;
use App\Http\Controllers\Api\V1\Learning\LearningDashboardController;
use App\Http\Controllers\Api\V1\Learning\StudyAidController;
use App\Http\Controllers\Api\V1\Learning\SummaryController;
use App\Http\Controllers\Api\V1\Platform\ActivityController;
use App\Http\Controllers\Api\V1\Platform\AuthController;
use App\Http\Controllers\Api\V1\Platform\CalendarController;
use App\Http\Controllers\Api\V1\Platform\DashboardController;
use App\Http\Controllers\Api\V1\Platform\ExportController;
use App\Http\Controllers\Api\V1\Platform\FirebaseController;
use App\Http\Controllers\Api\V1\Platform\ModuleController;
use App\Http\Controllers\Api\V1\Platform\NotificationController;
use App\Http\Controllers\Api\V1\Platform\ProfileController;
use App\Http\Controllers\Api\V1\Platform\SearchController;
use App\Http\Controllers\Api\V1\Platform\SettingsController;
use App\Http\Controllers\Api\V1\Platform\SystemController;
use App\Http\Controllers\Api\V1\Platform\TrashController;
use App\Http\Controllers\Api\V1\Study\StudyAnalyticsController;
use App\Http\Controllers\Api\V1\Study\StudyPlanController;
use App\Http\Controllers\Api\V1\Study\StudyReminderController;
use App\Http\Controllers\Api\V1\Study\StudySessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EDU-SMART API v1  —  every route is prefixed with /api/v1
|--------------------------------------------------------------------------
|   Common Platform Layer : auth, profile, settings, modules, notifications,
|                           activity (audit), search, calendar, exports, trash
|   BETHMI   · Learning   : documents, summaries, study aids
|   PASINDU  · Study      : sessions, engagement, analytics, plans, reminders
|   KAVISHKA · Assistant  : knowledge base, chatbot, academic dates
|   JITHMI   · Assignments: assignments, risk, workload, history
*/

Route::get('health', [SystemController::class, 'health'])->name('health');

/* ------------------------------- Public -------------------------------- */
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:login')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset')->name('forgot');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('reset');
});

/* ---------------------------- Authenticated ---------------------------- */
Route::middleware('auth:sanctum')->group(function () {

    /* ======================= Common Platform Layer ===================== */
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::put('me', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('me', [ProfileController::class, 'destroy'])->name('account.delete');
        Route::post('me/avatar', [ProfileController::class, 'uploadAvatar'])->middleware('throttle:uploads')->name('avatar.upload');
        Route::delete('me/avatar', [ProfileController::class, 'removeAvatar'])->name('avatar.remove');
        Route::put('password', [ProfileController::class, 'changePassword'])->name('password.change');
        Route::get('sessions', [ProfileController::class, 'sessions'])->name('sessions.index');
        Route::delete('sessions', [ProfileController::class, 'revokeOtherSessions'])->name('sessions.revoke-others');
        Route::delete('sessions/{token}', [ProfileController::class, 'revokeSession'])->whereNumber('token')->name('sessions.revoke');
    });

    Route::get('firebase/token', FirebaseController::class)->name('firebase.token');
    Route::get('system/status', [SystemController::class, 'status'])->name('system.status');
    Route::get('meta/options', [SystemController::class, 'options'])->name('meta.options');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::post('modules/reorder', [ModuleController::class, 'reorder'])->name('modules.reorder');
    Route::apiResource('modules', ModuleController::class);

    Route::prefix('notifications')->name('notifications.')->controller(NotificationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('read-all', 'markAllRead')->name('read-all');
        Route::delete('/', 'clear')->name('clear');
        Route::patch('{notification}/read', 'markRead')->name('read');
        Route::delete('{notification}', 'destroy')->name('destroy');
    });

    Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('activity/stats', [ActivityController::class, 'stats'])->name('activity.stats');

    Route::get('search', [SearchController::class, 'search'])->name('search');
    Route::get('search/history', [SearchController::class, 'history'])->name('search.history');
    Route::delete('search/history', [SearchController::class, 'clearHistory'])->name('search.history.clear');

    Route::prefix('calendar')->name('calendar.')->controller(CalendarController::class)->group(function () {
        Route::get('/', 'feed')->name('feed');
        Route::get('upcoming', 'upcoming')->name('upcoming');
        Route::post('events', 'store')->name('events.store');
        Route::get('events/{event}', 'show')->name('events.show');
        Route::put('events/{event}', 'update')->name('events.update');
        Route::put('events/{event}/reminder', 'reminder')->name('events.reminder');
        Route::delete('events/{event}', 'destroy')->name('events.destroy');
    });

    Route::prefix('exports')->name('exports.')->middleware('throttle:exports')->controller(ExportController::class)->group(function () {
        Route::get('/', 'catalogue')->name('catalogue');
        Route::get('history', 'history')->name('history');
        Route::get('backup', 'backup')->name('backup');
        Route::get('{dataset}', 'download')->where('dataset', '[a-z_]+')->name('download');
    });

    Route::prefix('trash')->name('trash.')->controller(TrashController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::delete('/', 'empty')->name('empty');
        Route::post('{type}/{id}/restore', 'restore')->whereNumber('id')->name('restore');
        Route::delete('{type}/{id}', 'destroy')->whereNumber('id')->name('destroy');
    });

    /* ===================== BETHMI · Learning Materials ================= */
    Route::get('learning/dashboard', LearningDashboardController::class)->name('learning.dashboard');

    Route::get('documents/topics', [DocumentController::class, 'topics'])->name('documents.topics');
    Route::post('documents/notes', [DocumentController::class, 'storeNote'])->name('documents.note');
    Route::post('documents', [DocumentController::class, 'store'])->middleware('throttle:uploads')->name('documents.store');
    Route::apiResource('documents', DocumentController::class)->except('store');
    Route::get('documents/{document}/file', [DocumentController::class, 'file'])->name('documents.file');
    Route::post('documents/{document}/extract', [DocumentController::class, 'extract'])->name('documents.extract');
    Route::get('documents/{document}/analysis', [DocumentController::class, 'analysis'])->name('documents.analysis');
    Route::post('documents/{document}/summaries', [SummaryController::class, 'generate'])->name('documents.summaries.generate');
    Route::post('documents/{document}/summaries/save', [SummaryController::class, 'store'])->name('documents.summaries.store');
    Route::post('documents/{document}/study-aids', [StudyAidController::class, 'generate'])->name('documents.study-aids.generate');

    Route::get('summaries/{summary}/download', [SummaryController::class, 'download'])->name('summaries.download');
    Route::apiResource('summaries', SummaryController::class)->except('store');

    Route::post('study-aids/{studyAid}/attempt', [StudyAidController::class, 'attempt'])->name('study-aids.attempt');
    Route::apiResource('study-aids', StudyAidController::class)->only(['index', 'show', 'destroy']);

    /* ===================== PASINDU · Study & Engagement ================ */
    Route::prefix('study')->name('study.')->group(function () {
        Route::get('dashboard', [StudyAnalyticsController::class, 'dashboard'])->name('dashboard');
        Route::get('analytics', [StudyAnalyticsController::class, 'analytics'])->name('analytics');
        Route::get('engagement', [StudySessionController::class, 'engagementHistory'])->name('engagement.index');

        Route::get('sessions/active', [StudySessionController::class, 'active'])->name('sessions.active');
        Route::controller(StudySessionController::class)->prefix('sessions/{session}')->name('sessions.')->group(function () {
            Route::post('pause', 'pause')->name('pause');
            Route::post('resume', 'resume')->name('resume');
            Route::post('break', 'startBreak')->name('break');
            Route::post('stop', 'stop')->name('stop');
            Route::post('cancel', 'cancel')->name('cancel');
            Route::post('engagement', 'engagement')->name('engagement');
            Route::get('advice', 'advice')->name('advice');
        });
        Route::apiResource('sessions', StudySessionController::class);
        Route::apiResource('plans', StudyPlanController::class)->except('show');
        Route::apiResource('reminders', StudyReminderController::class)->except('show');
    });

    /* ===================== KAVISHKA · Academic Assistant ============== */
    Route::prefix('assistant')->name('assistant.')->group(function () {
        Route::get('dashboard', AssistantDashboardController::class)->name('dashboard');
        Route::post('chat', [ChatController::class, 'ask'])->middleware('throttle:chat')->name('chat');
        Route::get('messages/search', [ChatController::class, 'searchMessages'])->name('messages.search');
        Route::patch('messages/{message}/feedback', [ChatController::class, 'feedback'])->name('messages.feedback');
        Route::get('conversations', [ChatController::class, 'conversations'])->name('conversations.index');
        Route::delete('conversations', [ChatController::class, 'clear'])->name('conversations.clear');
        Route::get('conversations/{conversation}', [ChatController::class, 'show'])->name('conversations.show');
        Route::put('conversations/{conversation}', [ChatController::class, 'update'])->name('conversations.update');
        Route::delete('conversations/{conversation}', [ChatController::class, 'destroy'])->name('conversations.destroy');
        Route::get('conversations/{conversation}/export', [ChatController::class, 'export'])->name('conversations.export');
    });

    Route::get('knowledge/search', [KnowledgeController::class, 'search'])->name('knowledge.search');
    Route::post('knowledge', [KnowledgeController::class, 'store'])->middleware('throttle:uploads')->name('knowledge.store');
    Route::apiResource('knowledge', KnowledgeController::class)->except('store');
    Route::get('knowledge/{knowledge}/file', [KnowledgeController::class, 'file'])->name('knowledge.file');
    Route::post('knowledge/{knowledge}/process', [KnowledgeController::class, 'process'])->name('knowledge.process');
    Route::post('knowledge/{knowledge}/extract-dates', [AcademicDateController::class, 'extract'])->name('knowledge.extract-dates');

    Route::prefix('academic-dates')->name('academic-dates.')->controller(AcademicDateController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('calendar', 'addAll')->name('add-all');
        Route::put('{academicDate}', 'update')->name('update');
        Route::delete('{academicDate}', 'destroy')->name('destroy');
        Route::post('{academicDate}/calendar', 'addToCalendar')->name('calendar');
        Route::post('{academicDate}/dismiss', 'dismiss')->name('dismiss');
        Route::post('{academicDate}/restore', 'restore')->name('restore');
        Route::post('{academicDate}/assignment', 'createAssignment')->name('assignment');
    });

    /* ===================== JITHMI · Assignments & Risk ================= */
    Route::prefix('assignments')->name('assignments.')->group(function () {
        Route::controller(RiskController::class)->group(function () {
            Route::get('dashboard', 'dashboard')->name('dashboard');
            Route::get('ranking', 'ranking')->name('ranking');
            Route::get('recommendation', 'recommendation')->name('recommendation');
            Route::get('workload', 'workload')->name('workload');
            Route::get('plan', 'plan')->name('plan');
            Route::post('what-if', 'whatIf')->name('what-if');
            Route::post('recalculate', 'recalculate')->name('recalculate');
            Route::get('{assignment}/risk-history', 'history')->whereNumber('assignment')->name('risk-history');
        });
        Route::controller(AssignmentHistoryController::class)->prefix('history')->name('history.')->group(function () {
            Route::get('progress', 'progress')->name('progress');
            Route::get('submissions', 'submissions')->name('submissions');
            Route::get('completed', 'completed')->name('completed');
            Route::get('overdue', 'overdue')->name('overdue');
        });
        Route::post('{assignment}/progress', [AssignmentController::class, 'progress'])->whereNumber('assignment')->name('progress');
        Route::post('{assignment}/complete', [AssignmentController::class, 'complete'])->whereNumber('assignment')->name('complete');
        Route::post('{assignment}/reopen', [AssignmentController::class, 'reopen'])->whereNumber('assignment')->name('reopen');
    });
    Route::apiResource('assignments', AssignmentController::class)->whereNumber('assignment');
});
