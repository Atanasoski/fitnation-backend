<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccessSource;
use App\Enums\ActivityStatus;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use App\Services\Admin\AccessSources;
use App\Services\Admin\ActivityStatuses;
use App\Services\FitnessMetrics\CompletedSessions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The super admin's Users list: every app user across all partners. Admin and
 * partner-admin accounts are staff, not users, and never appear. Filters are
 * query parameters (`partner`, `activity`, `access`) so Overview and partner pages can
 * link straight to a filtered list, and they ride along on every page link.
 */
class UserController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $partnerId = $request->integer('partner') ?: null;
        $activity = ActivityStatus::tryFrom((string) $request->query('activity', ''));
        $access = AccessSource::tryFrom((string) $request->query('access', ''));

        $query = User::query()
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('slug', ['admin', 'partner_admin']))
            ->with('partner')
            ->withMax(['workoutSessions as last_completed_session_at' => fn (Builder $sessions) => $sessions->completed()], 'completed_at')
            ->withCount(['workoutSessions as completed_sessions_30d' => fn (Builder $sessions) => $sessions
                ->completed()
                ->where('completed_at', '>=', now()->subDays(CompletedSessions::RECENT_DAYS))])
            ->when($partnerId, fn (Builder $q) => $q->where('partner_id', $partnerId))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($activity !== null) {
            ActivityStatuses::constrain($query, $activity);
        }

        if ($access !== null) {
            AccessSources::constrain($query, $access);
        }

        $users = $query->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'statuses' => ActivityStatuses::forUsers($users->getCollection()),
            'access' => AccessSources::forUsers($users->getCollection()),
            'partners' => Partner::query()->orderBy('name')->get(['id', 'name']),
            'filters' => ['partner' => $partnerId, 'activity' => $activity, 'access' => $access],
        ]);
    }
}
