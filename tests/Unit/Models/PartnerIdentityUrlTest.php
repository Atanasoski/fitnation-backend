<?php

namespace Tests\Unit\Models;

use App\Models\PartnerIdentity;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Where a partner's logo (or background pattern) is served from. Uploads land
 * on the public disk and are recorded as "storage/partners/<file>", so their
 * URL is the disk's — on Laravel Cloud, the bucket's — not the app host's.
 */
class PartnerIdentityUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('https://api.test');
        URL::forceScheme('https');
        config()->set('filesystems.disks.public.url', 'https://bucket.test/fitnation');
    }

    public function test_an_uploaded_logo_is_served_from_the_public_disk(): void
    {
        $identity = new PartnerIdentity(['logo' => 'storage/partners/abc.jpg']);

        $this->assertSame('https://bucket.test/fitnation/partners/abc.jpg', $identity->logo_url);
    }

    public function test_a_seeded_path_under_public_is_served_from_the_app(): void
    {
        $identity = new PartnerIdentity(['logo' => '/images/partners/premium-logo.png']);

        $this->assertSame('https://api.test/images/partners/premium-logo.png', $identity->logo_url);
    }

    public function test_an_absolute_url_is_used_as_is(): void
    {
        $identity = new PartnerIdentity(['logo' => 'https://cdn.test/logo.png']);

        $this->assertSame('https://cdn.test/logo.png', $identity->logo_url);
    }

    public function test_no_logo_is_no_url(): void
    {
        $this->assertNull((new PartnerIdentity(['logo' => null]))->logo_url);
        $this->assertNull((new PartnerIdentity(['logo' => '']))->logo_url);
    }

    public function test_the_background_pattern_resolves_the_same_way(): void
    {
        $identity = new PartnerIdentity(['background_pattern' => 'storage/partners/bg.png']);

        $this->assertSame('https://bucket.test/fitnation/partners/bg.png', $identity->background_pattern_url);
        $this->assertNull((new PartnerIdentity)->background_pattern_url);
    }
}
