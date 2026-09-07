<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PartnerIdentity extends Model
{
    protected $fillable = [
        'partner_id',
        'primary_color',
        'secondary_color',
        'logo',
        'font_family',
        'background_color',
        'card_background_color',
        'text_primary_color',
        'text_secondary_color',
        'text_on_primary_color',
        'success_color',
        'warning_color',
        'danger_color',
        'accent_color',
        'border_color',
        'background_pattern',
        'primary_color_dark',
        'secondary_color_dark',
        'background_color_dark',
        'card_background_color_dark',
        'text_primary_color_dark',
        'text_secondary_color_dark',
        'text_on_primary_color_dark',
        'success_color_dark',
        'warning_color_dark',
        'danger_color_dark',
        'accent_color_dark',
        'border_color_dark',
    ];

    /**
     * Where the logo is served from. See imageUrl().
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => self::imageUrl($this->logo));
    }

    /**
     * Where the background pattern is served from. See imageUrl().
     */
    protected function backgroundPatternUrl(): Attribute
    {
        return Attribute::get(fn () => self::imageUrl($this->background_pattern));
    }

    /**
     * An identity image is recorded one of three ways. Uploads (PartnerController)
     * go to the public disk and are stored as "storage/<path>": their URL is the
     * disk's, which on Laravel Cloud is the bucket's, not the app host's. Seeded
     * identities point at a file under public/ ("/images/…"). Anything already
     * absolute is used as is.
     */
    private static function imageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return Storage::disk('public')->url(substr($path, strlen('storage/')));
        }

        return asset($path);
    }

    /**
     * Get the partner that owns the identity.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
