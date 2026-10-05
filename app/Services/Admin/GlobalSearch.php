<?php

namespace App\Services\Admin;

use App\Helpers\MenuHelper;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

/**
 * What the super admin's ⌘K palette finds for a term: app users whose name or
 * email contains it (staff excluded, deleted accounts last), each with its
 * Activity Status and Access Source; partners by name; and admin pages by
 * title. Results are capped so the palette stays one screen.
 */
final class GlobalSearch
{
    public const MAX_USERS = 8;

    public const MAX_PARTNERS = 5;

    /**
     * @return array{users: list<array<string, mixed>>, partners: list<array<string, mixed>>, pages: list<array<string, string>>}
     */
    public static function find(string $term): array
    {
        $term = trim($term);

        if ($term === '') {
            return ['users' => [], 'partners' => [], 'pages' => []];
        }

        return [
            'users' => self::users($term),
            'partners' => self::partners($term),
            'pages' => self::pages($term),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function users(string $term): array
    {
        $users = User::query()
            ->withTrashed()
            ->appUsers()

            ->matching($term)
            ->with('partner')
            ->orderByRaw('users.deleted_at IS NOT NULL')
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->limit(self::MAX_USERS)
            ->get();

        $statuses = ActivityStatuses::forUsers($users);
        $access = AccessSources::forUsers($users);

        return $users->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'partner' => $user->partner?->name,
            'url' => self::userUrl($user),
            'activity' => ['value' => $statuses[$user->id]->value, 'label' => $statuses[$user->id]->label()],
            'access' => [
                'value' => $access[$user->id]->source->value,
                'label' => $access[$user->id]->source->label(),
                'detail' => $access[$user->id]->detail(),
            ],
            'chips' => Blade::render(
                '<x-admin.activity-chip :status="$status" /> <x-admin.access-chip :access="$access" />',
                ['status' => $statuses[$user->id], 'access' => $access[$user->id]],
            ),
        ])->values()->all();
    }

    private static function userUrl(User $user): string
    {
        return route('admin.users.show', $user->id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function partners(string $term): array
    {
        return Partner::query()
            ->where('name', 'like', '%'.addcslashes($term, '\\%_').'%')
            ->orderBy('name')
            ->limit(self::MAX_PARTNERS)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Partner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'url' => route('partners.show', $partner),
            ])->values()->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private static function pages(string $term): array
    {
        return collect(MenuHelper::superAdminPages())
            ->filter(fn (array $page) => Str::contains($page['title'], $term, ignoreCase: true))
            ->values()
            ->all();
    }
}
