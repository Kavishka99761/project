<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * Data Export & Backup Controller.
 *
 * GET  /api/export          — download full profile as JSON
 * POST /api/backup/firebase — push snapshot to Firestore
 * GET  /api/backup/status   — last backup metadata
 */
class ExportController extends Controller
{
    public function __construct(private readonly FirebaseService $firebase) {}

    /** GET /api/export — full JSON export (assignments, documents, sessions, dates). */
    public function export(Request $request): \Illuminate\Http\Response
    {
        $user = $request->user()->load([
            'modules',
            'documents.summaries',
            'assignments',
            'studySessions',
            'academicDates',
        ]);

        $payload = [
            'exportedAt'    => now()->toIso8601String(),
            'user'          => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'program'       => $user->program,
                'academic_year' => $user->academic_year,
            ],
            'modules'       => $user->modules,
            'documents'     => $user->documents->map(fn ($d) => [
                'id'      => $d->id,
                'title'   => $d->title,
                'topic'   => $d->topic,
                'type'    => $d->type,
                'pages'   => $d->pages,
                'summaries' => $d->summaries,
            ]),
            'assignments'   => $user->assignments,
            'studySessions' => $user->studySessions,
            'academicDates' => $user->academicDates,
        ];

        $filename = 'acadealert-export-' . now()->format('Ymd-His') . '.json';

        return Response::make(
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            200,
            [
                'Content-Type'        => 'application/json',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }

    /** POST /api/backup/firebase — push current snapshot to Firestore. */
    public function backupToFirebase(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'modules',
            'assignments',
            'academicDates',
            'studySessions',
        ]);

        $payload = [
            'modules'       => $user->modules->toArray(),
            'assignments'   => $user->assignments->toArray(),
            'academicDates' => $user->academicDates->toArray(),
            'studySessions' => $user->studySessions->map(fn ($s) => [
                'id'             => $s->id,
                'status'         => $s->status,
                'actual_minutes' => $s->actual_minutes,
                'started_at'     => $s->started_at,
            ])->toArray(),
        ];

        $success = $this->firebase->backupUserData($user, $payload);

        if (! $success) {
            return response()->json([
                'message' => 'Firebase backup skipped (credentials not configured). Use /api/export for local backup.',
                'success' => false,
            ]);
        }

        return response()->json([
            'message'    => 'Backup pushed to Firebase successfully.',
            'success'    => true,
            'backedUpAt' => now()->toIso8601String(),
        ]);
    }
}
