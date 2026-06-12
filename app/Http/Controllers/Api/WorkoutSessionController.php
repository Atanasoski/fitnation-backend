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

        $session->load(['workoutSessionExercises.exercise.category', 'setLogs']);

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

        $session->load([
            'workoutSessionExercises.exercise.category',
            'setLogs' => fn ($q) => $q->orderBy('set_number'),
        ]);

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
            $exerciseId = $setLog->exercise_id;
            $deleted = $setLog->set_number;

            $setLog->delete();

            // Shift every later set down by one. Safe as a single bulk UPDATE:
            // there is no unique constraint on set_number.
            SetLog::where('workout_session_id', $session->id)
                ->where('exercise_id', $exerciseId)
                ->where('set_number', '>', $deleted)
                ->decrement('set_number');
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

        $sessionSetLogs = SetLog::query()
            ->where('workout_session_id', $session->id)
            ->with('exercise:id,name')
            ->get();

        $exerciseIds = $sessionSetLogs->pluck('exercise_id')->unique()->values()->all();

        $allTimeBests = collect();

        if ($exerciseIds !== []) {
            $allTimeBests = SetLog::query()
                ->whereIn('exercise_id', $exerciseIds)
                ->whereHas('workoutSession', fn ($q) => $q
                    ->where('user_id', $session->user_id)
                    ->where('status', WorkoutSessionStatus::Completed)
                    ->where('id', '!=', $session->id)
                )
                ->selectRaw('exercise_id, MAX(weight) as best_weight, MAX(reps) as best_reps')
                ->groupBy('exercise_id')
                ->get()
                ->keyBy('exercise_id');
        }

        $newPrs = [];

        foreach ($sessionSetLogs->groupBy('exercise_id') as $exerciseId => $logs) {
            $exerciseId = (int) $exerciseId;
            $sessionMaxWeight = (float) $logs->max(fn (SetLog $log) => (float) $log->weight);
            $sessionMaxReps = (int) $logs->max(fn (SetLog $log) => (int) $log->reps);
            $exerciseName = $logs->first()->exercise?->name ?? '';

            $historic = $allTimeBests->get($exerciseId);
            $histWeight = $historic && $historic->best_weight !== null ? (float) $historic->best_weight : null;
            $histReps = $historic && $historic->best_reps !== null ? (int) $historic->best_reps : null;

            if ($histWeight === null || $sessionMaxWeight > $histWeight) {
                $newPrs[] = [
                    'exercise_id' => (int) $exerciseId,
                    'exercise_name' => $exerciseName,
                    'pr_type' => 'weight',
                    'previous_best' => $histWeight ?? 0,
                    'new_best' => $sessionMaxWeight,
                ];
            }

            if ($histReps === null || $sessionMaxReps > $histReps) {
                $newPrs[] = [
                    'exercise_id' => (int) $exerciseId,
                    'exercise_name' => $exerciseName,
                    'pr_type' => 'reps',
                    'previous_best' => $histReps ?? 0,
                    'new_best' => $sessionMaxReps,
                ];
            }
        }

        $session->load([
            'workoutSessionExercises.exercise.category',
            'setLogs' => fn ($q) => $q->orderBy('set_number'),
        ]);

        return response()->json([
            'data' => new WorkoutSessionResource($session),
            'message' => 'Workout completed! Great job! 💪',
            'new_prs' => $newPrs,
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

        $sessionExercise->load('exercise.category');

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

        // Delete associated set logs
        SetLog::where('workout_session_id', $session->id)
            ->where('exercise_id', $exercise->exercise_id)
            ->delete();

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

        $exercise->load('exercise.category');

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

        $sessionExercise->update(['exercise_id' => $request->validated('exercise_id')]);

        $session->load([
            'workoutSessionExercises.exercise.category',
            'setLogs' => fn ($q) => $q->orderBy('set_number'),
        ]);

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

        $session->load('workoutSessionExercises.exercise.category');

        return response()->json([
            'data' => WorkoutSessionExerciseResource::collection($session->workoutSessionExercises),
            'message' => 'Exercises reordered successfully',
        ]);
    }
}
