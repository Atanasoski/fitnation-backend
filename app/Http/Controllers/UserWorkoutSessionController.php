<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserWorkoutSessionController extends Controller
{
    public function index(Request $request, User $user): View
    {
        $currentUser = $request->user();

        if ($user->partner_id !== $currentUser->partner_id) {
            abort(403, 'Unauthorized.');
        }

        $workoutSessions = WorkoutSession::query()
            ->where('user_id', $user->id)
            ->with('workoutTemplate')
            ->latest('performed_at')
            ->paginate(15);

        return view('users.workout-sessions.index', compact('user', 'workoutSessions'));
    }

    public function show(Request $request, User $user, WorkoutSession $workoutSession): View
    {
        $currentUser = $request->user();

        if ($user->partner_id !== $currentUser->partner_id) {
            abort(403, 'Unauthorized.');
        }

        if ($workoutSession->user_id !== $user->id) {
            abort(404);
        }

        $workoutSession->load(['workoutTemplate', 'workoutSessionExercises.exercise', 'setLogs']);

        $totalSessionVolume = $workoutSession->setLogs->sum(
            fn ($log) => (float) $log->weight * (int) $log->reps
        );
        $totalExercises = $workoutSession->workoutSessionExercises->count();
        $exercisesWithSets = $workoutSession->workoutSessionExercises->filter(
            fn ($e) => $this->setsFor($workoutSession, $e)->isNotEmpty()
        )->count();
        $progressPercent = $totalExercises > 0 ? (int) round(($exercisesWithSets / $totalExercises) * 100) : 0;
        $durationMinutes = null;
        if ($workoutSession->performed_at && $workoutSession->completed_at) {
            $durationMinutes = (int) $workoutSession->performed_at->diffInMinutes($workoutSession->completed_at);
        }

        $exerciseRows = $workoutSession->workoutSessionExercises->map(function ($sessionExercise) use ($workoutSession) {
            $setsForExercise = $this->setsFor($workoutSession, $sessionExercise)
                ->map(fn ($log) => (object) [
                    'set_number' => $log->set_number,
                    'weight' => (float) $log->weight,
                    'reps' => (int) $log->reps,
                    'volume' => (float) $log->weight * (int) $log->reps,
                ]);
            $exerciseVolume = $setsForExercise->sum(fn ($set) => $set->volume);

            return (object) [
                'sessionExercise' => $sessionExercise,
                'setsForExercise' => $setsForExercise,
                'exerciseVolume' => $exerciseVolume,
                'hasSets' => $setsForExercise->isNotEmpty(),
            ];
        });

        return view('users.workout-sessions.show', compact(
            'user',
            'workoutSession',
            'totalSessionVolume',
            'totalExercises',
            'exercisesWithSets',
            'progressPercent',
            'durationMinutes',
            'exerciseRows'
        ));
    }

    /**
     * The sets logged against one session-exercise row, ordered.
     */
    private function setsFor(WorkoutSession $workoutSession, $sessionExercise): \Illuminate\Support\Collection
    {
        return $sessionExercise->ownedSetsFrom($workoutSession->setLogs);
    }
}
