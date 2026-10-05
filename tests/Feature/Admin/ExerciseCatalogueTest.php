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
