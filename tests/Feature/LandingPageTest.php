<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    private const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';

    private const IPHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    public function test_english_landing_page_renders(): void
    {
        $response = $this->get('/landing');

        $response->assertStatus(200);
        $response->assertSee('lang="en"', false);
        $response->assertSee('Train smarter.');
    }

    public function test_macedonian_landing_page_renders_in_macedonian(): void
    {
        $response = $this->get('/landing/mk');

        $response->assertStatus(200);
        $response->assertSee('lang="mk"', false);
        $response->assertSee('Тренирај попаметно.');
    }

    public function test_get_redirects_android_to_play_store(): void
    {
        $response = $this->get('/get', ['User-Agent' => self::ANDROID_UA]);

        $response->assertStatus(302);
        $response->assertRedirect('https://play.google.com/store/apps/details?id=com.fitnation.app');
    }

    public function test_get_redirects_iphone_to_app_store(): void
    {
        $response = $this->get('/get', ['User-Agent' => self::IPHONE_UA]);

        $response->assertStatus(302);
        $response->assertRedirect('https://apps.apple.com/mk/app/fit-nation-the-movement/id6766201705');
    }

    public function test_get_falls_back_to_landing_for_desktop(): void
    {
        $response = $this->get('/get', ['User-Agent' => self::DESKTOP_UA]);

        $response->assertStatus(302);
        $response->assertRedirect(route('landing'));
    }

    public function test_landing_page_survives_cache_or_database_outage(): void
    {
        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('database unavailable'));

        $response = $this->get('/landing');

        $response->assertStatus(200);
        $response->assertSee('171 exercises');
    }

    public function test_get_response_is_not_cacheable(): void
    {
        $response = $this->get('/get', ['User-Agent' => self::ANDROID_UA]);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('User-Agent', $response->headers->get('Vary'));
    }
}
