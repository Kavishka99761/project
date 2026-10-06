<?php

namespace App\Http\Resources\Assignments;

use App\Http\Resources\Platform\ModuleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Assignment
 *
 * Pass a live assessment with ->additional(['risk' => …]) or set
 * $resource->liveRisk to embed the full risk breakdown.
 */
class AssignmentResource extends JsonResource
{
    /** @var array<int, array<string, mixed>> live assessments keyed by assignment id */
    public static array $risk = [];

    public function toArray(Request $request): array
    {
        $live = self::$risk[$this->id] ?? null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'deadline' => $this->deadline->toIso8601String(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'priority_color' => $this->priority->meta()['color'],
            'weight_percent' => $this->weight_percent,
            'estimated_hours' => $this->estimated_hours,
            'completed_hours' => $this->completed_hours,
            'remaining_hours' => $this->remainingHours(),
            'progress' => $this->progress,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'bucket' => $this->bucket(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'was_overdue' => $this->was_overdue,
            'risk_score' => $live['score'] ?? $this->risk_score,
            'risk_level' => ($live['level'] ?? $this->risk_level)?->value,
            'risk_color' => ($live['level'] ?? $this->risk_level)?->meta()['color'],
            'priority_rank' => $live['rank'] ?? $this->priority_rank,
            'risk_updated_at' => $this->risk_updated_at?->toIso8601String(),
            'risk' => $live ? array_merge($live, ['level' => $live['level']->value]) : null,
            'notes' => $this->notes,
            'module' => ModuleResource::brief($this->whenLoaded('module', fn () => $this->module, null)),
            'academic_date' => $this->whenLoaded('academicDate', fn () => $this->academicDate ? [
                'id' => $this->academicDate->id, 'title' => $this->academicDate->title, 'document' => $this->academicDate->document?->title,
            ] : null),
            'study_minutes' => $this->when(isset($this->study_minutes), fn () => (int) $this->study_minutes),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
