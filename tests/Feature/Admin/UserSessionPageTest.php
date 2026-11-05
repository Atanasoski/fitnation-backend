<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One session's page in the admin panel: the stored record only — status,
 * timestamps, notes, the session's exercises in order with their stored
 * targets, and the sets logged against each. Never the derived athlete view.
 */
class UserSessionPageTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-10-05 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::NOW);
    }

    public function test_only_an_admin_may_open_a_session_page(): void
    {
        $gym = Partner::factory()->create();
        $member = $this->member($gym);
        $session = $this->workout($member, 'Push Day', WorkoutSessionStatus::Completed);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}/sessions/{$session->id}")
            ->assertOk();

        $this->actingAs($this->userWithRole('partner_admin', ['partner_id' => $gym->id]))
            ->get("/admin/users/{$member->id}/sessions/{$session->id}")
            ->assertForbidden();
    }

    public function test_another_users_session_is_not_found_under_this_user(): void
    {
        $gym = Partner::factory()->create();
        $member = $this->member($gym);
        $other = $this->member($gym);
        $session = $this->workout($other, 'Push Day', WorkoutSessionStatus::Completed);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}/sessions/{$session->id}")
            ->assertNotFound();
    }

    public function test_shows_the_stored_record_with_exercises_in_order_and_their_sets(): void
    {
        $member = $this->member(Partner::factory()->create());
        $session = $this->workout($member, 'Push Day', WorkoutSessionStatus::Completed, notes: 'Felt strong today');

        $bench = Exercise::factory()->create(['name' => 'Bench Press']);
        $fly = Exercise::factory()->create(['name' => 'Cable Fly']);
        $benchRow = $this->sessionExercise($session, $bench, order: 1, targetSets: 3, minReps: 5, maxReps: 8);
        $flyRow = $this->sessionExercise($session, $fly, order: 2, targetSets: 2, minReps: 10, maxReps: 12);
        $this->set($session, $benchRow, setNumber: 1, kg: 100, reps: 8);
        $this->set($session, $benchRow, setNumber: 2, kg: 102.5, reps: 6);
        $this->set($session, $flyRow, setNumber: 1, kg: 25, reps: 12);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}/sessions/{$session->id}")
            ->assertOk()
            ->assertSee('Push Day')
            ->assertSee('Completed')
            ->assertSee('5 Oct 2026')
            ->assertSee('Felt strong today')
            ->assertSeeInOrder([
                'Bench Press', '3 sets × 5–8',
                '100 kg', '8',
                '102.5 kg', '6',
                'Cable Fly', '2 sets × 10–12',
                '25 kg', '12',
            ]);
    }

    public function test_weights_follow_the_users_unit_system(): void
    {
        $member = $this->member(Partner::factory()->create());
        $member->profile->update(['unit_system' => \App\Enums\UnitSystem::Imperial]);
        $session = $this->workout($member, 'Push Day', WorkoutSessionStatus::Completed);
        $bench = Exercise::factory()->create(['name' => 'Bench Press']);
        $row = $this->sessionExercise($session, $bench, order: 1, targetSets: 3, minReps: 5, maxReps: 8);
        $this->set($session, $row, setNumber: 1, kg: 100, reps: 8);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}/sessions/{$session->id}")
            ->assertOk()
            ->assertSee('220 lbs')
            ->assertDontSee('100 kg');
    }

    public function test_an_auto_generated_session_says_so(): void
    {
        $member = $this->member(Partner::factory()->create());
        $session = $this->workout($member, 'Push Day', WorkoutSessionStatus::Draft);
        $session->update(['is_auto_generated' => true]);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}/sessions/{$session->id}")
            ->assertOk()
            ->assertSee('Auto-generated');
    }

    public function test_a_replaced_session_links_to_its_replacement(): void
    {
        $member = $this->member(Partner::factory()->create());
        $old = $this->workout($member, 'Push Day', WorkoutSessionStatus::Cancelled);
        $new = $this->workout($member, 'Push Day v2', WorkoutSessionStatus::Draft);
        $new->update(['replaced_session_id' => $old->id]);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}/sessions/{$old->id}")
            ->assertOk()
            ->assertSee('Replaced by')
            ->assertSee('href="'.e(route('admin.users.sessions.show', [$member, $new])).'"', false);
    }

    public function test_a_stuck_session_is_badged_stuck(): void
    {
        $member = $this->member(Partner::factory()->create());
        $session = $this->workout($member, 'Pull Day', WorkoutSessionStatus::Active, daysAgo: 2);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}/sessions/{$session->id}")
            ->assertOk()
            ->assertSee('Stuck');
    }

    private function workout(User $user, string $templateName, WorkoutSessionStatus $status, ?string $notes = null, int $daysAgo = 0): WorkoutSession
    {
        $performedAt = now()->subDays($daysAgo)->subHours(2);

        return WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => WorkoutTemplate::factory()->create([
                'plan_id' => Plan::factory()->create(['user_id' => $user->id, 'is_active' => false])->id,
                'name' => $templateName,
            ])->id,
            'status' => $status,
            'performed_at' => $performedAt,
            'completed_at' => $status === WorkoutSessionStatus::Completed ? $performedAt->copy()->addHour() : null,
            'notes' => $notes,
            'is_auto_generated' => false,
        ]);
    }

    private function sessionExercise(WorkoutSession $session, Exercise $exercise, int $order, int $targetSets, int $minReps, int $maxReps): WorkoutSessionExercise
    {
        return WorkoutSessionExercise::query()->create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
            'order' => $order,
            'target_sets' => $targetSets,
            'min_target_reps' => $minReps,
            'max_target_reps' => $maxReps,
        ]);
    }

    private function set(WorkoutSession $session, WorkoutSessionExercise $row, int $setNumber, float $kg, int $reps): SetLog
    {
        return SetLog::query()->create([
            'workout_session_id' => $session->id,
            'workout_session_exercise_id' => $row->id,
            'exercise_id' => $row->exercise_id,
            'set_number' => $setNumber,
            'weight' => $kg,
            'reps' => $reps,
        ]);
    }

    private function member(Partner $partner, array $attributes = []): User
    {
        return User::factory()->create([
            'partner_id' => $partner->id,
            'onboarding_completed_at' => now()->subDays(60),
            ...$attributes,
        ]);
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

        return $user;
    }
}
