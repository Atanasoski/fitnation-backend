<?php

namespace App\Helpers;

use App\Models\PartnerIdentity;

class ColorHelper
{
    /**
     * Process partner identity colors and return hex values with defaults from config.
     */
    public static function processPartnerColors(?PartnerIdentity $identity): array
    {
        $defaults = config('branding.light');

        if (! $identity) {
            return $defaults;
        }

        return [
            'primary' => $identity->primary_color ?? $defaults['primary'],
            'secondary' => $identity->secondary_color ?? $defaults['secondary'],
            'background' => $identity->background_color ?? $defaults['background'],
            'card_background' => $identity->card_background_color ?? $defaults['card_background'],
            'text_primary' => $identity->text_primary_color ?? $defaults['text_primary'],
            'text_secondary' => $identity->text_secondary_color ?? $defaults['text_secondary'],
            'text_on_primary' => $identity->text_on_primary_color ?? $defaults['text_on_primary'],
            'success' => $identity->success_color ?? $defaults['success'],
            'warning' => $identity->warning_color ?? $defaults['warning'],
            'danger' => $identity->danger_color ?? $defaults['danger'],
            'accent' => $identity->accent_color ?? $defaults['accent'],
            'border' => $identity->border_color ?? $defaults['border'],
        ];
    }

    /**
     * Process partner identity dark-mode colors, keyed like processPartnerColors(),
     * with defaults from config.
     */
    public static function processPartnerDarkColors(?PartnerIdentity $identity): array
    {
        $defaults = config('branding.dark');

        if (! $identity) {
            return $defaults;
        }

        return collect($defaults)
            ->map(fn (string $default, string $key) => $identity->{$key.'_color_dark'} ?? $default)
            ->all();
    }

    /**
     * Get dark mode color palette array for display.
     */
    public static function getDarkColorPalette(?PartnerIdentity $identity): array
    {
        $colors = self::processPartnerDarkColors($identity);

        return array_map(
            fn (string $key, string $value) => ['name' => ucwords(str_replace('_', ' ', $key)), 'value' => $value],
            array_keys($colors),
            $colors,
        );
    }
}
