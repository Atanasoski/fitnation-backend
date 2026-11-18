<?php

namespace Database\Seeders;

use App\Enums\SplitFocus;
use App\Models\WorkoutSplit;
use Illuminate\Database\Seeder;

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
                ['UPPER_PUSH', 'UPPER_PULL', 'LOWER'], // Full Body (Push focus)
            ],
            // 2 days per week
            2 => [
                ['UPPER_PUSH', 'UPPER_PULL', 'LOWER'], // Full Body (Push focus)
                ['UPPER_PULL', 'UPPER_PUSH', 'LOWER'], // Full Body (Pull focus)
            ],
            // 3 days per week
            3 => [
                ['UPPER_PUSH', 'UPPER_PULL', 'LOWER'], // Full Body (Push focus)
                ['UPPER_PULL', 'UPPER_PUSH', 'LOWER'], // Full Body (Pull focus)
                ['LOWER', 'UPPER_PUSH', 'UPPER_PULL'], // Full Body (Lower focus)
            ],
            // 4 days per week
            4 => [
                ['UPPER_PUSH', 'ARMS'], // Push + Triceps
                ['LOWER', 'CORE'], // Legs + Core
                ['UPPER_PULL', 'ARMS'], // Pull + Biceps
                ['LOWER', 'CORE'], // Lower Body
            ],
            // 5 days per week
            5 => [
                ['UPPER_PUSH', 'ARMS'], // Push + Triceps
                ['LOWER', 'CORE'], // Legs + Core
                ['UPPER_PULL', 'ARMS'], // Pull + Biceps
                ['LOWER', 'CORE'], // Lower Body
                ['UPPER_PUSH', 'UPPER_PULL'], // Upper Body
            ],
            // 6 days per week
            6 => [
                ['UPPER_PUSH', 'ARMS'], // Push + Triceps
                ['UPPER_PULL', 'ARMS'], // Pull + Biceps
                ['LOWER', 'CORE'], // Legs + Core
                ['UPPER_PUSH', 'ARMS'], // Push + Triceps
                ['UPPER_PULL', 'ARMS'], // Pull + Biceps
                ['LOWER', 'CORE'], // Legs + Core
            ],
            // 7 days per week
            7 => [
                ['UPPER_PUSH', 'ARMS'], // Push + Triceps
                ['UPPER_PULL', 'ARMS'], // Pull + Biceps
                ['LOWER', 'CORE'], // Legs + Core
                ['UPPER_PUSH', 'ARMS'], // Push + Triceps
                ['UPPER_PULL', 'ARMS'], // Pull + Biceps
                ['LOWER', 'CORE'], // Legs + Core
                ['UPPER_PUSH', 'UPPER_PULL', 'LOWER'], // Full Body (Push focus)
            ],
        ];

        $upperFocusSplits = [
            // 1 day per week
            1 => [
                ['UPPER_PUSH', 'UPPER_PULL', 'ARMS'], // Upper Body
            ],
            // 2 days per week
            2 => [
                ['UPPER_PUSH', 'ARMS'], // Push + Arms
                ['UPPER_PULL', 'ARMS'], // Pull + Arms
            ],
            // 3 days per week
            3 => [
                ['UPPER_PUSH', 'ARMS'], // Push + Arms
                ['UPPER_PULL', 'ARMS'], // Pull + Arms
                ['LOWER', 'CORE'],      // Legs + Core
            ],
            // 4 days per week
            4 => [
                ['UPPER_PUSH', 'ARMS'],         // Push + Arms
                ['UPPER_PULL', 'ARMS'],         // Pull + Arms
                ['UPPER_PUSH', 'UPPER_PULL'],   // Upper Body
                ['LOWER', 'CORE'],              // Legs + Core
            ],
            // 5 days per week
            5 => [
                ['UPPER_PUSH', 'ARMS'],         // Push + Arms
                ['UPPER_PULL', 'ARMS'],         // Pull + Arms
                ['UPPER_PUSH', 'UPPER_PULL'],   // Upper Body
                ['LOWER', 'CORE'],              // Legs + Core
                ['UPPER_PUSH', 'ARMS'],         // Push + Arms
            ],
            // 6 days per week
            6 => [
                ['UPPER_PUSH', 'ARMS'],         // Push + Arms
                ['UPPER_PULL', 'ARMS'],         // Pull + Arms
                ['LOWER', 'CORE'],              // Legs + Core
                ['UPPER_PUSH', 'ARMS'],         // Push + Arms
                ['UPPER_PULL', 'ARMS'],         // Pull + Arms
                ['UPPER_PUSH', 'UPPER_PULL'],   // Upper Body
            ],
            // 7 days per week
            7 => [
                ['UPPER_PUSH', 'ARMS'],         // Push + Arms
                ['UPPER_PULL', 'ARMS'],         // Pull + Arms
                ['LOWER', 'CORE'],              // Legs + Core
                ['UPPER_PUSH', 'ARMS'],         // Push + Arms
                ['UPPER_PULL', 'ARMS'],         // Pull + Arms
                ['UPPER_PUSH', 'UPPER_PULL'],   // Upper Body
                ['LOWER', 'CORE'],              // Legs + Core
            ],
        ];

        $lowerFocusSplits = [
            // 1 day per week
            1 => [
                ['LOWER', 'UPPER_PUSH', 'UPPER_PULL'], // Full Body (Lower focus)
            ],
            // 2 days per week
            2 => [
                ['LOWER', 'CORE'],                     // Legs + Core
                ['LOWER', 'UPPER_PUSH', 'UPPER_PULL'], // Full Body (Lower focus)
            ],
            // 3 days per week
            3 => [
                ['LOWER', 'CORE'],              // Legs + Core
                ['UPPER_PUSH', 'UPPER_PULL'],   // Upper Body
                ['LOWER', 'CORE'],              // Legs + Core
            ],
            // 4 days per week — three leg days to one upper (#146). The
            // 2:2 shape this replaces was the balanced split reordered,
            // so picking Lower Body at four days changed nothing.
            4 => [
                ['LOWER'],                          // Legs
                ['UPPER_PUSH', 'UPPER_PULL'],       // Upper Body
                ['LOWER'],                          // Legs
                ['LOWER', 'CORE'],                  // Legs + Core
            ],
            // 5 days per week
            5 => [
                ['LOWER', 'CORE'],          // Legs + Core
                ['UPPER_PUSH', 'ARMS'],     // Push + Arms
                ['LOWER', 'CORE'],          // Legs + Core
                ['UPPER_PULL', 'ARMS'],     // Pull + Arms
                ['LOWER', 'CORE'],          // Legs + Core
            ],
            // 6 days per week
            6 => [
                ['LOWER', 'CORE'],              // Legs + Core
                ['UPPER_PUSH', 'ARMS'],         // Push + Arms
                ['LOWER', 'CORE'],              // Legs + Core
                ['UPPER_PULL', 'ARMS'],         // Pull + Arms
                ['LOWER', 'CORE'],              // Legs + Core
                ['UPPER_PUSH', 'UPPER_PULL'],   // Upper Body
            ],
            // 7 days per week
            7 => [
                ['LOWER', 'CORE'],              // Legs + Core
                ['UPPER_PUSH', 'ARMS'],         // Push + Arms
                ['LOWER', 'CORE'],              // Legs + Core
                ['UPPER_PULL', 'ARMS'],         // Pull + Arms
                ['LOWER', 'CORE'],              // Legs + Core
                ['UPPER_PUSH', 'UPPER_PULL'],   // Upper Body
                ['LOWER', 'CORE'],              // Legs + Core
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
