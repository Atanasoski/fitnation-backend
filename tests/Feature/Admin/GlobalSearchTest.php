<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Partner;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The ⌘K palette's endpoint (ticket 07): users by name or email with both
 * chips, partners by name, pages by title. Admin only.
 */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private Partner $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->gym = Partner::factory()->create(['name' => 'Iron Temple']);
    }

    public function test_users_are_found_by_part_of_their_name(): void
    {
        $ada = $this->member(['name' => 'Ada Lovelace', 'email' => 'countess@example.com']);
        $this->member(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

        $users = $this->search('lovel')->json('users');

        $this->assertSame([$ada->id], array_column($users, 'id'));
        $this->assertSame('Ada Lovelace', $users[0]['name']);
        $this->assertSame('countess@example.com', $users[0]['email']);
        $this->assertSame('Iron Temple', $users[0]['partner']);
    }

    public function test_users_are_found_by_part_of_their_email(): void
    {
        $this->member(['name' => 'Ada Lovelace', 'email' => 'countess@example.com']);
        $grace = $this->member(['name' => 'Grace Hopper', 'email' => 'amazing.grace@navy.example']);

        $this->assertSame([$grace->id], array_column($this->search('navy.ex')->json('users'), 'id'));
    }

    public function test_admin_and_partner_admin_accounts_are_never_found(): void
    {
        $ada = $this->member(['name' => 'Ada Lovelace']);
        $this->userWithRole('admin', ['name' => 'Ada Admin']);
        $this->userWithRole('partner_admin', ['name' => 'Ada Gym Owner', 'partner_id' => $this->gym->id]);

        $this->assertSame([$ada->id], array_column($this->search('ada')->json('users'), 'id'));
    }

    public function test_each_user_result_carries_its_activity_status_and_access_source_chips(): void
    {
        $ada = $this->member(['name' => 'Ada Lovelace']);
        Subscription::factory()->trial()->create(['user_id' => $ada->id]);
        $this->member(['name' => 'Ada Unfinished', 'onboarding_completed_at' => null]);

        $users = collect($this->search('ada')->json('users'))->keyBy('name');

        $this->assertSame(['value' => 'inactive', 'label' => 'Inactive'], $users['Ada Lovelace']['activity']);
        $this->assertSame('trial', $users['Ada Lovelace']['access']['value']);
        $this->assertSame('Trial', $users['Ada Lovelace']['access']['label']);
        $this->assertSame('unfinished', $users['Ada Unfinished']['activity']['value']);
        $this->assertSame('none', $users['Ada Unfinished']['access']['value']);
        $this->assertStringContainsString('>Trial</span>', $users['Ada Lovelace']['chips']);
        $this->assertStringContainsString('rounded-full', $users['Ada Lovelace']['chips']);
    }

    public function test_at_most_eight_users_come_back(): void
    {
        foreach (range(1, 12) as $i) {
            $this->member(['name' => "Lifter {$i}"]);
        }

        $this->assertCount(8, $this->search('lifter')->json('users'));
    }

    public function test_deleted_users_are_found_after_live_ones(): void
    {
        $this->member(['name' => 'Ada Gone'])->delete();
        $this->member(['name' => 'Ada Lovelace']);

        $users = $this->search('ada')->json('users');

        $this->assertSame(['Ada Lovelace', 'Ada Gone'], array_column($users, 'name'));
        $this->assertSame('deleted', $users[1]['activity']['value']);
    }

    public function test_a_user_result_opens_that_person(): void
    {
        $this->member(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

        $url = $this->search('ada')->json('users.0.url');

        $this->actingAs($this->userWithRole('admin'))->get($url)->assertOk()->assertSee('ada@example.com');
    }

    public function test_partners_are_found_by_name(): void
    {
        $studio = Partner::factory()->create(['name' => 'Flow Studio']);

        $partners = $this->search('flow')->json('partners');

        $this->assertSame([$studio->id], array_column($partners, 'id'));
        $this->assertSame('Flow Studio', $partners[0]['name']);
        $this->assertSame(route('admin.partners.show', $studio), $partners[0]['url']);
    }

    public function test_pages_are_found_by_title(): void
    {
        $pages = $this->search('split')->json('pages');

        $this->assertSame([['title' => 'Workout Splits', 'section' => 'Content', 'url' => url('/admin/workout-splits')]], $pages);
        $this->assertSame(['System'], array_column($this->search('syst')->json('pages'), 'title'));
    }

    public function test_a_blank_term_finds_nothing(): void
    {
        $this->member(['name' => 'Ada Lovelace']);

        $this->search('   ')->assertExactJson(['users' => [], 'partners' => [], 'pages' => []]);
    }

    public function test_wildcards_in_the_term_are_matched_literally(): void
    {
        $this->member(['name' => 'Ada Lovelace']);

        $this->assertSame([], $this->search('%')->json('users'));
    }

    public function test_partner_admins_and_plain_users_get_403(): void
    {
        $this->actingAs($this->userWithRole('partner_admin', ['partner_id' => $this->gym->id]))
            ->getJson('/admin/search?q=ada')
            ->assertForbidden();

        $this->actingAs($this->member())
            ->getJson('/admin/search?q=ada')
            ->assertForbidden();
    }

    public function test_admin_pages_carry_the_search_button_and_palette_but_partner_admin_pages_do_not(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('data-global-search', false)
            ->assertSee(str_replace('/', '\\/', route('admin.search')), false);

        $this->actingAs($this->userWithRole('partner_admin', ['partner_id' => $this->gym->id]))
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('data-global-search', false);
    }

    private function search(string $q): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->userWithRole('admin'))
            ->getJson('/admin/search?q='.urlencode($q))
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function member(array $attributes = []): User
    {
        return User::factory()->create([
            'partner_id' => $this->gym->id,
            'onboarding_completed_at' => now()->subDays(60),
            ...$attributes,
        ]);
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create(['name' => "Staff {$slug}", 'email' => "{$slug}-".uniqid().'@staff.test', ...$attributes]);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

        return $user;
    }
}
