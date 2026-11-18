<?php

namespace Database\Seeders;

use App\Enums\SplitFocus;
use App\Models\WorkoutSplit;
use Illuminate\Database\Seeder;

/**
 * Bootstraps the workout-split catalog for a fresh install.
 *
 * `workout_splits` is staff-maintained through the splits admin CRUD, and
 * ADR-0010 names the live catalog as the intended state — so this seeder is
 * a mirror of production, never the other way round (#146). It had drifted
 * in 18 of the 21 (days_per_week, focus) combinations before being
 * reconciled; `firstOrCreate` means it only ever fills gaps, so a later
 * admin edit is never overwritten — and never reflected here either. When
 * the catalog is changed deliberately, change it by migration and edit this
 * file in the same commit, the way #147 did for lower focus at four days.
 *
 * The comment on each day is the Blueprint that
 * `BlueprintResolver::resolve()` returns for those regions at the Standard
 * tier; different region lists can resolve to the same day (ARMS alongside
 * UPPER_PUSH still resolves to Push).
 */
class WorkoutSplitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $splits = [
            // 1 day per week
            1 => [
                ['UPPER_PUSH', 'UPPER_PULL', 'LOWER'], // Full Body (Push)
            ],
            // 2 days per week — composed days, not full body: ADR-0010 names
            // this split's push+lower and pull+lower days explicitly.
            2 => [
                ['UPPER_PUSH', 'LOWER'], // Push & Legs
                ['UPPER_PULL', 'LOWER'], // Pull & Legs
            ],
            // 3 days per week
            3 => [
                ['UPPER_PUSH', 'UPPER_PULL'], // Upper Body
                ['LOWER'],                    // Legs
                ['UPPER_PUSH', 'UPPER_PULL'], // Upper Body
            ],
            // 4 days per week
            4 => [
                ['UPPER_PUSH', 'ARMS'], // Push
                ['LOWER'],              // Legs
                ['UPPER_PULL', 'ARMS'], // Pull
                ['LOWER', 'CORE'],      // Legs
            ],
            // 5 days per week
            5 => [
                ['UPPER_PUSH', 'UPPER_PULL'], // Upper Body
                ['LOWER'],                    // Legs
                ['UPPER_PUSH'],               // Push
                ['UPPER_PULL'],               // Pull
                ['LOWER'],                    // Legs
            ],
            // 6 days per week
            6 => [
                ['UPPER_PUSH', 'ARMS'], // Push
                ['UPPER_PULL', 'ARMS'], // Pull
                ['LOWER'],              // Legs
                ['UPPER_PUSH', 'ARMS'], // Push
                ['UPPER_PULL', 'ARMS'], // Pull
                ['LOWER', 'CORE'],      // Legs
            ],
            // 7 days per week
            7 => [
                ['UPPER_PUSH', 'ARMS'],                // Push
                ['UPPER_PULL', 'ARMS'],                // Pull
                ['LOWER'],                             // Legs
                ['UPPER_PUSH', 'ARMS'],                // Push
                ['UPPER_PULL', 'ARMS'],                // Pull
                ['LOWER', 'CORE'],                     // Legs
                ['UPPER_PUSH', 'UPPER_PULL', 'LOWER'], // Full Body (Push)
            ],
        ];

        // Upper focus carries no LOWER region at 1 and 2 days. That is
        // deliberate and recorded in ADR-0013: adding a leg day there would
        // make upper focus identical to the balanced split, which removes
        // the member's choice rather than honouring it. Do not "fix" it.
        $upperFocusSplits = [
            // 1 day per week
            1 => [
                ['UPPER_PUSH', 'UPPER_PULL'], // Upper Body
            ],
            // 2 days per week
            2 => [
                ['UPPER_PUSH'], // Push
                ['UPPER_PULL'], // Pull
            ],
            // 3 days per week
            3 => [
                ['UPPER_PUSH'],    // Push
                ['UPPER_PULL'],    // Pull
                ['LOWER', 'CORE'], // Legs
            ],
            // 4 days per week
            4 => [
                ['UPPER_PUSH'],               // Push
                ['UPPER_PULL'],               // Pull
                ['UPPER_PUSH', 'UPPER_PULL'], // Upper Body
                ['LOWER', 'CORE'],            // Legs
            ],
            // 5 days per week
            5 => [
                ['UPPER_PUSH'],               // Push
                ['UPPER_PULL'],               // Pull
                ['UPPER_PUSH', 'UPPER_PULL'], // Upper Body
                ['LOWER', 'CORE'],            // Legs
                ['UPPER_PUSH'],               // Push
            ],
            // 6 days per week
            6 => [
                ['UPPER_PUSH'],               // Push
                ['UPPER_PULL'],               // Pull
                ['LOWER', 'CORE'],            // Legs
                ['UPPER_PUSH'],               // Push
                ['UPPER_PULL'],               // Pull
                ['UPPER_PUSH', 'UPPER_PULL'], // Upper Body
            ],
            // 7 days per week
            7 => [
                ['UPPER_PUSH'],               // Push
                ['UPPER_PULL'],               // Pull
                ['LOWER', 'CORE'],            // Legs
                ['UPPER_PUSH'],               // Push
                ['UPPER_PULL'],               // Pull
                ['UPPER_PUSH', 'UPPER_PULL'], // Upper Body
                ['LOWER', 'CORE'],            // Legs
            ],
        ];

        $lowerFocusSplits = [
            // 1 day per week
            1 => [
                ['LOWER', 'UPPER_PUSH', 'UPPER_PULL'], // Full Body (Legs)
            ],
            // 2 days per week
            2 => [
                ['LOWER', 'CORE'],                     // Legs
                ['LOWER', 'UPPER_PUSH', 'UPPER_PULL'], // Full Body (Legs)
            ],
            // 3 days per week
            3 => [
                ['LOWER'],                    // Legs
                ['UPPER_PUSH', 'UPPER_PULL'], // Upper Body
                ['LOWER', 'CORE'],            // Legs
            ],
            // 4 days per week — three leg days to one upper (#146). The
            // 2:2 shape this replaces was the balanced split reordered,
            // so picking Lower Body at four days changed nothing. This is
            // the one row ahead of production rather than mirroring it:
            // the migration that applies it ships with this branch.
            4 => [
                ['LOWER'],                    // Legs
                ['UPPER_PUSH', 'UPPER_PULL'], // Upper Body
                ['LOWER'],                    // Legs
                ['LOWER', 'CORE'],            // Legs
            ],
            // 5 days per week
            5 => [
                ['LOWER'],         // Legs
                ['UPPER_PUSH'],    // Push
                ['LOWER'],         // Legs
                ['UPPER_PULL'],    // Pull
                ['LOWER', 'CORE'], // Legs
            ],
            // 6 days per week
            6 => [
                ['LOWER'],                    // Legs
                ['UPPER_PUSH'],               // Push
                ['LOWER'],                    // Legs
                ['UPPER_PULL'],               // Pull
                ['LOWER', 'CORE'],            // Legs
                ['UPPER_PUSH', 'UPPER_PULL'], // Upper Body
            ],
            // 7 days per week
            7 => [
                ['LOWER'],                    // Legs
                ['UPPER_PUSH'],               // Push
                ['LOWER'],                    // Legs
                ['UPPER_PULL'],               // Pull
                ['LOWER', 'CORE'],            // Legs
                ['UPPER_PUSH', 'UPPER_PULL'], // Upper Body
                ['LOWER', 'CORE'],            // Legs
            ],
        ];

        $allSplits = [
            SplitFocus::Balanced->value => $splits,
            SplitFocus::UpperFocus->value => $upperFocusSplits,
            SplitFocus::LowerFocus->value => $lowerFocusSplits,
        ];

        foreach ($allSplits as $focusValue => $focusSplits) {
            $focus = SplitFocus::from($focusValue);

            foreach ($focusSplits as $daysPerWeek => $targetRegionsArray) {
                foreach ($targetRegionsArray as $dayIndex => $targetRegions) {
                    WorkoutSplit::firstOrCreate(
                        [
                            'days_per_week' => $daysPerWeek,
                            'focus' => $focus,
                            'day_index' => $dayIndex,
                        ],
                        [
                            'target_regions' => $targetRegions,
                        ]
                    );
                }
            }
        }

        $this->command->info('Workout splits seeded successfully!');
    }
}
