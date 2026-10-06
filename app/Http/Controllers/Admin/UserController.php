<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccessSource;
use App\Enums\ActivityStatus;
use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Enums\UnitSystem;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\FormatsMeasurements;
use App\Models\Partner;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Admin\AccessSources;
use App\Services\Admin\ActivePlan;
use App\Services\Admin\ActivityStatuses;
use App\Services\Admin\UserChanges;
use App\Services\FitnessMetrics\CompletedSessions;
use App\Services\WorkoutSession\BestSets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The super admin's Users list: every app user across all partners. Admin and
 * partner-admin accounts are staff, not users, and never appear.
 *
 * Every filter is a query parameter so Overview and partner pages can link
 * straight to a filtered list, and they ride along on every page link:
 * `partner`, `activity`, `access`, `goal`, `experience`, `platform` (has a
 * Device on it), `signin` (social / password), `stuck` (has a Stuck Session),
 * `deleted` (only deleted users), `signed_up_days` (joined in the last N
 * days), `q` (name or email contains) and `sort`
 * (`signup` / `last_session`, a leading `-` for descending; `-signup` by
 * default). Unknown values are ignored.
 */
class UserController extends Controller
{
    use FormatsMeasurements;

    public const PER_PAGE = 25;

    public const RECENT_SESSIONS = 10;

    public const RECENT_SENT_RECORDS = 10;

    /** Device platforms, as the mobile app registers them. */
    public const PLATFORMS = ['ios' => 'iOS', 'android' => 'Android'];

    /** Social = signed in through Google/Apple; password = everyone else. */
    public const SIGNIN_METHODS = ['social' => 'Social', 'password' => 'Password'];

    public const SORTS = [
        '-signup' => 'Newest signup',
        'signup' => 'Oldest signup',
        '-last_session' => 'Last session, recent first',
        'last_session' => 'Last session, oldest first',
    ];

    public const DEFAULT_SORT = '-signup';

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $query = User::query()
            ->appUsers()
            ->with('partner')
            ->withMax(['workoutSessions as last_completed_session_at' => fn (Builder $sessions) => $sessions->completed()], 'completed_at')
            ->withCount(['workoutSessions as completed_sessions_30d' => fn (Builder $sessions) => $sessions
                ->completed()
                ->where('completed_at', '>=', now()->subDays(CompletedSessions::RECENT_DAYS))])
            ->when($filters['partner'], fn (Builder $q, int $id) => $q->where('partner_id', $id))
            ->when($filters['goal'], fn (Builder $q, FitnessGoal $goal) => $q->whereRelation('profile', 'fitness_goal', $goal))
            ->when($filters['experience'], fn (Builder $q, TrainingExperience $exp) => $q->whereRelation('profile', 'training_experience', $exp))
            ->when($filters['platform'], fn (Builder $q, string $platform) => $q->whereRelation('devices', 'platform', $platform))
            ->when($filters['signin'] === 'social', fn (Builder $q) => $q->whereNotNull('users.social_provider'))
            ->when($filters['signin'] === 'password', fn (Builder $q) => $q->whereNull('users.social_provider'))
            ->when($filters['stuck'], fn (Builder $q) => $q->whereHas('workoutSessions', fn (Builder $sessions) => $sessions->stuck()))
            ->when($filters['deleted'], fn (Builder $q) => $q->onlyTrashed())
            ->when($filters['signed_up_days'], fn (Builder $q, int $days) => $q->where('users.created_at', '>=', now()->subDays($days)))
            ->when($filters['q'], fn (Builder $q, string $term) => $q->matching($term));

        if ($filters['activity'] !== null) {
            ActivityStatuses::constrain($query, $filters['activity']);
        }

        if ($filters['access'] !== null) {
            AccessSources::constrain($query, $filters['access']);
        }

        $this->sort($query, $filters['sort']);

