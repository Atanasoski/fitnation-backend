<?php

namespace Tests\Feature\Admin;

use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Role;
use App\Models\Station;
use App\Models\User;
use App\Services\Exercise\PartnerExerciseView;
use App\Services\PartnerExerciseFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The partner lens on the exercise gallery (#134): a super admin sees one
 * Partner's view of the catalogue and links or unlinks Exercises in bulk.
 */
class PartnerLibraryTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->partner = Partner::factory()->create(['name' => 'Iron Gym', 'slug' => 'iron-gym']);
    }

    public function test_bulk_link_adds_exercises_and_leaves_an_existing_partner_override_alone(): void
    {
        $bench = Exercise::factory()->create(['name' => 'Bench Press']);
        $squat = Exercise::factory()->create(['name' => 'Back Squat']);
        $this->partner->exercises()->attach($bench->id, ['description' => 'Iron Gym cues']);

        $this->actingAs($this->admin())
            ->post(route('admin.partners.library.link', $this->partner), [
                'exercise_ids' => [$bench->id, $squat->id],
                'back' => 'partner='.$this->partner->id.'&library=out',
            ])
            ->assertRedirect(route('exercises.index', ['partner' => (string) $this->partner->id, 'library' => 'out']))
            ->assertSessionHas('success', 'Linked 1 to Iron Gym.');

        $this->assertEqualsCanonicalizing([$bench->id, $squat->id], $this->libraryIds());
        $this->assertSame('Iron Gym cues', PartnerExerciseView::of($bench->fresh(), $this->partner)->description);
    }

    public function test_bulk_unlink_takes_exercises_out_and_deletes_their_override_files(): void
    {
        $bench = Exercise::factory()->create(['name' => 'Bench Press']);
        $squat = Exercise::factory()->create(['name' => 'Back Squat']);
        $keep = Exercise::factory()->create(['name' => 'Deadlift']);
        $imagePath = (new PartnerExerciseFileService)->getImagePath($this->partner, $bench, 'jpg');
        Storage::put($imagePath, 'image');
        $this->partner->exercises()->attach($bench->id, ['image' => $imagePath]);
        $this->partner->exercises()->attach([$squat->id, $keep->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.partners.library.unlink', $this->partner), [
                'exercise_ids' => [$bench->id, $squat->id],
                'back' => 'partner='.$this->partner->id,
            ])
            ->assertRedirect(route('exercises.index', ['partner' => (string) $this->partner->id]))
            ->assertSessionHas('success', 'Unlinked 2 from Iron Gym.');

        $this->assertSame([$keep->id], $this->libraryIds());
        Storage::assertMissing($imagePath);
    }

    public function test_bulk_unlink_keeps_exercises_on_the_partners_stations_and_names_them(): void
    {
        $bench = Exercise::factory()->create(['name' => 'Bench Press']);
        $fly = Exercise::factory()->create(['name' => 'Cable Fly']);
        $squat = Exercise::factory()->create(['name' => 'Back Squat']);
        $this->partner->exercises()->attach([$bench->id, $fly->id, $squat->id]);
        Station::factory()->for($this->partner)->create(['name' => 'Cable Crossover'])->exercises()->attach($fly->id, ['display_order' => 1]);
        Station::factory()->for($this->partner)->create(['name' => 'Cable Tower'])->exercises()->attach($fly->id, ['display_order' => 1]);
        $gone = Station::factory()->for($this->partner)->create(['name' => 'Old Rack']);
        $gone->exercises()->attach($squat->id, ['display_order' => 1]);
        $gone->delete();
        Station::factory()->create(['name' => 'Elsewhere'])->exercises()->attach($bench->id, ['display_order' => 1]);

        $this->actingAs($this->admin())
            ->delete(route('admin.partners.library.unlink', $this->partner), [
                'exercise_ids' => [$bench->id, $fly->id, $squat->id],
            ])
            ->assertSessionHas('success', 'Unlinked 2 from Iron Gym; 1 kept: Cable Fly (on Cable Crossover, Cable Tower).');

        $this->assertSame([$fly->id], $this->libraryIds());
    }

    public function test_the_partner_picker_lists_the_house_partner_first_and_marks_inactive_ones(): void
    {
        $house = Partner::factory()->create(['name' => 'Zed Fit Nation']);
        config(['partners.house_partner_id' => $house->id]);
        Partner::factory()->inactive()->create(['name' => 'Closed Club']);

        $this->actingAs($this->admin())
            ->get(route('exercises.index'))
            ->assertOk()
            ->assertSeeInOrder(['Zed Fit Nation', 'Closed Club (inactive)', 'Iron Gym']);
    }

    public function test_under_the_lens_a_card_shows_what_the_partners_customers_see_and_offers_unlink(): void
    {
        $bench = Exercise::factory()->create(['name' => 'Bench Press', 'image' => 'exercises/images/catalogue.jpg']);
        $this->partner->exercises()->attach($bench->id, ['image' => 'iron-gym/own.jpg', 'description' => 'Iron Gym cues']);

        $this->actingAs($this->admin())
            ->get(route('exercises.index', ['partner' => $this->partner->id]))
            ->assertOk()
            ->assertSee(Storage::url('iron-gym/own.jpg'), false)
            ->assertDontSee(Storage::url('exercises/images/catalogue.jpg'), false)
            ->assertSee('own image')
            ->assertSee('own description')
            ->assertDontSee('own video')
            ->assertSee('Unlink from Iron Gym')
            ->assertSee('<input type="hidden" name="_method" value="DELETE">', false)
            ->assertDontSee('Link to Iron Gym')
            ->assertSee('Archive or delete (all partners)');
    }

    public function test_outside_the_library_a_card_shows_the_catalogue_media_and_offers_link(): void
    {
        Exercise::factory()->create(['name' => 'Bench Press', 'image' => 'exercises/images/catalogue.jpg']);

        $this->actingAs($this->admin())
            ->get(route('exercises.index', ['partner' => $this->partner->id, 'library' => 'out']))
            ->assertOk()
            ->assertSee('Bench Press')
            ->assertSee(Storage::url('exercises/images/catalogue.jpg'), false)
            ->assertSee('Link to Iron Gym')
            ->assertDontSee('Unlink from Iron Gym')
            ->assertDontSee('name="_method" value="DELETE"', false);
    }

    public function test_opening_an_exercise_under_the_lens_puts_that_partners_override_first(): void
    {
        $bench = Exercise::factory()->create(['name' => 'Bench Press']);
        $aaa = Partner::factory()->create(['name' => 'Aaa Gym']);
        $aaa->exercises()->attach($bench->id);
        $this->partner->exercises()->attach($bench->id);

        $this->actingAs($this->admin())
            ->get(route('exercises.index', ['partner' => $this->partner->id, 'edit' => $bench->id]))
            ->assertOk()
            ->assertSeeInOrder(['Partner Overrides', 'Iron Gym', 'Aaa Gym']);
    }

    public function test_the_partner_page_links_to_its_library(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.partners.show', $this->partner))
            ->assertOk()
            ->assertSee(route('exercises.index', ['partner' => $this->partner->id]), false);
    }

    public function test_non_admins_cannot_link_or_unlink(): void
    {
        $bench = Exercise::factory()->create();
        $this->partner->exercises()->attach($bench->id);
        $partnerAdmin = User::factory()->create(['partner_id' => $this->partner->id]);
        $partnerAdmin->roles()->attach(Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id);
        $other = Exercise::factory()->create();

        foreach ([User::factory()->create(), $partnerAdmin] as $user) {
            $this->actingAs($user)->post(route('admin.partners.library.link', $this->partner), ['exercise_ids' => [$other->id]])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.partners.library.unlink', $this->partner), ['exercise_ids' => [$bench->id]])->assertForbidden();
        }

        $this->assertSame([$bench->id], $this->libraryIds());
    }

    private function libraryIds(): array
    {
        return $this->partner->exercises()->pluck('workout_exercises.id')->all();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);

        return $admin;
    }
}
