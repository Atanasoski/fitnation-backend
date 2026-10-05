<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\MuscleGroup;
use App\Models\Role;
use App\Models\TargetRegion;
use App\Models\TrainingStyle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What creating and updating an exercise through the admin pages does, locked
 * before the exercise gallery (spec 023, ticket 03) changes it.
 *
 * Changed deliberately by the gallery: update used to land on the old
 * exercise page (exercises.show); it now returns to the gallery.
 */
class ExerciseStoreUpdateCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_creates_an_exercise_with_its_classification(): void
    {
        $chest = MuscleGroup::factory()->create();
        $triceps = MuscleGroup::factory()->create();
        $style = TrainingStyle::create(['code' => 'STRENGTH', 'name' => 'Strength', 'display_order' => 1]);

        $this->actingAs($this->admin())
            ->post(route('exercises.store'), $this->fields([
                'name' => 'Close-grip bench',
                'description' => 'Elbows in.',
                'default_rest_sec' => 120,
                'primary_muscle_group_ids' => [$chest->id],
                'secondary_muscle_group_ids' => [$triceps->id, $chest->id],
                'training_style_ids' => [$style->id],
            ]))
            ->assertRedirect(route('exercises.index'))
            ->assertSessionHas('success', 'Exercise created successfully!');

        $exercise = Exercise::where('name', 'Close-grip bench')->sole();
        $this->assertSame('Elbows in.', $exercise->description);
        $this->assertSame(120, $exercise->default_rest_sec);
        $this->assertEquals([$chest->id], $exercise->primaryMuscleGroups()->pluck('muscle_groups.id')->all());
        $this->assertEquals([$triceps->id], $exercise->secondaryMuscleGroups()->pluck('muscle_groups.id')->all());
        $this->assertEquals([$style->id], $exercise->trainingStyles()->pluck('training_styles.id')->all());
    }

    public function test_create_defaults_the_rest_to_ninety_seconds(): void
    {
        $this->actingAs($this->admin())
            ->post(route('exercises.store'), $this->fields(['name' => 'Dip']))
            ->assertRedirect(route('exercises.index'));

        $this->assertSame(90, Exercise::where('name', 'Dip')->sole()->default_rest_sec);
    }

    public function test_create_validates_the_required_classification(): void
    {
        $this->actingAs($this->admin())
            ->post(route('exercises.store'), ['name' => 'Nothing else'])
            ->assertSessionHasErrors(['category_id', 'movement_pattern_id', 'target_region_id', 'equipment_type_id']);

        $this->assertDatabaseMissing('workout_exercises', ['name' => 'Nothing else']);
    }

    public function test_an_admin_updates_an_exercise_and_replaces_its_media(): void
    {
        Storage::fake();
        $exercise = Exercise::factory()->create(['image' => 'exercises/images/old.jpg']);
        Storage::put('exercises/images/old.jpg', 'old');
        $back = MuscleGroup::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('exercises.update', $exercise), $this->fields([
                'name' => 'Renamed',
                'default_rest_sec' => 60,
                'image' => UploadedFile::fake()->image('new.jpg'),
                'video' => UploadedFile::fake()->create('demo.mp4', 100, 'video/mp4'),
                'primary_muscle_group_ids' => [$back->id],
            ]))
            ->assertRedirect(route('exercises.index'))
            ->assertSessionHas('success', 'Exercise updated successfully!');

        $exercise->refresh();
        $this->assertSame('Renamed', $exercise->name);
        $this->assertSame(60, $exercise->default_rest_sec);
        $this->assertStringStartsWith('exercises/images/', $exercise->image);
        $this->assertStringStartsWith('exercises/videos/', $exercise->video);
        Storage::assertExists([$exercise->image, $exercise->video]);
        Storage::assertMissing('exercises/images/old.jpg');
        $this->assertEquals([$back->id], $exercise->primaryMuscleGroups()->pluck('muscle_groups.id')->all());
    }

    public function test_a_non_admin_can_neither_create_nor_update(): void
    {
        $exercise = Exercise::factory()->create(['name' => 'Untouched']);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('exercises.store'), $this->fields(['name' => 'Sneaky']))->assertForbidden();
        $this->actingAs($user)->put(route('exercises.update', $exercise), $this->fields(['name' => 'Sneaky']))->assertForbidden();

        $this->assertDatabaseMissing('workout_exercises', ['name' => 'Sneaky']);
        $this->assertSame('Untouched', $exercise->fresh()->name);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fields(array $overrides = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->workout()->create()->id,
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'PRESS'], ['name' => 'Press', 'display_order' => 1])->id,
            'target_region_id' => TargetRegion::firstOrCreate(['code' => 'UPPER_PUSH'], ['name' => 'Upper Push', 'display_order' => 1])->id,
            'equipment_type_id' => EquipmentType::firstOrCreate(['code' => 'BARBELL'], ['name' => 'Barbell', 'display_order' => 1])->id,
        ], $overrides);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);

        return $admin;
    }
}
