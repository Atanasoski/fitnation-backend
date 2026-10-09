<?php

namespace App\Http\Controllers;

use App\Enums\SplitFocus;
use App\Http\Requests\StoreWorkoutSplitRequest;
use App\Http\Requests\UpdateWorkoutSplitRequest;
use App\Models\TargetRegion;
use App\Models\WorkoutSplit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WorkoutSplitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        // Group splits by focus and days_per_week
        $splits = WorkoutSplit::query()
            ->orderBy('focus')
            ->orderBy('days_per_week')
            ->orderBy('day_index')
            ->get()
            ->groupBy(['focus', 'days_per_week']);

        $targetRegions = TargetRegion::orderBy('display_order')->get();

        return view('workout-splits.index', compact('splits', 'targetRegions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $targetRegions = TargetRegion::orderBy('display_order')->get();
        $focusOptions = SplitFocus::cases();

        return view('workout-splits.create', compact('targetRegions', 'focusOptions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWorkoutSplitRequest $request): RedirectResponse
    {
        WorkoutSplit::create([
            'days_per_week' => $request->days_per_week,
            'focus' => $request->focus,
            'day_index' => $request->day_index,
            'target_regions' => $request->target_regions,
        ]);

        return redirect()->route('workout-splits.index')
            ->with('success', 'Workout split created successfully!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(WorkoutSplit $workoutSplit): View
    {
        $targetRegions = TargetRegion::orderBy('display_order')->get();
        $focusOptions = SplitFocus::cases();

        return view('workout-splits.edit', compact('workoutSplit', 'targetRegions', 'focusOptions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWorkoutSplitRequest $request, WorkoutSplit $workoutSplit): RedirectResponse
    {
        $workoutSplit->update([
            'days_per_week' => $request->days_per_week,
            'focus' => $request->focus,
            'day_index' => $request->day_index,
            'target_regions' => $request->target_regions,
        ]);

        return redirect()->route('workout-splits.index')
            ->with('success', 'Workout split updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WorkoutSplit $workoutSplit): RedirectResponse
    {
        $workoutSplit->delete();

        return redirect()->route('workout-splits.index')
            ->with('success', 'Workout split deleted successfully!');
    }
}
