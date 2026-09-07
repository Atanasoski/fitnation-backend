<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\InactivityNudge;
use App\Notifications\WeeklySummary;
use App\Services\Notifications\SentRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Sent Record (CONTEXT.md) as a read: what of one kind was recorded for
 * these users since an instant. Each rule keeps its own idea of which row
 * counts; this is only the read they share.
 */
class SentRecordTest extends TestCase
{
    use RefreshDatabase;

    private function recorded(User $user, string $type, string $instant, array $data = []): void
    {
        $this->travelTo(CarbonImmutable::parse($instant));
        $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => $type, 'data' => $data]);
        $this->travelBack();
    }

    public function test_it_reads_one_kind_for_the_given_users_since_an_instant_inclusive(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $other = User::factory()->create();

        $this->recorded($a, WeeklySummary::class, '2026-09-07 06:02:00 UTC', ['workouts' => 3]);
        $this->recorded($a, WeeklySummary::class, '2026-08-31 06:02:00 UTC');   // before: out
        $this->recorded($a, InactivityNudge::class, '2026-09-07 07:00:00 UTC'); // other kind: out
        $this->recorded($b, WeeklySummary::class, '2026-09-07 04:00:00 UTC');   // exactly the instant: in
        $this->recorded($other, WeeklySummary::class, '2026-09-07 06:02:00 UTC'); // not asked about

        // Monday 00:00 in New York, written in that clock: the read binds it on the stored one.
        $sent = SentRecord::since(WeeklySummary::class, [$a->id, $b->id], CarbonImmutable::parse('2026-09-07 00:00:00', 'America/New_York'));

        $this->assertCount(1, $sent->for($a->id));
        $this->assertSame(['workouts' => 3], $sent->for($a->id)->first()->data);
        $this->assertCount(1, $sent->for($b->id));
        $this->assertTrue($sent->for($b->id)->first()->created_at->equalTo(CarbonImmutable::parse('2026-09-07 04:00:00 UTC')));
        $this->assertCount(0, $sent->for($other->id));
    }

    public function test_a_user_with_nothing_recorded_reads_as_empty_not_missing(): void
    {
        $user = User::factory()->create();

        $sent = SentRecord::since(WeeklySummary::class, [$user->id], CarbonImmutable::parse('2026-09-07 00:00:00 UTC'));

        $this->assertTrue($sent->for($user->id)->isEmpty());
        $this->assertFalse($sent->for($user->id)->contains(fn ($row) => true));
    }

    public function test_no_users_means_no_query(): void
    {
        DB::enableQueryLog();
        $sent = SentRecord::since(WeeklySummary::class, [], CarbonImmutable::parse('2026-09-07 00:00:00 UTC'));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queries);
        $this->assertTrue($sent->for(1)->isEmpty());
    }
}
