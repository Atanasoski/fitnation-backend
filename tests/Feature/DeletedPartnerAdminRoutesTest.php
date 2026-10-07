<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spec 025 ticket 02 deleted the partner-admin pages, the library-programs UI
 * and the partner exercise endpoints. Every one of their routes is gone: a
 * stray route left behind fails here. Requests are made as a super admin with
 * real records behind every id, so only a missing route can answer 404.
 */
class DeletedPartnerAdminRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * [method, uri, expected status]. `{user}`, `{session}`, `{plan}`,
     * `{exercise}` and `{partner}` are replaced with real ids (slug for a
     * partner).
     *
     * GET /partners and GET /partners/{partner} answer 405, not 404: the kept
     * partner store, update and destroy routes share those URIs.
     *
     * @return array<string, array{string, string, int}>
     */
    public static function deletedRoutes(): array
    {
        return [
            'users list' => ['GET', '/users', 404],
            'user page' => ['GET', '/users/{user}', 404],
            'user workout sessions' => ['GET', '/users/{user}/workout-sessions', 404],
            'user workout session' => ['GET', '/users/{user}/workout-sessions/{session}', 404],
            'programs index' => ['GET', '/partner/programs', 404],
            'programs create' => ['GET', '/partner/programs/create', 404],
            'programs store' => ['POST', '/partner/programs', 404],
            'programs show' => ['GET', '/partner/programs/{plan}', 404],
            'programs edit' => ['GET', '/partner/programs/{plan}/edit', 404],
            'programs update' => ['PUT', '/partner/programs/{plan}', 404],
            'programs destroy' => ['DELETE', '/partner/programs/{plan}', 404],
            'partner exercises' => ['GET', '/partner/exercises', 404],
            'partner exercise' => ['GET', '/partner/exercises/{exercise}', 404],
            'partner exercise edit' => ['GET', '/partner/exercises/{exercise}/edit', 404],
            'partner exercise update' => ['PUT', '/exercises/{exercise}/partner', 404],
            'partner exercises bulk link' => ['POST', '/partner/exercises/bulk-link', 404],
            'exercise link' => ['POST', '/exercises/{exercise}/link', 404],
            'exercise unlink' => ['POST', '/exercises/{exercise}/unlink', 404],
            'partners list' => ['GET', '/partners', 405],
            'partner page' => ['GET', '/partners/{partner}', 405],
        ];
    }

    #[DataProvider('deletedRoutes')]
    public function test_the_route_is_gone(string $method, string $uri, int $status): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-gym']);
        $member = User::factory()->create(['partner_id' => $partner->id]);
        $session = WorkoutSession::factory()->create(['user_id' => $member->id]);
        $plan = Plan::factory()->partnerLibrary($partner)->create();
        $exercise = Exercise::factory()->create();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);

        $uri = strtr($uri, [
            '{user}' => (string) $member->id,
            '{session}' => (string) $session->id,
            '{plan}' => (string) $plan->id,
            '{exercise}' => (string) $exercise->id,
            '{partner}' => $partner->slug,
        ]);

        $this->actingAs($admin)->call($method, $uri)->assertStatus($status);
    }
}
