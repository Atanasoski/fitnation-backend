<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'state' => $this->derivedState(),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'cancelled_at' => $this->cancelled_at,
            'plan' => $this->whenLoaded('subscriptionPlan', function () {
                return $this->subscriptionPlan
                    ? [
                        'id' => $this->subscriptionPlan->id,
                        'name' => $this->subscriptionPlan->name,
                        'price' => (string) $this->subscriptionPlan->price,
                        'billing_cycle' => $this->subscriptionPlan->billing_cycle?->value,
                    ]
                    : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
