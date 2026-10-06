<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\TrashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trash — restore or permanently delete removed records.
 */
class TrashController extends Controller
{
    public function __construct(private readonly TrashService $trash) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->trash->list($request->user()));
    }

    public function restore(Request $request, string $type, int $id): JsonResponse
    {
        abort_unless(isset(TrashService::TYPES[$type]), 404);
        $record = $this->trash->restore($request->user(), $type, $id);
        activity()->action('trash.restored')->describe('Restored '.TrashService::TYPES[$type][2].' “'.$record->title.'”')->on($record);

        return response()->json(['message' => 'Restored.', 'type' => $type, 'id' => $record->id]);
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        abort_unless(isset(TrashService::TYPES[$type]), 404);
        $record = $this->trash->find($request->user(), $type, $id);
        activity()->action('trash.purged')->describe('Permanently deleted '.TrashService::TYPES[$type][2].' “'.$record->title.'”');
        $this->trash->purge($request->user(), $type, $id);

        return response()->json(['message' => 'Permanently deleted.']);
    }

    public function empty(Request $request): JsonResponse
    {
        $count = $this->trash->empty($request->user());
        activity()->action('trash.emptied')->describe("Emptied the trash ({$count} items)");

        return response()->json(['message' => 'Trash emptied.', 'deleted' => $count]);
    }
}
