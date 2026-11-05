<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WorkoutSessionStatus;
use App\Http\Controllers\Admin\Concerns\DisplaysWeights;
use App\Http\Controllers\Admin\Concerns\EnsuresAppUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Services\Admin\UserChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One user's workout sessions in the admin panel: the full paginated list
 * (`status` filters by status, with `stuck` as an extra value — a Stuck
 * Session is a predicate on active, not a status). Staff accounts are not
 * users and 404, as on the user page itself.
 */
class UserSessionController extends Controller
{
    use DisplaysWeights;
    use EnsuresAppUser;

    public const PER_PAGE = 25;

    /** The status filter's values: every status, plus the Stuck predicate. */
    public const STUCK_FILTER = 'stuck';

    public function index(Request $request, User $user): View
    {
        $this->ensureAppUser($user);

        $filter = $this->statusFilter($request);

        $sessions = $user->workoutSessions()
            ->with('workoutTemplate:id,name')
            ->when($filter === self::STUCK_FILTER, fn (Builder $q) => $q->stuck())
            ->when($filter instanceof WorkoutSessionStatus, fn (Builder $q) => $q->where('status', $filter))
            ->latest('performed_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.users.sessions.index', [
            'user' => $user,
            'sessions' => $sessions,
            'filter' => $filter instanceof WorkoutSessionStatus ? $filter->value : $filter,
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    /**
     * The status filter's choices, labelled: In Progress first with Stuck
     * right after it (a stuck session is the filter's reason to exist), then
     * the terminal statuses.
     *
     * @return array<string, string>
     */
    private function filterOptions(): array
    {
        return [
            WorkoutSessionStatus::Active->value => WorkoutSessionStatus::Active->label(),
            self::STUCK_FILTER => 'Stuck',
            WorkoutSessionStatus::Completed->value => WorkoutSessionStatus::Completed->label(),
            WorkoutSessionStatus::Cancelled->value => WorkoutSessionStatus::Cancelled->label(),
            WorkoutSessionStatus::Draft->value => WorkoutSessionStatus::Draft->label(),
        ];
    }

    /**
     * One session's page: the stored record only — status, timestamps, notes,
     * the exercises in order with their stored targets, and the sets logged
     * against each. Weights are shown in the user's own Unit System
     * (ADR-0001), like everything else on the user's admin pages. Never the
     * derived athlete view, which can legitimately differ between two reads.
     */
    public function show(Request $request, User $user, WorkoutSession $workoutSession): View
    {
        $this->ensureAppUser($user);

        $workoutSession->load([
            'workoutTemplate:id,name',
            'workoutSessionExercises.exercise:id,name',
            'workoutSessionExercises.setLogs',
            'replacedSession.workoutTemplate:id,name',
            'replacedBy.workoutTemplate:id,name',
        ]);

        $units = $user->unitSystem();

        return view('admin.users.sessions.show', [
            'user' => $user,
            'session' => $workoutSession,
            'exercises' => $workoutSession->workoutSessionExercises->map(fn (WorkoutSessionExercise $row) => [
                'name' => $row->exercise?->name ?? 'Exercise #'.$row->exercise_id,
                'target_sets' => $row->target_sets,
                'min_target_reps' => $row->min_target_reps,
                'max_target_reps' => $row->max_target_reps,
                'target_weight' => $this->weight($row->target_weight, 'workout_session_exercises', 'target_weight', $units),
                'rest_seconds' => $row->rest_seconds,
                'sets' => $row->setLogs->map(fn ($set) => [
                    'set_number' => $set->set_number,
                    'reps' => $set->reps,
                    'weight' => $this->weight($set->weight, 'workout_session_set_logs', 'weight', $units),
                ]),
            ]),
        ]);
    }

    /**
     * Cancel a Stuck Session: the one session state an admin may change, and
     * only while it is stuck — the same transition as the athlete's own
     * cancel, recorded as an Admin Change. Confirmed in the browser first and
     * redirected back to the session page with a flash.
     */
    public function cancel(Request $request, User $user, WorkoutSession $workoutSession): RedirectResponse
    {
        $this->ensureAppUser($user);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        if (! $workoutSession->isStuck()) {
            return redirect()
                ->route('admin.users.sessions.show', [$user, $workoutSession])
                ->withErrors(['session' => 'Only a Stuck Session can be cancelled — this one is '.strtolower($workoutSession->status_label).'.']);
        }

        UserChanges::cancelStuckSession($user, $workoutSession, $data['reason'] ?? null, $request->user());

        return redirect()
            ->route('admin.users.sessions.show', [$user, $workoutSession])
            ->with('success', 'Session cancelled.');
    }

    private function statusFilter(Request $request): WorkoutSessionStatus|string|null
    {
        $status = $request->query('status');

        if (! is_string($status)) {
            return null;
        }

        return $status === self::STUCK_FILTER ? $status : WorkoutSessionStatus::tryFrom($status);
    }
}
