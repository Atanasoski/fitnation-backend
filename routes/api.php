<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ExerciseClassificationController;
use App\Http\Controllers\Api\ExerciseController;
use App\Http\Controllers\Api\FitnessMetricsController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MuscleGroupController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkoutGeneratorController;
use App\Http\Controllers\Api\WorkoutPlannerController;
use App\Http\Controllers\Api\WorkoutSessionController;
use App\Http\Controllers\Api\WorkoutTemplateController;
use Illuminate\Support\Facades\Route;

// Public authentication endpoints
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/social', [SocialAuthController::class, 'authenticate'])
    ->middleware('throttle:10,1');
Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])
    ->middleware('throttle:6,1');
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
    ->middleware('throttle:6,1');

// Public invitation validation
Route::get('/invitations/{token}', [InvitationController::class, 'show']);

Route::get('/partners', [PartnerController::class, 'activeList'])
    ->middleware('throttle:30,1');

Route::get('/partners/{partner}/branding', [PartnerController::class, 'branding'])
    ->middleware('throttle:6,1');

// Protected endpoints
Route::middleware('auth:sanctum')->name('api.')->group(function () {
    // Auth endpoints
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:6,1')
        ->name('api.verification.send');

    // User endpoints
    Route::get('/user', [UserController::class, 'show']);
    Route::delete('/user', [UserController::class, 'destroy']);
    Route::get('/user/fitness-metrics', [FitnessMetricsController::class, 'index']);

    // Profile endpoints
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto']);

    // Onboarding endpoints
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete']);

    // Exercises CRUD
    Route::apiResource('exercises', ExerciseController::class);
    Route::get('/exercises/{exercise}/history', [ExerciseController::class, 'history']);

    // Muscle Groups (read-only)
    Route::get('/muscle-groups', [MuscleGroupController::class, 'index']);
    Route::get('/muscle-groups/{muscleGroup}', [MuscleGroupController::class, 'show']);

    // Categories (read-only)
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);

    // Exercise Classification Lookup Tables (read-only)
    Route::get('/movement-patterns', [ExerciseClassificationController::class, 'movementPatterns']);
    Route::get('/target-regions', [ExerciseClassificationController::class, 'targetRegions']);
    Route::get('/equipment-types', [ExerciseClassificationController::class, 'equipmentTypes']);
    Route::get('/angles', [ExerciseClassificationController::class, 'angles']);

    Route::prefix('plans')->group(function () {
        Route::get('/', [PlanController::class, 'index']);
        Route::post('/', [PlanController::class, 'store']);
        Route::post('/regenerate', [PlanController::class, 'regenerate']);
        Route::get('/{plan}', [PlanController::class, 'show']);
        Route::put('/{plan}', [PlanController::class, 'update']);
        Route::delete('/{plan}', [PlanController::class, 'destroy']);
    });

    // Custom Plans API - User can create/manage their own
    Route::prefix('custom-plans')->group(function () {
        Route::get('/', [PlanController::class, 'customPlansIndex']);
        Route::post('/', [PlanController::class, 'customPlansStore']);
        Route::get('/{customPlan}', [PlanController::class, 'customPlansShow']);
        Route::put('/{customPlan}', [PlanController::class, 'customPlansUpdate']);
        Route::delete('/{customPlan}', [PlanController::class, 'customPlansDestroy']);
    });

    // Programs API - User clones from partner library
    Route::prefix('programs')->group(function () {
        Route::get('/', [PlanController::class, 'programsIndex']);
        Route::get('/library', [PlanController::class, 'programsLibrary']);
        Route::get('/active', [PlanController::class, 'activeProgram']);
        Route::get('/{program}', [PlanController::class, 'programsShow']);
        Route::patch('/{program}', [PlanController::class, 'programsUpdate']);
        Route::delete('/{program}', [PlanController::class, 'programsDestroy']);
        Route::post('/{program}/clone', [PlanController::class, 'programsClone']);
        Route::get('/{program}/next-workout', [PlanController::class, 'programsNextWorkout']);
    });

    // Routines API - Partner-provided browsable repeatable workouts
    Route::prefix('routines')->group(function () {
        Route::get('/', [PlanController::class, 'routinesIndex']);
        Route::get('/{routine}', [PlanController::class, 'routinesShow']);
    });

    // Workout Templates CRUD
    Route::apiResource('workout-templates', WorkoutTemplateController::class);

    // Workout Template Exercise Management
    Route::post('/workout-templates/{workoutTemplate}/exercises', [WorkoutTemplateController::class, 'addExercise']);
    Route::delete('/workout-templates/{workoutTemplate}/exercises/{exercise}', [WorkoutTemplateController::class, 'removeExercise']);
    Route::put('/workout-templates/{workoutTemplate}/exercises/{exercise}', [WorkoutTemplateController::class, 'updateExercise']);
    Route::patch('/workout-templates/{workoutTemplate}/exercises/{pivot}/swap', [WorkoutTemplateController::class, 'swapExercise']);
    Route::post('/workout-templates/{workoutTemplate}/order', [WorkoutTemplateController::class, 'updateOrder']);

    // Weekly Workout Planner
    Route::get('/planner/weekly', [WorkoutPlannerController::class, 'index']);
    Route::post('/planner/assign', [WorkoutPlannerController::class, 'assign']);
    Route::post('/planner/unassign', [WorkoutPlannerController::class, 'unassign']);

    // Workout Sessions
    Route::get('/workout-sessions/calendar', [WorkoutSessionController::class, 'calendar']);
    Route::get('/workout-sessions/today', [WorkoutSessionController::class, 'today']);
    Route::post('/workout-sessions/start', [WorkoutSessionController::class, 'start']);
    Route::post('/workout-sessions/generate', [WorkoutGeneratorController::class, 'generate']);
    Route::post('/workout-sessions/{session}/confirm', [WorkoutGeneratorController::class, 'confirm']);
    Route::post('/workout-sessions/{session}/regenerate', [WorkoutGeneratorController::class, 'regenerate']);
    Route::get('/workout-sessions/{session}', [WorkoutSessionController::class, 'show']);
    Route::post('/workout-sessions/{session}/complete', [WorkoutSessionController::class, 'complete']);
    Route::delete('/workout-sessions/{session}/cancel', [WorkoutSessionController::class, 'cancel']);

    // Workout Session Set Logs
    Route::post('/workout-sessions/{session}/sets', [WorkoutSessionController::class, 'logSet']);
    Route::put('/workout-sessions/{session}/sets/{setLog}', [WorkoutSessionController::class, 'updateSet']);
    Route::delete('/workout-sessions/{session}/sets/{setLog}', [WorkoutSessionController::class, 'deleteSet']);

    // Workout Session Exercise Management
    Route::post('/workout-sessions/{session}/exercises', [WorkoutSessionController::class, 'addExercise']);
    Route::delete('/workout-sessions/{session}/exercises/{exercise}', [WorkoutSessionController::class, 'removeExercise']);
    Route::put('/workout-sessions/{session}/exercises/{exercise}', [WorkoutSessionController::class, 'updateExercise']);
    Route::patch('/workout-sessions/{session}/exercises/{sessionExercise}/swap', [WorkoutSessionController::class, 'swapExercise']);
    Route::post('/workout-sessions/{session}/exercises/reorder', [WorkoutSessionController::class, 'reorderExercises']);
});
