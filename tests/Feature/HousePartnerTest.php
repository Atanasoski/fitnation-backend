<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HousePartnerTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey = '';

    private Partner $house;

    protected function setUp(): void
    {
        parent::setUp();

        $this->house = Partner::factory()->create(['name' => 'Fit Nation']);
        config(['partners.house_partner_id' => $this->house->id]);
    }

    public function test_the_house_partner_is_the_configured_one(): void
    {
        Partner::factory()->create();

        $this->assertSame($this->house->id, Partner::houseId());
        $this->assertTrue($this->house->isHouse());
        $this->assertFalse(Partner::factory()->create()->isHouse());
    }

    public function test_web_registration_without_an_invitation_lands_on_the_house_partner(): void
    {
        $this->post('/register', [
            'name' => 'Web Person',
            'email' => 'web@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame($this->house->id, User::where('email', 'web@example.com')->firstOrFail()->partner_id);
    }

    public function test_social_sign_in_without_a_partner_lands_on_the_configured_house_partner(): void
    {
        $this->fakeGoogleKeys();

        $this->postJson('/api/auth/social', [
            'provider' => 'google',
            'token' => $this->googleToken('sub-1', 'social@example.com'),
        ])->assertOk();

        $this->assertSame($this->house->id, User::where('email', 'social@example.com')->firstOrFail()->partner_id);
    }

    public function test_social_sign_in_with_an_inactive_partner_lands_on_the_configured_house_partner(): void
    {
        $this->fakeGoogleKeys();
        $inactive = Partner::factory()->inactive()->create();

        $this->postJson('/api/auth/social', [
            'provider' => 'google',
            'token' => $this->googleToken('sub-2', 'social2@example.com'),
            'partner_id' => $inactive->id,
        ])->assertOk();

        $this->assertSame($this->house->id, User::where('email', 'social2@example.com')->firstOrFail()->partner_id);
    }

    public function test_the_migration_moves_partnerless_users_to_the_house_partner_and_leaves_admins_alone(): void
    {
        $gym = Partner::factory()->create();
        $partnerless = User::factory()->create(['partner_id' => null]);
        $deletedPartnerless = User::factory()->create(['partner_id' => null]);
        $deletedPartnerless->delete();
        $gymMember = User::factory()->create(['partner_id' => $gym->id]);
        $admin = $this->userWithRole('admin');
        $partnerAdmin = $this->userWithRole('partner_admin');
        $plainRoleUser = $this->userWithRole('user');

        $migration = require database_path('migrations/2026_10_05_000001_move_partnerless_users_to_the_house_partner.php');
        $migration->up();

        $this->assertSame($this->house->id, $partnerless->fresh()->partner_id);
        $this->assertSame($this->house->id, User::withTrashed()->find($deletedPartnerless->id)->partner_id);
        $this->assertSame($this->house->id, $plainRoleUser->fresh()->partner_id);
        $this->assertSame($gym->id, $gymMember->fresh()->partner_id);
        $this->assertNull($admin->fresh()->partner_id);
        $this->assertNull($partnerAdmin->fresh()->partner_id);
    }

    public function test_the_migration_does_nothing_when_the_house_partner_does_not_exist(): void
    {
        $partnerless = User::factory()->create(['partner_id' => null]);
        config(['partners.house_partner_id' => 999999]);

        $migration = require database_path('migrations/2026_10_05_000001_move_partnerless_users_to_the_house_partner.php');
        $migration->up();

        $this->assertNull($partnerless->fresh()->partner_id);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create(['partner_id' => null]);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

        return $user;
    }

    private function fakeGoogleKeys(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $this->privateKey);
        $details = openssl_pkey_get_details($key);
        $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');

        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [[
                'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'test-key',
                'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e']),
            ]]]),
        ]);

        config(['services.google.client_id' => 'test-client']);
    }

    private function googleToken(string $sub, string $email): string
    {
        return JWT::encode([
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-client',
            'sub' => $sub,
            'email' => $email,
            'iat' => time(),
            'exp' => time() + 3600,
        ], $this->privateKey, 'RS256', 'test-key');
    }
}
