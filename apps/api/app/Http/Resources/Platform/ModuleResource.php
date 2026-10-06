<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Module */
class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'icon' => $this->icon,
            'credits' => $this->credits,
            'semester' => $this->semester,
            'lecturer' => $this->lecturer,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'counts' => $this->when(isset($this->documents_count), fn () => [
                'documents' => $this->documents_count,
                'assignments' => $this->assignments_count ?? 0,
                'study_sessions' => $this->study_sessions_count ?? 0,
                'knowledge_documents' => $this->knowledge_documents_count ?? 0,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** Compact form embedded in other resources. */
    public static function brief($module): ?array
    {
        return $module ? ['id' => $module->id, 'code' => $module->code, 'name' => $module->name, 'color' => $module->color, 'icon' => $module->icon] : null;
    }
}
