<?php

namespace Tests\Feature\Notifications;

use App\Models\Partner;
use App\Models\PartnerIdentity;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\WeeklySummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * One Weekly Summary, sent: the database row that records what the user was
 * told, and the email that tells them — numbers in their Unit System, whole,
 * with a signed one-click unsubscribe. Mail is read from the array transport,
 * which keeps the rendered message and its headers.
 */
class WeeklySummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('https://fitnation.test');
        URL::forceScheme('https');
    }

    /**
     * A Weekly Progress payload as WeeklyProgress::for() shapes it, in
     * Canonical Units. 12960 kg is the payload lock's fixture week.
     *
     * @return array<string, mixed>
     */
    private static function progress(array $overrides = []): array
    {
        return [
            'percentage' => 100,
            'trend' => 'up',
            'current_week_workouts' => 2,
            'previous_week_workouts' => 1,
            'current_week_volume' => 12960,
            'previous_week_volume' => 2880,
            'volume_difference' => 10080,
            'volume_difference_percent' => 350,
            'current_week_time_minutes' => 120,
            ...$overrides,
        ];
    }

    private function soleMail(): Email
    {
        $mail = Mail::mailer()->getSymfonyTransport()->messages()
            ->map(fn ($sent) => $sent->getOriginalMessage())
            ->all();
        $this->assertCount(1, $mail);

        return $mail[0];
    }

    public function test_it_mails_last_weeks_numbers_in_metric_and_records_what_it_said(): void
    {
        $partner = Partner::factory()->create(['name' => 'Iron Temple']);
        PartnerIdentity::create(['partner_id' => $partner->id, 'primary_color' => '#0a1b2c', 'logo' => 'partners/iron-temple.png']);
        $user = User::factory()->for($partner)->create();

        $user->notify(new WeeklySummary(self::progress()));

        $row = $user->notifications()->sole();
        $this->assertSame(WeeklySummary::class, $row->type);
        $this->assertSame([
            'workouts' => 2,
            'previous_workouts' => 1,
            'volume' => 12960,
            'unit' => 'kg',
            'volume_change_percent' => 350,
            'time_minutes' => 120,
            'trend' => 'up',
        ], $row->data);

        $mail = $this->soleMail();
        $this->assertSame($user->email, $mail->getTo()[0]->getAddress());
        $this->assertSame('Your week: 2 workouts', $mail->getSubject());

        $html = $mail->getHtmlBody();
        $this->assertStringContainsString('2 workouts', $html);
        $this->assertStringContainsString('+1 vs last week', $html);
        $this->assertStringContainsString('12,960 kg lifted', $html);
        $this->assertStringContainsString('+350%', $html);
        $this->assertStringContainsString('120 min training', $html);
        $this->assertStringContainsString('More than the week before. Keep the streak.', $html);
        $this->assertStringContainsString('Open the app', $html);
        $this->assertStringContainsString('https://fitnation.test/get', $html);
        $this->assertStringContainsString('Unsubscribe from weekly summaries', $html);
        $this->assertStringContainsString('Iron Temple', $html);
        $this->assertStringContainsString('#0a1b2c', $html);

        $text = $mail->getTextBody();
        $this->assertStringContainsString('12,960 kg lifted', $text);
        $this->assertStringContainsString('Unsubscribe from weekly summaries', $text);
    }

    public function test_an_imperial_user_reads_the_volume_in_whole_pounds(): void
    {
        $user = User::factory()->create();
        UserProfile::where('user_id', $user->id)->update(['unit_system' => 'imperial']);

        $user->notify(new WeeklySummary(self::progress()));

        // 12960 kg is 28571.9 lbs: a total is rounded to the whole pound, not
        // snapped to a plate step, and never shows decimals.
        $this->assertStringContainsString('28,572 lbs lifted', $this->soleMail()->getHtmlBody());
        $this->assertStringNotContainsString('28571.', $this->soleMail()->getHtmlBody());

        $row = $user->notifications()->sole();
        $this->assertSame(28572, $row->data['volume']);
        $this->assertSame('lbs', $row->data['unit']);
    }

    public function test_the_headers_carry_a_signed_one_click_unsubscribe_and_the_footer_links_it(): void
    {
        $user = User::factory()->create();

        $user->notify(new WeeklySummary(self::progress()));

        $mail = $this->soleMail();
        $listUnsubscribe = $mail->getHeaders()->get('List-Unsubscribe')?->getBodyAsString();
        $this->assertNotNull($listUnsubscribe, 'no List-Unsubscribe header');
        $this->assertSame('List-Unsubscribe=One-Click', $mail->getHeaders()->get('List-Unsubscribe-Post')?->getBodyAsString());

        preg_match('/^<(.+)>$/', $listUnsubscribe, $match);
        $this->assertNotEmpty($match, "List-Unsubscribe is not a bracketed URL: {$listUnsubscribe}");
        $url = $match[1];
        $this->assertStringContainsString("https://fitnation.test/email/weekly-summary/unsubscribe/{$user->id}?", $url);
        $this->assertTrue(URL::hasValidSignature(Request::create($url)), 'unsubscribe link is not signed');

        $this->assertStringContainsString(e($url), $mail->getHtmlBody());
        $this->assertStringContainsString($url, $mail->getTextBody());
    }

    public function test_a_week_with_no_workouts_says_so_and_points_back_to_the_plan(): void
    {
        $user = User::factory()->create();

        $user->notify(new WeeklySummary(self::progress([
            'percentage' => -100,
            'trend' => 'down',
            'current_week_workouts' => 0,
            'previous_week_workouts' => 3,
            'current_week_volume' => 0,
            'previous_week_volume' => 9000,
            'volume_difference' => -9000,
            'volume_difference_percent' => -100,
            'current_week_time_minutes' => 0,
        ])));

        $mail = $this->soleMail();
        $this->assertSame('Your week: no workouts', $mail->getSubject());

        $html = $mail->getHtmlBody();
        $this->assertStringContainsString('no workouts', $html);
        $this->assertStringContainsString('-3 vs last week', $html);
        $this->assertStringContainsString('0 kg lifted', $html);
        $this->assertStringContainsString('-100%', $html);
        $this->assertStringContainsString('0 min training', $html);
        $this->assertStringContainsString('Nothing logged this week. Your plan is where you left it.', $html);
        $this->assertSame('zero', $user->notifications()->sole()->data['trend']);
    }

    public function test_a_lighter_week_and_a_level_week_each_get_their_line(): void
    {
        $lighter = User::factory()->create();
        $level = User::factory()->create();

        $lighter->notify(new WeeklySummary(self::progress([
            'trend' => 'down', 'current_week_workouts' => 1, 'previous_week_workouts' => 3,
        ])));
        $level->notify(new WeeklySummary(self::progress([
            'trend' => 'same', 'percentage' => 0, 'current_week_workouts' => 3, 'previous_week_workouts' => 3,
        ])));

        [$first, $second] = Mail::mailer()->getSymfonyTransport()->messages()
            ->map(fn ($sent) => $sent->getOriginalMessage())->all();

        $this->assertSame('Your week: 1 workout', $first->getSubject());
        $this->assertStringContainsString('-2 vs last week', $first->getHtmlBody());
        $this->assertStringContainsString('A lighter week. Next one&#039;s yours.', $first->getHtmlBody());

        $this->assertSame('Your week: 3 workouts', $second->getSubject());
        $this->assertStringContainsString('±0 vs last week', $second->getHtmlBody());
        $this->assertStringContainsString('Same as last week. Consistency is the whole game.', $second->getHtmlBody());
    }

    public function test_the_volume_block_is_left_out_when_neither_week_lifted_anything(): void
    {
        $user = User::factory()->create();

        $user->notify(new WeeklySummary([
            'percentage' => 100,
            'trend' => 'up',
            'current_week_workouts' => 2,
            'previous_week_workouts' => 0,
            'current_week_time_minutes' => 45,
        ]));

        $html = $this->soleMail()->getHtmlBody();
        $this->assertStringNotContainsString('lifted', $html);
        $this->assertStringContainsString('45 min training', $html);

        $row = $user->notifications()->sole();
        $this->assertNull($row->data['volume']);
        $this->assertNull($row->data['volume_change_percent']);
    }
}
