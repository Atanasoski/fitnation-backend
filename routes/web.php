<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserWorkoutSessionController;
use App\Http\Controllers\WorkoutPreviewController;
use App\Http\Controllers\WorkoutSplitController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // Exercise Library - Admin routes (prefixed with /admin, no conflict with API routes since API routes use 'api.' prefix)
    Route::prefix('admin')->group(function () {
        Route::get('/exercises', [ExerciseController::class, 'index'])->name('exercises.index');
        Route::get('/exercises/create', [ExerciseController::class, 'adminCreate'])->name('exercises.create');
        Route::get('/exercises/{exercise}', [ExerciseController::class, 'adminShow'])->name('exercises.show');
        Route::get('/exercises/{exercise}/edit', [ExerciseController::class, 'adminEdit'])->name('exercises.edit');
        Route::post('/exercises', [ExerciseController::class, 'store'])->name('exercises.store');
        Route::put('/exercises/{exercise}', [ExerciseController::class, 'update'])->name('exercises.update');
        Route::delete('/exercises/{exercise}', [ExerciseController::class, 'destroy'])->name('exercises.destroy');
        Route::post('/exercises/{exercise}/update-muscle-group-image', [ExerciseController::class, 'updateMuscleGroupImage'])->name('exercises.updateMuscleGroupImage');

        Route::resource('workout-splits', WorkoutSplitController::class)
            ->names([
                'index' => 'workout-splits.index',
                'create' => 'workout-splits.create',
                'store' => 'workout-splits.store',
                'edit' => 'workout-splits.edit',
                'update' => 'workout-splits.update',
                'destroy' => 'workout-splits.destroy',
            ]);

        Route::get('/workout-preview', [WorkoutPreviewController::class, 'index'])->name('workout-preview.index');
        Route::post('/workout-preview', [WorkoutPreviewController::class, 'preview'])->name('workout-preview.preview');
    });

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Partner Management
    Route::resource('partners', \App\Http\Controllers\PartnerController::class);

    // Users Management
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/subscriptions', [SubscriptionController::class, 'indexForUser'])->name('users.subscriptions.index');
    Route::get('/users/{user}/workout-sessions', [UserWorkoutSessionController::class, 'index'])->name('users.workout-sessions.index');
    Route::get('/users/{user}/workout-sessions/{workoutSession}', [UserWorkoutSessionController::class, 'show'])->name('users.workout-sessions.show');

    // Partner Programs Management (Library) – CRUD methods
    Route::prefix('partner')->group(function () {
        Route::get('/programs', [PlanController::class, 'index'])->name('partner.programs.index');
        Route::get('/programs/create', [PlanController::class, 'create'])->name('partner.programs.create');
        Route::post('/programs', [PlanController::class, 'store'])->name('partner.programs.store');
        Route::get('/programs/{plan}', [PlanController::class, 'show'])->name('partner.programs.show');
        Route::get('/programs/{plan}/edit', [PlanController::class, 'edit'])->name('partner.programs.edit');
        Route::put('/programs/{plan}', [PlanController::class, 'update'])->name('partner.programs.update');
        Route::delete('/programs/{plan}', [PlanController::class, 'destroy'])->name('partner.programs.destroy');
    });

    // Plans Management (user flow: plan for a specific user) – userPlan* methods
    Route::get('/users/{user}/plans', [PlanController::class, 'userPlanIndex'])->name('plans.index');
    Route::get('/users/{user}/plans/create', [PlanController::class, 'userPlanCreate'])->name('plans.create');
    Route::post('/users/{user}/plans', [PlanController::class, 'userPlanStore'])->name('plans.store');
    Route::get('/plans/{plan}', [PlanController::class, 'userPlanShow'])->name('plans.show');
    Route::get('/plans/{plan}/edit', [PlanController::class, 'userPlanEdit'])->name('plans.edit');
    Route::put('/plans/{plan}', [PlanController::class, 'userPlanUpdate'])->name('plans.update');
    Route::delete('/plans/{plan}', [PlanController::class, 'userPlanDestroy'])->name('plans.destroy');

    // Workout Templates Management
    Route::get('/plans/{plan}/workouts/create', [\App\Http\Controllers\WorkoutTemplateController::class, 'create'])->name('workouts.create');
    Route::post('/plans/{plan}/workouts', [\App\Http\Controllers\WorkoutTemplateController::class, 'store'])->name('workouts.store');
    Route::get('/workouts/{workoutTemplate}', [\App\Http\Controllers\WorkoutTemplateController::class, 'show'])->name('workouts.show');
    Route::get('/workouts/{workoutTemplate}/edit', [\App\Http\Controllers\WorkoutTemplateController::class, 'edit'])->name('workouts.edit');
    Route::put('/workouts/{workoutTemplate}', [\App\Http\Controllers\WorkoutTemplateController::class, 'update'])->name('workouts.update');
    Route::delete('/workouts/{workoutTemplate}', [\App\Http\Controllers\WorkoutTemplateController::class, 'destroy'])->name('workouts.destroy');

    // Workout Template Exercises Management
    Route::get('/workouts/{workoutTemplate}/exercises/create', [\App\Http\Controllers\WorkoutTemplateExerciseController::class, 'create'])->name('workout-exercises.create');
    Route::post('/workouts/{workoutTemplate}/exercises', [\App\Http\Controllers\WorkoutTemplateExerciseController::class, 'store'])->name('workout-exercises.store');
    Route::get('/workouts/{workoutTemplate}/exercises/{workoutTemplateExercise}/edit', [\App\Http\Controllers\WorkoutTemplateExerciseController::class, 'edit'])->name('workout-exercises.edit');
    Route::put('/workouts/{workoutTemplate}/exercises/{workoutTemplateExercise}', [\App\Http\Controllers\WorkoutTemplateExerciseController::class, 'update'])->name('workout-exercises.update');
    Route::delete('/workouts/{workoutTemplate}/exercises/{workoutTemplateExercise}', [\App\Http\Controllers\WorkoutTemplateExerciseController::class, 'destroy'])->name('workout-exercises.destroy');

    // User Invitations Management
    Route::get('/user-invitations', [UserController::class, 'invitationsIndex'])->name('user-invitations.index');
    Route::post('/user-invitations/invite', [UserController::class, 'invitationsStore'])->name('user-invitations.invite');
    Route::post('/user-invitations/{invitation}/resend', [UserController::class, 'invitationsResend'])->name('user-invitations.resend');
    Route::delete('/user-invitations/{invitation}', [UserController::class, 'invitationsDestroy'])->name('user-invitations.destroy');

    // Exercise Library - Partner routes
    Route::get('/partner/exercises', [ExerciseController::class, 'partnerIndex'])->name('partner.exercises.index');
    Route::get('/partner/exercises/{exercise}', [ExerciseController::class, 'show'])->name('partner.exercises.show');
    Route::get('/partner/exercises/{exercise}/edit', [ExerciseController::class, 'edit'])->name('partner.exercises.edit');
    Route::put('/exercises/{exercise}/partner', [ExerciseController::class, 'updatePartnerExercises'])->name('exercises.updatePartner');
    Route::post('/partner/exercises/bulk-link', [ExerciseController::class, 'bulkLink'])->name('partner.exercises.bulkLink');
    Route::post('/exercises/{exercise}/link', [ExerciseController::class, 'linkExercise'])->name('exercises.link');
    Route::post('/exercises/{exercise}/unlink', [ExerciseController::class, 'unlinkExercise'])->name('exercises.unlink');

    Route::resource('subscription-plans', SubscriptionPlanController::class);
    Route::get('subscriptions/create', [SubscriptionController::class, 'create'])->name('subscriptions.create');
    Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
    Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
