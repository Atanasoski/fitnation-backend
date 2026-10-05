<?php

namespace App\Http\Controllers;

use App\Enums\PlanType;
use App\Http\Requests\UserPlanRequest;
use App\Models\Plan;
use App\Models\User;
use App\Services\MeasuredFields;
use App\Services\Plan\PlanActivation;
use App\Services\Plan\PlanOutline;
use App\Services\PlanFileService;
use App\Services\UnitConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A user's plan outline (023/06, 023/07), shared by super admins and partner admins:
 * every route is guarded by PlanPolicy. Each write redirects back to the
 * outline with the plan it touched selected.
 */
class UserPlanController extends Controller
{
    public function __construct(
        private PlanFileService $planFileService,
        private UnitConversionService $units,
    ) {}

    public function index(Request $request, User $user): View
    {
        $outline = PlanOutline::for(
            $user,
            $request->integer('plan') ?: null,
            $request->integer('workout') ?: null,
            $request->integer('row') ?: null,
        );
        $creating = PlanType::tryFrom((string) $request->query('create'));
        $adding = $creating || $outline->plan === null ? null : match ($request->query('add')) {
            'workout' => 'workout',
            'exercise' => $outline->workout ? 'exercise' : null,
            default => null,
        };

        $back = $request->user()->hasRole('admin')
            ? route('admin.users.show', $user)
            : route('users.show', $user);

        return view('plans.outline', [
            'outline' => $outline,
            'user' => $user,
            'creating' => $creating,
            'adding' => $adding,
            'replaces' => $creating ? null : $outline->replaces(),
            // The picker, for adding a row or swapping one's exercise.
            'offered' => $adding === 'exercise' || $outline->row
                ? $outline->plan->offeredExercises()->with('equipmentType')->orderBy('name')->get()
                : collect(),
            'rowWeight' => $outline->row
                ? $this->units->toDisplay($outline->row->target_weight, MeasuredFields::kindFor('workout_template_exercises', 'target_weight'), $outline->plan->ownerUnitSystem())
                : null,
            'units' => $user->unitSystem(),
            'back' => $back,
        ]);
    }

    /**
     * The old plan pages (create, show, edit) are the outline now; their
     * links redirect to the matching node. A library plan keeps its pages.
     */
    public function create(User $user): RedirectResponse
    {
        return redirect()->route('plans.index', ['user' => $user, 'create' => PlanType::Program->value]);
    }

    public function show(Plan $plan): RedirectResponse
    {
        return $plan->user_id === null
            ? redirect()->route('partner.programs.show', $plan)
            : redirect(PlanOutline::url($plan));
    }

    public function edit(Plan $plan): RedirectResponse
    {
        return $plan->user_id === null
            ? redirect()->route('partner.programs.edit', $plan)
            : redirect(PlanOutline::url($plan));
    }

    /**
     * A new Program or Routine starts inactive; activating it is its own step.
     */
    public function store(UserPlanRequest $request, User $user): RedirectResponse
    {
        $attributes = $request->planAttributes() + [
            'user_id' => $user->id,
            'partner_id' => null,
            'is_active' => false,
        ];
        if ($request->hasFile('cover_image')) {
            $attributes['cover_image'] = $this->planFileService->storeCoverImage($request->file('cover_image'), null);
        }

        $plan = Plan::create($attributes);

        return $this->toOutline($plan, ucfirst($plan->type->value).' created.');
    }

    public function update(UserPlanRequest $request, Plan $plan): RedirectResponse
    {
        abort_if($plan->user_id === null, 404);

        $attributes = $request->planAttributes();
        if ($request->hasFile('cover_image')) {
            $this->planFileService->deleteCoverImage($plan->cover_image);
            $attributes['cover_image'] = $this->planFileService->storeCoverImage($request->file('cover_image'), null);
        }
        $plan->update($attributes);

        // Absent is_active still re-enters activation for an active plan, so a
        // type change cannot leave two active plans of one type (ADR-0002).
        PlanActivation::apply($plan, $request->validated('is_active'));

        return $this->toOutline($plan, 'Plan saved.');
    }

    public function activate(Plan $plan): RedirectResponse
    {
        abort_if($plan->user_id === null, 404);

        PlanActivation::activate($plan);

        return $this->toOutline($plan, "{$plan->name} is now the active ".$plan->type->value.'.');
    }

    /**
     * Workouts and rows cascade; logged sessions keep their history with no
     * template.
     */
    public function destroy(Plan $plan): RedirectResponse
    {
        abort_if($plan->user_id === null, 404);

        $user = $plan->user;
        $plan->delete();

        return redirect()->route('plans.index', $user)
            ->with('success', "{$plan->name} deleted. Logged sessions are kept.");
    }

    private function toOutline(Plan $plan, string $message): RedirectResponse
    {
        return redirect()->route('plans.index', ['user' => $plan->user_id, 'plan' => $plan->id])
            ->with('success', $message);
    }
}
