<?php

use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\PartnerController as AdminPartnerController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserActionController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPlanController;
use App\Http\Controllers\UserWorkoutSessionController;
use App\Http\Controllers\WeeklySummaryUnsubscribeController;
use App\Http\Controllers\WorkoutPreviewController;
use App\Http\Controllers\WorkoutSplitController;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Smart store redirect — QR codes and shared links point here; sends each device to its store
Route::get('/get', [LandingPageController::class, 'storeRedirect'])->name('app.get');

// One-click unsubscribe from the Weekly Summary email: signed, no login. GET is
// the link in the footer; POST is what mail clients send for List-Unsubscribe-Post.
Route::match(['get', 'post'], '/email/weekly-summary/unsubscribe/{user}', WeeklySummaryUnsubscribeController::class)
    ->middleware('signed')
    ->name('email.weekly-summary.unsubscribe');

Route::middleware(['auth', 'verified'])->group(function () {

    // Super-admin panel: everything under /admin is for the admin role only.
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/', [OverviewController::class, 'index'])->name('admin.overview');
        Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->whereNumber('user')->withTrashed()->name('admin.users.show');
        Route::post('/users/{user}/complimentary-access', [UserActionController::class, 'grantComplimentaryAccess'])->whereNumber('user')->name('admin.users.complimentary-access.store');
        Route::delete('/users/{user}/complimentary-access', [UserActionController::class, 'endComplimentaryAccess'])->whereNumber('user')->name('admin.users.complimentary-access.destroy');
        Route::patch('/users/{user}/partner', [UserActionController::class, 'changePartner'])->whereNumber('user')->name('admin.users.partner.update');
        Route::post('/users/{user}/verification', [UserActionController::class, 'resendVerification'])->whereNumber('user')->name('admin.users.verification.send');
        Route::delete('/users/{user}', [UserActionController::class, 'deactivate'])->whereNumber('user')->name('admin.users.destroy');
        Route::post('/users/{user}/restore', [UserActionController::class, 'restore'])->whereNumber('user')->withTrashed()->name('admin.users.restore');
        Route::get('/partners', [AdminPartnerController::class, 'index'])->name('admin.partners.index');
        Route::get('/partners/{partner}', [AdminPartnerController::class, 'show'])->name('admin.partners.show');
        Route::patch('/partners/{partner}/active', [AdminPartnerController::class, 'updateActive'])->name('admin.partners.active.update');
        Route::get('/search', SearchController::class)->name('admin.search');
        Route::view('/insights', 'admin.insights')->name('admin.insights');
        Route::get('/system', [SystemController::class, 'index'])->name('admin.system');
        Route::post('/system/failed-jobs/{id}/retry', [SystemController::class, 'retryJob'])->name('admin.system.failed-jobs.retry');
        Route::delete('/system/failed-jobs/{id}', [SystemController::class, 'forgetJob'])->name('admin.system.failed-jobs.destroy');
        Route::post('/system/webhooks/replay', [SystemController::class, 'replayAllWebhooks'])->name('admin.system.webhooks.replay-all');
        Route::post('/system/webhooks/{id}/replay', [SystemController::class, 'replayWebhook'])->whereNumber('id')->name('admin.system.webhooks.replay');

        // Content: exercise library, workout splits and the generator preview
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
    Route::get('/users/{user}/workout-sessions', [UserWorkoutSessionController::class, 'index'])->name('users.workout-sessions.index');
    Route::get('/users/{user}/workout-sessions/{workoutSession}', [UserWorkoutSessionController::class, 'show'])->name('users.workout-sessions.show');

    // Partner Programs Management (Library) – CRUD methods
    Route::prefix('partner')->group(function () {
        Route::get('/programs', [PlanController::class, 'index'])->name('partner.programs.index');
        Route::get('/programs/create', [PlanController::class, 'create'])->name('partner.programs.create');
        Route::post('/programs', [PlanController::class, 'store'])->name('partner.programs.store');
        Route::get('/programs/{plan}', [PlanController::class, 'show'])->can('manage', 'plan')->name('partner.programs.show');
        Route::get('/programs/{plan}/edit', [PlanController::class, 'edit'])->can('manage', 'plan')->name('partner.programs.edit');
        Route::put('/programs/{plan}', [PlanController::class, 'update'])->can('manage', 'plan')->name('partner.programs.update');
        Route::delete('/programs/{plan}', [PlanController::class, 'destroy'])->can('manage', 'plan')->name('partner.programs.destroy');
    });

    // A user's plans: the plan outline (023/06), shared by super admins and
    // partner admins. Every plan, workout and workout-exercise route is
    // guarded by PlanPolicy.
    Route::get('/users/{user}/plans', [UserPlanController::class, 'index'])->can('manageFor', [Plan::class, 'user'])->name('plans.index');
    Route::get('/users/{user}/plans/create', [PlanController::class, 'userPlanCreate'])->can('manageFor', [Plan::class, 'user'])->name('plans.create');
    Route::post('/users/{user}/plans', [UserPlanController::class, 'store'])->can('manageFor', [Plan::class, 'user'])->name('plans.store');
    Route::get('/plans/{plan}', [PlanController::class, 'userPlanShow'])->can('manage', 'plan')->name('plans.show');
    Route::get('/plans/{plan}/edit', [PlanController::class, 'userPlanEdit'])->can('manage', 'plan')->name('plans.edit');
    Route::put('/plans/{plan}', [UserPlanController::class, 'update'])->can('manage', 'plan')->name('plans.update');
    Route::post('/plans/{plan}/activate', [UserPlanController::class, 'activate'])->can('manage', 'plan')->name('plans.activate');
    Route::delete('/plans/{plan}', [UserPlanController::class, 'destroy'])->can('manage', 'plan')->name('plans.destroy');

    // Workout Templates Management
    Route::get('/plans/{plan}/workouts/create', [\App\Http\Controllers\WorkoutTemplateController::class, 'create'])->can('manage', 'plan')->name('workouts.create');
    Route::post('/plans/{plan}/workouts', [\App\Http\Controllers\WorkoutTemplateController::class, 'store'])->can('manage', 'plan')->name('workouts.store');
    Route::get('/workouts/{workoutTemplate}', [\App\Http\Controllers\WorkoutTemplateController::class, 'show'])->can('manage', 'workoutTemplate')->name('workouts.show');
    Route::get('/workouts/{workoutTemplate}/edit', [\App\Http\Controllers\WorkoutTemplateController::class, 'edit'])->can('manage', 'workoutTemplate')->name('workouts.edit');
    Route::put('/workouts/{workoutTemplate}', [\App\Http\Controllers\WorkoutTemplateController::class, 'update'])->can('manage', 'workoutTemplate')->name('workouts.update');
    Route::delete('/workouts/{workoutTemplate}', [\App\Http\Controllers\WorkoutTemplateController::class, 'destroy'])->can('manage', 'workoutTemplate')->name('workouts.destroy');

    // Workout Template Exercises Management
    Route::get('/workouts/{workoutTemplate}/exercises/create', [\App\Http\Controllers\WorkoutTemplateExerciseController::class, 'create'])->can('manage', 'workoutTemplate')->name('workout-exercises.create');
    Route::post('/workouts/{workoutTemplate}/exercises', [\App\Http\Controllers\WorkoutTemplateExerciseController::class, 'store'])->can('manage', 'workoutTemplate')->name('workout-exercises.store');
    Route::get('/workouts/{workoutTemplate}/exercises/{workoutTemplateExercise}/edit', [\App\Http\Controllers\WorkoutTemplateExerciseController::class, 'edit'])->can('manage', 'workoutTemplate')->name('workout-exercises.edit');
    Route::put('/workouts/{workoutTemplate}/exercises/{workoutTemplateExercise}', [\App\Http\Controllers\WorkoutTemplateExerciseController::class, 'update'])->can('manage', 'workoutTemplate')->name('workout-exercises.update');
    Route::delete('/workouts/{workoutTemplate}/exercises/{workoutTemplateExercise}', [\App\Http\Controllers\WorkoutTemplateExerciseController::class, 'destroy'])->can('manage', 'workoutTemplate')->name('workout-exercises.destroy');

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

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
