<?php

namespace Tests\Feature\Notifications;

use App\Mail\WeeklySummaryMail;
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

    /**
     * The same week with the day-by-day and week-by-week series WeeklyProgress
     * ships beside the totals. Eight historical weeks, the last of which is the
     * week in progress; Monday and Wednesday trained.
     *
     * @return array<string, mixed>
     */
    private static function progressWithSeries(array $overrides = []): array
    {
        $days = [];
        foreach (['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06', '2026-03-07', '2026-03-08'] as $i => $date) {
            $days[] = ['day_of_week' => $i, 'date' => $date, 'volume' => 0, 'workouts' => 0, 'time_minutes' => 0];
        }
        $days[0] = [...$days[0], 'volume' => 8280, 'workouts' => 1, 'time_minutes' => 60];
        $days[2] = [...$days[2], 'volume' => 4680, 'workouts' => 1, 'time_minutes' => 60];

        return self::progress([
            'daily_breakdown' => $days,
            'historical_weeks' => [
                ['week' => 'Jan 19', 'workouts' => 0],
                ['week' => 'Jan 26', 'workouts' => 0],
                ['week' => 'Feb 02', 'workouts' => 3],
                ['week' => 'Feb 09', 'workouts' => 0],
                ['week' => 'Feb 16', 'workouts' => 0],
                ['week' => 'Feb 23', 'workouts' => 1],
                ['week' => 'Mar 02', 'workouts' => 2],
                ['week' => 'Mar 09', 'workouts' => 0],
            ],
            ...$overrides,
        ]);
    }

    private function mailFor(User $user, array $progress): WeeklySummaryMail
    {
        return (new WeeklySummary($progress))->toMail($user);
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

    public function test_the_charts_show_seven_finished_weeks_with_the_subject_week_marked_and_the_days_of_that_week(): void
    {
        $charts = $this->mailFor(User::factory()->create(), self::progressWithSeries())->charts();

        $this->assertSame(
            ['Jan 19', 'Jan 26', 'Feb 02', 'Feb 09', 'Feb 16', 'Feb 23', 'Mar 02'],
            array_column($charts['weeks'], 'label'),
            'the week in progress is never the subject and is not drawn',
        );
        $this->assertSame([0, 0, 3, 0, 0, 1, 2], array_column($charts['weeks'], 'workouts'));
        $this->assertSame([false, false, false, false, false, false, true], array_column($charts['weeks'], 'current'));
        $this->assertSame(72, $charts['weeks'][2]['height'], 'the tallest bar is the full height');
        $this->assertSame(48, $charts['weeks'][6]['height'], 'bars scale to the tallest');
        $this->assertSame(0, $charts['weeks'][0]['height']);

        $this->assertSame(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], array_column($charts['days'], 'label'));
        $this->assertSame('kg', $charts['days_unit']);
        $this->assertSame([8280, 0, 4680, 0, 0, 0, 0], array_column($charts['days'], 'value'));
        $this->assertSame(72, $charts['days'][0]['height']);
        $this->assertSame(41, $charts['days'][2]['height']);
    }

    public function test_the_day_chart_is_in_the_users_unit_system(): void
    {
        $user = User::factory()->create();
        UserProfile::where('user_id', $user->id)->update(['unit_system' => 'imperial']);

        $charts = $this->mailFor($user->fresh(), self::progressWithSeries())->charts();

        $this->assertSame('lbs', $charts['days_unit']);
        $this->assertSame(18254, $charts['days'][0]['value']); // 8280 kg, to the whole pound
    }

    public function test_a_week_with_no_lifting_charts_minutes_instead(): void
    {
        $days = self::progressWithSeries()['daily_breakdown'];
        foreach ($days as &$day) {
            $day['volume'] = 0;
        }

        $charts = $this->mailFor(User::factory()->create(), self::progressWithSeries(['daily_breakdown' => $days]))->charts();

        $this->assertSame('min', $charts['days_unit']);
        $this->assertSame([60, 0, 60, 0, 0, 0, 0], array_column($charts['days'], 'value'));
    }

    public function test_the_charts_are_drawn_into_the_mail_and_left_out_without_series(): void
    {
        $user = User::factory()->create();

        $user->notify(new WeeklySummary(self::progressWithSeries()));

        $html = $this->soleMail()->getHtmlBody();
        $this->assertStringContainsString('Last 7 weeks', $html);
        $this->assertStringContainsString('Your week, day by day', $html);
        foreach (['Jan 19', 'Mar 02', 'Mon', 'Sun', '8,280'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringNotContainsString('Mar 09', $html, 'the week in progress is not drawn');

        $text = $this->soleMail()->getTextBody();
        $this->assertStringContainsString('Last 7 weeks: 0 · 0 · 3 · 0 · 0 · 1 · 2', $text);
        $this->assertStringContainsString('Mon 8,280 kg · Wed 4,680 kg', $text);

        Mail::mailer()->getSymfonyTransport()->messages()->pop();
        $bare = User::factory()->create();
        $bare->notify(new WeeklySummary(self::progress()));

        $this->assertNull($this->mailFor($bare, self::progress())->charts());
        $this->assertStringNotContainsString('Last 7 weeks', $this->soleMail()->getHtmlBody());
    }
}
