<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GymLocation;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin configuration of a Partner for the Join Prompt (issue #87): Gym
 * Locations, the benefits list, and the geo_prompt_enabled switch, all on the
 * partner edit form. Enabling refuses until the Partner has at least one Gym
 * Location and one benefit (GLOSSARY: Join Prompt, Gym Location).
 */
class PartnerJoinPromptConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_benefits_are_posted_one_per_line_and_stored_as_an_array(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);

        $this->actingAs($this->admin())
            ->put('/partners/iron-temple', $this->form([
                'benefits' => "Free towel service\n\n  Sauna access  \nMember pricing\n",
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Free towel service', 'Sauna access', 'Member pricing'],
            $partner->refresh()->benefits,
        );
    }

    public function test_a_gym_location_can_be_added_with_the_radius_defaulting_to_150(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);

        $this->actingAs($this->admin())
            ->put('/partners/iron-temple', $this->form([
                'gym_locations' => [
                    ['id' => '', 'label' => 'Main floor', 'latitude' => '41.9981', 'longitude' => '21.4254', 'radius_m' => ''],
                    ['id' => '', 'label' => 'Annex', 'latitude' => '-41.12345', 'longitude' => '-21.5', 'radius_m' => '200'],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $locations = $partner->refresh()->gymLocations()->orderBy('id')->get();
        $this->assertCount(2, $locations);
        $this->assertSame('Main floor', $locations[0]->label);
        $this->assertEqualsWithDelta(41.9981, $locations[0]->latitude, 0.0000001);
        $this->assertEqualsWithDelta(21.4254, $locations[0]->longitude, 0.0000001);
        $this->assertSame(150, $locations[0]->radius_m);
        $this->assertSame(200, $locations[1]->radius_m);
    }

    public function test_an_existing_gym_location_is_updated_in_place_and_an_omitted_one_is_deleted(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);
        $kept = GymLocation::factory()->for($partner)->create(['label' => 'Main floor', 'radius_m' => 150]);
        $removed = GymLocation::factory()->for($partner)->create(['label' => 'Annex']);

        $this->actingAs($this->admin())
            ->put('/partners/iron-temple', $this->form([
                'gym_locations' => [
                    ['id' => (string) $kept->id, 'label' => 'Ground floor', 'latitude' => '42.0', 'longitude' => '21.0', 'radius_m' => '175'],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($removed);
        $kept->refresh();
        $this->assertSame('Ground floor', $kept->label);
        $this->assertSame(175, $kept->radius_m);
        $this->assertEqualsWithDelta(42.0, $kept->latitude, 0.0000001);
    }

    public function test_removing_every_row_deletes_all_gym_locations(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);
        GymLocation::factory()->for($partner)->count(2)->create();

        // With all rows removed the form posts only the section's marker.
        $this->actingAs($this->admin())
            ->put('/partners/iron-temple', $this->form(['gym_locations_submitted' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $partner->gymLocations()->count());
    }

    public function test_another_partners_gym_location_cannot_be_hijacked_by_posting_its_id(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);
        $other = GymLocation::factory()->create(['label' => 'Someone else']);

        $this->actingAs($this->admin())
            ->put('/partners/iron-temple', $this->form([
                'gym_locations' => [
                    ['id' => (string) $other->id, 'label' => 'Mine now', 'latitude' => '1', 'longitude' => '1', 'radius_m' => ''],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Someone else', $other->refresh()->label);
        $this->assertSame('Mine now', $partner->gymLocations()->sole()->label);
    }

    public function test_enabling_the_join_prompt_requires_at_least_one_gym_location(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);

        $this->actingAs($this->admin())
            ->put('/partners/iron-temple', $this->form([
                'geo_prompt_enabled' => '1',
                'benefits' => 'Free towel service',
                'gym_locations_submitted' => '1',
            ]))
            ->assertSessionHasErrors('geo_prompt_enabled');

        $this->assertFalse($partner->refresh()->geo_prompt_enabled);
    }

    public function test_enabling_the_join_prompt_requires_at_least_one_benefit(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);

        $this->actingAs($this->admin())
            ->put('/partners/iron-temple', $this->form([
                'geo_prompt_enabled' => '1',
                'benefits' => '',
                'gym_locations' => [
                    ['id' => '', 'label' => 'Main floor', 'latitude' => '42.0', 'longitude' => '21.0', 'radius_m' => ''],
                ],
            ]))
            ->assertSessionHasErrors('geo_prompt_enabled');

        $this->assertFalse($partner->refresh()->geo_prompt_enabled);
    }

    public function test_blank_benefits_posted_as_an_array_do_not_satisfy_the_enable_gate(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);

        $this->actingAs($this->admin())
            ->put('/partners/iron-temple', $this->form([
                'geo_prompt_enabled' => '1',
                'benefits' => ['', '   '],
                'gym_locations' => [
                    ['id' => '', 'label' => 'Main floor', 'latitude' => '42.0', 'longitude' => '21.0', 'radius_m' => ''],
                ],
            ]))
            ->assertSessionHasErrors('geo_prompt_enabled');

        $this->assertFalse($partner->refresh()->geo_prompt_enabled);
    }

    public function test_the_join_prompt_can_be_enabled_once_a_location_and_a_benefit_exist(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);

        $this->actingAs($this->admin())
            ->put('/partners/iron-temple', $this->form([
                'geo_prompt_enabled' => '1',
                'benefits' => 'Free towel service',
                'gym_locations' => [
                    ['id' => '', 'label' => 'Main floor', 'latitude' => '42.0', 'longitude' => '21.0', 'radius_m' => ''],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($partner->refresh()->geo_prompt_enabled);
    }

    public function test_disabling_the_join_prompt_is_always_allowed(): void
    {
        // A partner whose configuration has since been emptied out.
        $partner = Partner::factory()->create(['slug' => 'iron-temple', 'geo_prompt_enabled' => true, 'benefits' => []]);

        $this->actingAs($this->admin())
            ->put('/partners/iron-temple', $this->form([
                'geo_prompt_enabled' => '0',
                'benefits' => '',
                'gym_locations_submitted' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($partner->refresh()->geo_prompt_enabled);
    }

    public function test_the_edit_form_shows_the_join_prompt_section_prefilled(): void
    {
        $partner = Partner::factory()->create([
            'slug' => 'iron-temple',
            'benefits' => ['Free towel service', 'Sauna access'],
        ]);
        GymLocation::factory()->for($partner)->create(['label' => 'Main floor', 'radius_m' => 175]);

        $response = $this->actingAs($this->admin())->get('/partners/iron-temple/edit')->assertOk();

        $response->assertSee('name="geo_prompt_enabled"', escape: false)
            ->assertSee('name="benefits"', escape: false)
            ->assertSee('name="gym_locations_submitted"', escape: false)
            ->assertSee("Free towel service\nSauna access")
            ->assertSee('Main floor')
            ->assertSee('175');
    }

    public function test_the_create_form_has_no_join_prompt_section(): void
    {
        $this->actingAs($this->admin())->get('/partners/create')
            ->assertOk()
            ->assertDontSee('name="geo_prompt_enabled"', escape: false)
            ->assertDontSee('name="gym_locations_submitted"', escape: false);
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);

        $this->actingAs($this->admin())
            ->put('/partners/iron-temple', $this->form([
                'gym_locations' => [
                    ['id' => '', 'label' => 'Nowhere', 'latitude' => '91', 'longitude' => '181', 'radius_m' => '5'],
                ],
            ]))
            ->assertSessionHasErrors([
                'gym_locations.0.latitude',
                'gym_locations.0.longitude',
                'gym_locations.0.radius_m',
            ]);

        $this->assertSame(0, $partner->gymLocations()->count());
    }

    /**
     * What the edit form posts. Join Prompt fields ride on the same PUT as the
     * rest of the partner form.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Iron Temple',
            'slug' => 'iron-temple',
            'domain' => '',
            'is_active' => '1',
            'geo_prompt_enabled' => '0',
            'benefits' => '',
            'primary_color' => '#aabbcc',
            'secondary_color' => '#ddeeff',
            'primary_color_dark' => '#123456',
            'secondary_color_dark' => '#654321',
        ];
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);

        return $user;
    }
}
