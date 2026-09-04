<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Smart store redirect for QR codes and shared links.
 *
 * The marketing site itself lives in front-end/apps/landing_page and is
 * deployed separately; this controller only routes devices to the right place.
 */
class LandingPageController extends Controller
{
    private const APP_STORE_URL = 'https://apps.apple.com/mk/app/fit-nation-the-movement/id6766201705';

    private const PLAY_STORE_URL = 'https://play.google.com/store/apps/details?id=com.fitnation.app';

    private const LANDING_URL = 'https://joinfitnation.com';

    /**
     * Sends each device to its app store; anything else goes to the marketing site.
     */
    public function storeRedirect(Request $request): RedirectResponse
    {
        $userAgent = strtolower($request->userAgent() ?? '');

        if (str_contains($userAgent, 'android')) {
            return $this->uncachedRedirect(self::PLAY_STORE_URL);
        }

        if (preg_match('/iphone|ipad|ipod/', $userAgent)) {
            return $this->uncachedRedirect(self::APP_STORE_URL);
        }

        return $this->uncachedRedirect(self::LANDING_URL);
    }

    /**
     * The redirect target depends on the requesting device, so the response
     * must never be cached by a CDN or proxy and replayed to other devices.
     */
    private function uncachedRedirect(string $url): RedirectResponse
    {
        return redirect()->away($url)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, private',
            'Vary' => 'User-Agent',
        ]);
    }
}
