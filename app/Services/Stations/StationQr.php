<?php

namespace App\Services\Stations;

use App\Models\Station;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * The print-ready QR image for a Station's sticker.
 *
 * Encodes the public station URL on the main web app domain (ADR-0005):
 * `{webapp}/s/{short code}`. SVG is the print format of choice (scales to any
 * sticker size); PNG is rendered through GD for tools that can't take SVG.
 */
class StationQr
{
    /**
     * Pixel size of the rendered PNG; SVG scales freely so it only sets the
     * coordinate system there.
     */
    private const SIZE = 512;

    /**
     * The URL a printed sticker carries. Everything in it must survive
     * renames and re-seeds, which is why it is only the immutable short code
     * on the main web app domain.
     */
    public function url(Station $station): string
    {
        return rtrim(config('app.webapp_url'), '/').'/s/'.$station->short_code;
    }

    public function svg(Station $station): string
    {
        $renderer = new ImageRenderer(new RendererStyle(self::SIZE, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($this->url($station));
    }

    public function png(Station $station): string
    {
        return (new Writer(new GDLibRenderer(self::SIZE)))->writeString($this->url($station));
    }
}
