<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerVisualIdentityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Core branding
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'logo' => $this->logo_url,
            'font_family' => $this->font_family,

            // Essential colors
            'background_color' => $this->background_color,
            'card_background_color' => $this->card_background_color,
            'text_primary_color' => $this->text_primary_color,
            'text_secondary_color' => $this->text_secondary_color,
            'text_on_primary_color' => $this->text_on_primary_color,

            // Semantic colors
            'success_color' => $this->success_color,
            'warning_color' => $this->warning_color,
            'danger_color' => $this->danger_color,

            // Optional styling
            'accent_color' => $this->accent_color,
            'border_color' => $this->border_color,
            'background_image' => $this->background_pattern_url,

            // Dark mode colors
            'primary_color_dark' => $this->primary_color_dark,
            'secondary_color_dark' => $this->secondary_color_dark,
            'background_color_dark' => $this->background_color_dark,
            'card_background_color_dark' => $this->card_background_color_dark,
            'text_primary_color_dark' => $this->text_primary_color_dark,
            'text_secondary_color_dark' => $this->text_secondary_color_dark,
            'text_on_primary_color_dark' => $this->text_on_primary_color_dark,
            'success_color_dark' => $this->success_color_dark,
            'warning_color_dark' => $this->warning_color_dark,
            'danger_color_dark' => $this->danger_color_dark,
            'accent_color_dark' => $this->accent_color_dark,
            'border_color_dark' => $this->border_color_dark,
        ];
    }
}
