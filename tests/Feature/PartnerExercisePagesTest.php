<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The two partner-facing exercise pages read their branding from the
 * PartnerExerciseView the controller hands them. These render both so a change
 * to that shape cannot pass the unit tests and still break a view.
 */
class PartnerExercisePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_library_lists_the_partners_own_image(): void
    {
        [$admin, $exercise] = $this->partnerAdminWithOverriddenExercise();

        $this->actingAs($admin)
            ->get(route('partner.exercises.index'))
            ->assertOk()
            ->assertSee(Storage::url('partner/image.jpg'), false);
    }

    public function test_the_detail_page_shows_the_partners_own_presentation(): void
    {
        [$admin, $exercise] = $this->partnerAdminWithOverriddenExercise();

        $this->actingAs($admin)
            ->get(route('partner.exercises.show', $exercise))
            ->assertOk()
            ->assertSee('Partner description')
            ->assertSee(Storage::url('partner/image.jpg'), false)
            ->assertSee(Storage::url('partner/video.mp4'), false)
            ->assertDontSee('Default description');
    }

    /**
     * @return array{0: User, 1: Exercise}
     */
    private function partnerAdminWithOverriddenExercise(): array
    {
        $category = Category::factory()->create(['type' => 'workout']);

        $exercise = Exercise::factory()->create([
            'category_id' => $category->id,
            'description' => 'Default description',
            'image' => 'default/image.jpg',
            'video' => 'default/video.mp4',
        ]);

        $partner = Partner::factory()->create();
        $partner->exercises()->attach($exercise->id, [
            'description' => 'Partner description',
            'image' => 'partner/image.jpg',
            'video' => 'partner/video.mp4',
        ]);

        $admin = User::factory()->create(['partner_id' => $partner->id]);
        $admin->roles()->attach(
            Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id
        );

        return [$admin, $exercise];
    }
}
