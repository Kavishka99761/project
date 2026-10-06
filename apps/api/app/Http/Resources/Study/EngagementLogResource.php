<?php

namespace App\Http\Resources\Study;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\EngagementLog */
class EngagementLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'study_session_id' => $this->study_session_id,
            'score' => $this->score,
            'level' => $this->level->value,
            'level_label' => $this->level->label(),
            'source' => $this->source,
            'concentration' => $this->concentration,
            'signals' => $this->signals,
            'note' => $this->note,
            'logged_at' => $this->logged_at->toIso8601String(),
        ];
    }
}
