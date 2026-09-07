<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentTypeResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'display_order' => $this->display_order,
            // Server-owned: whether an exercise on this equipment takes a logged weight.
            'supports_added_weight' => (bool) $this->supports_added_weight,
        ];
    }
}
