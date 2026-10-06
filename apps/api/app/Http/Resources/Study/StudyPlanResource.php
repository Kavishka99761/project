<?php

namespace App\Http\Resources\Study;

use App\Http\Resources\Platform\ModuleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StudyPlan */
class StudyPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_date' => $this->plan_date->toDateString(),
            'planned_minutes' => $this->planned_minutes,
            'title' => $this->title,
            'notes' => $this->notes,
            'is_done' => $this->is_done,
            'module' => ModuleResource::brief($this->whenLoaded('module', fn () => $this->module, null)),
            'assignment' => $this->whenLoaded('assignment', fn () => $this->assignment ? ['id' => $this->assignment->id, 'title' => $this->assignment->title] : null),
            'actual_minutes' => $this->when(isset($this->actual_minutes), fn () => (int) $this->actual_minutes),
        ];
    }
}
