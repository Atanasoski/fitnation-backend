<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

/**
 * Public marketing / app download landing page.
 */
class LandingPageController extends Controller
{
    private const APP_STORE_URL = 'https://apps.apple.com/mk/app/fit-nation-the-movement/id6766201705';

    private const PLAY_STORE_URL = 'https://play.google.com/store/apps/details?id=com.fitnation.app';

    public function english(): View
    {
        return $this->render('en');
    }

    public function macedonian(): View
    {
        return $this->render('mk');
    }

    /**
     * Smart store redirect — QR codes and shared links point here;
     * sends each device to its app store.
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

        return $this->uncachedRedirect(route('landing'));
    }

    private function render(string $locale): View
    {
        App::setLocale($locale);

        // Marketing stat only — a DB/cache outage or an empty table must not break the public page.
        $exerciseCount = rescue(
            fn () => Cache::remember('landing.exercise-count', 21600, fn () => Exercise::query()->count('*')),
            rescue: 171,
            report: false,
        ) ?: 171;

        return view('landing', ['exerciseCount' => $exerciseCount]);
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
