<?php

namespace Tests\Feature\Admin;

use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Services\Exercise\PartnerExerciseView;
use App\Services\PartnerExerciseFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The super admin edits, clears, links and unlinks any partner's Partner
 * Override from the exercise slide-over (spec 023, ticket 04).
 */
class PartnerOverridesTest extends TestCase
{
    use RefreshDatabase;

    private Exercise $exercise;

    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->exercise = Exercise::factory()->create([
            'name' => 'Bench Press',
            'description' => 'Catalogue cues',
            'image' => 'exercises/images/catalogue.jpg',
            'video' => 'exercises/videos/catalogue.mp4',
        ]);
        $this->partner = Partner::factory()->create(['name' => 'Iron Gym', 'slug' => 'iron-gym']);
    }

    public function test_a_super_admin_edits_a_partners_override_and_the_partner_sees_it(): void
    {
        $this->partner->exercises()->attach($this->exercise->id);

        $this->actingAs($this->admin())
            ->put(route('exercises.partners.update', [$this->exercise, $this->partner]), [
                'description' => 'Iron Gym cues',
                'image' => UploadedFile::fake()->image('own.jpg'),
                'back' => 'q=bench',
            ])
            ->assertRedirect(route('exercises.index', ['q' => 'bench', 'edit' => $this->exercise->id]))
            ->assertSessionHas('success');

        $view = PartnerExerciseView::of($this->exercise->fresh(), $this->partner);
        $imagePath = (new PartnerExerciseFileService)->getImagePath($this->partner, $this->exercise, 'jpg');
        $this->assertSame('Iron Gym cues', $view->description);
        $this->assertSame(Storage::url($imagePath), $view->imageUrl);
        $this->assertSame(Storage::url('exercises/videos/catalogue.mp4'), $view->videoUrl);
        Storage::assertExists($imagePath);
    }

    public function test_remove_image_and_remove_video_clear_just_those_files(): void
    {
        [$imagePath, $videoPath] = $this->fullOverride();

        $this->actingAs($this->admin())
            ->put(route('exercises.partners.update', [$this->exercise, $this->partner]), [
                'remove_image' => '1',
                'remove_video' => '1',
            ])
            ->assertRedirect(route('exercises.index', ['edit' => $this->exercise->id]));

        $view = PartnerExerciseView::of($this->exercise->fresh(), $this->partner);
        $this->assertSame('Iron Gym cues', $view->description);
        $this->assertSame(Storage::url('exercises/images/catalogue.jpg'), $view->imageUrl);
        $this->assertSame(Storage::url('exercises/videos/catalogue.mp4'), $view->videoUrl);
        Storage::assertMissing([$imagePath, $videoPath]);
    }

    public function test_clear_restores_the_catalogue_values_and_keeps_the_link(): void
    {
        [$imagePath, $videoPath] = $this->fullOverride();

        $this->actingAs($this->admin())
            ->delete(route('exercises.partners.clear', [$this->exercise, $this->partner]), ['back' => 'archived=1'])
            ->assertRedirect(route('exercises.index', ['archived' => '1', 'edit' => $this->exercise->id]))
            ->assertSessionHas('success');

        $view = PartnerExerciseView::of($this->exercise->fresh(), $this->partner);
        $this->assertSame('Catalogue cues', $view->description);
        $this->assertSame(Storage::url('exercises/images/catalogue.jpg'), $view->imageUrl);
        $this->assertSame(Storage::url('exercises/videos/catalogue.mp4'), $view->videoUrl);
        $this->assertFalse($view->hasDescriptionOverride || $view->hasImageOverride || $view->hasVideoOverride);
        $this->assertTrue($this->isLinked());
        Storage::assertMissing([$imagePath, $videoPath]);
    }

    public function test_link_puts_the_exercise_in_the_partners_library_with_no_override(): void
    {
        $this->actingAs($this->admin())
            ->post(route('exercises.partners.link', $this->exercise), ['partner_id' => $this->partner->id])
            ->assertRedirect(route('exercises.index', ['edit' => $this->exercise->id]));

        $this->assertTrue($this->isLinked());
        $this->assertSame('Catalogue cues', PartnerExerciseView::of($this->exercise->fresh(), $this->partner)->description);
    }

    public function test_link_needs_a_real_partner(): void
    {
        $this->actingAs($this->admin())
            ->post(route('exercises.partners.link', $this->exercise), ['partner_id' => 999999])
            ->assertSessionHasErrors('partner_id', null, 'override');

        $this->assertSame(0, $this->exercise->partners()->count());
    }

    public function test_unlink_takes_it_out_of_the_partners_library_and_deletes_the_files(): void
    {
        [$imagePath, $videoPath] = $this->fullOverride();

        $this->actingAs($this->admin())
            ->delete(route('exercises.partners.unlink', [$this->exercise, $this->partner]))
            ->assertRedirect(route('exercises.index', ['edit' => $this->exercise->id]));

        $this->assertFalse($this->isLinked());
        Storage::assertMissing([$imagePath, $videoPath]);
    }

    public function test_the_slide_over_lists_linked_partners_and_offers_the_others(): void
    {
        $this->fullOverride();
        Partner::factory()->create(['name' => 'Unlinked Club']);

        $this->actingAs($this->admin())
            ->get(route('exercises.index', ['edit' => $this->exercise->id]))
            ->assertOk()
            ->assertSee('Partner Overrides')
            ->assertSee('Iron Gym cues')
            ->assertSee(route('exercises.partners.update', [$this->exercise, $this->partner]), false)
            ->assertSee(route('exercises.partners.unlink', [$this->exercise, $this->partner]), false)
            ->assertSee('Unlinked Club');
    }

    public function test_non_admins_get_403_and_change_nothing(): void
    {
        $this->fullOverride();
        $partnerAdmin = User::factory()->create(['partner_id' => $this->partner->id]);
        $partnerAdmin->roles()->attach(Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id);
        $other = Partner::factory()->create();

        foreach ([User::factory()->create(), $partnerAdmin] as $user) {
            $this->actingAs($user)->put(route('exercises.partners.update', [$this->exercise, $this->partner]), ['description' => 'Sneaky'])->assertForbidden();
            $this->actingAs($user)->delete(route('exercises.partners.clear', [$this->exercise, $this->partner]))->assertForbidden();
            $this->actingAs($user)->post(route('exercises.partners.link', $this->exercise), ['partner_id' => $other->id])->assertForbidden();
            $this->actingAs($user)->delete(route('exercises.partners.unlink', [$this->exercise, $this->partner]))->assertForbidden();
        }

        $this->assertSame('Iron Gym cues', PartnerExerciseView::of($this->exercise->fresh(), $this->partner)->description);
        $this->assertSame([$this->partner->id], $this->exercise->partners()->pluck('partners.id')->all());
    }

    /**
     * Link the partner with an override on all three fields, files on disk.
     *
     * @return array{0: string, 1: string} the image and video paths
     */
    private function fullOverride(): array
    {
        $files = new PartnerExerciseFileService;
        $imagePath = $files->getImagePath($this->partner, $this->exercise, 'jpg');
        $videoPath = $files->getVideoPath($this->partner, $this->exercise, 'mp4');
        Storage::put($imagePath, 'image');
        Storage::put($videoPath, 'video');
        $this->partner->exercises()->attach($this->exercise->id, [
            'description' => 'Iron Gym cues', 'image' => $imagePath, 'video' => $videoPath,
        ]);

        return [$imagePath, $videoPath];
    }

    private function isLinked(): bool
    {
        return $this->partner->exercises()->whereKey($this->exercise->id)->exists();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);

        return $admin;
    }
}