        $users = $query->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'statuses' => ActivityStatuses::forUsers($users->getCollection()),
            'access' => AccessSources::forUsers($users->getCollection()),
            'partners' => Partner::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    /**
     * One user's page: the four-fact strip (Activity Status, Access Source,
     * Partner, active plan) and everything else about them below it. Staff
     * accounts are not users and 404; deleted users keep their page.
     */
    public function show(Request $request, User $user): View
    {
        abort_unless(User::query()->appUsers()->withTrashed()->whereKey($user->getKey())->exists(), 404);

        $user->load(['partner', 'profile']);
        $units = $user->unitSystem();

        return view('admin.users.show', [
            'user' => $user,
            'status' => ActivityStatuses::for($user),
            'access' => AccessSources::for($user),
            'plan' => ActivePlan::for($user),
            'plans' => $user->plans()
                ->withCount('workoutTemplates')
                ->orderByDesc('is_active')
                ->latest('updated_at')
                ->latest('id')
                ->get(),
            'height' => $this->height($user->profile?->height, $units),
            'weight' => $this->weight($user->profile?->weight, 'user_profiles', 'weight', $units),
            'sessions' => $user->workoutSessions()
                ->with('workoutTemplate:id,name')
                ->latest('performed_at')
                ->latest('id')
                ->limit(self::RECENT_SESSIONS)
                ->get(),
            'bests' => BestSets::forUser($user)->map(fn (array $best) => [
                ...$best,
                'weight' => $this->weight($best['weight'], 'workout_session_set_logs', 'weight', $units),
            ]),
            'devices' => $user->devices()->latest('last_seen_at')->get(),
            'sent' => $user->notifications()->latest()->limit(self::RECENT_SENT_RECORDS)->get(),
            'invitation' => UserInvitation::query()
                ->where('email', $user->email)
                ->with(['inviter:id,name', 'partner:id,name'])
                ->orderByRaw('accepted_at IS NULL')
                ->latest()
                ->first(),
            'history' => UserChanges::history($user),
            'activePartners' => Partner::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'back' => $this->backToList($request),
        ]);
    }

    /**
     * The Users list the page was opened from, filters intact: list rows pass
     * their own query string as `back`. Only ever rebuilt as a query on the
     * Users list route, so it cannot point anywhere else.
     */
    private function backToList(Request $request): string
    {
        $back = $request->query('back');
        parse_str(is_string($back) ? $back : '', $query);
        unset($query['back']);

        return route('admin.users.index', $query);
    }

    /**
     * Measurements on the user page are shown in the user's own Unit System,
     * converted here at the HTTP boundary (ADR-0001), so the admin reads the
     * same numbers the user sees in the app.
     */
    private function weight(string|float|null $kg, string $table, string $column, UnitSystem $units): ?string
    {
        $value = $this->formatMeasured($kg, $table, $column, $units);

        return $value === null ? null : $value.' '.$units->weightUnit();
    }

    private function height(?int $cm, UnitSystem $units): ?string
    {
        $value = $this->formatMeasured($cm, 'user_profiles', 'height', $units);

        return match (true) {
            $value === null => null,
            $units === UnitSystem::Imperial => intdiv((int) $value, 12).' ft '.((int) $value % 12).' in',
            default => $value.' cm',
        };
    }

    /**
     * @return array{partner: ?int, activity: ?ActivityStatus, access: ?AccessSource, goal: ?FitnessGoal, experience: ?TrainingExperience, platform: ?string, signin: ?string, stuck: bool, deleted: bool, q: ?string, sort: string}
     */
    private function filters(Request $request): array
    {
        $string = fn (string $key) => is_string($value = $request->query($key)) ? trim($value) : '';
        $oneOf = fn (string $key, array $allowed) => array_key_exists($string($key), $allowed) ? $string($key) : null;

        return [
            'partner' => $request->integer('partner') ?: null,
            'activity' => ActivityStatus::tryFrom($string('activity')),
            'access' => AccessSource::tryFrom($string('access')),
            'goal' => FitnessGoal::tryFrom($string('goal')),
            'experience' => TrainingExperience::tryFrom($string('experience')),
            'platform' => $oneOf('platform', self::PLATFORMS),
            'signin' => $oneOf('signin', self::SIGNIN_METHODS),
            'stuck' => $request->boolean('stuck'),
            'deleted' => $request->boolean('deleted'),
            'signed_up_days' => ($days = $request->integer('signed_up_days')) > 0 ? min($days, 3650) : null,
            'q' => $string('q') !== '' ? $string('q') : null,
            'sort' => $oneOf('sort', self::SORTS) ?? self::DEFAULT_SORT,
        ];
    }

    /**
     * Users who never completed a session sort last in both directions of the
     * last-session sort; id breaks ties so pages never overlap.
     *
     * @param  Builder<User>  $query
     */
    private function sort(Builder $query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        if (ltrim($sort, '-') === 'last_session') {
            $query->orderByRaw('last_completed_session_at IS NULL')->orderBy('last_completed_session_at', $direction);
        } else {
            $query->orderBy('users.created_at', $direction);
        }

        $query->orderBy('users.id', $direction);
    }
}
