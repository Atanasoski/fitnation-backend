<?php

namespace Tests\Feature\Notifications;

use App\Models\Device;
use App\Models\Partner;
use App\Models\PartnerIdentity;
use App\Models\User;
use App\Notifications\UnfinishedAccountNudge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * One Unfinished Account nudge, sent: the database row that is its sent
 * record, the email that reaches the user, and the push that goes out when
 * there happens to be a Device. Mail is read from the array transport, which
 * keeps the rendered message; Expo is faked at the HTTP boundary.
 */
class UnfinishedAccountNudgeTest extends TestCase
{
    use RefreshDatabase;

    private const SEND_URL = 'https://exp.host/--/api/v2/push/send';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('notifications.enabled', true);
        config()->set('notifications.expo.access_token', 'expo-secret');
        config()->set('notifications.only_build_profile', null);
        URL::forceRootUrl('https://fitnation.test');
        URL::forceScheme('https');
        Http::fake([self::SEND_URL => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket']]])]);
    }

    /** @return list<Email> */
    private function sentMail(): array
    {
        return Mail::mailer()->getSymfonyTransport()->messages()
            ->map(fn ($sent) => $sent->getOriginalMessage())
            ->all();
    }

    private function soleMail(): Email
    {
        $mail = $this->sentMail();
        $this->assertCount(1, $mail);

        return $mail[0];
    }

    public function test_step_one_unverified_mails_a_verification_link_and_records_what_they_are_stuck_at(): void
    {
        $partner = Partner::factory()->create(['name' => 'Iron Temple']);
        $user = User::factory()->unverified()->for($partner)->create(['onboarding_completed_at' => null]);

        $user->notify(new UnfinishedAccountNudge(1, 'unverified'));

        $row = $user->notifications()->sole();
        $this->assertSame(UnfinishedAccountNudge::class, $row->type);
        $this->assertSame(1, $row->data['step']);
        $this->assertSame('unverified', $row->data['stuck_at']);
        $this->assertSame('Confirm your email for Iron Temple', $row->data['title']);
        $this->assertSame('https://fitnation.test/get', $row->data['url']);

        $mail = $this->soleMail();
        $this->assertSame($user->email, $mail->getTo()[0]->getAddress());
        $this->assertSame('Confirm your email for Iron Temple', $mail->getSubject());

        $html = $mail->getHtmlBody();
        $this->assertStringContainsString('You&#039;re one tap away from your first workout.', $html);
        $this->assertStringContainsString('Your first plan will be ready the moment you are.', $html);
        $this->assertStringContainsString('Verify email', $html);
        $this->assertStringContainsString('Then open the app to finish setting up', $html);
        $this->assertStringContainsString('https://fitnation.test/get', $html);

        preg_match('#href="([^"]*verify[^"]*)"#', $html, $link);
        $this->assertNotEmpty($link, 'no verification link in the mail');
        $verifyUrl = html_entity_decode($link[1]);
        $this->assertStringContainsString("/verify-email/{$user->id}/".sha1($user->email), $verifyUrl);
        $this->assertTrue(URL::hasValidSignature(\Illuminate\Http\Request::create($verifyUrl)), 'verification link is not signed');

        $this->assertStringContainsString($verifyUrl, $mail->getTextBody());

        Http::assertNothingSent();
    }

    public function test_step_one_not_onboarded_mails_finish_setting_up_with_the_store_link_only(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => null]);

        $user->notify(new UnfinishedAccountNudge(1, 'not_onboarded'));

        $this->assertSame('not_onboarded', $user->notifications()->sole()->data['stuck_at']);

        $mail = $this->soleMail();
        $this->assertSame('Finish setting up '.config('app.name'), $mail->getSubject());

        $html = $mail->getHtmlBody();
        $this->assertStringContainsString('Two quick minutes about your goals and your first plan is ready.', $html);
        $this->assertStringContainsString('Open the app', $html);
        $this->assertStringContainsString('https://fitnation.test/get', $html);
        $this->assertStringNotContainsString('verify-email', $html);
        $this->assertStringNotContainsString('Then open the app', $html);
    }

    public function test_the_last_step_says_so_whatever_they_are_stuck_at(): void
    {
        $unverified = User::factory()->unverified()->create(['onboarding_completed_at' => null]);
        $verified = User::factory()->create(['onboarding_completed_at' => null]);

        $unverified->notify(new UnfinishedAccountNudge(7, 'unverified'));
        $verified->notify(new UnfinishedAccountNudge(7, 'not_onboarded'));

        [$first, $second] = $this->sentMail();
        $this->assertSame('Last one from us', $first->getSubject());
        $this->assertSame('Last one from us', $second->getSubject());
        $this->assertStringContainsString('This is the last time we&#039;ll email you about this.', $first->getHtmlBody());
        $this->assertStringContainsString('verify-email', $first->getHtmlBody());
        $this->assertStringNotContainsString('verify-email', $second->getHtmlBody());
    }

    public function test_with_a_device_a_push_goes_out_too(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => null]);
        Device::factory()->for($user)->create();

        $user->notify(new UnfinishedAccountNudge(3, 'not_onboarded'));

        $this->assertCount(1, $this->sentMail());
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->data()[0]['title'] === 'Your first plan is two minutes away'
            && $request->data()[0]['body'] === "Tell us your goal and how often you can train, and we'll build a plan around your week."
            && $request->data()[0]['data']['url'] === 'https://fitnation.test/get');
    }

    public function test_the_mail_carries_the_partner_identity_when_there_is_one(): void
    {
        $partner = Partner::factory()->create(['name' => 'Iron Temple']);
        PartnerIdentity::create(['partner_id' => $partner->id, 'primary_color' => '#0a1b2c', 'logo' => 'partners/iron-temple.png']);
        $user = User::factory()->for($partner)->create(['onboarding_completed_at' => null]);

        $user->notify(new UnfinishedAccountNudge(3, 'not_onboarded'));

        $mail = $this->soleMail();
        $this->assertSame('Your first plan is two minutes away', $mail->getSubject());
        $this->assertStringContainsString('#0a1b2c', $mail->getHtmlBody());
        $this->assertStringContainsString('partners/iron-temple.png', $mail->getHtmlBody());
        $this->assertStringContainsString('Iron Temple', $mail->getHtmlBody());
    }

    public function test_the_mail_falls_back_to_the_app_name_without_a_partner(): void
    {
        $user = User::factory()->unverified()->create(['onboarding_completed_at' => null, 'partner_id' => null]);

        $user->notify(new UnfinishedAccountNudge(3, 'unverified'));

        $mail = $this->soleMail();
        $this->assertSame('Still want to train with '.config('app.name').'?', $mail->getSubject());
        $this->assertStringContainsString(config('app.name'), $mail->getHtmlBody());
        $this->assertStringNotContainsString('<img', $mail->getHtmlBody());
    }
}
