<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Partner as the Join Prompt shows it: name, branding essentials, and the
 * benefits list the admin maintains (GLOSSARY: Join Prompt). Shaped after
 * PartnerResource so the mobile client can reuse its branding handling.
 */
class NearbyPartnerResource extends JsonResource
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
            // Always false in practice — only joinable Partners are listed and
            // the House Partner never is (acceptsJoins) — but derived rather
            // than hardcoded so every partner payload carries is_house (#95).
            'is_house' => $this->isHouse(),
            'benefits' => $this->benefits ?? [],
            'visual_identity' => $this->whenLoaded('identity', function () {
                return new PartnerVisualIdentityResource($this->identity);
            }),
        ];
    }
}
