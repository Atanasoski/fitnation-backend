<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MovementPattern;
use Illuminate\Database\Seeder;

class MovementPatternSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $patterns = [
            // Upper Push
            ['code' => 'PRESS', 'name' => 'Press', 'display_order' => 10],
            ['code' => 'FLY', 'name' => 'Fly / Adduction', 'display_order' => 20],
            ['code' => 'DIP', 'name' => 'Dip', 'display_order' => 30],
            ['code' => 'PUSHUP', 'name' => 'Push-up', 'display_order' => 40],
            ['code' => 'SHOULDER_RAISE', 'name' => 'Shoulder Raise', 'display_order' => 50],

            // Upper Pull
            ['code' => 'ROW', 'name' => 'Row', 'display_order' => 110],
            ['code' => 'PULL_VERTICAL', 'name' => 'Vertical Pull', 'display_order' => 120],
            ['code' => 'PULLOVER_STRAIGHT_ARM', 'name' => 'Straight-Arm Pullover', 'display_order' => 130],
            ['code' => 'FACE_PULL', 'name' => 'Face Pull', 'display_order' => 140],
            ['code' => 'REAR_DELT_FLY', 'name' => 'Rear Delt Fly', 'display_order' => 150],

            // Lower Body
            ['code' => 'SQUAT', 'name' => 'Squat', 'display_order' => 210],
            ['code' => 'HINGE', 'name' => 'Hip Hinge', 'display_order' => 220],
            ['code' => 'LUNGE_SPLIT_SQUAT', 'name' => 'Lunge / Split Squat', 'display_order' => 230],
            ['code' => 'LEG_PRESS', 'name' => 'Leg Press', 'display_order' => 240],
            ['code' => 'KNEE_EXTENSION', 'name' => 'Knee Extension', 'display_order' => 250],
            ['code' => 'KNEE_FLEXION', 'name' => 'Knee Flexion', 'display_order' => 260],
            ['code' => 'HIP_THRUST_BRIDGE', 'name' => 'Hip Thrust / Bridge', 'display_order' => 270],
            ['code' => 'HIP_ABDUCTION', 'name' => 'Hip Abduction', 'display_order' => 280],
            ['code' => 'CALF_RAISE', 'name' => 'Calf Raise', 'display_order' => 290],
            ['code' => 'BACK_EXTENSION', 'name' => 'Back Extension', 'display_order' => 295],

            // Arms
            ['code' => 'ELBOW_FLEXION', 'name' => 'Elbow Flexion (Biceps)', 'display_order' => 310],
            ['code' => 'ELBOW_EXTENSION', 'name' => 'Elbow Extension (Triceps)', 'display_order' => 320],
            ['code' => 'CARRY', 'name' => 'Carry / Grip', 'display_order' => 330],

            // Core
            ['code' => 'TRUNK_FLEXION', 'name' => 'Trunk Flexion', 'display_order' => 410],
            ['code' => 'ROTATION', 'name' => 'Rotation', 'display_order' => 420],
            ['code' => 'ANTI_ROTATION', 'name' => 'Anti-Rotation', 'display_order' => 430],
        ];

        foreach ($patterns as $pattern) {
            MovementPattern::firstOrCreate(
                ['code' => $pattern['code']],
                $pattern
            );
        }

        $this->command->info('Movement patterns seeded successfully!');
    }
}
