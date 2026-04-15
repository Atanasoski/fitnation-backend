@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
{{-- <div class="container py-4">
    <!-- Welcome Header -->
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="fw-bold mb-1">
                <i class="bi bi-lightning-charge-fill text-warning"></i> Welcome back, {{ Auth::user()->name }}!
            </h2>
            <p class="text-muted">Let's crush your fitness goals today</p>
        </div>
    </div>

    <!-- Stats Cards Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card bg-warning text-white border-0 shadow-sm">
                <div class="card-body d-flex align-items-center p-3">
                    <div class="bg-light bg-opacity-25 rounded p-2 me-3">
                        <i class="bi bi-calendar-check fs-3"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0">{{ $weekWorkouts->count() }}</h4>
                        <small class="opacity-75">Workouts This Week</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card bg-dark text-white border-0 shadow-sm">
                <div class="card-body d-flex align-items-center p-3">
                    <div class="bg-light bg-opacity-25 rounded p-2 me-3">
                        <i class="bi bi-fire fs-3"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0">{{ $streak }}</h4>
                        <small class="opacity-75">Day Streak</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card bg-success text-white border-0 shadow-sm">
                <div class="card-body d-flex align-items-center p-3">
                    <div class="bg-light bg-opacity-25 rounded p-2 me-3">
                        <i class="bi bi-trophy fs-3"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0">{{ $weekWorkouts->sum(function($w) { return $w->exercises->count(); }) }}</h4>
                        <small class="opacity-75">Exercises Planned</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card bg-info text-white border-0 shadow-sm">
                <div class="card-body d-flex align-items-center p-3">
                    <div class="bg-light bg-opacity-25 rounded p-2 me-3">
                        <i class="bi bi-journal-text fs-3"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0">{{ Auth::user()->workoutTemplates->count() }}</h4>
                        <small class="opacity-75">Templates</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Today's Workout -->
        <div class="col-12">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-warning text-white border-0 py-2">
                    <h5 class="mb-0 d-flex align-items-center">
                        <i class="bi bi-calendar-day-fill me-2"></i> Today's Workout
                    </h5>
                </div>
                <div class="card-body p-3">
                    @if($todayWorkout)
                        <div class="workout-card">
                            <h5 class="fw-bold mb-1">{{ $todayWorkout->name }}</h5>
                            <p class="text-muted mb-2 small">{{ $todayWorkout->description }}</p>
                            
                            @if($todayWorkout->exercises->count() > 0)
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <div class="badge bg-light text-dark rounded-pill px-3 py-2">
                                        <i class="bi bi-list-check me-1"></i>
                                        <strong>{{ $todayWorkout->exercises->count() }}</strong> exercises
                                    </div>
                                    <div class="badge bg-light text-dark rounded-pill px-3 py-2">
                                        <i class="bi bi-clock me-1"></i>
                                        ~{{ $todayWorkout->exercises->count() * 5 }} min
                                    </div>
                                </div>
                                
                                <!-- Exercise List -->
                                <div class="mb-3">
                                    <h6 class="fw-bold mb-2 text-muted small">
                                        <i class="bi bi-list-ul"></i> Exercise Plan
                                    </h6>
                                    <div class="exercise-list">
                                        @foreach($todayWorkout->workoutTemplateExercises->sortBy('order') as $index => $templateExercise)
                                            <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background: rgba(0,0,0,0.02);">
                                                <div class="fw-bold" style="min-width: 25px; color: #ff6b35; font-size: 1rem;">{{ $index + 1 }}</div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-semibold">{{ $templateExercise->exercise->name }}</div>
                                                    <small class="text-muted">
                                                        <span class="fw-bold" style="color: #ff6b35;">{{ $templateExercise->target_sets }}×{{ $templateExercise->min_target_reps }}-{{ $templateExercise->max_target_reps }}</span>
                                                        @if($templateExercise->target_weight)
                                                            @ <span class="fw-bold" style="color: #ff6b35;">{{ $templateExercise->target_weight }}kg</span>
                                                        @endif
                                                    </small>
                                                </div>
                                                <span class="badge bg-secondary">{{ $templateExercise->exercise->category->name }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                
                                @if(!$todayWorkoutCompleted)
                                    <form action="{{ route('workouts.start') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="template_id" value="{{ $todayWorkout->id }}">
                                        <x-button variant="success" type="submit" class="w-100 py-2 shadow-sm" icon="bi-play-circle-fill">
                                            Start Workout
                                        </x-button>
                                    </form>
                                @else
                                    <div class="alert alert-success border-0 shadow-sm mb-0 py-2">
                                        <i class="bi bi-check-circle-fill me-2"></i>
                                        <strong>Workout Completed!</strong> Great job today! 💪
                                    </div>
                                @endif
                                
                                @if($recentWorkouts->count() > 0)
                                    <div class="mt-3">
                                        <h6 class="fw-bold mb-2 small">
                                            <i class="bi bi-clock-history text-muted"></i> Recent Workouts
                                        </h6>
                                        @foreach($recentWorkouts as $workout)
                                            <div class="p-2 bg-light border rounded-3 mb-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <p class="mb-1 fw-bold">{{ $workout->workoutTemplate ? $workout->workoutTemplate->name : 'Free Workout' }}</p>
                                                        <small class="text-muted">
                                                            <i class="bi bi-calendar3"></i> {{ $workout->performed_at->diffForHumans() }}
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-success text-white">
                                                            {{ $workout->setLogs->count() }} sets
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <div class="alert alert-warning border-0 mb-3">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    No exercises added yet to this template.
                                </div>
                                <div class="text-muted small">
                                    <i class="bi bi-phone me-1"></i> Manage exercises via the mobile app
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="bi bi-calendar-x fs-1 text-muted mb-2"></i>
                            <h6 class="text-muted mb-2">No workout scheduled for today</h6>
                            <p class="text-muted small mb-3">
                                <i class="bi bi-phone me-1"></i> Create and schedule workouts using the mobile app
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Weekly Overview -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-dark text-white border-0 py-2">
                    <h5 class="mb-0 d-flex align-items-center">
                        <i class="bi bi-calendar-range-fill me-2"></i> This Week's Training Plan
                    </h5>
                </div>
                <div class="card-body p-3">
                    @if($weekWorkouts->count() > 0)
                        <div class="row g-3">
                            @php
                                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            @endphp
                            @foreach($days as $index => $day)
                                @php
                                    $workout = $weekWorkouts->firstWhere('day_of_week', $index);
                                    $isToday = $index === $dayOfWeek;
                                @endphp
                                <div class="col-lg-3 col-md-4 col-sm-6">
                                    @if($workout)
                                        <div class="card h-100 border-2 {{ $isToday ? 'border-warning' : 'border-secondary' }}">
                                            <div class="card-header bg-warning text-white p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                                <div class="fw-bold">{{ $day }}</div>
                                                @if($isToday)
                                                    <span class="badge bg-primary text-white">Today</span>
                                                @endif
                                            </div>
                                            <div class="card-body p-3">
                                                <h6 class="fw-semibold text-dark mb-1">{{ $workout->name }}</h6>
                                                @if($workout->description)
                                                    <p class="text-muted small mb-2">{{ $workout->description }}</p>
                                                @endif
                                                <div class="text-secondary small">
                                                    <i class="bi bi-list-check"></i> {{ $workout->exercises->count() }} exercises
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="card h-100 border-2 {{ $isToday ? 'border-warning' : '' }}">
                                            <div class="card-header bg-secondary text-white p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                                <div class="fw-bold">{{ $day }}</div>
                                                @if($isToday)
                                                    <span class="badge bg-primary text-white">Today</span>
                                                @endif
                                            </div>
                                            <div class="card-body p-3">
                                                <div class="text-secondary fst-italic small">
                                                    <i class="bi bi-moon-stars-fill"></i> Rest & Recovery
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="bi bi-calendar-x fs-1 text-muted mb-2"></i>
                            <h6 class="text-muted mb-2">No workouts planned this week</h6>
                            <p class="text-muted small mb-3">
                                <i class="bi bi-phone me-1"></i> Create and schedule templates using the mobile app
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div> --}}

@endsection
