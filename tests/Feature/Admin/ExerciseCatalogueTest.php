<?php

namespace Tests\Feature\Admin;

use App\Enums\ExerciseDifficulty;
use App\Models\Category;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\Role;
use App\Models\TargetRegion;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The admin exercise catalogue over HTTP (spec 023, ticket 03, seam 3):
 * create and update with the new fields, saves returning to the gallery.
 */
class ExerciseCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_takes_an_image_a_video_a_difficulty_and_a_selection_priority(): void
    {
        Storage::fake();

        $this->actingAs($this->admin())
            ->post(route('exercises.store'), $this->fields([
                'name' => 'Front squat',
                'difficulty' => 'advanced',
                'selection_priority' => 750,
                'image' => UploadedFile::fake()->image('squat.jpg'),
                'video' => UploadedFile::fake()->create('squat.mp4', 100, 'video/mp4'),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('exercises.index'));

        $exercise = Exercise::where('name', 'Front squat')->sole();
        $this->assertSame(ExerciseDifficulty::Advanced, $exercise->difficulty);
        $this->assertSame(750, $exercise->selection_priority);
        $this->assertStringStartsWith('exercises/images/', $exercise->image);
        $this->assertStringStartsWith('exercises/videos/', $exercise->video);
        Storage::assertExists([$exercise->image, $exercise->video]);
    }

    public function test_update_sets_difficulty_and_selection_priority(): void
    {
        $exercise = Exercise::factory()->withPriority(100)->create();

        $this->actingAs($this->admin())
            ->put(route('exercises.update', $exercise), $this->fields([
                'name' => $exercise->name,
                'difficulty' => 'beginner',
                'selection_priority' => 0,
            ]))
            ->assertSessionHasNoErrors();

        $exercise->refresh();
        $this->assertSame(ExerciseDifficulty::Beginner, $exercise->difficulty);
        $this->assertSame(0, $exercise->selection_priority);
    }

    public function test_update_can_clear_the_difficulty(): void
    {
        $exercise = Exercise::factory()->create(['difficulty' => 'advanced']);

        $this->actingAs($this->admin())
            ->put(route('exercises.update', $exercise), $this->fields(['name' => $exercise->name, 'difficulty' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($exercise->fresh()->difficulty);
    }

    public function test_priority_must_be_within_0_and_1000_and_difficulty_a_known_level(): void
    {
        $exercise = Exercise::factory()->withPriority(100)->create();
        $admin = $this->admin();

        foreach ([['selection_priority' => 1001], ['selection_priority' => -1], ['difficulty' => 'elite']] as $bad) {
            $this->actingAs($admin)
                ->put(route('exercises.update', $exercise), $this->fields(['name' => $exercise->name] + $bad))
                ->assertSessionHasErrors(array_keys($bad));

            $this->actingAs($admin)
                ->post(route('exercises.store'), $this->fields(['name' => 'Rejected'] + $bad))
                ->assertSessionHasErrors(array_keys($bad));
        }

        $this->assertSame(100, $exercise->fresh()->selection_priority);
        $this->assertDatabaseMissing('workout_exercises', ['name' => 'Rejected']);
    }

    public function test_the_gallery_shows_the_filtered_slice(): void
    {
        $upper = TargetRegion::create(['code' => 'UPPER', 'name' => 'Upper', 'display_order' => 1]);
        $lower = TargetRegion::create(['code' => 'LOWER', 'name' => 'Lower', 'display_order' => 2]);
        Exercise::factory()->create(['name' => 'Bench press', 'target_region_id' => $upper->id]);
        Exercise::factory()->create(['name' => 'Back squat', 'target_region_id' => $lower->id]);
        Exercise::factory()->create(['name' => 'Retired row', 'target_region_id' => $upper->id, 'archived_at' => now()]);

        $this->actingAs($this->admin())
            ->get(route('exercises.index', ['region' => [$upper->id]]))
            ->assertOk()
            ->assertSee('Bench press')
            ->assertDontSee('Back squat')
            ->assertDontSee('Retired row');
    }

    public function test_opening_an_exercise_keeps_the_filters_in_its_link(): void
    {
        $region = TargetRegion::create(['code' => 'UPPER', 'name' => 'Upper', 'display_order' => 1]);
        $exercise = Exercise::factory()->create(['target_region_id' => $region->id]);

        $this->actingAs($this->admin())
            ->get(route('exercises.index', ['q' => $exercise->name, 'region' => [$region->id]]))
            ->assertOk()
            ->assertSee(route('exercises.index', ['q' => $exercise->name, 'region' => [(string) $region->id], 'edit' => $exercise->id]));
    }

    public function test_edit_opens_the_slide_over_on_the_exercise(): void
    {
        $exercise = Exercise::factory()->create(['name' => 'Cable crossover']);

        $this->actingAs($this->admin())
            ->get(route('exercises.index', ['edit' => $exercise->id]))
            ->assertOk()
            ->assertSee('action="'.route('exercises.update', $exercise).'"', false)
            ->assertSee('value="Cable crossover"', false)
            ->assertSee(route('exercises.destroy', $exercise), false)
            ->assertSee(route('exercises.updateMuscleGroupImage', $exercise), false);
    }

    public function test_create_opens_an_empty_slide_over(): void
    {
        $this->actingAs($this->admin())
            ->get(route('exercises.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('action="'.route('exercises.store').'"', false);
    }

    public function test_editing_an_exercise_that_does_not_exist_is_not_found(): void
    {
        $this->actingAs($this->admin())
            ->get(route('exercises.index', ['edit' => 999999]))
            ->assertNotFound();
    }

    public function test_the_old_pages_open_the_gallery_with_the_slide_over(): void
    {
        $exercise = Exercise::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('exercises.show', $exercise))
            ->assertRedirect(route('exercises.index', ['edit' => $exercise->id]));
        $this->actingAs($admin)->get(route('exercises.edit', $exercise))
            ->assertRedirect(route('exercises.index', ['edit' => $exercise->id]));
        $this->actingAs($admin)->get(route('exercises.create'))
            ->assertRedirect(route('exercises.index', ['create' => 1]));
    }

    public function test_saves_return_to_the_gallery_slice_they_came_from(): void
    {
        $exercise = Exercise::factory()->create();
        $back = 'q=press&region%5B0%5D=3&difficulty%5B0%5D=beginner&edit=9&evil=https%3A%2F%2Fexample.com';
        $gallery = route('exercises.index', ['q' => 'press', 'region' => ['3'], 'difficulty' => ['beginner']]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('exercises.update', $exercise), $this->fields(['name' => 'Kept', 'back' => $back]))
            ->assertRedirect($gallery)
            ->assertSessionHas('success', 'Exercise updated successfully!');

        $this->actingAs($admin)
            ->post(route('exercises.store'), $this->fields(['name' => 'New', 'back' => $back]))
            ->assertRedirect($gallery);

        $this->actingAs($admin)
            ->delete(route('exercises.destroy', $exercise), ['back' => $back])
            ->assertRedirect($gallery)
            ->assertSessionHas('success', 'Exercise deleted successfully!');
    }

    public function test_bulk_archives_the_used_and_deletes_the_rest(): void
    {
        $used = Exercise::factory()->create();
        WorkoutTemplateExercise::create([
            'workout_template_id' => WorkoutTemplate::factory()->create()->id,
            'exercise_id' => $used->id,
            'order' => 1,
        ]);
        $unused = Exercise::factory()->create();
        $alsoUnused = Exercise::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('exercises.bulkDestroy'), [
                'exercise_ids' => [$used->id, $unused->id, $alsoUnused->id],
                'back' => 'archived=0&missing=media',
            ])
            ->assertRedirect(route('exercises.index', ['missing' => 'media']))
            ->assertSessionHas('success', '1 archived (used in plans or logged sessions), 2 deleted.');

        $this->assertNotNull($used->fresh()->archived_at);
        $this->assertDatabaseMissing('workout_exercises', ['id' => $unused->id]);
        $this->assertDatabaseMissing('workout_exercises', ['id' => $alsoUnused->id]);
    }

    public function test_bulk_needs_at_least_one_exercise(): void
    {
        $this->actingAs($this->admin())
            ->post(route('exercises.bulkDestroy'), ['exercise_ids' => []])
            ->assertSessionHasErrors('exercise_ids');
    }

    public function test_an_archived_exercise_shows_restore_and_restoring_brings_it_back(): void
    {
        $exercise = Exercise::factory()->create(['name' => 'Retired row', 'archived_at' => now()]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('exercises.index', ['archived' => 1]))
            ->assertOk()
            ->assertSee('Retired row')
            ->assertSee(route('exercises.restore', $exercise), false);

        $this->actingAs($admin)
            ->post(route('exercises.restore', $exercise), ['back' => 'archived=1'])
            ->assertRedirect(route('exercises.index', ['archived' => '1']))
            ->assertSessionHas('success', 'Exercise restored to the catalogue.');

        $this->assertNull($exercise->fresh()->archived_at);
    }

    public function test_non_admins_are_refused_on_every_route(): void
    {
        $exercise = Exercise::factory()->create();
        $partnerAdmin = User::factory()->create();
        $partnerAdmin->roles()->attach(Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id);

        foreach ([User::factory()->create(), $partnerAdmin] as $user) {
            $this->actingAs($user);
            $this->get(route('exercises.index'))->assertForbidden();
            $this->get(route('exercises.index', ['edit' => $exercise->id]))->assertForbidden();
            $this->get(route('exercises.create'))->assertForbidden();
            $this->get(route('exercises.show', $exercise))->assertForbidden();
            $this->get(route('exercises.edit', $exercise))->assertForbidden();
            $this->post(route('exercises.store'), $this->fields(['name' => 'Sneaky']))->assertForbidden();
            $this->put(route('exercises.update', $exercise), $this->fields(['name' => 'Sneaky']))->assertForbidden();
            $this->delete(route('exercises.destroy', $exercise))->assertForbidden();
            $this->post(route('exercises.bulkDestroy'), ['exercise_ids' => [$exercise->id]])->assertForbidden();
            $this->post(route('exercises.restore', $exercise))->assertForbidden();
            $this->post(route('exercises.updateMuscleGroupImage', $exercise))->assertForbidden();
        }

        $this->assertDatabaseHas('workout_exercises', ['id' => $exercise->id, 'archived_at' => null]);
        $this->assertDatabaseMissing('workout_exercises', ['name' => 'Sneaky']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fields(array $overrides = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->workout()->create()->id,
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'SQUAT'], ['name' => 'Squat', 'display_order' => 1])->id,
            'target_region_id' => TargetRegion::firstOrCreate(['code' => 'LOWER'], ['name' => 'Lower', 'display_order' => 1])->id,
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
