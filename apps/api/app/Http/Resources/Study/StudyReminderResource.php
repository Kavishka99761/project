<?php

namespace App\Http\Resources\Study;

use App\Http\Resources\Platform\ModuleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StudyReminder */
class StudyReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'remind_time' => substr((string) $this->remind_time, 0, 5),
            'days' => $this->days,
            'is_active' => $this->is_active,
            'last_sent_at' => $this->last_sent_at?->toIso8601String(),
            'module' => ModuleResource::brief($this->whenLoaded('module', fn () => $this->module, null)),
        ];
    }
}
