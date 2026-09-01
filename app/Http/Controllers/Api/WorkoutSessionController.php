<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkoutSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddSessionExerciseRequest;
use App\Http\Requests\LogSetRequest;
use App\Http\Requests\ReorderSessionExercisesRequest;
use App\Http\Requests\StartWorkoutSessionRequest;
use App\Http\Requests\SwapWorkoutSessionExerciseRequest;
use App\Http\Requests\UpdateSessionExerciseRequest;
use App\Http\Requests\UpdateSetRequest;
use App\Http\Requests\WorkoutSessionCalendarRequest;
use App\Http\Resources\Api\PersonalRecordResource;
use App\Http\Resources\Api\SetLogResource;
use App\Http\Resources\Api\WorkoutSessionCalendarResource;
use App\Http\Resources\Api\WorkoutSessionExerciseResource;
use App\Http\Resources\Api\WorkoutSessionResource;
use App\Http\Resources\Api\WorkoutTemplateResource;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutTemplate;
use App\Services\WorkoutSession\PersonalRecords;
use App\Services\WorkoutSession\SetOwnership;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkoutSessionController extends Controller
{
    /**
     * Display workout sessions for the calendar view within a date range.
     */
    public function calendar(WorkoutSessionCalendarRequest $request): JsonResponse
    {
        $startDate = Carbon::createFromFormat('Y-m-d', $request->start_date)->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $request->end_date)->endOfDay();

        $sessions = WorkoutSession::query()
            ->select(['id', 'user_id', 'workout_template_id', 'performed_at', 'completed_at'])
            ->where('user_id', Auth::id())
            ->where('status', WorkoutSessionStatus::Completed)
            ->with('workoutTemplate:id,name')
            ->whereBetween('performed_at', [$startDate, $endDate])
            ->orderBy('performed_at')
            ->get();

        return response()->json([
            'data' => [
                'sessions' => WorkoutSessionCalendarResource::collection($sessions),
                'date_range' => [
                    'start' => $request->start_date,
                    'end' => $request->end_date,
                ],
            ],
        ]);
    }

    /**
     * Get today's workout template and session
     */
    public function today(): JsonResponse
    {
        $today = Carbon::now();
        $dayOfWeek = $today->dayOfWeek === 0 ? 6 : $today->dayOfWeek - 1;

        // Get today's template
        $template = WorkoutTemplate::whereHas('plan', function ($query) {
            $query->where('user_id', Auth::id());
            $query->where('is_active', true);
        })
            ->where('day_of_week', $dayOfWeek)
            ->with(['workoutTemplateExercises.exercise.category', 'exercises.category', 'exercises.muscleGroups', 'exercises.partners'])
            ->first();

        // Check if there's already an active or draft session for today
        $session = WorkoutSession::where('user_id', Auth::id())
            ->whereIn('status', [WorkoutSessionStatus::Draft, WorkoutSessionStatus::Active])
            ->where(function ($query) use ($today) {
                $query->whereDate('performed_at', $today->toDateString())
                    ->orWhereNull('performed_at'); // Draft sessions might not have performed_at
            })
            ->with(['workoutSessionExercises.exercise.category'])
            ->orderByDesc('created_at')
            ->first();

        return response()->json([
            'data' => [
                'template' => $template ? new WorkoutTemplateResource($template) : null,
                'session' => $session ? new WorkoutSessionResource($session) : null,
            ],
        ]);
    }

    /**
     * Start a new workout session
     */
    public function start(StartWorkoutSessionRequest $request): JsonResponse
    {
        $today = Carbon::now();

        // Check if an active session already exists for today
        $session = WorkoutSession::where('user_id', Auth::id())
            ->whereDate('performed_at', $today->toDateString())
            ->where('status', WorkoutSessionStatus::Active)
            ->first();

        if (! $session) {
            $session = DB::transaction(function () use ($request, $today) {
                $newSession = WorkoutSession::create([
                    'user_id' => Auth::id(),
                    'workout_template_id' => $request->template_id,
                    'performed_at' => $today,
                    'status' => WorkoutSessionStatus::Active,
                ]);

                // Snapshot template exercises if template is provided
                if ($request->template_id) {
                    $template = WorkoutTemplate::with('workoutTemplateExercises')->find($request->template_id);

                    if ($template && $template->workoutTemplateExercises->isNotEmpty()) {
                        // Bulk insert instead of individual creates
                        $now = now();
                        $exercisesToInsert = $template->workoutTemplateExercises->map(function ($templateExercise) use ($newSession, $now) {
                            return [
                                'workout_session_id' => $newSession->id,
                                'exercise_id' => $templateExercise->exercise_id,
                                'order' => $templateExercise->order,
                                'target_sets' => $templateExercise->target_sets,
                                'min_target_reps' => $templateExercise->min_target_reps,
                                'max_target_reps' => $templateExercise->max_target_reps,
                                'target_weight' => $templateExercise->target_weight,
                                'rest_seconds' => $templateExercise->rest_seconds,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        })->toArray();

                        WorkoutSessionExercise::insert($exercisesToInsert);
                    }
                }

                return $newSession;
            });
        }

        return response()->json([
            'data' => new WorkoutSessionResource($session),
            'message' => 'Workout session started successfully',
        ], 201);
    }

    /**
     * Show active workout session with exercises and set logs
     */
    public function show(WorkoutSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        return response()->json([
            'data' => new WorkoutSessionResource($session),
        ]);
    }

    /**
     * Log a set
     */
    public function logSet(LogSetRequest $request, WorkoutSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        $setLog = SetLog::create([
            'workout_session_id' => $session->id,
            'workout_session_exercise_id' => $this->resolveSessionExerciseId($request, $session),
            'exercise_id' => $request->exercise_id,
            'set_number' => $request->set_number,
            'weight' => $request->weight,
            'reps' => $request->reps,
            'rest_seconds' => $request->rest_seconds,
        ]);

        return response()->json([
            'data' => new SetLogResource($setLog),
            'message' => 'Set logged successfully',
        ], 201);
    }

    /**
     * Update a set log
     */
    public function updateSet(UpdateSetRequest $request, WorkoutSession $session, SetLog $setLog): JsonResponse
    {
        $this->authorize('update', $session);

        if ($setLog->workout_session_id !== $session->id) {
            abort(403, 'Set log does not belong to this session.');
        }

        $setLog->update([
            'weight' => $request->weight,
            'reps' => $request->reps,
        ]);

        return response()->json([
            'data' => new SetLogResource($setLog),
            'message' => 'Set updated successfully',
        ]);
    }

    /**
     * Delete a set log
     */
    public function deleteSet(WorkoutSession $session, SetLog $setLog): JsonResponse
    {
        $this->authorize('update', $session);

        if ($setLog->workout_session_id !== $session->id) {
            abort(403, 'Set log does not belong to this session.');
        }

        // Delete the set and re-sequence the remaining sets so their set_number
        // stays contiguous (1..N). target_sets is owned by the client, which
        // decrements it via updateSessionExercise, so we don't touch it here.
        DB::transaction(function () use ($session, $setLog) {
            $deleted = $setLog->set_number;

            // A legacy set carries no row id of its own, so ask which row the
            // read path renders it under; otherwise re-sequencing would cover
            // only the other legacy sets and leave the attached ones stranded.
            // Null means no row can claim it, so nothing rendered moved and
            // there is nothing to re-sequence.
            $ownership = SetOwnership::forSession($session);
            $row = $ownership->rowFor($setLog);

            $setLog->delete();

            if ($row === null) {
                return;
            }

            // Shift every later set down by one. Safe as a single bulk UPDATE:
            // there is no unique constraint on set_number. Scoped to exactly
            // the sets the read path renders under this row, so a duplicate
            // row's numbering is untouched and a row holding both attached and
            // legacy sets is renumbered whole.
            $ownership->constrain(
                SetLog::query()->where('set_number', '>', $deleted),
                $row
            )->decrement('set_number');
        });

        return response()->json([
            'message' => 'Set deleted successfully',
        ]);
    }

    /**
     * Complete workout session
     */
    public function complete(Request $request, WorkoutSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $session->update([
            'notes' => $request->notes,
            'completed_at' => Carbon::now(),
            'status' => WorkoutSessionStatus::Completed,
        ]);

        return response()->json([
            'data' => new WorkoutSessionResource($session),
            'message' => 'Workout completed! Great job! 💪',
            'new_prs' => PersonalRecordResource::collection(PersonalRecords::detect($session)),
        ]);
    }

    /**
     * Cancel a workout session
     */
    public function cancel(WorkoutSession $session): JsonResponse
    {
        $this->authorize('delete', $session);

        // Set status to cancelled instead of deleting for tracking purposes
        $session->update([
            'status' => WorkoutSessionStatus::Cancelled,
        ]);

        return response()->json([
            'message' => 'Workout cancelled successfully',
        ]);
    }

    /**
     * Add an exercise to the session
     */
    public function addExercise(AddSessionExerciseRequest $request, WorkoutSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        // Get the exercise to retrieve default values
        $exercise = Exercise::find($request->exercise_id);

        // If no order is specified, add to the end
        $order = $request->order ?? $session->workoutSessionExercises()->max('order') + 1;

        $sessionExercise = $session->workoutSessionExercises()->create([
            'exercise_id' => $request->exercise_id,
            'order' => $order,
            'target_sets' => $request->target_sets ?? 3,
            'min_target_reps' => $request->min_target_reps ?? 8,
            'max_target_reps' => $request->max_target_reps ?? 12,
            'target_weight' => $request->target_weight ?? 0,
            'rest_seconds' => $request->rest_seconds ?? $exercise->default_rest_sec ?? 90,
        ]);

        $sessionExercise->load(WorkoutSessionExercise::EXERCISE_RELATIONS);

        return response()->json([
            'data' => new WorkoutSessionExerciseResource($sessionExercise),
            'message' => 'Exercise added to session successfully',
        ], 201);
    }

    /**
     * Remove an exercise from the session
     */
    public function removeExercise(WorkoutSession $session, WorkoutSessionExercise $exercise): JsonResponse
    {
        $this->authorize('update', $session);

        if ($exercise->workout_session_id !== $session->id) {
            abort(403, 'Exercise does not belong to this session.');
        }

        // Delete only this row's set logs. Scoping by exercise_id would take
        // the sets of any duplicate row carrying the same exercise with it.
        $this->deleteSetLogsFor($session, $exercise);

        // Delete the exercise
        $exercise->delete();

        return response()->json([
            'message' => 'Exercise removed from session successfully',
        ]);
    }

    /**
     * Update exercise targets in the session
     */
    public function updateExercise(UpdateSessionExerciseRequest $request, WorkoutSession $session, WorkoutSessionExercise $exercise): JsonResponse
    {
        $this->authorize('update', $session);

        if ($exercise->workout_session_id !== $session->id) {
            abort(403, 'Exercise does not belong to this session.');
        }

        $exercise->update($request->only([
            'order',
            'target_sets',
            'min_target_reps',
            'max_target_reps',
            'target_weight',
            'rest_seconds',
        ]));

        $exercise->load(WorkoutSessionExercise::EXERCISE_RELATIONS);

        return response()->json([
            'data' => new WorkoutSessionExerciseResource($exercise),
            'message' => 'Exercise updated successfully',
        ]);
    }

    /**
     * Swap the exercise on a session exercise row without touching any other column.
     */
    public function swapExercise(SwapWorkoutSessionExerciseRequest $request, WorkoutSession $session, WorkoutSessionExercise $sessionExercise): JsonResponse
    {
        $this->authorize('update', $session);

        if ($sessionExercise->workout_session_id !== $session->id) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        }

        // The logged sets belong to the exercise being swapped out, not to the
        // one replacing it, so they go with it rather than being re-pointed at
        // a movement they were never performed on.
        DB::transaction(function () use ($session, $sessionExercise, $request) {
            $this->deleteSetLogsFor($session, $sessionExercise);

            $sessionExercise->update(['exercise_id' => $request->validated('exercise_id')]);
        });

        return response()->json([
            'data' => new WorkoutSessionResource($session),
        ]);
    }

    /**
     * Reorder exercises in the session
     */
    public function reorderExercises(ReorderSessionExercisesRequest $request, WorkoutSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        DB::transaction(function () use ($request, $session) {
            foreach ($request->exercise_ids as $order => $exerciseId) {
                WorkoutSessionExercise::where('id', $exerciseId)
                    ->where('workout_session_id', $session->id)
                    ->update(['order' => $order]);
            }
        });

        $session->load(array_map(
            fn (string $relation) => 'workoutSessionExercises.'.$relation,
            WorkoutSessionExercise::EXERCISE_RELATIONS
        ));

        return response()->json([
            // collectionForRows, not ::collection(): the latter leaves every row
            // to resolve its own progression, one history query each.
            'data' => WorkoutSessionExerciseResource::collectionForRows(
                $session->workoutSessionExercises,
                $request->user()
            ),
            'message' => 'Exercises reordered successfully',
        ]);
    }

    /**
     * Which session-exercise row a logged set belongs to.
     *
     * Clients that know the row send it; older builds send only exercise_id and
     * SetOwnership picks the row for them.
     */
    private function resolveSessionExerciseId(LogSetRequest $request, WorkoutSession $session): ?int
    {
        if ($request->workout_session_exercise_id) {
            return (int) $request->workout_session_exercise_id;
        }

        return SetOwnership::forSession($session)
            ->rowForExercise((int) $request->exercise_id)
            ?->id;
    }

    /**
     * Remove the set logs belonging to one session-exercise row.
     */
    private function deleteSetLogsFor(WorkoutSession $session, WorkoutSessionExercise $sessionExercise): void
    {
        SetOwnership::forSession($session)
            ->constrain(SetLog::query(), $sessionExercise)
            ->delete();
    }
}
