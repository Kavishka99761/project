<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Platform\ModuleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Summary */
class SummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = ! $request->routeIs('summaries.index', 'documents.show');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'length' => $this->length->value,
            'length_label' => $this->length->label(),
            'method' => $this->method,
            'content' => $detail ? $this->content : null,
            'excerpt' => mb_substr(preg_replace('/^### .*$/m', '', $this->content) ?? '', 0, 240),
            'bullet_points' => $this->when($detail, $this->bullet_points),
            'keywords' => $this->keywords,
            'key_concepts' => $this->when($detail, $this->key_concepts),
            'highlights' => $this->when($detail, $this->highlights),
            'word_count' => $this->word_count,
            'source_word_count' => $this->source_word_count,
            'sentence_count' => $this->sentence_count,
            'compression' => $this->source_word_count ? (int) round($this->word_count / $this->source_word_count * 100) : null,
            'is_favorite' => $this->is_favorite,
            'document' => $this->whenLoaded('document', fn () => $this->document ? [
                'id' => $this->document->id,
                'title' => $this->document->title,
                'deleted' => $this->document->trashed(),
            ] : null),
            'module' => ModuleResource::brief($this->whenLoaded('module', fn () => $this->module, null)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
