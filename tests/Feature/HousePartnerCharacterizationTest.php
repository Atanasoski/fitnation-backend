<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use App\Models\UserInvitation;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Locks how a new account gets its partner today, before the House Partner
 * comes from config: social sign-in falls back to partner 1, web registration
 * takes the invitation's partner or none.
 */
class HousePartnerCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $this->privateKey);
        $details = openssl_pkey_get_details($key);

        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [[
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => 'test-key',
                'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
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
            'name' => 'Social Person',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $this->privateKey, 'RS256', 'test-key');
    }

    private function socialSignIn(array $extra = []): User
    {
        $this->postJson('/api/auth/social', [
            'provider' => 'google',
            'token' => $this->googleToken('sub-'.uniqid(), $email = uniqid().'@example.com'),
            ...$extra,
        ])->assertOk();

        return User::where('email', $email)->firstOrFail();
    }

    public function test_social_sign_in_without_a_partner_lands_on_partner_one(): void
    {
        Partner::factory()->create(['id' => 1]);

        $this->assertSame(1, $this->socialSignIn()->partner_id);
    }

    public function test_social_sign_in_with_an_inactive_partner_lands_on_partner_one(): void
    {
        Partner::factory()->create(['id' => 1]);
        $inactive = Partner::factory()->inactive()->create();

        $this->assertSame(1, $this->socialSignIn(['partner_id' => $inactive->id])->partner_id);
    }

    public function test_social_sign_in_with_an_active_partner_lands_on_that_partner(): void
    {
        Partner::factory()->create(['id' => 1]);
        $gym = Partner::factory()->create();

        $this->assertSame($gym->id, $this->socialSignIn(['partner_id' => $gym->id])->partner_id);
    }

    public function test_web_registration_without_an_invitation_has_no_partner(): void
    {
        $this->post('/register', [
            'name' => 'Web Person',
            'email' => 'web@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertNull(User::where('email', 'web@example.com')->firstOrFail()->partner_id);
    }

    public function test_web_registration_with_an_invitation_takes_the_invitations_partner(): void
    {
        $gym = Partner::factory()->create();
        $invitation = UserInvitation::create([
            'partner_id' => $gym->id,
            'invited_by' => User::factory()->create(['partner_id' => $gym->id])->id,
            'email' => 'invited@example.com',
            'token' => UserInvitation::generateToken(),
            'expires_at' => now()->addWeek(),
        ]);

        $this->post('/register', [
            'name' => 'Invited Person',
            'email' => 'invited@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_token' => $invitation->token,
        ])->assertRedirect(route('registration.success'));

        $this->assertSame($gym->id, User::where('email', 'invited@example.com')->firstOrFail()->partner_id);
    }
}
