<?php

namespace Tests\Feature;

use App\Enums\PlanType;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlanWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(
            ['slug' => 'partner_admin'],
            ['name' => 'Partner Admin', 'description' => 'Can manage partner organization']
        );
    }

    public function test_library_plan_creation_sets_type_program_and_partner_id(): void
    {
        $partner = Partner::factory()->create();
        $admin = User::factory()->create([
            'partner_id' => $partner->id,
        ]);
        $admin->roles()->attach(Role::where('slug', 'partner_admin')->first());

        $response = $this->actingAs($admin)->post(route('partner.programs.store'), [
            'name' => 'Library Program',
            'description' => 'A library program',
            'type' => 'library',
            'duration_weeks' => 6,
        ]);

        $response->assertRedirect(route('partner.programs.index'));

        $plan = Plan::where('name', 'Library Program')->first();
        $this->assertNotNull($plan);
        $this->assertEquals(PlanType::Program, $plan->type);
        $this->assertEquals($partner->id, $plan->partner_id);
        $this->assertNull($plan->user_id);
        $this->assertEquals(6, $plan->duration_weeks);
        $this->assertTrue($plan->isPartnerLibraryPlan());
    }

    public function test_user_plan_creation_sets_type_program_and_user_id(): void
    {
        $partner = Partner::factory()->create();
        $admin = User::factory()->create([
            'partner_id' => $partner->id,
        ]);
        $admin->roles()->attach(Role::where('slug', 'partner_admin')->first());
        $member = User::factory()->create([
            'partner_id' => $partner->id,
        ]);

        $response = $this->actingAs($admin)->post(route('plans.store', $member), [
            'name' => 'User Program',
            'description' => 'A program for user',
            'type' => 'program',
            'duration_weeks' => 4,
            'is_active' => false,
        ]);

        $response->assertRedirect(route('plans.show', Plan::where('name', 'User Program')->first()));

        $plan = Plan::where('name', 'User Program')->first();
        $this->assertNotNull($plan);
        $this->assertEquals(PlanType::Program, $plan->type);
        $this->assertEquals($member->id, $plan->user_id);
        $this->assertNull($plan->partner_id);
        $this->assertEquals(4, $plan->duration_weeks);
    }

    public function test_workout_store_sets_week_number_and_order_index_redirects_to_plan_show(): void
    {
        $partner = Partner::factory()->create();
        $admin = User::factory()->create([
            'partner_id' => $partner->id,
        ]);
        $admin->roles()->attach(Role::where('slug', 'partner_admin')->first());
        $plan = Plan::factory()->partnerLibrary($partner)->create();

        $response = $this->actingAs($admin)->post(route('workouts.store', $plan), [
            'plan_id' => $plan->id,
            'name' => 'Week 2 Workout',
            'description' => 'First workout of week 2',
            'week_number' => 2,
        ]);

        $response->assertRedirect(route('partner.programs.show', $plan));

        $workout = WorkoutTemplate::where('name', 'Week 2 Workout')->first();
        $this->assertNotNull($workout);
        $this->assertEquals(2, $workout->week_number);
        $this->assertEquals(0, $workout->order_index);
    }

    public function test_workout_store_for_user_plan_redirects_to_plans_show(): void
    {
        $partner = Partner::factory()->create();
        $admin = User::factory()->create([
            'partner_id' => $partner->id,
        ]);
        $admin->roles()->attach(Role::where('slug', 'partner_admin')->first());
        $member = User::factory()->create(['partner_id' => $partner->id]);
        $plan = Plan::factory()->program()->create([
            'user_id' => $member->id,
            'partner_id' => null,
        ]);

        $response = $this->actingAs($admin)->post(route('workouts.store', $plan), [
            'plan_id' => $plan->id,
            'name' => 'User Plan Workout',
            'description' => null,
            'week_number' => 1,
        ]);

        $response->assertRedirect(route('plans.show', $plan));

        $workout = WorkoutTemplate::where('name', 'User Plan Workout')->first();
        $this->assertNotNull($workout);
        $this->assertEquals(1, $workout->week_number);
        $this->assertEquals(0, $workout->order_index);
    }

    public function test_workout_store_order_index_increments_within_same_week(): void
    {
        $partner = Partner::factory()->create();
        $admin = User::factory()->create([
            'partner_id' => $partner->id,
        ]);
        $admin->roles()->attach(Role::where('slug', 'partner_admin')->first());
        $plan = Plan::factory()->partnerLibrary($partner)->create();

        WorkoutTemplate::factory()->create([
            'plan_id' => $plan->id,
            'week_number' => 3,
            'order_index' => 0,
        ]);
        WorkoutTemplate::factory()->create([
            'plan_id' => $plan->id,
            'week_number' => 3,
            'order_index' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('workouts.store', $plan), [
            'plan_id' => $plan->id,
            'name' => 'Third in week 3',
            'description' => null,
            'week_number' => 3,
        ]);

        $response->assertRedirect(route('partner.programs.show', $plan));

        $workout = WorkoutTemplate::where('name', 'Third in week 3')->first();
        $this->assertNotNull($workout);
        $this->assertEquals(3, $workout->week_number);
        $this->assertEquals(2, $workout->order_index);
    }

    public function test_library_plan_creation_with_cover_image_stores_file(): void
    {
        Storage::fake();
        $partner = Partner::factory()->create();
        $admin = User::factory()->create([
            'partner_id' => $partner->id,
        ]);
        $admin->roles()->attach(Role::where('slug', 'partner_admin')->first());

        $file = UploadedFile::fake()->image('cover.jpg', 800, 600);

        $response = $this->actingAs($admin)->post(route('partner.programs.store'), [
            'name' => 'Program with Cover',
            'description' => 'Has a cover image',
            'type' => 'library',
            'duration_weeks' => 4,
            'cover_image' => $file,
        ]);

        $response->assertRedirect(route('partner.programs.index'));
        $plan = Plan::where('name', 'Program with Cover')->first();
        $this->assertNotNull($plan);
        $this->assertNotNull($plan->cover_image);
        $this->assertStringStartsWith("{$partner->slug}/plans/cover-images/", $plan->cover_image);
        Storage::assertExists($plan->cover_image);
    }

    public function test_user_plan_creation_with_cover_image_stores_file(): void
    {
        Storage::fake();
        $partner = Partner::factory()->create();
        $admin = User::factory()->create([
            'partner_id' => $partner->id,
        ]);
        $admin->roles()->attach(Role::where('slug', 'partner_admin')->first());
        $member = User::factory()->create(['partner_id' => $partner->id]);

        $file = UploadedFile::fake()->image('plan-cover.png', 1200, 675);

        $response = $this->actingAs($admin)->post(route('plans.store', $member), [
            'name' => 'User Plan with Cover',
            'description' => null,
            'type' => 'program',
            'duration_weeks' => 2,
            'is_active' => false,
            'cover_image' => $file,
        ]);

        $response->assertRedirect();
        $plan = Plan::where('name', 'User Plan with Cover')->first();
        $this->assertNotNull($plan);
        $this->assertNotNull($plan->cover_image);
        $this->assertStringStartsWith('plans/cover-images/', $plan->cover_image);
        Storage::assertExists($plan->cover_image);
    }

    public function test_plan_update_with_cover_image_replaces_old_file(): void
    {
        Storage::fake();
        $partner = Partner::factory()->create();
        $admin = User::factory()->create([
            'partner_id' => $partner->id,
        ]);
        $admin->roles()->attach(Role::where('slug', 'partner_admin')->first());
        $member = User::factory()->create(['partner_id' => $partner->id]);
        $oldPath = 'plans/cover-images/old-cover.jpg';
        $plan = Plan::factory()->program()->create([
            'user_id' => $member->id,
            'partner_id' => null,
            'cover_image' => $oldPath,
        ]);
        Storage::put($oldPath, 'old content');

        $newFile = UploadedFile::fake()->image('new-cover.jpg', 800, 450);

        $response = $this->actingAs($admin)->put(route('plans.update', $plan), [
            'name' => $plan->name,
            'description' => $plan->description,
            'type' => 'program',
            'duration_weeks' => $plan->duration_weeks,
            'is_active' => $plan->is_active,
            'cover_image' => $newFile,
        ]);

        $response->assertRedirect(route('plans.index', $member));
        $plan->refresh();
        $this->assertNotNull($plan->cover_image);
        $this->assertStringStartsWith('plans/cover-images/', $plan->cover_image);
        Storage::assertMissing($oldPath);
        Storage::assertExists($plan->cover_image);
    }
}
