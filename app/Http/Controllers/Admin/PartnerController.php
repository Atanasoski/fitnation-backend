<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ColorHelper;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use App\Services\Admin\AccessSources;
use App\Services\Admin\ActivityStatuses;
use App\Services\Admin\Overview;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The super admin's Partners list and partner page. Creating and editing a
 * partner (and its branding) stay on the existing PartnerController forms.
 *
 * Members are app users (User::appUsers()): staff accounts never count.
 * "Active this week" is members with a Completed Session since Monday.
 */
class PartnerController extends Controller
{
    private const MEMBERS_SHOWN = 10;

    public function index(Request $request): View
    {
        $expiring = $request->boolean('expiring');

        $partners = Partner::query()
            ->when($expiring, fn (Builder $q) => $q->sponsorshipExpiringWithin(Overview::EXPIRING_SPONSORSHIP_DAYS))
            ->withCount([
                'users as members_count' => fn (Builder $users) => $users->appUsers(),
                'users as active_this_week_count' => fn (Builder $users) => $users->appUsers()->trainedBetween(...$this->thisWeek()),
            ])
            ->orderBy('name')
            ->get();

        return view('admin.partners.index', ['partners' => $partners, 'expiring' => $expiring]);
    }

    public function show(Partner $partner): View
    {
        $partner->load('identity');

        $members = fn () => $partner->users()->appUsers();
        $recentMembers = $members()->latest()->limit(self::MEMBERS_SHOWN)->get();

        return view('admin.partners.show', [
            'partner' => $partner,
            'memberCount' => $members()->count(),
            'activeThisWeek' => $members()->trainedBetween(...$this->thisWeek())->count(),
            'members' => $recentMembers,
            'statuses' => ActivityStatuses::forUsers($recentMembers),
            'access' => AccessSources::forUsers($recentMembers),
            'admins' => $partner->users()
                ->whereHas('roles', fn (Builder $roles) => $roles->where('slug', 'partner_admin'))
                ->orderBy('name')
                ->get(),
            'lightBranding' => ColorHelper::processPartnerColors($partner->identity),
            'darkBranding' => collect(ColorHelper::getDarkColorPalette($partner->identity))->pluck('value', 'name')->all(),
        ]);
    }

    public function updateActive(Request $request, Partner $partner): RedirectResponse
    {
        $active = $request->validate(['active' => ['required', 'boolean']])['active'];

        if (! $active && ! $partner->canBeDeactivated()) {
            return back()->withErrors(['active' => Partner::HOUSE_CANNOT_BE_DEACTIVATED]);
        }

        $partner->update(['is_active' => (bool) $active]);

        return back()->with('success', $partner->is_active
            ? "{$partner->name} is active again."
            : "{$partner->name} is deactivated.");
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function thisWeek(): array
    {
        $now = CarbonImmutable::now();

        return [$now->startOfWeek(CarbonImmutable::MONDAY), $now];
    }
}
