<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Enums\AcademicDateStatus;
use App\Enums\AcademicDateType;
use App\Enums\AssignmentPriority;
use App\Enums\AssignmentStatus;
use App\Enums\DocumentKind;
use App\Enums\EngagementLevel;
use App\Enums\EventType;
use App\Enums\KnowledgeCategory;
use App\Enums\ModuleKey;
use App\Enums\NotificationType;
use App\Enums\RiskLevel;
use App\Enums\StudyActivity;
use App\Enums\StudyAidType;
use App\Enums\SummaryLength;
use App\Http\Controllers\Controller;
use App\Services\Ai\LlmClient;
use App\Services\Assistant\SemanticReranker;
use App\Services\Firebase\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Health, configuration status and the enum catalogue the web client uses
 * for dropdowns, colours and icons (single source of truth).
 */
class SystemController extends Controller
{
    public function health(): JsonResponse
    {
        try {
            DB::select('SELECT 1 AS ok');
            $database = 'up';
        } catch (\Throwable) {
            $database = 'down';
        }

        return response()->json([
            'status' => $database === 'up' ? 'ok' : 'degraded',
            'service' => 'edu-smart-api',
            'version' => config('edusmart.version'),
            'database' => $database,
            'time' => now()->toIso8601String(),
        ], $database === 'up' ? 200 : 503);
    }

    public function status(FirebaseService $firebase, SemanticReranker $reranker, LlmClient $llm): JsonResponse
    {
        $server = DB::selectOne("SELECT CAST(SERVERPROPERTY('ProductVersion') AS nvarchar(64)) AS version, CAST(SERVERPROPERTY('Edition') AS nvarchar(128)) AS edition, DB_NAME() AS db");

        return response()->json([
            'app' => ['name' => config('app.name'), 'version' => config('edusmart.version'), 'environment' => app()->environment(), 'timezone' => config('app.timezone')],
            'database' => ['driver' => DB::getDriverName(), 'name' => $server->db ?? null, 'server' => trim(($server->edition ?? '').' '.($server->version ?? ''))],
            'firebase' => ['enabled' => $firebase->enabled(), 'emulator' => $firebase->usesEmulator(), 'project_id' => $firebase->projectId(), 'reachable' => $firebase->ping()],
            'ai_engine' => ['configured' => (string) config('edusmart.ai_engine.url') !== '', 'available' => $reranker->available()],
            'llm' => ['enabled' => $llm->enabled(), 'model' => $llm->enabled() ? $llm->model() : null],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'modules' => ModuleKey::options(),
            'document_kinds' => DocumentKind::options(),
            'summary_lengths' => SummaryLength::options(),
            'study_aid_types' => StudyAidType::options(),
            'study_activities' => StudyActivity::options(),
            'engagement_levels' => EngagementLevel::options(),
            'knowledge_categories' => KnowledgeCategory::options(),
            'academic_date_types' => AcademicDateType::options(),
            'academic_date_statuses' => AcademicDateStatus::options(),
            'event_types' => EventType::options(),
            'assignment_priorities' => AssignmentPriority::options(),
            'assignment_statuses' => AssignmentStatus::options(),
            'risk_levels' => RiskLevel::options(),
            'notification_types' => NotificationType::options(),
            'assignment_types' => collect(['coursework', 'project', 'lab', 'report', 'presentation', 'exam_prep', 'quiz', 'other'])
                ->map(fn ($t) => ['value' => $t, 'label' => ucwords(str_replace('_', ' ', $t))])->all(),
            'upload' => [
                'max_mb' => (int) (config('edusmart.uploads.max_kb') / 1024),
                'learning_extensions' => config('edusmart.uploads.learning_extensions'),
                'knowledge_extensions' => config('edusmart.uploads.knowledge_extensions'),
            ],
            'risk_levels_thresholds' => config('edusmart.risk.levels'),
            'engagement_thresholds' => config('edusmart.study.engagement'),
        ]);
    }
}
