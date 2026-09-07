<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EquipmentType;
use Illuminate\Database\Seeder;

class EquipmentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['code' => 'BARBELL', 'name' => 'Barbell', 'display_order' => 10, 'supports_added_weight' => true],
            ['code' => 'DUMBBELL', 'name' => 'Dumbbell', 'display_order' => 20, 'supports_added_weight' => true],
            ['code' => 'CABLE', 'name' => 'Cable', 'display_order' => 30, 'supports_added_weight' => true],
            ['code' => 'MACHINE', 'name' => 'Machine', 'display_order' => 40, 'supports_added_weight' => true],
            ['code' => 'SMITH', 'name' => 'Smith Machine', 'display_order' => 50, 'supports_added_weight' => true],
            ['code' => 'BODYWEIGHT', 'name' => 'Bodyweight', 'display_order' => 60, 'supports_added_weight' => false],
            ['code' => 'BAND', 'name' => 'Band', 'display_order' => 70, 'supports_added_weight' => false],
            ['code' => 'KETTLEBELL', 'name' => 'Kettlebell', 'display_order' => 80, 'supports_added_weight' => true],
            ['code' => 'TRX', 'name' => 'TRX', 'display_order' => 90, 'supports_added_weight' => false],
            ['code' => 'MEDICINE_BALL', 'name' => 'Medicine Ball', 'display_order' => 100, 'supports_added_weight' => true],
            ['code' => 'LANDMINE', 'name' => 'Landmine', 'display_order' => 110, 'supports_added_weight' => true],
        ];

        foreach ($types as $type) {
            EquipmentType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }

        $this->command->info('Equipment types seeded successfully!');
    }
}
