<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'initials' => $this->initials,
            'avatar_url' => $this->avatar_url,
            'student_id' => $this->student_id,
            'university' => $this->university,
            'program' => $this->program,
            'academic_year' => $this->academic_year,
            'phone' => $this->phone,
            'bio' => $this->bio,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'settings' => SettingsResource::make($this->whenLoaded('settings')),
        ];
    }
}
