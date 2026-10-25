<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Angle;
use App\Models\Category;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\TargetRegion;
use App\Models\TrainingStyle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExerciseClassificationSeeder extends Seeder
{
    /**
     * Lookup arrays populated from database.
     *
     * @var array<string, int>
     */
    private array $movementPatterns = [];

    private array $targetRegions = [];

    private array $equipmentTypes = [];

    private array $angles = [];

    private array $categories = [];

    private array $trainingStyles = [];

    /**
     * Movement pattern to target region mapping.
     *
     * @var array<string, string>
     */
    private const MOVEMENT_TO_TARGET = [
        'PRESS' => 'UPPER_PUSH',
        'FLY' => 'UPPER_PUSH',
        'PUSHUP' => 'UPPER_PUSH',
        'DIP' => 'UPPER_PUSH',
        'SHOULDER_RAISE' => 'UPPER_PUSH',
        'ROW' => 'UPPER_PULL',
        'PULL_VERTICAL' => 'UPPER_PULL',
        'PULLOVER_STRAIGHT_ARM' => 'UPPER_PULL',
        'FACE_PULL' => 'UPPER_PULL',
        'REAR_DELT_FLY' => 'UPPER_PULL',
        'SQUAT' => 'LOWER',
        'HINGE' => 'LOWER',
        'LUNGE_SPLIT_SQUAT' => 'LOWER',
        'LEG_PRESS' => 'LOWER',
        'KNEE_EXTENSION' => 'LOWER',
        'KNEE_FLEXION' => 'LOWER',
        'HIP_THRUST_BRIDGE' => 'LOWER',
        'HIP_ABDUCTION' => 'LOWER',
        'CALF_RAISE' => 'LOWER',
        'BACK_EXTENSION' => 'LOWER',
        'ELBOW_FLEXION' => 'ARMS',
        'ELBOW_EXTENSION' => 'ARMS',
        'CARRY' => 'ARMS',
        'TRUNK_FLEXION' => 'CORE',
        'ROTATION' => 'CORE',
        'ANTI_ROTATION' => 'CORE',
    ];

    /**
     * Category slug to equipment type mapping.
     *
     * @var array<string, string>
     */
    private const CATEGORY_TO_EQUIPMENT = [
        'barbell' => 'BARBELL',
        'dumbbell' => 'DUMBBELL',
        'kettlebell' => 'KETTLEBELL',
        'cable' => 'CABLE',
        'machine-cable' => 'MACHINE',
        'machine-plate-loaded' => 'MACHINE',
        'bodyweight' => 'BODYWEIGHT',
        'bands' => 'BAND',
        'trx' => 'TRX',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->loadLookupTables();

        $processed = 0;
        $skipped = 0;

        Exercise::query()
            ->with(['category', 'primaryMuscleGroups'])
            ->chunkById(100, function ($exercises) use (&$processed, &$skipped) {
                foreach ($exercises as $exercise) {
                    $classification = $this->classifyExercise($exercise);

                    if ($classification['movement_pattern_id'] === null) {
                        $this->command->warn("Could not classify: {$exercise->name}");
                        $skipped++;

                        continue;
                    }

                    $exercise->update($classification);

                    // Sync training styles
                    $trainingStyleCodes = $this->inferTrainingStyles($exercise);
                    if (! empty($trainingStyleCodes)) {
                        $trainingStyleIds = [];
                        foreach ($trainingStyleCodes as $code) {
                            if (isset($this->trainingStyles[$code])) {
                                $trainingStyleIds[] = $this->trainingStyles[$code];
                            }
                        }
                        if (! empty($trainingStyleIds)) {
                            $exercise->trainingStyles()->sync($trainingStyleIds);
                        }
                    }

                    $processed++;
                }
            });

        $this->command->info("Exercise classification complete: {$processed} classified, {$skipped} skipped.");
    }

    /**
     * Infer training styles from exercise name and equipment type.
     *
     * @return array<string>
     */
    private function inferTrainingStyles(Exercise $exercise): array
    {
        $name = Str::lower($exercise->name);
        $equipmentCode = $exercise->equipmentType?->code ?? '';

        $styles = [];

        // Equipment-based classification
        if (in_array($equipmentCode, ['TRX', 'LANDMINE', 'MEDICINE_BALL', 'KETTLEBELL', 'BAND'])) {
            $styles[] = 'FUNCTIONAL';
        }

        // Name-based classification for Olympic lifts
        $olympicPatterns = ['clean', 'snatch', 'push press', 'high pull', 'clean and press'];
        foreach ($olympicPatterns as $pattern) {
            if (Str::contains($name, $pattern)) {
                $styles[] = 'OLYMPIC';
                break;
            }
        }

        // Name-based classification for functional movements
        $functionalPatterns = [
            'thruster',
            'ground to overhead',
            'renegade',
            'box jump',
            'jump squat',
            'squat jump',
            'weighted squat jump',
            'windmill',
            'sit through',
            't-plank',
            't plank',
            'side row',
            'overhead march',
            'march',
            'bridge pullover',
            'cossack',
            'lunge to march',
            'cross lunge',
        ];
        foreach ($functionalPatterns as $pattern) {
            if (Str::contains($name, $pattern)) {
                $styles[] = 'FUNCTIONAL';
                break;
            }
        }

        // Calisthenics exercises (bodyweight movements that are also bodybuilding)
        $calisthenicsPatterns = ['dips', 'pull-ups', 'pull ups', 'handstand', 'push-up', 'pushup'];
        foreach ($calisthenicsPatterns as $pattern) {
            if (Str::contains($name, $pattern) && $equipmentCode === 'BODYWEIGHT') {
                $styles[] = 'CALISTHENICS';
                break;
            }
        }

        // Default to BODYBUILDING if no other styles found
        if (empty($styles)) {
            $styles[] = 'BODYBUILDING';
        }

        return array_unique($styles);
    }

    /**
     * Load all lookup tables into memory for fast access.
     */
    private function loadLookupTables(): void
    {
        $this->movementPatterns = MovementPattern::pluck('id', 'code')->toArray();
        $this->targetRegions = TargetRegion::pluck('id', 'code')->toArray();
        $this->equipmentTypes = EquipmentType::pluck('id', 'code')->toArray();
        $this->angles = Angle::pluck('id', 'code')->toArray();
        $this->categories = Category::pluck('id', 'slug')->toArray();
        $this->trainingStyles = TrainingStyle::pluck('id', 'code')->toArray();
    }

    /**
     * Classify an exercise based on name, category, and primary muscle groups.
     *
     * @return array{category_id: ?int, movement_pattern_id: ?int, target_region_id: ?int, equipment_type_id: ?int, angle_id: ?int}
     */
    private function classifyExercise(Exercise $exercise): array
    {
        $name = $exercise->name;
        $categorySlug = $exercise->category?->slug;
        $primaryMuscles = $exercise->primaryMuscleGroups->pluck('name')->toArray();

        // Determine exercise type category (if not already set)
        $categoryId = $exercise->category_id;
        if (! $categoryId) {
            $inferredCategorySlug = $this->inferCategory($name, $primaryMuscles);
            $categoryId = $inferredCategorySlug ? ($this->categories[$inferredCategorySlug] ?? null) : null;
        }

        // Determine equipment type
        $equipmentCode = $this->inferEquipmentType($name, $categorySlug);
        $equipmentTypeId = $this->equipmentTypes[$equipmentCode] ?? $this->equipmentTypes['MACHINE'];

        // Determine movement pattern
        $movementCode = $this->inferMovementPattern($name, $primaryMuscles);
        $movementPatternId = $this->movementPatterns[$movementCode] ?? null;

        // Determine target region based on movement pattern
        $targetCode = self::MOVEMENT_TO_TARGET[$movementCode] ?? 'UPPER_PUSH';
        $targetRegionId = $this->targetRegions[$targetCode] ?? $this->targetRegions['UPPER_PUSH'];

        // Determine angle (can be null)
        $angleCode = $this->inferAngle($name, $movementCode);
        $angleId = $angleCode ? ($this->angles[$angleCode] ?? null) : null;

        return [
            'category_id' => $categoryId,
            'movement_pattern_id' => $movementPatternId,
            'target_region_id' => $targetRegionId,
            'equipment_type_id' => $equipmentTypeId,
            'angle_id' => $angleId,
        ];
    }

    /**
     * Well-known exercises that don't contain equipment in their name.
     * Checked via exact match (lowercased) before heuristic inference.
     *
     * @var array<string, string>
     */
    private const EXERCISE_EQUIPMENT_OVERRIDES = [
        // Barbell exercises
        'deadlift' => 'BARBELL',
        'romanian deadlift' => 'BARBELL',
        'sumo deadlift' => 'BARBELL',
        'trap bar deadlift' => 'BARBELL',
        'rack pull' => 'BARBELL',
        'bent-over row' => 'BARBELL',
        'pendlay row' => 'BARBELL',
        't-bar row' => 'BARBELL',
        'chest-supported t-bar row' => 'BARBELL',
        'close-grip bench press (chest focus)' => 'BARBELL',
        'close-grip bench press (triceps focus)' => 'BARBELL',
        'power clean' => 'BARBELL',
        'power snatch' => 'BARBELL',
        'push press' => 'BARBELL',
        'high pull' => 'BARBELL',
        'thruster' => 'BARBELL',
        'wrist curls' => 'BARBELL',
        'reverse wrist curls' => 'BARBELL',
        'plate front raises' => 'BARBELL',

        // Dumbbell exercises
        'arnold press' => 'DUMBBELL',
        'concentration curl' => 'DUMBBELL',
        'hammer curl' => 'DUMBBELL',
        'renegade row' => 'DUMBBELL',
        'farmer\'s walk' => 'DUMBBELL',

        // Cable exercises
        'face pulls' => 'CABLE',
        'rope triceps pushdown' => 'CABLE',
        'reverse-grip triceps pushdown' => 'CABLE',
        'single-arm triceps pushdown' => 'CABLE',

        // Bodyweight exercises
        'inverted row' => 'BODYWEIGHT',
        'nordic hamstring curl' => 'BODYWEIGHT',
        'box jump' => 'BODYWEIGHT',
        'hyperextensions' => 'BODYWEIGHT',
        '45-degree back extension' => 'BODYWEIGHT',
        'ballerina split stretch' => 'BODYWEIGHT',
    ];

    /**
     * Infer equipment type from exercise name and category slug.
     */
    private function inferEquipmentType(string $name, ?string $categorySlug): string
    {
        $nameLower = Str::lower($name);

        // 1. Exact name override for well-known exercises
        if (isset(self::EXERCISE_EQUIPMENT_OVERRIDES[$nameLower])) {
            return self::EXERCISE_EQUIPMENT_OVERRIDES[$nameLower];
        }

        // 2. Explicit equipment in name (most reliable)
        if (Str::contains($nameLower, 'smith')) {
            return 'SMITH';
        }

        if (Str::contains($nameLower, 'trx')) {
            return 'TRX';
        }

        if (Str::contains($nameLower, 'landmine')) {
            return 'LANDMINE';
        }

        if (Str::contains($nameLower, 'dumbbell')) {
            return 'DUMBBELL';
        }

        if (Str::contains($nameLower, ['barbell', 'ez-bar', 'ez bar', 'ez curl bar'])) {
            return 'BARBELL';
        }

        if (Str::contains($nameLower, 'kettlebell')) {
            return 'KETTLEBELL';
        }

        if (Str::contains($nameLower, 'cable')) {
            return 'CABLE';
        }

        if (Str::contains($nameLower, ['band', 'resistance band', 'resistance-band'])) {
            return 'BAND';
        }

        if (Str::contains($nameLower, ['medicine ball', 'med ball', 'medicine-ball'])) {
            return 'MEDICINE_BALL';
        }

        // 3. "Machine" explicitly in name → MACHINE
        if (Str::contains($nameLower, 'machine')) {
            return 'MACHINE';
        }

        // 4. Context-based inference for exercises without equipment keywords

        // Machine indicators: plate-loaded, pulldown, leg curl/extension, pec deck, hack squat
        if (Str::contains($nameLower, [
            'plate-loaded', 'plate loaded',
            'pulldown', 'pull-down', 'pull down',
            'leg extension', 'leg curl', 'lying leg', 'seated leg', 'standing leg curl',
            'pec deck', 'hip abduction',
            'hack squat', 'pendulum squat',
            'leg press',
        ])) {
            return 'MACHINE';
        }

        // Cable indicators: pushdown, face pull, crossover
        if (Str::contains($nameLower, [
            'pushdown', 'push-down', 'push down',
            'face pull', 'crossover', 'cross-over',
        ])) {
            return 'CABLE';
        }

        // Barbell indicators: bench press, deadlift, clean, snatch, press (Olympic context)
        if (Str::contains($nameLower, [
            'bench press', 'deadlift', 'clean and', 'power clean', 'power snatch',
            'snatch grip', 'rack pull', 'pendlay',
        ])) {
            return 'BARBELL';
        }

        // Bodyweight indicators
        if (Str::contains($nameLower, [
            'push-up', 'pushup', 'push up',
            'pull-up', 'pullup', 'pull up',
            'chin-up', 'chinup', 'chin up',
            'plank', 'burpee', 'mountain climber', 'jumping jack',
            'sit-up', 'situp',
            'handstand', 'pike', 'wall sit',
            'sit through', 'sit-through',
            'snap', 'windmill',
            'bear crawl', 'crab walk', 'duck walk',
            'hollow body', 'superman', 'dead bug',
            'bird dog', 'scorpion',
            'v-up', 'v sit', 'v-sit',
            'flutter kick', 'scissor kick',
            'dragon flag', 'human flag',
            'muscle-up', 'muscle up',
            'box jump', 'broad jump', 'depth jump',
            'inverted row', 'nordic',
            'hyperextension', 'back extension',
        ]) && ! Str::contains($nameLower, ['dumbbell', 'barbell', 'kettlebell', 'cable', 'band', 'machine', 'smith', 'trx', 'plate-loaded'])) {
            return 'BODYWEIGHT';
        }

        // Bodyweight: exercises with these patterns that have no equipment qualifier
        if (Str::contains($nameLower, [
            'crunch', 'leg raise', 'russian twist',
            'hip thrust', 'glute bridge',
            'single leg', 'single-leg',
            'calf raise', 'standing calf',
            'dip', 'lunge', 'squat',
            'hip circle', 'hip mobility',
        ]) && ! Str::contains($nameLower, ['dumbbell', 'barbell', 'kettlebell', 'cable', 'band', 'machine', 'smith', 'trx', 'plate-loaded', 'hack', 'pendulum', 'leg press'])) {
            return 'BODYWEIGHT';
        }

        // 5. Use category slug mapping (for specific equipment categories)
        if ($categorySlug && isset(self::CATEGORY_TO_EQUIPMENT[$categorySlug])) {
            return self::CATEGORY_TO_EQUIPMENT[$categorySlug];
        }

        // 6. Power/Olympic lifting without specific equipment → typically barbell
        if ($categorySlug === 'power-olympic-lifting') {
            return 'BARBELL';
        }

        // 7. Fallback to MACHINE only for exercises that genuinely seem like machines
        return 'MACHINE';
    }

    /**
     * Infer movement pattern from exercise name.
     *
     * @param  array<string>  $primaryMuscles
     */
    private function inferMovementPattern(string $name, array $primaryMuscles): string
    {
        $nameLower = Str::lower($name);

        // Press patterns (chest press, shoulder press, overhead press, bench press, push press, landmine press)
        if (Str::contains($nameLower, ['bench press', 'chest press', 'shoulder press', 'overhead press', 'military press', 'push press', 'landmine press'])) {
            return 'PRESS';
        }

        // Fly patterns
        if (Str::contains($nameLower, ['fly', 'pec deck', 'cable crossover'])) {
            return 'FLY';
        }

        // Push-up patterns
        if (Str::contains($nameLower, ['push-up', 'pushup', 'push up'])) {
            return 'PUSHUP';
        }

        // Dip patterns
        if (Str::contains($nameLower, ['dip'])) {
            return 'DIP';
        }

        // Row patterns
        if (Str::contains($nameLower, ['row']) && ! Str::contains($nameLower, ['narrow'])) {
            return 'ROW';
        }

        // Vertical pull patterns (pull-up, chin-up, pulldown, lat pulldown)
        if (Str::contains($nameLower, ['pull-up', 'pullup', 'chin-up', 'chinup', 'chin up', 'pulldown', 'pull-down', 'pull down'])) {
            return 'PULL_VERTICAL';
        }

        // Straight-arm pullover patterns
        if (Str::contains($nameLower, ['straight-arm', 'straight arm', 'pullover'])) {
            return 'PULLOVER_STRAIGHT_ARM';
        }

        // Face pull patterns
        if (Str::contains($nameLower, ['face pull'])) {
            return 'FACE_PULL';
        }

        // Rear delt fly patterns
        if (Str::contains($nameLower, ['reverse fly', 'rear delt fly', 'rear delt raise'])) {
            return 'REAR_DELT_FLY';
        }

        // Shoulder raise patterns (lateral and front raises); rear-delt raises matched above
        if (Str::contains($nameLower, ['lateral raise', 'front raise', 'side raise'])) {
            return 'SHOULDER_RAISE';
        }

        // Squat patterns
        if (Str::contains($nameLower, ['squat']) && ! Str::contains($nameLower, ['split squat'])) {
            return 'SQUAT';
        }

        // Hip hinge patterns (deadlift, rdl, rack pull)
        if (Str::contains($nameLower, ['deadlift', 'romanian', 'rdl', 'rack pull', 'good morning'])) {
            return 'HINGE';
        }

        // Lunge / Split squat patterns
        if (Str::contains($nameLower, ['lunge', 'split squat', 'bulgarian', 'step-up', 'step up'])) {
            return 'LUNGE_SPLIT_SQUAT';
        }

        // Leg press patterns
        if (Str::contains($nameLower, ['leg press'])) {
            return 'LEG_PRESS';
        }

        // Knee extension patterns
        if (Str::contains($nameLower, ['leg extension'])) {
            return 'KNEE_EXTENSION';
        }

        // Knee flexion patterns (leg curl, nordic)
        if (Str::contains($nameLower, ['leg curl', 'hamstring curl', 'nordic'])) {
            return 'KNEE_FLEXION';
        }

        // Hip thrust / bridge patterns
        if (Str::contains($nameLower, ['hip thrust', 'glute bridge', 'bridge'])) {
            return 'HIP_THRUST_BRIDGE';
        }

        // Hip abduction patterns
        if (Str::contains($nameLower, ['hip abduction', 'abductor'])) {
            return 'HIP_ABDUCTION';
        }

        // Calf raise patterns
        if (Str::contains($nameLower, ['calf raise', 'calf press'])) {
            return 'CALF_RAISE';
        }

        // Back extension patterns
        if (Str::contains($nameLower, ['back extension', 'hyperextension', 'hyper extension'])) {
            return 'BACK_EXTENSION';
        }

        // Elbow flexion patterns (biceps curls)
        if (Str::contains($nameLower, ['curl']) && ! Str::contains($nameLower, ['leg curl', 'hamstring curl'])) {
            return 'ELBOW_FLEXION';
        }

        // Elbow extension patterns (triceps)
        if (Str::contains($nameLower, ['pushdown', 'push down', 'skull crusher', 'triceps extension', 'tricep extension', 'kickback', 'overhead extension'])) {
            return 'ELBOW_EXTENSION';
        }

        // Carry patterns
        if (Str::contains($nameLower, ["farmer's walk", 'farmers walk', 'farmer walk', 'carry', 'suitcase'])) {
            return 'CARRY';
        }

        // Trunk flexion patterns (crunches, sit-ups)
        if (Str::contains($nameLower, ['crunch', 'sit-up', 'situp', 'leg raise', 'hanging knee'])) {
            return 'TRUNK_FLEXION';
        }

        // Rotation patterns
        if (Str::contains($nameLower, ['woodchopper', 'wood chop', 'russian twist', 'rotation', 'twist'])) {
            return 'ROTATION';
        }

        // Anti-rotation patterns
        if (Str::contains($nameLower, ['pallof', 'anti-rotation', 'anti rotation'])) {
            return 'ANTI_ROTATION';
        }

        // Fallback based on primary muscle groups
        return $this->inferFromMuscleGroups($primaryMuscles);
    }

    /**
     * Fallback movement pattern inference based on primary muscle groups.
     *
     * @param  array<string>  $primaryMuscles
     */
    private function inferFromMuscleGroups(array $primaryMuscles): string
    {
        $musclesLower = array_map(fn ($m) => Str::lower($m), $primaryMuscles);

        // Check for chest/shoulders/triceps -> PRESS
        if (array_intersect($musclesLower, ['chest', 'front delts', 'triceps'])) {
            return 'PRESS';
        }

        // Check for lats/upper back -> ROW
        if (array_intersect($musclesLower, ['lats', 'upper back'])) {
            return 'ROW';
        }

        // Check for quads/glutes -> SQUAT
        if (array_intersect($musclesLower, ['quadriceps', 'glutes'])) {
            return 'SQUAT';
        }

        // Check for biceps -> ELBOW_FLEXION
        if (in_array('biceps', $musclesLower, true)) {
            return 'ELBOW_FLEXION';
        }

        // Check for abs -> TRUNK_FLEXION
        if (in_array('abs', $musclesLower, true)) {
            return 'TRUNK_FLEXION';
        }

        // Check for hamstrings -> HINGE
        if (in_array('hamstrings', $musclesLower, true)) {
            return 'HINGE';
        }

        // Check for calves -> CALF_RAISE
        if (in_array('calves', $musclesLower, true)) {
            return 'CALF_RAISE';
        }

        // Check for rear delts -> REAR_DELT_FLY
        if (in_array('rear delts', $musclesLower, true)) {
            return 'REAR_DELT_FLY';
        }

        // Default fallback
        return 'PRESS';
    }

    /**
     * Movement pattern to default angle mapping.
     *
     * @var array<string, string>
     */
    private const MOVEMENT_TO_DEFAULT_ANGLE = [
        'PRESS' => 'FLAT',
        'ROW' => 'HORIZONTAL',
        'FLY' => 'FLAT',
        'PULL_VERTICAL' => 'VERTICAL',
        'DIP' => 'VERTICAL',
        'PUSHUP' => 'HORIZONTAL',
    ];

    /**
     * Infer angle from exercise name.
     */
    private function inferAngle(string $name, string $movementCode): ?string
    {
        $nameLower = Str::lower($name);

        // Explicit angle keywords (highest priority)
        if (Str::contains($nameLower, 'incline')) {
            return 'INCLINE';
        }

        if (Str::contains($nameLower, 'decline')) {
            return 'DECLINE';
        }

        if (Str::contains($nameLower, ['low-to-high', 'low to high', 'landmine'])) {
            return 'LOW_TO_HIGH';
        }

        if (Str::contains($nameLower, ['high-to-low', 'high to low'])) {
            return 'HIGH_TO_LOW';
        }

        // Vertical angle keywords (overhead, standing press variations, front raise, etc.)
        if (Str::contains($nameLower, [
            'overhead', 'push press', 'standing press', 'arnold press', 'z press',
            'front raise', 'military press', 'strict press', 'press behind neck',
            'upright row', 'pull-up', 'chin-up', 'lat pulldown', 'cable pulldown',
        ])) {
            return 'VERTICAL';
        }

        // Shoulder press variations (without incline qualifier) are vertical
        if (Str::contains($nameLower, 'shoulder press') && ! Str::contains($nameLower, 'incline')) {
            return 'VERTICAL';
        }

        // Position-based angle inference for PRESS movements
        if ($movementCode === 'PRESS') {
            // Standing/seated variations suggest vertical
            if (Str::contains($nameLower, ['standing', 'seated press', 'seated shoulder'])) {
                return 'VERTICAL';
            }

            // Half-kneeling, kneeling variations often suggest vertical or low-to-high
            if (Str::contains($nameLower, ['half-kneeling', 'half kneeling', 'kneeling press'])) {
                return 'LOW_TO_HIGH';
            }

            // Bench press (no incline/decline qualifier) is flat
            if (Str::contains($nameLower, 'bench press') && ! Str::contains($nameLower, ['incline', 'decline'])) {
                return 'FLAT';
            }

            // Floor press is flat
            if (Str::contains($nameLower, 'floor press')) {
                return 'FLAT';
            }

            // Close-grip, wide-grip variations of bench press are flat
            if (Str::contains($nameLower, ['close-grip', 'close grip', 'wide-grip', 'wide grip']) && Str::contains($nameLower, 'press')) {
                return 'FLAT';
            }
        }

        // Position-based angle inference for ROW movements
        if ($movementCode === 'ROW') {
            // Bent-over, pendlay, t-bar rows are horizontal
            if (Str::contains($nameLower, ['bent-over', 'bent over', 'pendlay', 't-bar', 't bar', 'barbell row'])) {
                return 'HORIZONTAL';
            }

            // Seated rows are horizontal
            if (Str::contains($nameLower, ['seated row', 'seated cable row', 'machine row'])) {
                return 'HORIZONTAL';
            }

            // Single-arm, one-arm variations often suggest low-to-high
            if (Str::contains($nameLower, ['single-arm', 'single arm', 'one-arm', 'one arm'])) {
                return 'LOW_TO_HIGH';
            }

            // Chest-supported rows are horizontal
            if (Str::contains($nameLower, ['chest-supported', 'chest supported', 'chest support'])) {
                return 'HORIZONTAL';
            }

            // Inverted rows are horizontal
            if (Str::contains($nameLower, 'inverted row')) {
                return 'HORIZONTAL';
            }
        }

        // Lateral raise variations are horizontal
        if (Str::contains($nameLower, ['lateral raise', 'side raise', 'side delt'])) {
            return 'HORIZONTAL';
        }

        // Rear delt fly is horizontal
        if (Str::contains($nameLower, ['rear delt', 'rear deltoid', 'rear fly'])) {
            return 'HORIZONTAL';
        }

        // Face pull is horizontal
        if (Str::contains($nameLower, 'face pull')) {
            return 'HORIZONTAL';
        }

        // Vertical pulls are inherently vertical
        if ($movementCode === 'PULL_VERTICAL') {
            return 'VERTICAL';
        }

        // Dips are vertical
        if ($movementCode === 'DIP') {
            return 'VERTICAL';
        }

        // Pushups are horizontal
        if ($movementCode === 'PUSHUP') {
            return 'HORIZONTAL';
        }

        // For exercises without explicit keywords, use pattern-based defaults
        // but add some variation based on exercise name patterns
        if (isset(self::MOVEMENT_TO_DEFAULT_ANGLE[$movementCode])) {
            $defaultAngle = self::MOVEMENT_TO_DEFAULT_ANGLE[$movementCode];

            // Add variation for PRESS: if it's not a bench press, consider vertical
            if ($movementCode === 'PRESS' && $defaultAngle === 'FLAT') {
                // If it contains "press" but not "bench", it might be vertical
                if (Str::contains($nameLower, 'press') && ! Str::contains($nameLower, 'bench')) {
                    // Check if it's likely vertical based on other keywords
                    if (Str::contains($nameLower, ['standing', 'seated', 'military', 'strict', 'overhead'])) {
                        return 'VERTICAL';
                    }
                }
            }

            // Add variation for ROW: single-arm variations suggest low-to-high
            if ($movementCode === 'ROW' && $defaultAngle === 'HORIZONTAL') {
                if (Str::contains($nameLower, ['single-arm', 'single arm', 'one-arm', 'one arm', 'alternating'])) {
                    return 'LOW_TO_HIGH';
                }
            }

            return $defaultAngle;
        }

        // Don't force any angle - return null
        return null;
    }

    /**
     * Infer exercise type category from exercise name and characteristics.
     *
     * @param  array<string>  $primaryMuscles
     */
    private function inferCategory(string $name, array $primaryMuscles): ?string
    {
        $nameLower = Str::lower($name);

        // Power/Olympic Lifting - explosive movements
        if (Str::contains($nameLower, ['clean', 'snatch', 'jerk', 'high pull'])) {
            return 'power-olympic-lifting';
        }

        // Cardio - running, rowing, cycling, HIIT
        if (Str::contains($nameLower, ['run', 'row', 'cycle', 'bike', 'sprint', 'jog', 'treadmill', 'elliptical', 'hiit'])) {
            return 'cardio';
        }

        // Mobility & Flexibility - stretching, yoga
        if (Str::contains($nameLower, ['stretch', 'yoga', 'mobility', 'flexibility', 'foam roll'])) {
            return 'mobility-flexibility';
        }

        // Bodyweight - calisthenics, gymnastics movements
        $bodyweightPatterns = [
            'push-up', 'pushup', 'push up',
            'pull-up', 'pullup', 'pull up',
            'chin-up', 'chinup', 'chin up',
            'dip',
            'bodyweight squat',
            'jump squat',
            'wall sit',
            'handstand',
            'pike push-up',
            'inverted row',
            'plank',
            'burpee',
        ];

        $isBodyweight = false;
        foreach ($bodyweightPatterns as $pattern) {
            if (Str::contains($nameLower, $pattern)) {
                $isBodyweight = true;
                break;
            }
        }

        // Check if it's bodyweight by absence of equipment keywords
        if (! $isBodyweight) {
            $equipmentKeywords = ['barbell', 'dumbbell', 'machine', 'cable', 'smith', 'plate', 'band', 'kettlebell', 'trx'];
            $hasEquipment = false;
            foreach ($equipmentKeywords as $keyword) {
                if (Str::contains($nameLower, $keyword)) {
                    $hasEquipment = true;
                    break;
                }
            }

            if (! $hasEquipment && (
                Str::contains($nameLower, ['squat', 'lunge', 'step-up', 'step up', 'bridge', 'raise', 'crunch', 'sit-up', 'situp'])
            )) {
                $isBodyweight = true;
            }
        }

        if ($isBodyweight) {
            return 'bodyweight';
        }

        // Functional Training - movement-based, real-world patterns
        $functionalPatterns = [
            'lunge', 'walking', 'step-up', 'step up', 'carry', "farmer's walk", 'farmers walk',
            'bulgarian', 'split squat', 'goblet', 'trap bar', 'kettlebell swing',
        ];

        foreach ($functionalPatterns as $pattern) {
            if (Str::contains($nameLower, $pattern)) {
                return 'functional-training';
            }
        }

        // Hybrid/CrossFit - complex movements, combinations
        if (Str::contains($nameLower, ['thruster', 'burpee', 'man maker', 'devil press', 'complex'])) {
            return 'hybrid-crossfit';
        }

        // Strength Training - default for most weighted exercises
        // This includes: barbell, dumbbell, machine, cable exercises
        return 'strength-training';
    }
}
