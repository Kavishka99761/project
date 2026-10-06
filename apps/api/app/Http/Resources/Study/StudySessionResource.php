<?php

namespace App\Http\Resources\Study;

use App\Http\Resources\Platform\ModuleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StudySession */
class StudySessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'activity' => $this->activity->value,
            'activity_label' => $this->activity->label(),
            'activity_icon' => $this->activity->meta()['icon'],
            'goal' => $this->goal,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_live' => $this->isLive(),
            'planned_minutes' => $this->planned_minutes,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'last_resumed_at' => $this->last_resumed_at?->toIso8601String(),
            'paused_at' => $this->paused_at?->toIso8601String(),
            // The timer is server-authoritative; clients add (now - server_time).
            'elapsed_seconds' => $this->elapsedFocusSeconds(),
            'current_break_seconds' => $this->currentBreakSeconds(),
            'focus_seconds' => $this->focus_seconds,
            'break_seconds' => $this->break_seconds,
            'pause_count' => $this->pause_count,
            'break_count' => $this->break_count,
            'actual_minutes' => $this->actual_minutes,
            'avg_engagement' => $this->avg_engagement,
            'focus_score' => $this->focus_score,
            'rating' => $this->rating,
            'mood' => $this->mood,
            'notes' => $this->notes,
            'feedback' => $this->feedback,
            'module' => ModuleResource::brief($this->whenLoaded('module', fn () => $this->module, null)),
            'document' => $this->whenLoaded('document', fn () => $this->document ? [
                'id' => $this->document->id, 'title' => $this->document->title, 'kind' => $this->document->kind->value,
            ] : null),
            'assignment' => $this->whenLoaded('assignment', fn () => $this->assignment ? [
                'id' => $this->assignment->id, 'title' => $this->assignment->title,
                'progress' => $this->assignment->progress, 'risk_score' => $this->assignment->risk_score,
                'risk_level' => $this->assignment->risk_level?->value,
            ] : null),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => [
                'type' => $e->type, 'payload' => $e->payload, 'occurred_at' => $e->occurred_at->toIso8601String(),
            ])),
            'engagement' => EngagementLogResource::collection($this->whenLoaded('engagementLogs')),
            'server_time' => now()->toIso8601String(),
        ];
    }
}
