<?php

namespace App\Http\Resources\Learning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StudyAid */
class StudyAidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'icon' => $this->type->meta()['icon'],
            'color' => $this->type->meta()['color'],
            'title' => $this->title,
            'content' => $this->content,
            'item_count' => $this->item_count,
            'document' => $this->whenLoaded('document', fn () => $this->document ? ['id' => $this->document->id, 'title' => $this->document->title] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
