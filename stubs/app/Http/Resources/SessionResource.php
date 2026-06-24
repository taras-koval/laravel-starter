<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/** @mixin PersonalAccessToken */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'browser' => $this->browser,
            'platform' => $this->platform,
            'device' => $this->device,
            'device_type' => $this->device_type,
            'country' => $this->country,
            'region' => $this->region,
            'city' => $this->city,
            'ip_address' => $this->ip_address,
            'last_used_at' => $this->last_used_at?->toUserDateTime(null, true),
            'created_at' => $this->created_at->toUserDateTime(null, true),
            'is_current' => $this->id === $request->user()?->currentAccessToken()?->id,
        ];
    }
}
