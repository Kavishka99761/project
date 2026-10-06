<?php

namespace App\Http\Resources\Assistant;

use App\Http\Resources\Platform\ModuleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\KnowledgeDocument */
class KnowledgeDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'icon' => $this->category->meta()['icon'],
            'color' => $this->category->meta()['color'],
            'original_name' => $this->original_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'pages' => $this->pages,
            'word_count' => $this->word_count,
            'chunk_count' => $this->chunk_count,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'error' => $this->error,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'indexed_at' => $this->indexed_at?->toIso8601String(),
            'module' => ModuleResource::brief($this->whenLoaded('module', fn () => $this->module, null)),
            'dates_count' => $this->whenCounted('academicDates'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
