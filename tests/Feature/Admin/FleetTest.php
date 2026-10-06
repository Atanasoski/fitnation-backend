<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Device;
use App\Models\Role;
use App\Models\User;
use App\Services\System\Fleet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Seam 3 of spec 024: Fleet::summary() and the Old Build rule.
 */
class FleetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
    }

    public function test_versions_are_grouped_by_version_and_build_profile_with_platform_counts_and_flags(): void
    {
        $this->device('1.9.0', 'production', 'ios');
        $this->device('1.9.0', 'production', 'android');
        $this->device('1.9.0', 'production', 'ios');
        $this->device('1.8.3', 'production', 'android');
        $this->device('1.8.1', 'production', 'ios');
        $this->device('1.7.4', 'production', 'android');
        $this->device('1.7.4', 'preview', 'ios');
        $this->device(null, 'production', 'ios');

        $versions = collect(Fleet::summary()['versions'])
            ->map(fn (array $row) => Arr::only($row, ['version', 'build_profile', 'ios', 'android', 'latest', 'old']))
            ->all();

        $this->assertSame([
            ['version' => '1.9.0', 'build_profile' => 'production', 'ios' => 2, 'android' => 1, 'latest' => true, 'old' => false],
            ['version' => '1.8.3', 'build_profile' => 'production', 'ios' => 0, 'android' => 1, 'latest' => false, 'old' => false],
            ['version' => '1.8.1', 'build_profile' => 'production', 'ios' => 1, 'android' => 0, 'latest' => false, 'old' => true],
            ['version' => '1.7.4', 'build_profile' => 'preview', 'ios' => 1, 'android' => 0, 'latest' => false, 'old' => false],
            ['version' => '1.7.4', 'build_profile' => 'production', 'ios' => 0, 'android' => 1, 'latest' => false, 'old' => true],
            ['version' => null, 'build_profile' => 'production', 'ios' => 1, 'android' => 0, 'latest' => false, 'old' => false],
        ], $versions);
    }

    public function test_a_preview_build_on_an_old_number_is_not_old_and_a_null_version_never_is(): void
    {
        $this->device('2.0.0', 'production', 'ios');
        $this->device('1.9.0', 'production', 'ios');
        $this->device('1.0.0', 'preview', 'ios');
        $this->device('1.0.0', 'development', 'android');
        $this->device(null, 'production', 'android');

        $summary = Fleet::summary();

        $this->assertSame([], array_values(array_filter($summary['versions'], fn (array $row) => $row['old'])));
        $this->assertSame(0, $summary['old_build_users']);
    }

    public function test_the_old_build_count_reads_each_users_most_recently_seen_device_and_matches_the_users_list(): void
    {
        $this->device('1.9.0', 'production', 'ios');
        $this->device('1.8.3', 'production', 'ios');

        $stuck = User::factory()->create(['name' => 'Stuck Stan']);
        $this->device('1.9.0', 'production', 'ios', $stuck, '2026-09-01 12:00:00');
        $this->device('1.7.4', 'production', 'android', $stuck, '2026-10-04 12:00:00');

        $updated = User::factory()->create(['name' => 'Updated Ursula']);
        $this->device('1.7.4', 'production', 'android', $updated, '2026-09-01 12:00:00');
        $this->device('1.9.0', 'production', 'ios', $updated, '2026-10-04 12:00:00');

        $tester = User::factory()->create(['name' => 'Tester Tess']);
        $this->device('1.7.4', 'preview', 'ios', $tester);

        $staff = $this->staff();
        $this->device('1.7.4', 'production', 'ios', $staff);

        $this->assertSame(1, Fleet::summary()['old_build_users']);

        $this->actingAs($this->staff())
            ->get('/admin/users?old_build=1')
            ->assertOk()
            ->assertSee('Stuck Stan')
            ->assertDontSee('Updated Ursula')
            ->assertDontSee('Tester Tess')
            ->assertSee('1 user');
    }

    public function test_platform_counts_devices_and_push_is_over_app_users_with_a_device(): void
    {
        $on = User::factory()->create(['push_enabled' => true]);
        $this->device('1.9.0', 'production', 'ios', $on);
        $this->device('1.9.0', 'production', 'android', $on);
        $this->device('1.9.0', 'production', 'ios', User::factory()->create(['push_enabled' => false]));
        User::factory()->create(['push_enabled' => true]);
        $this->device('1.9.0', 'production', 'ios', $this->staff());

        $summary = Fleet::summary();

        $this->assertSame(['ios' => 2, 'android' => 1], $summary['platform']);
        $this->assertSame(['on' => 1, 'off' => 1], $summary['push']);
    }

    private function device(?string $version, ?string $profile, string $platform, ?User $user = null, ?string $lastSeen = null): Device
    {
        return Device::factory()->create([
            'user_id' => ($user ?? User::factory()->create())->id,
            'app_version' => $version,
            'build_profile' => $profile,
            'platform' => $platform,
            'last_seen_at' => $lastSeen ?? now(),
        ]);
    }

    private function staff(string $slug = 'admin'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

        return $user;
    }
}
