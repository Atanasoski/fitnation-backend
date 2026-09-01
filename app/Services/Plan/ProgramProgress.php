<?php

namespace App\Services\Plan;

use App\Enums\WorkoutSessionStatus;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Illuminate\Support\Collection;

/**
 * A program resolved for the athlete working through it: how far in they are,
 * which workout comes next, which week that lands in, and when they last
 * completed each workout.
 *
 * All four answers come from one question — which of this program's templates
 * does the user have a completed session against — so the module asks it once
 * and derives everything from the answer in memory. The shape it replaces asked
 * it once per template per answer: Plan::nextWorkout(),
 * Plan::getProgressPercentage() and Plan::getCurrentActiveWeek() each looped the
 * templates issuing an `exists()`, getCurrentActiveWeek() called nextWorkout()
 * again, and WorkoutTemplateResource added a query per template on top. One
 * serialized program cost 3n + 2 queries; a list of programs multiplied that by
 * its length.
 *
 * The completions are one query regardless of how many templates the program
 * has, which is the property that matters: the cost no longer grows with the
 * program's length, or with the length of a list of programs.
 *
 * The templates themselves come from the plan's already-loaded
 * workoutTemplates when it has them, so a caller that eager-loaded for its own
 * payload is not charged a second read. Two consequences worth knowing:
 *
 * - A caller that has not loaded them pays a read here, with the exercise
 *   relations the next workout is serialized with. Only the next-workout
 *   endpoint is in that position, and it serializes those exercises.
 * - percentComplete() counts the templates it was handed. A caller that
 *   eager-loads workoutTemplates through a *constrained* closure therefore
 *   changes the denominator. No caller does today, and every one of them
 *   serializes the same set it measures, so the alternative — a separate
 *   count() that can disagree with the payload beside it, which is what this
 *   replaced — is the worse failure.
 */
final class ProgramProgress
{
    /**
     * What a workout template needs loaded to be serialized as the next
     * workout — the same relations Plan::nextWorkout() used to load.
     */
    private const EXERCISE_RELATIONS = ['exercises.category', 'exercises.partners'];

    /**
     * @param  Collection<int, WorkoutTemplate>  $templates  in program order
     * @param  array<int, int>  $completedSessions  template id => most recent completed session id
     */
    private function __construct(
        private Plan $plan,
        private Collection $templates,
        private array $completedSessions,
    ) {}

    /**
     * A null user has completed nothing, and costs no query: the resource layer
     * serializes programs for unauthenticated readers too.
     */
    public static function for(Plan $plan, ?User $user): self
    {
        $templates = self::resolveTemplates($plan);

        return new self($plan, $templates, self::completedSessions($templates, $user));
    }

    /**
     * The first workout the user has not completed, or null once the program is
     * finished. Null for a plan that is not a program.
     */
    public function nextWorkout(): ?WorkoutTemplate
    {
        if (! $this->plan->isProgram()) {
            return null;
        }

        return $this->templates->first(
            fn (WorkoutTemplate $template) => ! $this->isCompleted($template)
        );
    }

    /**
     * Share of the program's workouts completed, 0–100 to two decimals. Null
     * for a plan that is not a program; zero for a program with no workouts.
     */
    public function percentComplete(): ?float
    {
        if (! $this->plan->isProgram()) {
            return null;
        }

        $total = $this->templates->count();

        if ($total === 0) {
            return 0.0;
        }

        $completed = $this->templates
            ->filter(fn (WorkoutTemplate $template) => $this->isCompleted($template))
            ->count();

        return round(($completed / $total) * 100, 2);
    }

    /**
     * The week the next workout falls in. Once every workout is done, the last
     * week that has one; failing that the program's declared length, or 1.
     */
    public function currentWeek(): ?int
    {
        if (! $this->plan->isProgram()) {
            return null;
        }

        $next = $this->nextWorkout();

        if ($next) {
            return $next->week_number;
        }

        return $this->templates->max('week_number')
            ?: ($this->plan->duration_weeks ?? 1);
    }

    /**
     * The user's most recently completed session against this template, or null
     * if they have never completed it.
     */
    public function lastCompletedSessionId(WorkoutTemplate $template): ?int
    {
        return $this->completedSessions[$template->id] ?? null;
    }

    /**
     * The same answer for a caller that holds one template rather than a whole
     * program — WorkoutTemplateResource serialized on its own. Here so that
     * "most recently completed" is defined once; a set of templates should go
     * through for() instead, which answers all of them in the same query.
     */
    public static function lastCompletedSessionIdFor(WorkoutTemplate $template, User $user): ?int
    {
        return self::completedSessions(collect([$template]), $user)[$template->id] ?? null;
    }

    private function isCompleted(WorkoutTemplate $template): bool
    {
        return isset($this->completedSessions[$template->id]);
    }

    /**
     * @return Collection<int, WorkoutTemplate>
     */
    private static function resolveTemplates(Plan $plan): Collection
    {
        $templates = $plan->relationLoaded('workoutTemplates')
            ? $plan->workoutTemplates
            : $plan->workoutTemplates()->with(self::EXERCISE_RELATIONS)->get();

        // Sorted here rather than trusted from the caller: an eager load that
        // forgot orderedByProgram() would otherwise silently hand back the
        // wrong "next" workout.
        return $templates
            ->sortBy([['week_number', 'asc'], ['order_index', 'asc']])
            ->values();
    }

    /**
     * The one query. Ordered oldest-first so that keying by template id leaves
     * the most recent session as the surviving value.
     *
     * @param  Collection<int, WorkoutTemplate>  $templates
     * @return array<int, int>
     */
    private static function completedSessions(Collection $templates, ?User $user): array
    {
        if ($user === null || $templates->isEmpty()) {
            return [];
        }

        return WorkoutSession::query()
            ->where('user_id', $user->id)
            ->where('status', WorkoutSessionStatus::Completed)
            ->whereIn('workout_template_id', $templates->pluck('id'))
            ->orderBy('completed_at')
            ->orderBy('id')
            ->pluck('id', 'workout_template_id')
            ->all();
    }
}
