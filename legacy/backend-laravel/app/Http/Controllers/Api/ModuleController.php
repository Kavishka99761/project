<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Common Platform Layer — module (subject) management.
 * Every other module references these via module_id.
 */
class ModuleController extends Controller
{
    /** GET /api/modules — list the student's modules with light counts. */
    public function index(Request $request): JsonResponse
    {
        $modules = $request->user()->modules()
            ->withCount(['documents', 'assignments'])
            ->orderBy('code')
            ->get();

        return response()->json($modules);
    }

    /** POST /api/modules */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'  => ['required', 'string', 'max:32'],
            'name'  => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:32'],
            'icon'  => ['nullable', 'string', 'max:48'],
        ]);

        $module = $request->user()->modules()->create([
            'code'  => $data['code'],
            'name'  => $data['name'],
            'color' => $data['color'] ?? 'primary',
            'icon'  => $data['icon'] ?? 'book',
        ]);

        return response()->json($module, 201);
    }

    /** PUT /api/modules/{module} */
    public function update(Request $request, Module $module): JsonResponse
    {
        $this->authorizeOwner($request, $module);

        $data = $request->validate([
            'code'  => ['sometimes', 'string', 'max:32'],
            'name'  => ['sometimes', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:32'],
            'icon'  => ['nullable', 'string', 'max:48'],
        ]);

        $module->update($data);

        return response()->json($module);
    }

    /** DELETE /api/modules/{module} */
    public function destroy(Request $request, Module $module): JsonResponse
    {
        $this->authorizeOwner($request, $module);
        $module->delete();

        return response()->json(['message' => 'Module deleted']);
    }

    private function authorizeOwner(Request $request, Module $module): void
    {
        abort_unless($module->user_id === $request->user()->id, 403, 'This module belongs to another user.');
    }
}
