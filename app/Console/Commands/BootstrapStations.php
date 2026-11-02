<?php

namespace App\Console\Commands;

use App\Models\Partner;
use App\Services\Stations\StationPlanner;
use App\Services\Stations\StationPlanningFailed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Drafts a Partner's Stations with AI (follow-up to spec qr-stations): groups
 * the partner's linked, available exercises that are not yet on any of their
 * stations into proposed floor spots, and creates them as ordinary Stations
 * to prune and rename in the admin panel.
 *
 * Existing stations are never touched, codes are never regenerated, and a
 * re-run only drafts the still-unstationed remainder — so it is safe to run
 * again after linking new exercises to the partner.
 */
class BootstrapStations extends Command
{
    protected $signature = 'stations:bootstrap
                            {partner : The partner\'s id or slug}
                            {--dry-run : Print the proposed stations without creating anything}';

    protected $description = 'Draft a partner\'s QR Stations with AI from their not-yet-stationed exercises';

    public function handle(StationPlanner $planner): int
    {
        $partner = $this->resolvePartner($this->argument('partner'));

        if ($partner === null) {
            $this->error("No partner found for \"{$this->argument('partner')}\".");

            return self::FAILURE;
        }

        $exercises = $this->unstationedExercises($partner);

        if ($exercises->isEmpty()) {
            $this->info("Nothing to draft: every available exercise linked to {$partner->name} is already on a station.");

            return self::SUCCESS;
        }

        $this->info("Planning stations for {$partner->name} from {$exercises->count()} not-yet-stationed exercise(s)…");

        try {
            $proposals = $planner->plan($partner, $exercises);
        } catch (StationPlanningFailed $e) {
            $this->error("Planning failed, nothing was created: {$e->getMessage()}");

            return self::FAILURE;
        }

        $names = $exercises->pluck('name', 'id');
        $dryRun = (bool) $this->option('dry-run');
        $this->newLine();

        foreach ($proposals as $proposal) {
            $this->line(($dryRun ? '[dry-run] ' : '')."Station: {$proposal->name}");
            foreach ($proposal->exerciseIds as $position => $id) {
                $this->line('    '.($position + 1).". {$names[$id]}");
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('[dry-run] '.count($proposals).' station(s) proposed; nothing persisted.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($partner, $proposals) {
            foreach ($proposals as $proposal) {
                $station = $partner->stations()->create(['name' => $proposal->name]);
                $station->exercises()->attach(collect($proposal->exerciseIds)->mapWithKeys(
                    fn (int $id, int $position) => [$id => ['display_order' => $position + 1]]
                )->all());
            }
        });

        $this->newLine();
        $this->info('Created '.count($proposals)." draft station(s) for {$partner->name}. Prune and rename them in the admin panel before printing any QR.");

        return self::SUCCESS;
    }

    private function resolvePartner(string $key): ?Partner
    {
        return ctype_digit($key)
            ? Partner::find((int) $key)
            : Partner::where('slug', $key)->first();
    }

    /**
     * The partner's available linked exercises that sit on none of their
     * stations yet — the only ones a bootstrap may draft with.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Exercise>
     */
    private function unstationedExercises(Partner $partner)
    {
        $stationed = $partner->stations()
            ->with('exercises:workout_exercises.id')
            ->get()
            ->flatMap(fn ($station) => $station->exercises->pluck('id'))
            ->unique();

        return $partner->exercises()
            ->available()
            ->whereKeyNot($stationed->all())
            ->with(['equipmentType', 'primaryMuscleGroups'])
            ->get();
    }
}
