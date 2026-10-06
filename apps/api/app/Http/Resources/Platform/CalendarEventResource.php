<?php

namespace App\Http\Resources\Platform;

use App\Services\Platform\CalendarService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CalendarEvent */
class CalendarEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return app(CalendarService::class)->fromEvent($this->resource) + [
            'reminder_at' => $this->reminder_at?->toIso8601String(),
            'reminder_sent_at' => $this->reminder_sent_at?->toIso8601String(),
            'academic_date_id' => $this->relationLoaded('academicDate') ? $this->academicDate?->id : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
