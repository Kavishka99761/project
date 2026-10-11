<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin \App\Models\DataChange */
class DataChangeResource extends JsonResource
{
    private bool $includeDiff = false;

    public function withDiff(): static
    {
        $this->includeDiff = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'changed_at' => $this->changed_at?->toIso8601String(),
            'table' => $this->table_name,
            'table_label' => Str::headline($this->table_name),
            'operation' => $this->operationName(),
            'record_id' => $this->record_id,
            'summary' => $this->summary(),
            'changed_columns' => $this->changedColumns(),
            'source' => $this->source,
            'actor_user_id' => $this->actor_user_id,
            'request_id' => $this->request_id,
            'activity' => $this->whenLoaded('activity', fn () => $this->activity ? [
                'id' => $this->activity->id,
                'action' => $this->activity->action,
                'description' => $this->activity->description,
            ] : null),
            'db_login' => $this->db_login,
            'app_name' => $this->app_name,
            $this->mergeWhen($this->includeDiff, fn () => [
                'old_values' => $this->old_values,
                'new_values' => $this->new_values,
                'diff' => $this->diff(),
            ]),
        ];
    }
}
