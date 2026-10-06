<?php

namespace App\Http\Resources\Assistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AcademicDate */
class AcademicDateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'date' => $this->date->toDateString(),
            'time' => $this->time ? substr((string) $this->time, 0, 5) : null,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'icon' => $this->type->meta()['icon'],
            'color' => $this->type->meta()['color'],
            'confidence' => $this->confidence,
            'context' => $this->context,
            'matched_text' => $this->matched_text,
            'page_number' => $this->page_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'days_until' => (int) now()->startOfDay()->diffInDays($this->date, false),
            'calendar_event_id' => $this->calendar_event_id,
            'document' => $this->whenLoaded('document', fn () => $this->document ? [
                'id' => $this->document->id, 'title' => $this->document->title, 'category' => $this->document->category->value,
            ] : null),
            'assignment' => $this->whenLoaded('assignment', fn () => $this->assignment ? ['id' => $this->assignment->id, 'title' => $this->assignment->title] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
