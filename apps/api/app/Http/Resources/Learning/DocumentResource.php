<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Platform\ModuleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Document */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = $request->routeIs('documents.show', 'documents.store', 'documents.update', 'documents.extract', 'documents.note');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'topic' => $this->topic,
            'description' => $this->description,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'icon' => $this->kind->meta()['icon'],
            'color' => $this->kind->meta()['color'],
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size_bytes' => $this->size_bytes,
            'pages' => $this->pages,
            'word_count' => $this->word_count,
            'reading_minutes' => $this->reading_minutes,
            'extraction_status' => $this->extraction_status->value,
            'extraction_label' => $this->extraction_status->label(),
            'extraction_error' => $this->extraction_error,
            'extracted_at' => $this->extracted_at?->toIso8601String(),
            'keywords' => array_slice(array_column((array) $this->keywords, 'term'), 0, $detail ? 12 : 5),
            'is_favorite' => $this->is_favorite,
            'module' => ModuleResource::brief($this->whenLoaded('module', fn () => $this->module, null)),
            'summaries_count' => $this->whenCounted('summaries'),
            'study_aids_count' => $this->whenCounted('studyAids'),
            'content' => $this->when($detail, fn () => $this->content),
            'excerpt' => $this->when(! $detail, fn () => mb_substr((string) $this->content, 0, 220)),
            'last_opened_at' => $this->last_opened_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
