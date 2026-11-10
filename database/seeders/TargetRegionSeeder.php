<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TargetRegion;
use Illuminate\Database\Seeder;

class TargetRegionSeeder extends Seeder
{
    /**
     * Every requestable target region. Each one but FULL_BODY declares a
     * Fragment in config/workout_blueprints.php (ADR-0010); FULL_BODY is
     * requested alone and maps to the Full Body Blueprint.
     */
    public const REGIONS = [
        ['code' => 'UPPER_PUSH', 'name' => 'Upper Push', 'display_order' => 10],
        ['code' => 'UPPER_PULL', 'name' => 'Upper Pull', 'display_order' => 20],
        ['code' => 'LOWER', 'name' => 'Lower Body', 'display_order' => 30],
        ['code' => 'ARMS', 'name' => 'Arms', 'display_order' => 40],
        ['code' => 'CORE', 'name' => 'Core', 'display_order' => 50],
        ['code' => 'FULL_BODY', 'name' => 'Full Body', 'display_order' => 60],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::REGIONS as $region) {
            TargetRegion::firstOrCreate(
                ['code' => $region['code']],
                $region
            );
        }

        $this->command->info('Target regions seeded successfully!');
    }
}
