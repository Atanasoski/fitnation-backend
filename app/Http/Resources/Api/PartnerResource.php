<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'is_active' => $this->is_active,
            'visual_identity' => $this->whenLoaded('identity', function () {
                return new PartnerVisualIdentityResource($this->identity);
            }),
            'users' => $this->whenLoaded('users', function () {
                return UserResource::collection($this->users);
            })
        ];
    }
}
