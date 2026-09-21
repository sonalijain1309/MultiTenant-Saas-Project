<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'is_active' => (bool) $this->is_active,
            'tenant' => new TenantResource($this->whenLoaded('tenant', fn () => $this->tenant)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
