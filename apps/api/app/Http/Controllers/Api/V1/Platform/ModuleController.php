<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ModuleRequest;
use App\Http\Resources\Platform\ModuleResource;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Common Platform Layer — module registration and selection. Deleting a
 * module keeps the student's material and simply unfiles it.
 */
class ModuleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $modules = $request->user()->modules()
            ->withCount(['documents', 'assignments', 'studySessions', 'knowledgeDocuments'])
            ->when($request->boolean('active'), fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('code')
            ->get();

        return ModuleResource::collection($modules);
    }

    public function store(ModuleRequest $request): JsonResponse
    {
        $module = $request->user()->modules()->create($request->validated() + [
            'sort_order' => (int) $request->user()->modules()->max('sort_order') + 1,
        ]);
        activity()->action('modules.registered')->describe("Registered module {$module->code} — {$module->name}")->on($module);

        return ModuleResource::make($module)->response()->setStatusCode(201);
    }

    public function show(Module $module): ModuleResource
    {
        return ModuleResource::make($module->loadCount(['documents', 'assignments', 'studySessions', 'knowledgeDocuments']));
    }

    public function update(ModuleRequest $request, Module $module): ModuleResource
    {
        $module->update($request->validated());
        activity()->action('modules.updated')->describe("Updated module {$module->code}")->on($module);

        return ModuleResource::make($module);
    }

    public function destroy(Module $module): JsonResponse
    {
        activity()->action('modules.deleted')->describe("Removed module {$module->code} — {$module->name}")->on($module);
        $module->delete();

        return response()->json(['message' => 'Module removed. Its documents and assignments were kept.']);
    }

    /** Persist drag-and-drop order. */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);
        foreach ($data['order'] as $position => $id) {
            $request->user()->modules()->whereKey($id)->update(['sort_order' => $position]);
        }
        activity()->action('modules.reordered')->describe('Reordered modules');

        return response()->json(['message' => 'Order saved.']);
    }
}
