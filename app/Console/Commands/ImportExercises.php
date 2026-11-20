<?php

namespace App\Console\Commands;

use App\Enums\CategoryType;
use App\Models\Angle;
use App\Models\Category;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\MuscleGroup;
use App\Models\TargetRegion;
use App\Models\TrainingStyle;
use App\Services\MuscleGroupImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportExercises extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exercises:import {--fetch-images : Fetch muscle group images for exercises} {--dry-run : Show what would be imported without actually importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import exercises from PHP array data structure';

    private array $lookups = [];

    /**
     * Execute the console command.
     */
    public function handle(MuscleGroupImageService $muscleGroupImageService): int
    {
        $this->info('Preparing lookup tables...');
        $this->prepareLookups();

        $this->info('Loading exercise data...');
        $exercises = $this->getExerciseData();

        if (empty($exercises)) {
            $this->warn('No exercises found in data array.');

            return Command::SUCCESS;
        }

        $this->info('Found '.count($exercises).' exercises to process.');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made to the database.');
            $this->newLine();
        }

        $canFetchImages = $this->option('fetch-images') && $muscleGroupImageService->isConfigured();
        if ($this->option('fetch-images') && ! $muscleGroupImageService->isConfigured()) {
            $this->warn('MuscleGroupImageService is not configured. Skipping image fetching.');
        }

        $total = count($exercises);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $imagesFetched = 0;
        $imagesSkipped = 0;

        foreach ($exercises as $index => $exerciseData) {
            $current = $index + 1;
            $this->info("Processing exercise {$current}/{$total}: {$exerciseData['name']}");

            try {
                // Validate required fields
                if (! $this->validateExerciseData($exerciseData)) {
                    $this->warn('  ⚠ Skipped - Missing required fields');
                    $skipped++;
                    $this->newLine();

                    continue;
                }

                // Look up relationships
                $relationships = $this->lookupRelationships($exerciseData);
                if ($relationships === null) {
                    $this->warn('  ⚠ Skipped - Invalid relationships');
                    $skipped++;
                    $this->newLine();

                    continue;
                }

                // Handle muscle group image
                $muscleGroupImagePath = null;
                if ($canFetchImages && isset($exerciseData['primary']) && isset($exerciseData['secondary'])) {
                    $primaryMuscles = $exerciseData['primary'];
                    $secondaryMuscles = $exerciseData['secondary'] ?? [];

                    // Check if image already exists
                    if ($muscleGroupImageService->imageExists($primaryMuscles, $secondaryMuscles)) {
                        $muscleGroupImagePath = $muscleGroupImageService->getImagePath($primaryMuscles, $secondaryMuscles);
                        $imagesSkipped++;
                    } else {
                        $muscleGroupImagePath = $muscleGroupImageService->fetchAndStoreMuscleImage(
                            $primaryMuscles,
                            $secondaryMuscles
                        );
                        if ($muscleGroupImagePath !== null) {
                            $imagesFetched++;
                        }
                    }
                }

                if ($this->option('dry-run')) {
                    $this->line("  [DRY RUN] Would create/update: {$exerciseData['name']}");
                    $created++;
                } else {
                    // Create or update exercise
                    $exercise = Exercise::firstOrCreate(
                        ['name' => $exerciseData['name']],
                        [
                            'category_id' => $relationships['category_id'],
                            'movement_pattern_id' => $relationships['movement_pattern_id'],
                            'target_region_id' => $relationships['target_region_id'],
                            'equipment_type_id' => $relationships['equipment_type_id'],
                            'angle_id' => $relationships['angle_id'],
                            'description' => $exerciseData['description'] ?? null,
                            'default_rest_sec' => $exerciseData['default_rest_sec'] ?? 90,
                            'muscle_group_image' => $muscleGroupImagePath,
                        ]
                    );

                    // Update if exercise already existed
                    if (! $exercise->wasRecentlyCreated) {
                        $updateData = [
                            'category_id' => $relationships['category_id'],
                            'movement_pattern_id' => $relationships['movement_pattern_id'],
                            'target_region_id' => $relationships['target_region_id'],
                            'equipment_type_id' => $relationships['equipment_type_id'],
                            'angle_id' => $relationships['angle_id'],
                        ];

                        if (isset($exerciseData['description'])) {
                            $updateData['description'] = $exerciseData['description'];
                        }

                        if (isset($exerciseData['default_rest_sec'])) {
                            $updateData['default_rest_sec'] = $exerciseData['default_rest_sec'];
                        }

                        // Update image if exercise exists but has no image
                        if (empty($exercise->muscle_group_image) && $muscleGroupImagePath !== null) {
                            $updateData['muscle_group_image'] = $muscleGroupImagePath;
                        }

                        $exercise->update($updateData);
                        $updated++;
                        $this->line('  ✓ Updated existing exercise');
                    } else {
                        $created++;
                        $this->line('  ✓ Created new exercise');
                    }

                    // Sync muscle groups
                    if (isset($exerciseData['primary']) || isset($exerciseData['secondary'])) {
                        $muscleGroupAttachments = [];

                        // Add primary muscle groups
                        if (isset($exerciseData['primary']) && is_array($exerciseData['primary'])) {
                            foreach ($exerciseData['primary'] as $muscleName) {
                                if (isset($this->lookups['muscle_groups'][$muscleName])) {
                                    $muscleGroupAttachments[$this->lookups['muscle_groups'][$muscleName]] = ['is_primary' => true];
                                }
                            }
                        }

                        // Add secondary muscle groups
                        if (isset($exerciseData['secondary']) && is_array($exerciseData['secondary'])) {
                            foreach ($exerciseData['secondary'] as $muscleName) {
                                if (isset($this->lookups['muscle_groups'][$muscleName])) {
                                    // If already in array as primary, skip (can't be both)
                                    if (! isset($muscleGroupAttachments[$this->lookups['muscle_groups'][$muscleName]])) {
                                        $muscleGroupAttachments[$this->lookups['muscle_groups'][$muscleName]] = ['is_primary' => false];
                                    }
                                }
                            }
                        }

                        if (! empty($muscleGroupAttachments)) {
                            $exercise->muscleGroups()->sync($muscleGroupAttachments);
                            $this->line('  ✓ Synced muscle groups');
                        }
                    }

                    // Sync training styles (default to BODYBUILDING if not specified)
                    $trainingStyleCodes = $exerciseData['training_styles'] ?? ['BODYBUILDING'];
                    $trainingStyleAttachments = [];

                    foreach ($trainingStyleCodes as $styleCode) {
                        if (isset($this->lookups['training_styles'][$styleCode])) {
                            $trainingStyleAttachments[] = $this->lookups['training_styles'][$styleCode];
                        }
                    }

                    if (! empty($trainingStyleAttachments)) {
                        $exercise->trainingStyles()->sync($trainingStyleAttachments);
                        $this->line('  ✓ Synced training styles');
                    }
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error('Error importing exercise', [
                    'exercise_data' => $exerciseData,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  ✗ Failed - {$e->getMessage()}");
            }

            $this->newLine();
        }

        // Output summary
        $this->outputSummary($created, $updated, $skipped, $failed, $imagesFetched, $imagesSkipped);

        return Command::SUCCESS;
    }

    /**
     * Prepare lookup tables for relationships
     */
    private function prepareLookups(): void
    {
        // Categories by slug
        $this->lookups['categories'] = Category::where('type', CategoryType::Workout)
            ->pluck('id', 'slug')
            ->toArray();

        // Muscle groups by name
        $this->lookups['muscle_groups'] = MuscleGroup::pluck('id', 'name')->toArray();

        // Movement patterns by code
        $this->lookups['movement_patterns'] = MovementPattern::pluck('id', 'code')->toArray();

        // Target regions by code
        $this->lookups['target_regions'] = TargetRegion::pluck('id', 'code')->toArray();

        // Equipment types by code
        $this->lookups['equipment_types'] = EquipmentType::pluck('id', 'code')->toArray();

        // Angles by code
        $this->lookups['angles'] = Angle::pluck('id', 'code')->toArray();

        // Training styles by code
        $this->lookups['training_styles'] = TrainingStyle::pluck('id', 'code')->toArray();
    }

    /**
     * Look up relationship IDs from exercise data
     */
    private function lookupRelationships(array $exerciseData): ?array
    {
        $relationships = [
            'category_id' => null,
            'movement_pattern_id' => null,
            'target_region_id' => null,
            'equipment_type_id' => null,
            'angle_id' => null,
        ];

        // Category (by slug)
        if (isset($exerciseData['category_slug'])) {
            if (! isset($this->lookups['categories'][$exerciseData['category_slug']])) {
                $this->warn("    Category not found: {$exerciseData['category_slug']}");

                return null;
            }
            $relationships['category_id'] = $this->lookups['categories'][$exerciseData['category_slug']];
        }

        // Movement pattern (by code)
        if (isset($exerciseData['movement_pattern'])) {
            if (! isset($this->lookups['movement_patterns'][$exerciseData['movement_pattern']])) {
                $this->warn("    Movement pattern not found: {$exerciseData['movement_pattern']}");

                return null;
            }
            $relationships['movement_pattern_id'] = $this->lookups['movement_patterns'][$exerciseData['movement_pattern']];
        }

        // Target region (by code)
        if (isset($exerciseData['target_region'])) {
            if (! isset($this->lookups['target_regions'][$exerciseData['target_region']])) {
                $this->warn("    Target region not found: {$exerciseData['target_region']}");

                return null;
            }
            $relationships['target_region_id'] = $this->lookups['target_regions'][$exerciseData['target_region']];
        }

        // Equipment type (by code)
        if (isset($exerciseData['equipment_type'])) {
            if (! isset($this->lookups['equipment_types'][$exerciseData['equipment_type']])) {
                $this->warn("    Equipment type not found: {$exerciseData['equipment_type']}");

                return null;
            }
            $relationships['equipment_type_id'] = $this->lookups['equipment_types'][$exerciseData['equipment_type']];
        }

        // Angle (by code, optional)
        if (isset($exerciseData['angle'])) {
            if (! isset($this->lookups['angles'][$exerciseData['angle']])) {
                $this->warn("    Angle not found: {$exerciseData['angle']}");

                return null;
            }
            $relationships['angle_id'] = $this->lookups['angles'][$exerciseData['angle']];
        }

        return $relationships;
    }

    /**
     * Validate exercise data has required fields
     */
    private function validateExerciseData(array $exerciseData): bool
    {
        if (empty($exerciseData['name'])) {
            return false;
        }

        // At least one of these should be present
        if (empty($exerciseData['primary']) && empty($exerciseData['secondary'])) {
            return false;
        }

        return true;
    }

    /**
     * Get exercise data array
     *
     * Modify this method to add your exercises. Each exercise should be an array with:
     *
     * Required fields:
     * - name: string (exercise name)
     * - primary: array (primary muscle group names, e.g., ['Chest', 'Triceps'])
     *
     * Optional fields:
     * - description: string
     * - default_rest_sec: int (default: 90)
     * - category_slug: string (e.g., 'strength-training', 'cardio')
     * - movement_pattern: string (code, e.g., 'PRESS', 'ROW', 'SQUAT')
     * - target_region: string (code, e.g., 'UPPER_PUSH', 'LOWER', 'CORE')
     * - equipment_type: string (code, e.g., 'BARBELL', 'DUMBBELL', 'BODYWEIGHT')
     * - angle: string (code, e.g., 'FLAT', 'INCLINE', 'DECLINE') - optional
     * - secondary: array (secondary muscle group names, e.g., ['Shoulders'])
     *
     * @return array<int, array<string, mixed>>
     */
    private function getExerciseData(): array
    {
        return [

            // Add your exercises here in this format
            [
                'name' => 'TRX Squat',
                'description' => 'Assisted bodyweight squat using TRX straps.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Quadriceps'],
                'secondary' => ['Glutes'],
                'movement_pattern' => 'SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'TRX',
                'angle' => 'VERTICAL',
                'training_styles' => ['BODYBUILDING', 'FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Reverse Lunge',
                'description' => 'Reverse lunge using TRX for assistance.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Glutes', 'Quadriceps'],
                'secondary' => ['Hamstrings'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'TRX',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Row',
                'description' => 'Suspension bodyweight row.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Lats'],
                'secondary' => ['Upper Back', 'Biceps'],
                'movement_pattern' => 'ROW',
                'target_region' => 'UPPER_PULL',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['BODYBUILDING', 'FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Wide Row',
                'description' => 'Wide grip suspension row targeting upper back.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Upper Back'],
                'secondary' => ['Rear Delts', 'Biceps'],
                'movement_pattern' => 'ROW',
                'target_region' => 'UPPER_PULL',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['BODYBUILDING', 'FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Y Fly',
                'description' => 'Suspension Y raise targeting rear delts and upper back.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Rear Delts'],
                'secondary' => ['Upper Back', 'Traps'],
                'movement_pattern' => 'REAR_DELT_FLY',
                'target_region' => 'UPPER_PULL',
                'equipment_type' => 'TRX',
                'angle' => 'LOW_TO_HIGH',
                'training_styles' => ['BODYBUILDING', 'FUNCTIONAL'],
            ],
            [
                'name' => 'TRX T Fly',
                'description' => 'Suspension T raise targeting rear delts.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Rear Delts'],
                'secondary' => ['Upper Back'],
                'movement_pattern' => 'REAR_DELT_FLY',
                'target_region' => 'UPPER_PULL',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['BODYBUILDING', 'FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Biceps Curl',
                'description' => 'Suspension bodyweight biceps curl.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Biceps'],
                'secondary' => ['Forearms'],
                'movement_pattern' => 'ELBOW_FLEXION',
                'target_region' => 'ARMS',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['BODYBUILDING', 'FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Triceps Extension',
                'description' => 'Suspension triceps extension.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Triceps'],
                'secondary' => ['Front Delts'],
                'movement_pattern' => 'ELBOW_EXTENSION',
                'target_region' => 'ARMS',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['BODYBUILDING', 'FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Push-Up',
                'description' => 'Suspension push-up variation.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Chest'],
                'secondary' => ['Triceps', 'Front Delts'],
                'movement_pattern' => 'PUSHUP',
                'target_region' => 'UPPER_PUSH',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['BODYBUILDING', 'FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Chest Fly',
                'description' => 'Suspension chest fly movement.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Chest'],
                'secondary' => ['Front Delts'],
                'movement_pattern' => 'FLY',
                'target_region' => 'UPPER_PUSH',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['BODYBUILDING', 'FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Squat to Y Fly',
                'description' => 'Squat combined with wide fly pull.',
                'default_rest_sec' => 60,
                'category_slug' => 'bodyweight',
                'primary' => ['Quadriceps'],
                'secondary' => ['Glutes', 'Upper Back'],
                'movement_pattern' => 'SQUAT',
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'TRX',
                'angle' => 'LOW_TO_HIGH',
                'training_styles' => ['FUNCTIONAL'],
            ],

            // CORE
            [
                'name' => 'TRX Pike',
                'description' => 'Suspension plank pike.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Abs'],
                'secondary' => ['Obliques'],
                'movement_pattern' => 'TRUNK_FLEXION',
                'target_region' => 'CORE',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Knee Tuck',
                'description' => 'Suspension plank knee tuck.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Abs'],
                'secondary' => ['Hip Flexors'],
                'movement_pattern' => 'TRUNK_FLEXION',
                'target_region' => 'CORE',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Mountain Climber',
                'description' => 'Suspension mountain climbers.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Abs'],
                'secondary' => ['Quadriceps'],
                'movement_pattern' => 'TRUNK_FLEXION',
                'target_region' => 'CORE',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Plank',
                'description' => 'Suspension plank hold.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Abs'],
                'secondary' => ['Obliques'],
                'movement_pattern' => 'ANTI_ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Body Saw',
                'description' => 'Suspension plank sliding movement.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Abs'],
                'secondary' => ['Obliques'],
                'movement_pattern' => 'ANTI_ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Atomic Push-Up',
                'description' => 'Push-up combined with knee tuck.',
                'default_rest_sec' => 60,
                'category_slug' => 'bodyweight',
                'primary' => ['Chest'],
                'secondary' => ['Abs', 'Triceps'],
                'movement_pattern' => 'PUSHUP',
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Rollout',
                'description' => 'Suspension ab rollout.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Abs'],
                'secondary' => ['Lats'],
                'movement_pattern' => 'ANTI_ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'TRX',
                'angle' => 'LOW_TO_HIGH',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Bulgarian Split Squat',
                'description' => 'Rear foot elevated split squat using TRX.',
                'default_rest_sec' => 60,
                'category_slug' => 'bodyweight',
                'primary' => ['Quadriceps'],
                'secondary' => ['Glutes'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'TRX',
                'angle' => 'VERTICAL',
                'training_styles' => ['BODYBUILDING', 'FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Hamstring Curl',
                'description' => 'Suspension hamstring leg curl.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Hamstrings'],
                'secondary' => ['Glutes'],
                'movement_pattern' => 'KNEE_FLEXION',
                'target_region' => 'LOWER',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['BODYBUILDING', 'FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Glute Bridge',
                'description' => 'Suspension hamstring bridge.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Glutes'],
                'secondary' => ['Hamstrings'],
                'movement_pattern' => 'HIP_THRUST_BRIDGE',
                'target_region' => 'LOWER',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Fallout',
                'description' => 'Standing suspension forward lean core movement.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Abs'],
                'secondary' => ['Lats'],
                'movement_pattern' => 'ANTI_ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'TRX',
                'angle' => 'LOW_TO_HIGH',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Reverse Lunge with Knee Drive',
                'description' => 'Reverse lunge transitioning into knee drive.',
                'default_rest_sec' => 60,
                'category_slug' => 'bodyweight',
                'primary' => ['Glutes', 'Quadriceps'],
                'secondary' => ['Abs'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'TRX',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Side Plank',
                'description' => 'Suspension side plank hold.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Obliques'],
                'secondary' => ['Abs'],
                'movement_pattern' => 'ANTI_ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'TRX',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'TRX Pistol Squat',
                'description' => 'Assisted single-leg squat using TRX.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Quadriceps'],
                'secondary' => ['Glutes'],
                'movement_pattern' => 'SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'TRX',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Army Plank',
                'description' => 'Forearm plank hold.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Abs'],
                'secondary' => ['Obliques', 'Lower Back'],
                'movement_pattern' => 'ANTI_ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'BODYWEIGHT',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Ballerina Split Stretch',
                'description' => 'Static split stretch variation.',
                'default_rest_sec' => 30,
                'category_slug' => 'mobility-flexibility',
                'primary' => ['Hamstrings'],
                'secondary' => ['Glutes', 'Quadriceps'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'BODYWEIGHT',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Commando Plank',
                'description' => 'Alternating forearm-to-hand plank movement.',
                'default_rest_sec' => 45,
                'category_slug' => 'bodyweight',
                'primary' => ['Abs'],
                'secondary' => ['Front Delts', 'Triceps'],
                'movement_pattern' => 'ANTI_ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'BODYWEIGHT',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Cross Body Mountain Climbers',
                'description' => 'Mountain climbers bringing knee across body.',
                'default_rest_sec' => 45,
                'category_slug' => 'functional-training',
                'primary' => ['Abs'],
                'secondary' => ['Obliques', 'Quadriceps'],
                'movement_pattern' => 'TRUNK_FLEXION',
                'target_region' => 'CORE',
                'equipment_type' => 'BODYWEIGHT',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Dumbbell Cossack Lunge',
                'description' => 'Side-to-side deep lunge holding dumbbell.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Glutes', 'Quadriceps'],
                'secondary' => ['Hamstrings'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Dumbbell Ground to Overhead',
                'description' => 'Lift dumbbell from floor to overhead in one motion.',
                'default_rest_sec' => 75,
                'category_slug' => 'hybrid-crossfit',
                'primary' => ['Quadriceps', 'Front Delts'],
                'secondary' => ['Glutes', 'Triceps', 'Abs'],
                'movement_pattern' => 'HINGE',
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'LOW_TO_HIGH',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Dumbbell Overhead March',
                'description' => 'Marching in place with dumbbell held overhead.',
                'default_rest_sec' => 45,
                'category_slug' => 'functional-training',
                'primary' => ['Abs'],
                'secondary' => ['Front Delts', 'Obliques'],
                'movement_pattern' => 'CARRY',
                'target_region' => 'CORE',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Dumbbell Side Row',
                'description' => 'Single-arm bent-over dumbbell row.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Lats'],
                'secondary' => ['Upper Back', 'Biceps'],
                'movement_pattern' => 'ROW',
                'target_region' => 'UPPER_PULL',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Dumbbell T-Plank Row',
                'description' => 'Renegade row with rotation into side plank.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Abs'],
                'secondary' => ['Lats', 'Obliques'],
                'movement_pattern' => 'ROW',
                'target_region' => 'CORE',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Foot Tap Snap',
                'description' => 'Explosive foot tap and snap movement.',
                'default_rest_sec' => 30,
                'category_slug' => 'cardio',
                'primary' => ['Quadriceps'],
                'secondary' => ['Calves'],
                'movement_pattern' => 'SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'BODYWEIGHT',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Kettlebell Alternating Reverse Lunge',
                'description' => 'Alternating reverse lunges holding kettlebell.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Glutes', 'Quadriceps'],
                'secondary' => ['Hamstrings'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Kettlebell Figure Eight',
                'description' => 'Kettlebell pass-through figure eight movement.',
                'default_rest_sec' => 45,
                'category_slug' => 'functional-training',
                'primary' => ['Abs'],
                'secondary' => ['Obliques', 'Glutes'],
                'movement_pattern' => 'HINGE',
                'target_region' => 'CORE',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Kettlebell High Plank Pull Through',
                'description' => 'Dragging kettlebell side-to-side in plank.',
                'default_rest_sec' => 45,
                'category_slug' => 'functional-training',
                'primary' => ['Abs'],
                'secondary' => ['Obliques', 'Lats'],
                'movement_pattern' => 'ANTI_ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Kettlebell Sumo Squat',
                'description' => 'Wide stance squat holding kettlebell.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Glutes', 'Quadriceps'],
                'secondary' => ['Hamstrings'],
                'movement_pattern' => 'SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Sit Through',
                'description' => 'Rotational ground movement switching legs through.',
                'default_rest_sec' => 45,
                'category_slug' => 'functional-training',
                'primary' => ['Obliques'],
                'secondary' => ['Abs', 'Front Delts'],
                'movement_pattern' => 'ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'BODYWEIGHT',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Snap Plank Push Back',
                'description' => 'Explosive plank to push-back movement.',
                'default_rest_sec' => 45,
                'category_slug' => 'functional-training',
                'primary' => ['Abs'],
                'secondary' => ['Front Delts', 'Quadriceps'],
                'movement_pattern' => 'ANTI_ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'BODYWEIGHT',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Half-Kneeling Windmill',
                'description' => 'Half-kneeling kettlebell windmill variation.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Obliques'],
                'secondary' => ['Glutes', 'Front Delts'],
                'movement_pattern' => 'ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'HIGH_TO_LOW',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Standing Windmill',
                'description' => 'Standing kettlebell windmill movement.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Obliques'],
                'secondary' => ['Glutes', 'Hamstrings'],
                'movement_pattern' => 'ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'HIGH_TO_LOW',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Medicine Ball Half-Kneeling Side Slam',
                'description' => 'Half-kneeling rotational slam with a medicine ball.',
                'default_rest_sec' => 45,
                'category_slug' => 'functional-training',
                'primary' => ['Obliques'],
                'secondary' => ['Abs', 'Front Delts'],
                'movement_pattern' => 'ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'MEDICINE_BALL',
                'angle' => 'HIGH_TO_LOW',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Medicine Ball Kneeling Slam',
                'description' => 'Kneeling overhead medicine ball slam.',
                'default_rest_sec' => 45,
                'category_slug' => 'functional-training',
                'primary' => ['Abs'],
                'secondary' => ['Lats', 'Front Delts', 'Triceps'],
                'movement_pattern' => 'TRUNK_FLEXION',
                'target_region' => 'CORE',
                'equipment_type' => 'MEDICINE_BALL',
                'angle' => 'HIGH_TO_LOW',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Medicine Ball Rotational Throw',
                'description' => 'Explosive standing rotational medicine ball throw.',
                'default_rest_sec' => 45,
                'category_slug' => 'functional-training',
                'primary' => ['Obliques'],
                'secondary' => ['Glutes', 'Front Delts'],
                'movement_pattern' => 'ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'MEDICINE_BALL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Medicine Ball Standing Side Slam',
                'description' => 'Explosive standing rotational slam with medicine ball.',
                'default_rest_sec' => 45,
                'category_slug' => 'functional-training',
                'primary' => ['Obliques'],
                'secondary' => ['Abs', 'Glutes', 'Front Delts'],
                'movement_pattern' => 'ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'MEDICINE_BALL',
                'angle' => 'HIGH_TO_LOW',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Landmine Squat to Press',
                'description' => 'Full body squat into landmine press.',
                'default_rest_sec' => 90,
                'category_slug' => 'strength-training',
                'primary' => ['Quadriceps', 'Front Delts'],
                'secondary' => ['Glutes', 'Triceps', 'Abs'],
                'movement_pattern' => 'SQUAT', // dominant driver
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'LANDMINE',
                'angle' => 'LOW_TO_HIGH',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Landmine Reverse Lunge',
                'description' => 'Reverse lunge holding a landmine barbell.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Quadriceps', 'Glutes'],
                'secondary' => ['Hamstrings', 'Abs'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'LANDMINE',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Standing Landmine Press',
                'description' => 'Standing single-arm landmine press.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Front Delts'],
                'secondary' => ['Triceps', 'Chest', 'Abs'],
                'movement_pattern' => 'PRESS',
                'target_region' => 'UPPER_PUSH',
                'equipment_type' => 'LANDMINE',
                'angle' => 'LOW_TO_HIGH',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Standing Landmine Rotation',
                'description' => 'Standing rotational landmine movement (across the body).',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Obliques'],
                'secondary' => ['Abs', 'Front Delts'],
                'movement_pattern' => 'ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'LANDMINE',
                'angle' => 'HIGH_TO_LOW',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Half-Kneeling Landmine Press',
                'description' => 'Half-kneeling single-arm landmine press.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Front Delts'],
                'secondary' => ['Triceps', 'Abs'],
                'movement_pattern' => 'PRESS',
                'target_region' => 'UPPER_PUSH',
                'equipment_type' => 'LANDMINE',
                'angle' => 'LOW_TO_HIGH',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Half-Kneeling Alternating Landmine Press',
                'description' => 'Alternating landmine press from half-kneeling position.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Front Delts'],
                'secondary' => ['Triceps', 'Abs', 'Obliques'],
                'movement_pattern' => 'PRESS',
                'target_region' => 'UPPER_PUSH',
                'equipment_type' => 'LANDMINE',
                'angle' => 'LOW_TO_HIGH',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Landmine Deadlift',
                'description' => 'Deadlift holding the end of a landmine barbell.',
                'default_rest_sec' => 90,
                'category_slug' => 'strength-training',
                'primary' => ['Glutes', 'Hamstrings'],
                'secondary' => ['Lower Back', 'Quadriceps'],
                'movement_pattern' => 'HINGE',
                'target_region' => 'LOWER',
                'equipment_type' => 'LANDMINE',
                'angle' => 'LOW_TO_HIGH',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Landmine Sumo Squat',
                'description' => 'Wide stance squat holding a landmine bar.',
                'default_rest_sec' => 75,
                'category_slug' => 'strength-training',
                'primary' => ['Glutes', 'Quadriceps'],
                'secondary' => ['Hamstrings', 'Abs'],
                'movement_pattern' => 'SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'LANDMINE',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Single-Leg Landmine Romanian Deadlift',
                'description' => 'Single-leg hip hinge with the landmine bar.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Glutes', 'Hamstrings'],
                'secondary' => ['Abs', 'Lower Back'],
                'movement_pattern' => 'HINGE',
                'target_region' => 'LOWER',
                'equipment_type' => 'LANDMINE',
                'angle' => 'LOW_TO_HIGH',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Single-Arm Landmine Row',
                'description' => 'Row using the landmine barbell.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Lats'],
                'secondary' => ['Upper Back', 'Biceps', 'Rear Delts'],
                'movement_pattern' => 'ROW',
                'target_region' => 'UPPER_PULL',
                'equipment_type' => 'LANDMINE',
                'angle' => 'LOW_TO_HIGH',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Landmine Thruster',
                'description' => 'Explosive squat into landmine press.',
                'default_rest_sec' => 90,
                'category_slug' => 'hybrid-crossfit',
                'primary' => ['Quadriceps', 'Front Delts'],
                'secondary' => ['Glutes', 'Triceps', 'Abs'],
                'movement_pattern' => 'SQUAT', // dominant driver; press is secondary
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'LANDMINE',
                'angle' => 'LOW_TO_HIGH',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Half-Kneeling Landmine Rotation',
                'description' => 'Rotational core movement from a half-kneeling position.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Obliques'],
                'secondary' => ['Abs', 'Front Delts'],
                'movement_pattern' => 'ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'LANDMINE',
                'angle' => 'HIGH_TO_LOW',
                'training_styles' => ['FUNCTIONAL'],
            ],
            [
                'name' => 'Landmine Lateral Lunge',
                'description' => 'Side lunge holding the landmine bar.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Glutes', 'Quadriceps'],
                'secondary' => ['Hamstrings', 'Abs'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'LANDMINE',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Cossack Lunge',
                'description' => 'Side-to-side deep lateral lunge.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Glutes'],
                'secondary' => ['Quadriceps', 'Adductors'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'BODYWEIGHT',
                'angle' => 'VERTICAL',
                'training_styles' => ['BODYBUILDING', 'FUNCTIONAL'],
            ],

            [
                'name' => 'Lunge to March',
                'description' => 'Forward or reverse lunge into knee drive.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Glutes'],
                'secondary' => ['Quadriceps', 'Abs'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'BODYWEIGHT',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Cross Lunge',
                'description' => 'Diagonal lunge targeting glutes.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Glutes'],
                'secondary' => ['Quadriceps'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'BODYWEIGHT',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Overhead Dumbbell March',
                'description' => 'Walking march holding dumbbells overhead.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Front Delts'],
                'secondary' => ['Abs', 'Traps'],
                'movement_pattern' => 'CARRY',
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Renegade Row',
                'description' => 'Plank row using dumbbells.',
                'default_rest_sec' => 75,
                'category_slug' => 'functional-training',
                'primary' => ['Lats'],
                'secondary' => ['Abs', 'Biceps'],
                'movement_pattern' => 'ROW',
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Power Snatch',
                'description' => 'Explosive lift from floor to overhead.',
                'default_rest_sec' => 180,
                'category_slug' => 'power-olympic-lifting',
                'primary' => ['Glutes'],
                'secondary' => ['Traps', 'Delts'],
                'movement_pattern' => 'HINGE',
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'BARBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['OLYMPIC'],
            ],

            [
                'name' => 'Dumbbell Thruster',
                'description' => 'Squat into overhead press.',
                'default_rest_sec' => 90,
                'category_slug' => 'hybrid-crossfit',
                'primary' => ['Quadriceps'],
                'secondary' => ['Delts', 'Triceps'],
                'movement_pattern' => 'SQUAT',
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['OLYMPIC'],
            ],

            [
                'name' => 'Dumbbell Step-Up',
                'description' => 'Step onto elevated surface holding dumbbells.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Quadriceps'],
                'secondary' => ['Glutes'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Weighted Squat Jump',
                'description' => 'Explosive squat jump holding weight.',
                'default_rest_sec' => 90,
                'category_slug' => 'power-olympic-lifting',
                'primary' => ['Quadriceps'],
                'secondary' => ['Glutes', 'Calves'],
                'movement_pattern' => 'SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Dumbbell Overhead Triceps Extension',
                'description' => 'Overhead elbow extension.',
                'default_rest_sec' => 45,
                'category_slug' => 'strength-training',
                'primary' => ['Triceps'],
                'secondary' => [],
                'movement_pattern' => 'ELBOW_EXTENSION',
                'target_region' => 'ARMS',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'VERTICAL',
            ],

            [
                'name' => 'Dumbbell Triceps Kickback',
                'description' => 'Hip hinge triceps extension.',
                'default_rest_sec' => 45,
                'category_slug' => 'strength-training',
                'primary' => ['Triceps'],
                'secondary' => [],
                'movement_pattern' => 'ELBOW_EXTENSION',
                'target_region' => 'ARMS',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'HORIZONTAL',
            ],

            [
                'name' => 'Dumbbell Biceps Curl',
                'description' => 'Elbow flexion exercise.',
                'default_rest_sec' => 45,
                'category_slug' => 'strength-training',
                'primary' => ['Biceps'],
                'secondary' => [],
                'movement_pattern' => 'ELBOW_FLEXION',
                'target_region' => 'ARMS',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'VERTICAL',
            ],

            [
                'name' => 'Hammer Curl',
                'description' => 'Neutral grip dumbbell curl.',
                'default_rest_sec' => 45,
                'category_slug' => 'strength-training',
                'primary' => ['Biceps'],
                'secondary' => ['Forearms'],
                'movement_pattern' => 'ELBOW_FLEXION',
                'target_region' => 'ARMS',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'VERTICAL',
            ],

            [
                'name' => 'Narrow Squat',
                'description' => 'Close stance squat.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Quadriceps'],
                'secondary' => ['Glutes'],
                'movement_pattern' => 'SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'BODYWEIGHT',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Single Leg Hip Thrust',
                'description' => 'Single leg hip bridge/thrust.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Glutes'],
                'secondary' => ['Hamstrings'],
                'movement_pattern' => 'HIP_THRUST_BRIDGE',
                'target_region' => 'LOWER',
                'equipment_type' => 'BODYWEIGHT',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Single Leg Deadlift',
                'description' => 'Unilateral hinge movement.',
                'default_rest_sec' => 60,
                'category_slug' => 'strength-training',
                'primary' => ['Glutes'],
                'secondary' => ['Hamstrings'],
                'movement_pattern' => 'HINGE',
                'target_region' => 'LOWER',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],

            ],

            [
                'name' => 'Kettlebell Swing',
                'description' => 'Explosive hip hinge swinging kettlebell to chest height.',
                'default_rest_sec' => 60,
                'category_slug' => 'power-olympic-lifting',
                'primary' => ['Glutes'],
                'secondary' => ['Hamstrings', 'Lower Back'],
                'movement_pattern' => 'HINGE',
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Single Arm Kettlebell Swing',
                'description' => 'One-arm explosive kettlebell swing.',
                'default_rest_sec' => 60,
                'category_slug' => 'power-olympic-lifting',
                'primary' => ['Glutes'],
                'secondary' => ['Obliques', 'Hamstrings'],
                'movement_pattern' => 'HINGE',
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Goblet Squat',
                'description' => 'Squat holding a dumbbell at chest.',
                'default_rest_sec' => 75,
                'category_slug' => 'strength-training',
                'primary' => ['Quadriceps'],
                'secondary' => ['Glutes', 'Abs'],
                'movement_pattern' => 'SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'DUMBBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['BODYBUILDING', 'FUNCTIONAL'],
            ],

            [
                'name' => 'Kettlebell Deadlift',
                'description' => 'Hip hinge lifting kettlebell from floor.',
                'default_rest_sec' => 75,
                'category_slug' => 'strength-training',
                'primary' => ['Glutes'],
                'secondary' => ['Hamstrings', 'Lower Back'],
                'movement_pattern' => 'HINGE',
                'target_region' => 'LOWER',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Single Leg Kettlebell Deadlift',
                'description' => 'Unilateral kettlebell hinge.',
                'default_rest_sec' => 75,
                'category_slug' => 'strength-training',
                'primary' => ['Glutes'],
                'secondary' => ['Hamstrings', 'Abs'],
                'movement_pattern' => 'HINGE',
                'target_region' => 'LOWER',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Kettlebell Windmill',
                'description' => 'Rotational hinge with kettlebell overhead.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Obliques'],
                'secondary' => ['Shoulders', 'Glutes'],
                'movement_pattern' => 'ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Kettlebell Lateral Lunge',
                'description' => 'Side lunge holding kettlebell.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Glutes'],
                'secondary' => ['Quadriceps', 'Adductors'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Kettlebell Reverse Lunge',
                'description' => 'Reverse lunge holding kettlebell.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Glutes'],
                'secondary' => ['Quadriceps'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],

            ],

            [
                'name' => 'Kettlebell Swing to Lateral Lunge',
                'description' => 'Swing followed by lateral lunge.',
                'default_rest_sec' => 75,
                'category_slug' => 'hybrid-crossfit',
                'primary' => ['Glutes'],
                'secondary' => ['Quadriceps', 'Obliques'],
                'movement_pattern' => 'HINGE',
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],

            ],

            [
                'name' => 'Sumo Squat (Kettlebell)',
                'description' => 'Wide stance squat holding kettlebell.',
                'default_rest_sec' => 75,
                'category_slug' => 'strength-training',
                'primary' => ['Glutes'],
                'secondary' => ['Quadriceps'],
                'movement_pattern' => 'SQUAT',
                'target_region' => 'LOWER',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],

            ],

            [
                'name' => 'Kettlebell Halo to Reverse Lunge',
                'description' => 'Halo around head followed by reverse lunge.',
                'default_rest_sec' => 75,
                'category_slug' => 'functional-training',
                'primary' => ['Glutes'],
                'secondary' => ['Shoulders', 'Abs'],
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL'],

            ],

            [
                'name' => 'Kettlebell Figure 8',
                'description' => 'Passing kettlebell between legs in figure 8 pattern.',
                'default_rest_sec' => 60,
                'category_slug' => 'functional-training',
                'primary' => ['Obliques'],
                'secondary' => ['Glutes', 'Abs'],
                'movement_pattern' => 'ROTATION',
                'target_region' => 'CORE',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],

            ],

            [
                'name' => 'Kettlebell Gorilla Row',
                'description' => 'Bent-over alternating kettlebell rows.',
                'default_rest_sec' => 75,
                'category_slug' => 'strength-training',
                'primary' => ['Lats'],
                'secondary' => ['Biceps', 'Upper Back'],
                'movement_pattern' => 'ROW',
                'target_region' => 'UPPER_PULL',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],

            ],

            [
                'name' => 'Single Leg Bridge Pullover',
                'description' => 'Bridge combined with pullover movement.',
                'default_rest_sec' => 75,
                'category_slug' => 'functional-training',
                'primary' => ['Glutes'],
                'secondary' => ['Lats', 'Abs'],
                'movement_pattern' => 'HIP_THRUST_BRIDGE',
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'HORIZONTAL',
                'training_styles' => ['FUNCTIONAL'],
            ],

            [
                'name' => 'Kettlebell Clean and Press',
                'description' => 'Clean kettlebell to shoulder then press overhead.',
                'default_rest_sec' => 90,
                'category_slug' => 'power-olympic-lifting',
                'primary' => ['Shoulders'],
                'secondary' => ['Glutes', 'Triceps'],
                'movement_pattern' => 'PRESS',
                'target_region' => 'FULL_BODY',
                'equipment_type' => 'KETTLEBELL',
                'angle' => 'VERTICAL',
                'training_styles' => ['FUNCTIONAL', 'OLYMPIC'],
            ],

        ];
    }

    /**
     * Output summary statistics
     */
    private function outputSummary(int $created, int $updated, int $skipped, int $failed, int $imagesFetched, int $imagesSkipped): void
    {
        $this->newLine();
        $this->info('Summary:');
        $this->line("  Created: {$created}");
        $this->line("  Updated: {$updated}");
        $this->line("  Skipped: {$skipped}");
        $this->line("  Failed: {$failed}");

        if ($this->option('fetch-images')) {
            $this->line("  Images fetched: {$imagesFetched}");
            $this->line("  Images skipped (already exist): {$imagesSkipped}");
        }

        if ($this->option('dry-run')) {
            $this->warn('  Mode: DRY RUN (no changes made)');
        }
    }
}
