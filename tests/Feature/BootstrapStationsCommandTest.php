<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Station;
use App\Services\Stations\ProposedStation;
use App\Services\Stations\StationPlanner;
use App\Services\Stations\StationPlanningFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * `stations:bootstrap` at the artisan seam with the planner faked: drafts
 * stations from the planner's proposals, offers it only not-yet-stationed
 * available exercises, persists nothing on dry-run or failure, and never
 * touches existing stations.
 */
class BootstrapStationsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<ProposedStation>|StationPlanningFailed */
    private mixed $planResult = [];

    private ?Collection $offeredExercises = null;

    protected function setUp(): void
    {
        parent::setUp();

        $test = $this;
        $this->app->instance(StationPlanner::class, new class($test) implements StationPlanner
        {
            public function __construct(private BootstrapStationsCommandTest $test) {}

            public function plan(Partner $partner, Collection $exercises): array
            {
                return $this->test->recordPlanCall($exercises);
            }
        });
    }

    /**
     * @return list<ProposedStation>
     */
    public function recordPlanCall(Collection $exercises): array
    {
        $this->offeredExercises = $exercises;

        if ($this->planResult instanceof StationPlanningFailed) {
            throw $this->planResult;
        }

        return $this->planResult;
    }

    public function test_creates_draft_stations_with_the_proposed_order(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-works']);
        [$a, $b, $c] = Exercise::factory()->count(3)->create();
        $partner->exercises()->attach([$a->id, $b->id, $c->id]);

        $this->planResult = [
            new ProposedStation('Cable Station', [$c->id, $a->id]),
            new ProposedStation('Leg Press', [$b->id]),
        ];

        $this->artisan('stations:bootstrap', ['partner' => 'iron-works'])
            ->expectsOutputToContain('Created 2 draft station(s)')
            ->assertSuccessful();

        $cable = Station::where('name', 'Cable Station')->firstOrFail();
        $this->assertSame($partner->id, $cable->partner_id);
        $this->assertNotNull($cable->short_code);
        $this->assertSame([$c->id, $a->id], $cable->exercises->pluck('id')->all());

        $this->assertSame([$b->id], Station::where('name', 'Leg Press')->firstOrFail()->exercises->pluck('id')->all());
    }

    public function test_offers_only_available_exercises_not_already_on_a_station(): void
    {
        $partner = Partner::factory()->create();
        $fresh = Exercise::factory()->create();
        $archived = Exercise::factory()->create(['archived_at' => now()]);
        $stationed = Exercise::factory()->create();
        $partner->exercises()->attach([$fresh->id, $archived->id, $stationed->id]);
        Station::factory()->for($partner)->create()->exercises()->attach($stationed->id, ['display_order' => 1]);

        $this->planResult = [new ProposedStation('Bench', [$fresh->id])];

        $this->artisan('stations:bootstrap', ['partner' => (string) $partner->id])->assertSuccessful();

        $this->assertSame([$fresh->id], $this->offeredExercises->pluck('id')->all());
    }

    public function test_dry_run_prints_the_proposal_and_persists_nothing(): void
    {
        $partner = Partner::factory()->create();
        $exercise = Exercise::factory()->create(['name' => 'Seated Row']);
        $partner->exercises()->attach($exercise->id);

        $this->planResult = [new ProposedStation('Row Machine', [$exercise->id])];

        $this->artisan('stations:bootstrap', ['partner' => (string) $partner->id, '--dry-run' => true])
            ->expectsOutputToContain('Station: Row Machine')
            ->expectsOutputToContain('1. Seated Row')
            ->expectsOutputToContain('nothing persisted')
            ->assertSuccessful();

        $this->assertSame(0, Station::count());
    }

    public function test_a_failed_plan_creates_nothing_and_fails_the_run(): void
    {
        $partner = Partner::factory()->create();
        $partner->exercises()->attach(Exercise::factory()->create()->id);

        $this->planResult = new StationPlanningFailed('OpenAI proposed no stations at all.');

        $this->artisan('stations:bootstrap', ['partner' => (string) $partner->id])
            ->expectsOutputToContain('Planning failed, nothing was created')
            ->assertFailed();

        $this->assertSame(0, Station::count());
    }

    public function test_says_so_when_everything_is_already_stationed(): void
    {
        $partner = Partner::factory()->create();
        $exercise = Exercise::factory()->create();
        $partner->exercises()->attach($exercise->id);
        Station::factory()->for($partner)->create()->exercises()->attach($exercise->id, ['display_order' => 1]);

        $this->artisan('stations:bootstrap', ['partner' => (string) $partner->id])
            ->expectsOutputToContain('already on a station')
            ->assertSuccessful();

        $this->assertNull($this->offeredExercises);
    }

    public function test_an_unknown_partner_fails(): void
    {
        $this->artisan('stations:bootstrap', ['partner' => 'no-such-gym'])
            ->expectsOutputToContain('No partner found')
            ->assertFailed();
    }
}
