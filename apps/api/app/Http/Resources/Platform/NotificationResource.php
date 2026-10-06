<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\UserNotification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'module' => $this->module?->value,
            'type' => $this->type?->value,
            'title' => $this->title,
            'message' => $this->message,
            'icon' => $this->icon,
            'action_url' => $this->action_url,
            'data' => $this->data,
            'read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
