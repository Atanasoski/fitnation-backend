<?php

namespace Tests\Feature\Notifications;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The one-click unsubscribe the Weekly Summary carries: a signed link, no
 * login, that turns the email off and says so. GET is what a person clicks;
 * POST is what a mail client sends for List-Unsubscribe-Post.
 */
class WeeklySummaryUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    private function unsubscribeUrl(User $user): string
    {
        return URL::signedRoute('email.weekly-summary.unsubscribe', ['user' => $user]);
    }

    public function test_a_signed_link_turns_the_weekly_summary_email_off_and_confirms_it(): void
    {
        $partner = Partner::factory()->create(['name' => 'Iron Temple']);
        $user = User::factory()->for($partner)->create();

        $response = $this->get($this->unsubscribeUrl($user));

        $response->assertOk()
            ->assertSee('Iron Temple')
            ->assertSee('weekly summar', escape: false)
            ->assertSee(route('app.get'));

        $this->assertFalse($user->fresh()->notificationSetting('weekly_summary_email', true));
    }

    public function test_it_needs_no_login_and_does_not_touch_the_push_switch(): void
    {
        $user = User::factory()->create(['push_enabled' => true]);

        $this->assertGuest();
        $this->get($this->unsubscribeUrl($user))->assertOk();

        $this->assertTrue($user->fresh()->push_enabled);
        $this->assertGuest();
    }

    public function test_a_tampered_signature_is_refused_and_changes_nothing(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $forged = str_replace("/unsubscribe/{$user->id}?", "/unsubscribe/{$other->id}?", $this->unsubscribeUrl($user));

        $this->get($forged)->assertForbidden();
        $this->get(route('email.weekly-summary.unsubscribe', ['user' => $user]))->assertForbidden();

        $this->assertTrue($user->fresh()->notificationSetting('weekly_summary_email', true));
        $this->assertTrue($other->fresh()->notificationSetting('weekly_summary_email', true));
    }

    public function test_a_one_click_post_from_a_mail_client_works_without_a_csrf_token(): void
    {
        $user = User::factory()->create();

        $this->post($this->unsubscribeUrl($user), ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertFalse($user->fresh()->notificationSetting('weekly_summary_email', true));
    }

    public function test_unsubscribing_twice_is_harmless(): void
    {
        $user = User::factory()->create();

        $this->get($this->unsubscribeUrl($user))->assertOk();
        $this->get($this->unsubscribeUrl($user))->assertOk();

        $this->assertSame(['weekly_summary_email' => false], $user->fresh()->notification_settings);
    }
}
