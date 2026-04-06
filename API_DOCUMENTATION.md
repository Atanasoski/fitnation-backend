# Fit Nation API Documentation

This documentation provides complete information about all API resources and endpoints for React TypeScript frontend integration.

## Table of Contents

1. [Base Configuration](#base-configuration)
2. [Authentication](#authentication)
3. [User Management](#user-management)
4. [User Profile](#user-profile)

5. [Onboarding](#onboarding)
6. [Exercises](#exercises)
7. [Muscle Groups](#muscle-groups)
8. [Categories](#categories)
9. [Exercise Classifications](#exercise-classifications)
10. [Fitness Metrics](#fitness-metrics)
11. [Plans](#plans)
12. [Routines](#routines)
13. [Programs](#programs)
14. [Browsable Routines](#browsable-routines)
15. [Workout Templates](#workout-templates)
16. [Workout Planner](#workout-planner)
17. [Workout Sessions](#workout-sessions)
18. [Complete TypeScript Definitions](#complete-typescript-definitions)
19. [Error Responses](#error-responses)

---

## Base Configuration

### Base URL
```
/api
```

### Required Headers
```typescript
const headers = {
  'Authorization': `Bearer ${token}`,
  'Content-Type': 'application/json',
  'Accept': 'application/json'
};
```

### Authentication Method
All protected endpoints require Laravel Sanctum Bearer token authentication.

---

## Authentication

### Validate Invitation Token
```
GET /api/invitations/{token}
```
*Public endpoint - No authentication required*

Validates an invitation token and returns partner information if valid. This endpoint should be called when a user clicks the invitation link in their email to verify the token before showing the registration form.

**URL Parameters:**
- `token` (required) - The 64-character invitation token

**Response (200 OK):**
```typescript
interface ValidateInvitationResponse {
  message: "Valid invitation";
  data: InvitationResource;
}

interface InvitationResource {
  token: string;
  email: string;
  expires_at: string;  // ISO 8601
  partner: {
    id: number;
    name: string;
    slug: string;
    visual_identity: PartnerVisualIdentityResource | null;
  };
}
```

**Error Responses:**

**404 Not Found:**
```typescript
{
  message: "Invalid invitation token"
}
```

**422 Unprocessable Entity:**
```typescript
{
  message: "This invitation has already been used"
}
// OR
{
  message: "This invitation has expired"
}
```

---

### Register User
```
POST /api/register
```
*Public endpoint - No authentication required*

**IMPORTANT:** Registration is invite-only. Users must have a valid invitation token received via email from a partner.

**Request Body:**
```typescript
interface RegisterRequest {
  name: string;                  // required, max 255 chars
  email: string;                 // required, unique, lowercase email
  password: string;              // required, min 8 chars
  password_confirmation: string; // required, must match password
  invitation_token: string;      // required, 64-char token from invitation email
}
```

**Response (201 Created):**
```typescript
interface RegisterResponse {
  message: "User registered successfully";
  user: UserResource;
  token: string;
}
```

**Error Responses:**

**422 Unprocessable Entity:**
```typescript
{
  message: "Invalid invitation token"
}
// OR
{
  message: "This invitation has already been used"
}
// OR
{
  message: "This invitation has expired"
}
// OR
{
  message: "Email does not match the invitation"
}
```

---

### Login
```
POST /api/login
```

**Request Body:**
```typescript
interface LoginRequest {
  email: string;    // required
  password: string; // required
}
```

**Response (200 OK):**
```typescript
interface LoginResponse {
  message: "Login successful";
  user: UserResource;
  token: string;
}
```

**Error Response (422):**
```typescript
interface LoginError {
  message: "The given data was invalid.";
  errors: {
    email: ["The provided credentials are incorrect."];
  };
}
```

---

### Logout
```
POST /api/logout
```
*Requires authentication*

**Response (200 OK):**
```typescript
interface LogoutResponse {
  message: "Logged out successfully";
}
```

---

## User Management

### Get Current User
```
GET /api/user
```
*Requires authentication*

**Response:**
```typescript
interface GetUserResponse {
  user: UserResource;
}
```

---

## User Profile

### Get Profile
```
GET /api/profile
```
*Requires authentication*

**Response:**
```typescript
interface GetProfileResponse {
  user: UserResource;
}
```

---

### Update Profile
```
PUT /api/profile
PATCH /api/profile
```
*Requires authentication*

**Request Body (all fields optional):**
```typescript
interface ProfileUpdateRequest {
  // User fields
  name?: string;                    // max 255 chars
  email?: string;                   // unique, lowercase email
  profile_photo?: File;             // image: jpeg, png, jpg, gif, max 2MB

  // Profile fields
  fitness_goal?: FitnessGoal;       // enum value
  age?: number;                     // 1-150
  gender?: Gender;                  // enum value
  height?: number;                  // 50-300 (cm)
  weight?: number;                  // 1-500 (kg)
  training_experience?: TrainingExperience; // enum value
  training_days_per_week?: number;  // 1-7
  workout_duration_minutes?: number; // 1-600
}

type FitnessGoal = 'fat_loss' | 'muscle_gain' | 'strength' | 'general_fitness';
type Gender = 'male' | 'female' | 'other';
type TrainingExperience = 'beginner' | 'intermediate' | 'advanced';
```

**Response:**
```typescript
interface ProfileUpdateResponse {
  message: "Profile updated successfully";
  user: UserResource;
}
```

---

### Delete Profile Photo
```
DELETE /api/profile/photo
```
*Requires authentication*

**Response:**
```typescript
interface DeletePhotoResponse {
  message: "Profile photo deleted successfully";
  user: UserResource;
}
```

---

## Onboarding

### Complete Onboarding
```
POST /api/onboarding/complete
```
*Requires authentication*

Generates a personalized welcome workout plan based on the user's profile. This endpoint should be called when a user completes their onboarding process. The plan is created as a custom plan that the user can fully edit.

**Request Body (optional):**
```typescript
interface CompleteOnboardingRequest {
  plan_name?: string;  // Optional: Custom name for the plan (defaults to "Your Personalized Plan")
}
```

**Response (201 Created):**
```typescript
interface CompleteOnboardingResponse {
  message: "Welcome plan created successfully";
  data: CustomPlanResource;  // Full plan with workout_templates loaded (type: 'routine')
}
```

**Error Responses:**

**409 Conflict:**
```typescript
{
  message: "Onboarding has already been completed"
}
```

**422 Unprocessable Entity:**
```typescript
{
  message: "User profile is required for personalized plan generation"
}
// OR
{
  message: "Fitness goal is required for personalized plan generation"
}
// OR
{
  message: "Training experience is required for personalized plan generation"
}
// OR
{
  message: "Training days per week is required for personalized plan generation"
}
// OR
{
  message: "Workout duration is required for personalized plan generation"
}
```

**500 Internal Server Error:**
```typescript
{
  message: "Failed to complete onboarding. Please try again later."
}
```

**Notes:**
- This endpoint can only be called once per user. If `onboarding_completed_at` is already set, it will return a 409 Conflict error.
- The generated plan is automatically set as active (`is_active: true`) and marked as auto-generated (`is_auto_generated: true`).
- The workout split is automatically determined based on `training_days_per_week`:
  - 1 day: Full Body
  - 2 days: Full Body, Full Body
  - 3 days: Full Body, Full Body, Full Body
  - 4 days: Push, Pull, Push, Pull
  - 5 days: Push, Legs, Pull, Lower Body, Upper Body
  - 6 days: Push, Pull, Legs, Push, Pull, Legs
  - 7 days: Push, Pull, Legs, Push, Pull, Legs, Full Body
- Each workout template includes exercises selected by the workout generator based on the user's fitness goal, training experience, and workout duration preferences. The number of exercises is determined by how many fit within the specified duration (with a 10% buffer for warm-up/transitions), with safety rails of 3-12 exercises.

---

## Exercises

### List All Exercises
```
GET /api/exercises
```
*Requires authentication*

**Response:**
```typescript
interface ExerciseListResponse {
  data: ExerciseResource[];
}
```

---

### Get Single Exercise
```
GET /api/exercises/{id}
```
*Requires authentication*

**Response:**
```typescript
interface ExerciseShowResponse {
  data: ExerciseResource;
}
```

---

### Create Exercise
```
POST /api/exercises
```
*Requires authentication + admin role*

**Request Body:**
```typescript
interface CreateExerciseRequest {
  name: string;              // required, max 255 chars
  description?: string;      // optional, max 5000 chars
  category_id: number;       // required, must be workout category
  image?: string;            // optional, storage path, max 255 chars
  default_rest_sec?: number; // optional, min 0, defaults to 90
}
```

**Response (201 Created):**
```typescript
interface CreateExerciseResponse {
  message: "Exercise created successfully";
  data: ExerciseResource;
}
```

---

### Update Exercise
```
PUT /api/exercises/{id}
PATCH /api/exercises/{id}
```
*Requires authentication + admin role*

**Request Body:**
```typescript
interface UpdateExerciseRequest {
  name: string;              // required, max 255 chars
  description?: string;      // optional, max 5000 chars
  category_id: number;       // required, must be workout category
  default_rest_sec?: number; // optional, min 0
  image?: File;              // optional, image file max 5MB
  video?: File;              // optional, video file max 50MB
}
```

**Response:**
```typescript
interface UpdateExerciseResponse {
  message: "Exercise updated successfully";
  data: ExerciseResource;
}
```

---

### Delete Exercise
```
DELETE /api/exercises/{id}
```
*Requires authentication + admin role*

**Response:**
```typescript
interface DeleteExerciseResponse {
  message: "Exercise deleted successfully";
}
```

---

### Get Exercise Performance History
```
GET /api/exercises/{id}/history
```
*Requires authentication (owner only - user's own data)*

**Query Parameters (optional):**
```typescript
interface ExerciseHistoryQueryParams {
  limit?: number;        // Optional: Limit number of data points (default: all)
  start_date?: string;   // Optional: Filter from date (YYYY-MM-DD)
  end_date?: string;     // Optional: Filter to date (YYYY-MM-DD)
}
```

**Response:**
```typescript
interface ExerciseHistoryResponse {
  data: ExerciseHistoryResource;
}

interface ExerciseHistoryResource {
  exercise_id: number;
  exercise_name: string;
  stats: ExerciseHistoryStats;
  performance_data: PerformanceDataPoint[];
}

interface ExerciseHistoryStats {
  current_weight: number;      // Most recent weight used (kg)
  best_weight: number;         // Highest weight ever used (kg)
  current_best_set_reps: number; // Best single-set reps from most recent session (useful for bodyweight exercises)
  best_set_reps: number;       // All-time best single-set reps (useful for bodyweight exercises)
  progress_percentage: number; // Percentage change from first to most recent (+28 means +28%)
  total_sessions: number;       // Total number of completed sessions with this exercise
  first_session_date: string | null; // ISO date string of first session (YYYY-MM-DD)
  last_session_date: string | null; // ISO date string of most recent session (YYYY-MM-DD)
}

interface PerformanceDataPoint {
  date: string;                // ISO date string (YYYY-MM-DD)
  session_id: number;          // Workout session ID (for reference)
  weight: number;              // Best/max weight used in that session (kg)
  best_set_reps: number;       // Highest reps in a single set for that session (useful for bodyweight exercises)
  reps: number;                // Total reps across all sets in that session
  volume: number;              // Total volume = sum of (weight × reps) for all sets
  sets: number;                // Number of sets performed
}
```

**Example Response:**
```json
{
  "data": {
    "exercise_id": 42,
    "exercise_name": "Leg Press",
    "stats": {
      "current_weight": 32,
      "best_weight": 32,
      "current_best_set_reps": 12,
      "best_set_reps": 12,
      "progress_percentage": 28,
      "total_sessions": 5,
      "first_session_date": "2024-01-01",
      "last_session_date": "2024-01-15"
    },
    "performance_data": [
      {
        "date": "2024-01-01",
        "session_id": 101,
        "weight": 25,
        "best_set_reps": 10,
        "reps": 30,
        "volume": 750,
        "sets": 3
      },
      {
        "date": "2024-01-15",
        "session_id": 120,
        "weight": 32,
        "best_set_reps": 12,
        "reps": 30,
        "volume": 960,
        "sets": 3
      }
    ]
  }
}
```

**Notes:**
- Only returns data from completed workout sessions (`completed_at IS NOT NULL`)
- Performance data is ordered chronologically (oldest first)
- Weight represents the maximum weight used in that session
- Volume is calculated as the sum of (weight × reps) for each set
- If no history exists, returns empty `performance_data` array with stats set to 0/null

---

## Muscle Groups

### List All Muscle Groups
```
GET /api/muscle-groups
```
*Requires authentication*

**Query Parameters:**
```typescript
interface MuscleGroupQueryParams {
  body_region?: 'upper' | 'lower' | 'core'; // optional filter
}
```

**Response:**
```typescript
interface MuscleGroupListResponse {
  data: MuscleGroupResource[];
}
```

---

### Get Single Muscle Group
```
GET /api/muscle-groups/{id}
```
*Requires authentication*

**Response:**
Returns muscle group with associated exercises loaded.
```typescript
// MuscleGroupResource with exercises relationship
```

---

## Categories

Categories represent exercise equipment types used to classify exercises.

### List All Categories
```
GET /api/categories
```
*Requires authentication*

**Query Parameters:**
```typescript
interface CategoryQueryParams {
  type?: 'workout'; // optional filter by category type
}
```

**Response:**
```typescript
interface CategoryListResponse {
  data: CategoryResource[];
}
```

**Example Response:**
```json
{
  "data": [
    {
      "id": 1,
      "type": "workout",
      "name": "Bodyweight",
      "slug": "bodyweight",
      "display_order": 1,
      "icon": "🤸",
      "color": "#ef4444",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    },
    {
      "id": 2,
      "type": "workout",
      "name": "Dumbbell",
      "slug": "dumbbell",
      "display_order": 2,
      "icon": "🏋️",
      "color": "#3b82f6",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    }
  ]
}
```

---

### Get Single Category
```
GET /api/categories/{id}
```
*Requires authentication*

**Response:**
Returns category with associated exercises loaded.
```typescript
interface CategoryShowResponse {
  data: CategoryResource;
}

// CategoryResource includes exercises relationship when loaded
```

**Available Category Types:**
- `bodyweight` - Bodyweight exercises (e.g., Push-ups, Pull-ups, Dips)
- `dumbbell` - Dumbbell exercises (e.g., Dumbbell Bench Press, Dumbbell Curl)
- `barbell` - Barbell exercises (e.g., Barbell Bench Press, Deadlift, Squat)
- `machine-plate-loaded` - Plate-loaded machine exercises (e.g., Plate-Loaded Chest Press, Leg Press)
- `machine-cable` - Cable-based machine exercises (e.g., Lat Pulldown, Machine Row, Leg Extensions)
- `cable` - Free cable exercises (e.g., Cable Fly, Cable Curl, Cable Row)
- `bands` - Resistance band exercises
- `trx` - TRX suspension training exercises

---

## Exercise Classifications

Exercise classification lookup tables used by the workout generator (Fit Nation Engine) to categorize exercises by movement pattern, target region, equipment type, and angle.

### List Movement Patterns
```
GET /api/movement-patterns
```
*Requires authentication*

**Response:**
```typescript
interface MovementPatternListResponse {
  data: MovementPatternResource[];
}
```

**Example Response:**
```json
{
  "data": [
    { "id": 1, "code": "PRESS", "name": "Press", "display_order": 1 },
    { "id": 2, "code": "FLY", "name": "Fly", "display_order": 2 },
    { "id": 3, "code": "DIP", "name": "Dip", "display_order": 3 },
    { "id": 4, "code": "ROW", "name": "Row", "display_order": 4 },
    { "id": 5, "code": "PULL_VERTICAL", "name": "Vertical Pull", "display_order": 5 }
  ]
}
```

---

### List Target Regions
```
GET /api/target-regions
```
*Requires authentication*

**Response:**
```typescript
interface TargetRegionListResponse {
  data: TargetRegionResource[];
}
```

**Example Response:**
```json
{
  "data": [
    { "id": 1, "code": "UPPER_PUSH", "name": "Upper Push", "display_order": 1 },
    { "id": 2, "code": "UPPER_PULL", "name": "Upper Pull", "display_order": 2 },
    { "id": 3, "code": "LOWER", "name": "Lower Body", "display_order": 3 },
    { "id": 4, "code": "ARMS", "name": "Arms", "display_order": 4 },
    { "id": 5, "code": "CORE", "name": "Core", "display_order": 5 }
  ]
}
```

---

### List Equipment Types
```
GET /api/equipment-types
```
*Requires authentication*

**Response:**
```typescript
interface EquipmentTypeListResponse {
  data: EquipmentTypeResource[];
}
```

**Example Response:**
```json
{
  "data": [
    { "id": 1, "code": "BARBELL", "name": "Barbell", "display_order": 1 },
    { "id": 2, "code": "DUMBBELL", "name": "Dumbbell", "display_order": 2 },
    { "id": 3, "code": "CABLE", "name": "Cable", "display_order": 3 },
    { "id": 4, "code": "MACHINE", "name": "Machine", "display_order": 4 },
    { "id": 5, "code": "BODYWEIGHT", "name": "Bodyweight", "display_order": 5 }
  ]
}
```

---

### List Angles
```
GET /api/angles
```
*Requires authentication*

**Response:**
```typescript
interface AngleListResponse {
  data: AngleResource[];
}
```

**Example Response:**
```json
{
  "data": [
    { "id": 1, "code": "FLAT", "name": "Flat", "display_order": 1 },
    { "id": 2, "code": "INCLINE", "name": "Incline", "display_order": 2 },
    { "id": 3, "code": "DECLINE", "name": "Decline", "display_order": 3 },
    { "id": 4, "code": "VERTICAL", "name": "Vertical", "display_order": 4 },
    { "id": 5, "code": "HORIZONTAL", "name": "Horizontal", "display_order": 5 }
  ]
}
```

---

## Fitness Metrics

### Get Fitness Metrics
```
GET /api/user/fitness-metrics
```
*Requires authentication*

**Response:**
```typescript
interface FitnessMetricsResponse {
  success: true;
  data: {
    strength_score: StrengthScore;
    strength_balance: StrengthBalance;
    weekly_progress: WeeklyProgress;
  };
  message: "Fitness metrics retrieved successfully";
}

interface StrengthScore {
  current: number;           // Relative strength score
  level: StrengthLevel;      // Based on demographics
  recent_gain: number;       // Change in last 30 days
  gain_period: "last_30_days";
  percentile?: number;        // 0-100, percentile ranking compared to similar users in same partner
  muscle_groups?: Record<string, number>; // Strength scores by muscle group (chest, back, shoulders, arms, legs, core)
}

type StrengthLevel = 'BEGINNER' | 'INTERMEDIATE' | 'ADVANCED';

interface StrengthBalance {
  percentage: number;        // 0-100, how balanced training is
  level: BalanceLevel;
  recent_change: number;     // Change from previous period
  muscle_groups: Record<MuscleGroupName, number>; // % distribution
  percentile?: number;       // 0-100, percentile ranking compared to similar users in same partner (optional)
}

type BalanceLevel = 'EXCELLENT' | 'GOOD' | 'FAIR' | 'NEEDS_IMPROVEMENT';

type MuscleGroupName = 
  | 'chest' | 'lats' | 'upper back' | 'lower back'
  | 'front delts' | 'side delts' | 'rear delts' | 'traps'
  | 'biceps' | 'triceps' | 'forearms'
  | 'quadriceps' | 'hamstrings' | 'glutes' | 'calves'
  | 'abs' | 'obliques';

interface WeeklyProgress {
  percentage: number;        // Absolute change %
  trend: 'up' | 'down' | 'same';
  current_week_workouts: number;
  previous_week_workouts: number;
  current_week_volume?: number;          // Total volume in lbs for current week (converted from KG stored in DB) (optional)
  current_week_time_minutes?: number;   // Total time in minutes for current week (optional)
  previous_week_volume?: number;        // Total volume in lbs for previous week (converted from KG stored in DB) (optional)
  volume_difference?: number;           // Difference in volume between weeks (optional)
  volume_difference_percent?: number;   // Percentage difference in volume (optional)
  daily_breakdown?: Array<{              // Daily data for the current week (optional)
    day_of_week: number;                // 0 = Monday, 1 = Tuesday, ..., 6 = Sunday
    date: string;                       // ISO date string (e.g., "2024-01-15")
    volume: number;                     // Volume in lbs for that day (converted from KG stored in DB)
    workouts: number;                   // Number of workouts
    time_minutes: number;                // Time spent in minutes
  }>;
  historical_weeks?: Array<{ week: string, workouts: number }>; // Historical weekly workout counts for last 8 weeks (optional)
}
```

---

## Plans

Plans are managed at `/api/plans`. All responses use `PlanResource`, which includes `cover_image` (full URL or null).

### List Plans
```
GET /api/plans
```
*Requires authentication*

**Response:**
```typescript
interface PlanListResponse {
  data: PlanResource[];
}
```

### Get Single Plan
```
GET /api/plans/{id}
```
*Requires authentication (owner only)*

**Response:**
```typescript
interface PlanShowResponse {
  data: PlanResource;
}
```

### Create Plan
```
POST /api/plans
```
*Requires authentication*

**Request Body (JSON):**
```typescript
interface StorePlanRequest {
  name: string;           // required, max 255 chars
  description?: string;   // optional
  is_active?: boolean;    // optional, defaults to false
}
```

**Request Body (multipart/form-data)** — when sending a cover image:
- `name` (required), `description` (optional), `is_active` (optional)
- `cover_image` (optional): image file (JPEG, PNG, WebP), max 5MB

**Response (201 Created):**
```typescript
interface CreatePlanResponse {
  message: "Plan created successfully";
  data: PlanResource;
}
```

### Regenerate Personalized Plan
```
POST /api/plans/regenerate
```
*Requires authentication*

Creates a new 12-week personalized program from the user's profile (same algorithm as onboarding). Any **active** plan with `is_auto_generated: true` for this user is deactivated (`is_active` set to `false`); templates and workout history on older plans remain in the database for calendars and history. The new plan is set active with `is_auto_generated: true`.

Requires onboarding to be complete and the same profile fields as plan generation (fitness goal, training experience, training days per week, workout duration).

**Request Body (optional):**
```typescript
interface RegeneratePlanRequest {
  plan_name?: string;  // Optional display name (defaults to "Your Personalized Plan")
}
```

**Response (201 Created):**
```typescript
interface RegeneratePlanResponse {
  message: "Personalized plan created successfully";
  data: CustomPlanResource;  // Full plan with workout_templates loaded
}
```

**Error Responses:**

**422 Unprocessable Entity:** Same profile validation messages as [Complete Onboarding](#complete-onboarding) (`"… required for personalized plan generation"`).

**500 Internal Server Error:**
```typescript
{
  message: "Failed to regenerate plan. Please try again later."
}
```

**Notes:**
- Does not change `onboarding_completed_at`.
- Users may still have another plan type (e.g. cloned PT program) active depending on earlier actions; only auto-generated active plans are deactivated by this endpoint before creating the new one.

### Update Plan
```
PUT /api/plans/{id}
PATCH /api/plans/{id}
```
*Requires authentication (owner only)*

**Request Body (JSON):**
```typescript
interface UpdatePlanRequest {
  name: string;           // required, max 255 chars
  description?: string;    // optional
  is_active?: boolean;     // optional
}
```

**Request Body (multipart/form-data)** — when sending a new cover image:
- Same as JSON, plus optional `cover_image` file (JPEG, PNG, WebP, max 5MB). Replaces existing cover image.

**Response:**
```typescript
interface UpdatePlanResponse {
  message: "Plan updated successfully";
  data: PlanResource;
}
```

### Delete Plan
```
DELETE /api/plans/{id}
```
*Requires authentication (owner only)*

**Response:**
```typescript
interface DeletePlanResponse {
  message: "Plan deleted successfully";
}
```

---

## Routines

Routines are user-created flexible workout collections. Users have full CRUD control over their routines. Routines differ from programs in that they are fully editable and don't have duration/progress tracking. Users can perform any workout from a routine as many times as they want.

### List User's Routines
```
GET /api/custom-plans
```
*Requires authentication*

**Response:**
```typescript
interface RoutineListResponse {
  data: CustomPlanResource[];
}

interface CustomPlanResource {
  id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  is_auto_generated: boolean;
  type: 'routine';
  workout_templates: WorkoutTemplateResource[] | null;
  created_at: string;
  updated_at: string;
}
```

---

### Get Single Routine
```
GET /api/custom-plans/{id}
```
*Requires authentication (owner only)*

**Response:**
```typescript
interface RoutineShowResponse {
  data: CustomPlanResource;
}
```

---

### Create Routine
```
POST /api/custom-plans
```
*Requires authentication*

**Request Body:**
```typescript
interface StoreCustomPlanRequest {
  name: string;          // required, max 255 chars
  description?: string;  // optional
  is_active?: boolean;   // optional, defaults to false
}
```

**Response (201 Created):**
```typescript
interface CreateRoutineResponse {
  message: "Routine created successfully";
  data: CustomPlanResource;
}
```

---

### Update Routine
```
PUT /api/custom-plans/{id}
```
*Requires authentication (owner only)*

**Request Body:**
```typescript
interface UpdateCustomPlanRequest {
  name: string;          // required, max 255 chars
  description?: string;  // optional
  is_active?: boolean;   // optional
}
```

**Response:**
```typescript
interface UpdateRoutineResponse {
  message: "Routine updated successfully";
  data: CustomPlanResource;
}
```

---

### Delete Routine
```
DELETE /api/custom-plans/{id}
```
*Requires authentication (owner only)*

**Response:**
```typescript
interface DeleteRoutineResponse {
  message: "Routine deleted successfully";
}
```

---

## Programs

Programs are structured workout plans with duration tracking and progress. There are two sources:

1. **Library Programs** (`type: 'program'`, `partner_id` set, `user_id` null) - Partner-designed programs available for users to clone
2. **User Programs** (`type: 'program'`, `user_id` set) - Programs that users have cloned from the library

Users can browse their partner's library and clone programs to their account. Programs track progress and provide next-workout suggestions.

### Plan Types

| Type | Description | Editable | Has Duration | Ownership |
|------|-------------|----------|--------------|-----------|
| `routine` | User-created flexible workout collection | Full CRUD | No | `user_id` set |
| `routine` | Partner-provided browsable workouts | Read-only (browse & start) | No | `partner_id` set, `user_id` null |
| `program` | Partner-designed program template | Read-only (browse & clone) | Yes | `partner_id` set, `user_id` null |
| `program` | User's cloned program from library | Toggle active only | Yes | `user_id` set |

**Note:** Ownership is determined by the `user_id` and `partner_id` fields. A plan with `partner_id` set and `user_id` null is partner-provided (library/browsable). A plan with `user_id` set is user-owned.

### List User's Programs
```
GET /api/programs
```
*Requires authentication*

Returns programs that the user has cloned from their partner's library (type: `program`).

**Response:**
```typescript
interface ProgramListResponse {
  data: ProgramResource[];
}

interface ProgramResource {
  id: number;
  name: string;
  description: string | null;
  duration_weeks: number | null;
  is_active: boolean;
  is_auto_generated: boolean;        // true for profile-generated programs (onboarding/regenerate)
  type: 'program';
  is_library_plan: boolean;          // false for user's cloned programs
  progress_percentage: number | null; // Calculated from completed workouts
  next_workout: WorkoutTemplateResource | null;
  workout_templates: WorkoutTemplateResource[] | null;
  created_at: string;
  updated_at: string;
}
```

---

### Browse Partner Library
```
GET /api/programs/library
```
*Requires authentication*

Returns partner library programs (`type: 'program'`, `partner_id` set, `user_id` null) available for cloning. Empty array if user has no partner.

**Response:**
```typescript
interface ProgramLibraryResponse {
  data: ProgramResource[];
}

// ProgramResource with is_library_plan: true indicates partner-provided library program
```

---

### Get Single Program
```
GET /api/programs/{id}
```
*Requires authentication*

Can view either own cloned programs or partner library programs.

**Response:**
```typescript
interface ProgramShowResponse {
  data: ProgramResource;
}
```

---

### Clone Library Program
```
POST /api/programs/{id}/clone
```
*Requires authentication*

Clones a partner library program (`type: 'program'`, `partner_id` set, `user_id` null) to the user's account with all workout templates and exercises. The cloned program remains `type: 'program'` but becomes owned by the user (`user_id` set, `partner_id` null).

**Response (201 Created):**
```typescript
interface CloneProgramResponse {
  message: "Program cloned successfully";
  data: ProgramResource;  // The new cloned program (type: 'program')
}
```

**Error Responses:**
- `403`: "Only library programs can be cloned"
- `403`: "Unauthorized" (program belongs to different partner)

---

### Update Program (Toggle Active Only)
```
PATCH /api/programs/{id}
```
*Requires authentication (owner only)*

Users can only toggle `is_active` status on their cloned programs. All other fields are read-only.

**Request Body:**
```typescript
interface UpdateProgramRequest {
  is_active: boolean;  // required
}
```

**Response:**
```typescript
interface UpdateProgramResponse {
  message: "Program updated successfully";
  data: ProgramResource;
}
```

---

### Delete Program
```
DELETE /api/programs/{id}
```
*Requires authentication (owner only)*

**Response:**
```typescript
interface DeleteProgramResponse {
  message: "Program deleted successfully";
}
```

---

### Get Next Workout
```
GET /api/programs/{id}/next-workout
```
*Requires authentication (owner only)*

Returns the next uncompleted workout template in the program sequence.

**Response:**
```typescript
interface NextWorkoutResponse {
  data: WorkoutTemplateResource | null;  // null if all workouts completed
}
```

---

## Browsable Routines

Browsable routines are partner-provided repeatable workout collections. Users can browse these routines and start any workout from them as many times as they want without cloning or setting them as active. These routines are identified by `type: 'routine'` with `partner_id` set and `user_id` null.

### List Partner Routines
```
GET /api/routines
```
*Requires authentication*

Returns active partner-provided routine plans available for browsing. Empty array if user has no partner.

**Response:**
```typescript
interface RoutinePlanListResponse {
  data: RoutinePlanResource[];
}

interface RoutinePlanResource {
  id: number;
  name: string;
  description: string | null;
  cover_image: string | null;  // Full URL
  is_active: boolean;
  type: 'routine';
  workout_templates: WorkoutTemplateResource[] | null;
  created_at: string;
  updated_at: string;
}
```

---

### Get Single Browsable Routine
```
GET /api/routines/{routine}
```
*Requires authentication*

Returns a single partner-provided routine plan with all workout templates. Users can start any workout from this routine directly without cloning.

**Response:**
```typescript
interface RoutinePlanShowResponse {
  data: RoutinePlanResource;
}
```

**Error Responses:**
- `403`: "Unauthorized" (routine belongs to different partner)
- `400`: "Not a browsable routine" (not a partner-provided routine)

**Notes:**
- Users can start workouts from these routines using `POST /api/workout-sessions/start` with the `template_id` from any workout template in the routine
- No cloning or activation needed - users can start workouts directly
- Workouts can be performed multiple times without restrictions

---

## Workout Templates

Workout templates define reusable workout structures with exercises and targets.

### List All Workout Templates
```
GET /api/workout-templates
```
*Requires authentication*

**Response:**
```typescript
interface WorkoutTemplateListResponse {
  data: WorkoutTemplateResource[];
}
```

---

### Get Single Workout Template
```
GET /api/workout-templates/{id}
```
*Requires authentication (owner only)*

**Response:**
```typescript
interface WorkoutTemplateShowResponse {
  data: WorkoutTemplateResource;
}
```

---

### Create Workout Template
```
POST /api/workout-templates
```
*Requires authentication*

**Request Body:**
```typescript
interface CreateWorkoutTemplateRequest {
  plan_id: number;       // required, must belong to user
  name: string;          // required, max 255 chars
  description?: string;  // optional
  day_of_week?: number;  // optional, 0-6 (Mon=0, Sun=6)
}
```

**Response (201 Created):**
```typescript
interface CreateWorkoutTemplateResponse {
  message: "Workout template created successfully";
  data: WorkoutTemplateResource;
}
```

---

### Update Workout Template
```
PUT /api/workout-templates/{id}
PATCH /api/workout-templates/{id}
```
*Requires authentication (owner only)*

**Request Body:**
```typescript
interface UpdateWorkoutTemplateRequest {
  plan_id?: number;      // optional, must belong to user
  name: string;          // required, max 255 chars
  description?: string;  // optional
  day_of_week?: number;  // optional, 0-6
}
```

**Response:**
```typescript
interface UpdateWorkoutTemplateResponse {
  message: "Workout template updated successfully";
  data: WorkoutTemplateResource;
}
```

---

### Delete Workout Template
```
DELETE /api/workout-templates/{id}
```
*Requires authentication (owner only)*

**Response:**
```typescript
interface DeleteWorkoutTemplateResponse {
  message: "Workout template deleted successfully";
}
```

---

### Add Exercise to Template
```
POST /api/workout-templates/{workoutTemplate}/exercises
```
*Requires authentication (owner only)*

**Request Body:**
```typescript
interface AddTemplateExerciseRequest {
  exercise_id: number;       // required, must exist
  target_sets?: number;      // optional, min 1
  target_reps?: number;      // optional, min 1
  target_weight?: number;    // optional, min 0
  rest_seconds?: number;     // optional, min 0
}
```

**Response (201 Created):**
```typescript
interface AddTemplateExerciseResponse {
  message: "Exercise added successfully";
  data: WorkoutTemplateResource;
}
```

---

### Update Exercise in Template
```
PUT /api/workout-templates/{workoutTemplate}/exercises/{exercise}
```
*Requires authentication (owner only)*

**Note:** `{exercise}` is the `WorkoutTemplateExercise` pivot ID, not the exercise ID.

**Request Body:**
```typescript
interface UpdateTemplateExerciseRequest {
  target_sets?: number;      // optional, min 1
  target_reps?: number;      // optional, min 1
  target_weight?: number;    // optional, min 0
  rest_seconds?: number;     // optional, min 0
}
```

**Response:**
```typescript
interface UpdateTemplateExerciseResponse {
  message: "Exercise updated successfully";
  data: WorkoutTemplateResource;
}
```

---

### Remove Exercise from Template
```
DELETE /api/workout-templates/{workoutTemplate}/exercises/{exercise}
```
*Requires authentication (owner only)*

**Note:** `{exercise}` is the `WorkoutTemplateExercise` pivot ID.

**Response:**
```typescript
interface RemoveTemplateExerciseResponse {
  message: "Exercise removed successfully";
  data: WorkoutTemplateResource;
}
```

---

### Reorder Exercises in Template
```
POST /api/workout-templates/{workoutTemplate}/order
```
*Requires authentication (owner only)*

**Request Body:**
```typescript
interface ReorderTemplateExercisesRequest {
  order: number[];  // Array of WorkoutTemplateExercise IDs in desired order
}
```

**Response:**
```typescript
interface ReorderTemplateExercisesResponse {
  message: "Order updated successfully";
  data: WorkoutTemplateResource;
}
```

---

## Workout Planner

The planner provides a weekly view of scheduled workouts.

### Get Weekly Planner
```
GET /api/planner/weekly
```
*Requires authentication*

**Response:**
```typescript
interface WeeklyPlannerResponse {
  data: {
    weekly_plan: DayPlan[];
    available_templates: WorkoutTemplateResource[];
  };
}

interface DayPlan {
  day_of_week: number;                    // 0-6 (Mon=0, Sun=6)
  day_name: string;                       // "Monday", "Tuesday", etc.
  template: WorkoutTemplateResource | null;
}
```

---

### Assign Template to Day
```
POST /api/planner/assign
```
*Requires authentication*

**Request Body:**
```typescript
interface AssignTemplateRequest {
  template_id: number;   // required, must belong to user
  day_of_week: number;   // required, 0-6
}
```

**Response:**
```typescript
interface AssignTemplateResponse {
  message: "Workout assigned successfully";
  data: WorkoutTemplateResource;
}
```

---

### Unassign Template from Day
```
POST /api/planner/unassign
```
*Requires authentication*

**Request Body:**
```typescript
interface UnassignTemplateRequest {
  template_id: number;   // required, must belong to user
}
```

**Response:**
```typescript
interface UnassignTemplateResponse {
  message: "Workout unassigned successfully";
  data: WorkoutTemplateResource;
}
```

---

## Workout Sessions

Workout sessions track actual performed workouts with logged sets.

### Get Calendar View
```
GET /api/workout-sessions/calendar
```
*Requires authentication*

**Query Parameters:**
```typescript
interface CalendarQueryParams {
  start_date: string;  // required, format: YYYY-MM-DD
  end_date: string;    // required, format: YYYY-MM-DD
}
```

**Response:**
```typescript
interface CalendarResponse {
  data: {
    sessions: WorkoutSessionCalendarResource[];
    date_range: {
      start: string;
      end: string;
    };
  };
}
```

---

### Get Today's Workout
```
GET /api/workout-sessions/today
```
*Requires authentication*

**Response:**
```typescript
interface TodayWorkoutResponse {
  data: {
    template: WorkoutTemplate | null;  // Scheduled template for today
    session: WorkoutSessionResource | null;  // Active session if exists
  };
}
```

---

### Start Workout Session
```
POST /api/workout-sessions/start
```
*Requires authentication*

**Request Body:**
```typescript
interface StartSessionRequest {
  template_id?: number;  // optional, creates blank session if not provided
}
```

**Response (201 Created):**
```typescript
interface StartSessionResponse {
  data: WorkoutSessionResource;
  message: "Workout session started successfully";
}
```

**Note:** If a session already exists for today, returns the existing session.

---

### Generate Draft Workout Session
```
POST /api/workout-sessions/generate
```
*Requires authentication*

Generates a new workout session in draft status. Creates a session with exercises that can be modified before confirming. User can then confirm to start the workout or regenerate for different exercises.

**Request Body:**
```typescript
interface GenerateWorkoutRequest {
  target_regions?: string[];           // optional, e.g., ["UPPER_PUSH", "UPPER_PULL"] - defaults to all regions (full body) if not provided
  equipment_types?: string[];          // optional, e.g., ["MACHINE", "BARBELL"] - defaults to all if not provided
  movement_patterns?: string[];        // optional, e.g., ["PRESS", "FLY", "DIP"]
  angles?: string[];                   // optional, e.g., ["FLAT", "INCLINE", "DECLINE"]
  duration_minutes?: number;           // optional, min 15, max 180 - uses profile default if not provided
  difficulty?: 'beginner' | 'intermediate' | 'advanced';  // optional - uses profile training_experience if not provided
}
```

**Defaults:**
- If `target_regions` not provided → system generates full body workout (all target regions)
- If `equipment_types` not provided → system uses all available equipment types
- If `duration_minutes` not provided → system uses user profile `workout_duration_minutes`
- If `difficulty` not provided → system uses user profile `training_experience`

**Note:** The frontend should map user-friendly labels (e.g., "Push", "Pull", "Legs", "Full Body") to target region codes. For example:
- "Push" → `["UPPER_PUSH"]`
- "Pull" → `["UPPER_PULL"]`
- "Legs" → `["LOWER"]`
- "Upper Body" → `["UPPER_PUSH", "UPPER_PULL"]`
- "Lower Body" → `["LOWER", "CORE"]`
- "Full Body" → `["UPPER_PUSH", "UPPER_PULL", "LOWER"]` or omit `target_regions` entirely

**Available Target Region Codes:**
- `UPPER_PUSH` - Chest, Triceps, Front/Side Delts
- `UPPER_PULL` - Lats, Biceps, Rear Delts, Traps, Upper Back
- `LOWER` - Quadriceps, Hamstrings, Glutes, Calves
- `ARMS` - Biceps, Triceps, Forearms
- `CORE` - Abs, Obliques

**Available Equipment Type Codes:**
- `MACHINE` - Machine exercises
- `BARBELL` - Barbell exercises
- `DUMBBELL` - Dumbbell exercises
- `CABLE` - Cable exercises
- `BODYWEIGHT` - Bodyweight exercises

**Available Movement Pattern Codes:**
- `PRESS` - Pressing movements
- `FLY` - Fly movements
- `DIP` - Dip movements
- `ROW` - Rowing movements
- `PULL` - Pulling movements

**Available Angle Codes:**
- `FLAT` - Flat angle
- `INCLINE` - Inclined angle
- `DECLINE` - Declined angle
- `VERTICAL` - Vertical angle
- `HORIZONTAL` - Horizontal angle

**Response (201 Created):**
```typescript
interface GenerateWorkoutResponse {
  data: GeneratedSessionResource;
  message: "Draft workout session created successfully";
}

interface GeneratedSessionResource extends WorkoutSessionResource {
  is_auto_generated: boolean;           // true for auto-generated sessions
  status: 'draft' | 'active' | 'completed' | 'cancelled';
  replaced_session_id: number | null;   // ID of session this replaced (null for first generation)
  rationale: string | null;             // explanation of the workout selection
}
```

**Error Responses:**

- **422 Unprocessable Entity:** User profile incomplete
  ```typescript
  {
    message: "Please complete your profile before generating workouts."
  }
  // OR
  {
    message: "Please set your fitness goal in your profile."
  }
  // OR
  {
    message: "Please set your training experience level in your profile."
  }
  ```

- **422 Unprocessable Entity:** No exercises available
  ```typescript
  {
    message: "No exercises available matching the specified criteria"
  }
  ```

**Example Request:**
```json
{
  "target_regions": ["UPPER_PUSH"],
  "equipment_types": ["MACHINE"],
  "duration_minutes": 45
}
```

**Notes:**
- Creates a session with `status: draft` and `performed_at: null`
- The session is stored in the database with all exercises
- User can modify exercises using standard exercise management endpoints before confirming
- Call regenerate endpoint to cancel this draft and create a new one
- **Exercise Selection Logic:** Duration is the primary constraint for exercise selection. The system selects exercises that fit within the specified `duration_minutes` (with a 10% buffer reserved for warm-up and transitions). The number of exercises varies based on:
  - **Fitness Goal:** Different goals have different rest periods and sets (e.g., strength: 180s rest, muscle gain: 90s rest, fat loss: 45s rest), which naturally results in different exercise counts for the same duration
  - **Exercise Type:** Compound exercises take longer than isolation exercises due to more sets and longer rest periods
  - **Safety Rails:** Minimum 3 exercises and maximum 12 exercises are enforced to prevent edge cases
- Different fitness goals will naturally produce different exercise counts for the same duration (e.g., a 60-minute strength workout may have 4-5 exercises, while a 60-minute fat loss workout may have 8-10 exercises)

---

### Confirm Draft Workout Session
```
POST /api/workout-sessions/{session}/confirm
```
*Requires authentication (owner only)*

Confirms a draft workout session and starts the workout. Sets `status` to `active` and sets `performed_at` to current timestamp.

**URL Parameters:**
- `session` (required) - ID of the draft workout session

**Request Body:** None

**Response (200 OK):**
```typescript
interface ConfirmWorkoutResponse {
  data: GeneratedSessionResource;
  message: "Workout session confirmed and started successfully";
}
```

**Error Responses:**

- **422 Unprocessable Entity:** Session is not in draft status
  ```typescript
  {
    message: "Only draft sessions can be confirmed"
  }
  ```

- **403 Forbidden:** User does not own the session
  ```typescript
  {
    message: "Unauthorized"
  }
  ```

**Typical Flow:**
1. Call `POST /workout-sessions/generate` with preferences to create draft session
2. Display session exercises to user (full session resource returned)
3. User can optionally modify exercises using:
   - `POST /workout-sessions/{id}/exercises` - Add exercise
   - `PUT /workout-sessions/{id}/exercises/{exerciseId}` - Update exercise
   - `DELETE /workout-sessions/{id}/exercises/{exerciseId}` - Remove exercise
   - `POST /workout-sessions/{id}/exercises/reorder` - Reorder exercises
4. If user wants different exercises, call `POST /workout-sessions/{id}/regenerate`
5. When user approves, call `POST /workout-sessions/{id}/confirm` to start the workout

---

### Regenerate Draft Workout Session
```
POST /api/workout-sessions/{session}/regenerate
```
*Requires authentication (owner only)*

Cancels the current draft session and generates a new one with optionally different preferences. Links the new session to the old one via `replaced_session_id` for tracking purposes.

**URL Parameters:**
- `session` (required) - ID of the draft workout session to regenerate

**Request Body:**
```typescript
interface RegenerateWorkoutRequest {
  target_regions?: string[];           // optional - defaults to all regions (full body) if not provided
  equipment_types?: string[];          // optional - defaults to all if not provided
  movement_patterns?: string[];        // optional
  angles?: string[];                   // optional
  duration_minutes?: number;           // optional, min 15, max 180
  difficulty?: 'beginner' | 'intermediate' | 'advanced';  // optional
}
```

**Response (201 Created):**
```typescript
interface RegenerateWorkoutResponse {
  data: GeneratedSessionResource;      // New session with replaced_session_id set
  message: "New workout session generated successfully";
}
```

**Error Responses:**

- **422 Unprocessable Entity:** Session is not in draft status
  ```typescript
  {
    message: "Only draft sessions can be regenerated"
  }
  ```

- **403 Forbidden:** User does not own the session
  ```typescript
  {
    message: "Unauthorized"
  }
  ```

---

### Get Session Details
```
GET /api/workout-sessions/{session}
```
*Requires authentication (owner only)*

**Response:**
```typescript
interface SessionDetailResponse {
  data: {
    id: number;
    user_id: number;
    workout_template_id: number | null;
    performed_at: string;      // ISO 8601
    completed_at: string | null;
    notes: string | null;
    exercises: SessionExerciseDetail[];
    progress: SessionProgress;
    created_at: string;
    updated_at: string;
  };
}

interface SessionExerciseDetail {
  session_exercise: WorkoutSessionExerciseResource;
  logged_sets: SetLogResource[];
  previous_sets: SetLogResource[];  // From last completed session
  is_completed: boolean;
}

interface SessionProgress {
  total_exercises: number;
  completed_exercises: number;
  progress_percent: number;  // 0-100
}
```

---

### Complete Workout Session
```
POST /api/workout-sessions/{session}/complete
```
*Requires authentication (owner only)*

**Request Body:**
```typescript
interface CompleteSessionRequest {
  notes?: string;  // optional, max 1000 chars
}
```

**Response:**
```typescript
interface CompleteSessionResponse {
  data: WorkoutSessionResource;
  message: "Workout completed! Great job! 💪";
  new_prs: NewPrResource[];  // Personal records set in this session vs. all prior completed sessions (empty if none)
}

interface NewPrResource {
  exercise_id: number;
  exercise_name: string;
  pr_type: 'weight' | 'reps';
  previous_best: number;  // 0 if first time logging this exercise in completed sessions
  new_best: number;       // Session max weight (kg) or max single-set reps, depending on pr_type
}
```

---

### Cancel Workout Session
```
DELETE /api/workout-sessions/{session}/cancel
```
*Requires authentication (owner only)*

**Response:**
```typescript
interface CancelSessionResponse {
  message: "Workout cancelled successfully";
}
```

**Note:** This deletes all set logs and the session itself.

---

### Log a Set
```
POST /api/workout-sessions/{session}/sets
```
*Requires authentication (owner only)*

**Request Body:**
```typescript
interface LogSetRequest {
  exercise_id: number;    // required, must exist
  set_number: number;     // required, min 1
  weight: number;         // required, min 0
  reps: number;           // required, min 0
  rest_seconds?: number;  // optional, min 0
}
```

**Response (201 Created):**
```typescript
interface LogSetResponse {
  data: SetLogResource;
  message: "Set logged successfully";
}
```

---

### Update a Set
```
PUT /api/workout-sessions/{session}/sets/{setLog}
```
*Requires authentication (owner only)*

**Request Body:**
```typescript
interface UpdateSetRequest {
  weight: number;  // required, min 0
  reps: number;    // required, min 0
}
```

**Response:**
```typescript
interface UpdateSetResponse {
  data: SetLogResource;
  message: "Set updated successfully";
}
```

---

### Delete a Set
```
DELETE /api/workout-sessions/{session}/sets/{setLog}
```
*Requires authentication (owner only)*

**Response:**
```typescript
interface DeleteSetResponse {
  message: "Set deleted successfully";
}
```

**Note:** Only the last set for an exercise can be deleted.

---

### Add Exercise to Session
```
POST /api/workout-sessions/{session}/exercises
```
*Requires authentication (owner only)*

**Request Body:**
```typescript
interface AddSessionExerciseRequest {
  exercise_id: number;       // required, must exist
  order?: number;            // optional, min 0, defaults to end
  target_sets?: number;      // optional, min 1, defaults to 3
  target_reps?: number;      // optional, min 1, defaults to 10
  target_weight?: number;    // optional, min 0, defaults to 0
  rest_seconds?: number;     // optional, min 0, uses exercise default
}
```

**Response (201 Created):**
```typescript
interface AddSessionExerciseResponse {
  data: WorkoutSessionExerciseResource;
  message: "Exercise added to session successfully";
}
```

---

### Update Session Exercise
```
PUT /api/workout-sessions/{session}/exercises/{exercise}
```
*Requires authentication (owner only)*

**Note:** `{exercise}` is the `WorkoutSessionExercise` ID.

**Request Body:**
```typescript
interface UpdateSessionExerciseRequest {
  order?: number;            // optional, min 0
  target_sets?: number;      // optional, min 1
  target_reps?: number;      // optional, min 1
  target_weight?: number;    // optional, min 0
  rest_seconds?: number;     // optional, min 0
}
```

**Response:**
```typescript
interface UpdateSessionExerciseResponse {
  data: WorkoutSessionExerciseResource;
  message: "Exercise updated successfully";
}
```

---

### Remove Exercise from Session
```
DELETE /api/workout-sessions/{session}/exercises/{exercise}
```
*Requires authentication (owner only)*

**Note:** Also deletes all associated set logs for this exercise.

**Response:**
```typescript
interface RemoveSessionExerciseResponse {
  message: "Exercise removed from session successfully";
}
```

---

### Reorder Session Exercises
```
POST /api/workout-sessions/{session}/exercises/reorder
```
*Requires authentication (owner only)*

**Request Body:**
```typescript
interface ReorderSessionExercisesRequest {
  exercise_ids: number[];  // Array of WorkoutSessionExercise IDs in desired order
}
```

**Response:**
```typescript
interface ReorderSessionExercisesResponse {
  data: WorkoutSessionExerciseResource[];
  message: "Exercises reordered successfully";
}
```

---

## Complete TypeScript Definitions

### Enums and Constants

```typescript
// Fitness Goal
type FitnessGoal = 'fat_loss' | 'muscle_gain' | 'strength' | 'general_fitness';

// Gender
type Gender = 'male' | 'female' | 'other';

// Training Experience
type TrainingExperience = 'beginner' | 'intermediate' | 'advanced';

// Strength Level
type StrengthLevel = 'BEGINNER' | 'INTERMEDIATE' | 'ADVANCED';

// Balance Level
type BalanceLevel = 'EXCELLENT' | 'GOOD' | 'FAIR' | 'NEEDS_IMPROVEMENT';

// Trend Direction
type TrendDirection = 'up' | 'down' | 'same';

// Body Region
type BodyRegion = 'upper' | 'lower' | 'core';

// Day of Week (for planner)
// 0 = Monday, 1 = Tuesday, ..., 6 = Sunday
type DayOfWeek = 0 | 1 | 2 | 3 | 4 | 5 | 6;
```

### Resource Interfaces

```typescript
// ============================================
// USER RESOURCES
// ============================================

interface UserResource {
  id: number;
  name: string;
  email: string;
  profile_photo: string;  // Full URL
  profile: UserProfileResource | null;
  partner: UserPartner | null;
  email_verified_at: string | null;  // ISO 8601
  created_at: string;                 // ISO 8601
  updated_at: string;                 // ISO 8601
}

interface UserProfileResource {
  fitness_goal: FitnessGoal | null;
  age: number | null;
  gender: Gender | null;
  height: number | null;              // in cm
  weight: number | null;              // in kg
  training_experience: TrainingExperience | null;
  training_days_per_week: number | null;  // 1-7
  workout_duration_minutes: number | null;
}

interface UserPartner {
  id: number;
  name: string;
  slug: string;
  visual_identity: PartnerVisualIdentityResource | null;
}

// ============================================
// PARTNER RESOURCES
// ============================================

interface PartnerVisualIdentityResource {
  // Core branding
  primary_color: string | null;
  secondary_color: string | null;
  logo: string;  // Full URL
  font_family: string | null;
  
  // Essential colors
  background_color: string | null;
  card_background_color: string | null;
  text_primary_color: string | null;
  text_secondary_color: string | null;
  text_on_primary_color: string | null;
  
  // Semantic colors
  success_color: string | null;
  warning_color: string | null;
  danger_color: string | null;
  
  // Optional styling
  accent_color: string | null;
  border_color: string | null;
  background_image: string;  // Full URL
  
  // Dark mode colors
  primary_color_dark: string | null;
  secondary_color_dark: string | null;
  background_color_dark: string | null;
  card_background_color_dark: string | null;
  text_primary_color_dark: string | null;
  text_secondary_color_dark: string | null;
  text_on_primary_color_dark: string | null;
  success_color_dark: string | null;
  warning_color_dark: string | null;
  danger_color_dark: string | null;
  accent_color_dark: string | null;
  border_color_dark: string | null;
}

// ============================================
// EXERCISE RESOURCES
// ============================================

interface ExerciseResource {
  id: number;
  category: CategoryResource | null;
  muscle_groups: MuscleGroupResource[] | null;
  primary_muscle_groups: MuscleGroupResource[] | null;
  secondary_muscle_groups: MuscleGroupResource[] | null;
  name: string;
  description: string | null;
  muscle_group_image: string | null;  // Full URL
  image: string | null;               // Full URL
  video: string | null;               // Full URL
  default_rest_sec: number;
  angle?: AngleResource | null;                    // Optional: loaded via relationship
  movement_pattern?: MovementPatternResource | null;  // Optional: loaded via relationship
  target_region?: TargetRegionResource | null;     // Optional: loaded via relationship
  equipment_type?: EquipmentTypeResource | null;   // Optional: loaded via relationship
  created_at: string;
  updated_at: string;
}

interface ExerciseHistoryResource {
  exercise_id: number;
  exercise_name: string;
  stats: ExerciseHistoryStats;
  performance_data: PerformanceDataPoint[];
}

interface ExerciseHistoryStats {
  current_weight: number;      // Most recent weight used (kg)
  best_weight: number;         // Highest weight ever used (kg)
  current_best_set_reps: number; // Best single-set reps from most recent session
  best_set_reps: number;       // All-time best single-set reps
  progress_percentage: number; // Percentage change from first to most recent
  total_sessions: number;      // Total number of completed sessions
  first_session_date: string | null; // ISO date string (YYYY-MM-DD)
  last_session_date: string | null; // ISO date string (YYYY-MM-DD)
}

interface PerformanceDataPoint {
  date: string;                // ISO date string (YYYY-MM-DD)
  session_id: number;          // Workout session ID
  weight: number;              // Best/max weight used in that session (kg)
  best_set_reps: number;       // Highest reps in a single set for that session
  reps: number;                // Total reps across all sets
  volume: number;              // Total volume = sum of (weight × reps)
  sets: number;                // Number of sets performed
}

interface CategoryResource {
  id: number;
  type: 'workout';
  name: string;  // Equipment type name (e.g., "Bodyweight", "Dumbbell", "Barbell")
  slug: string;  // Equipment type slug (e.g., "bodyweight", "dumbbell", "barbell")
  display_order: number;
  icon: string | null;  // Emoji icon
  color: string | null;  // Hex color
  created_at: string;
  updated_at: string;
  exercises?: ExerciseResource[];  // Only present when loaded via relationship
}

// Available category slugs:
type CategorySlug = 
  | 'bodyweight'
  | 'dumbbell'
  | 'barbell'
  | 'machine-plate-loaded'
  | 'machine-cable'
  | 'cable'
  | 'bands'
  | 'trx';

interface MuscleGroupResource {
  id: number;
  name: string;  // e.g., "Chest", "Biceps", "Quadriceps"
  body_region: BodyRegion;
  is_primary?: boolean;  // Only present when loaded via exercise relationship
  created_at: string;
  updated_at: string;
}

// Available muscle groups (17 total):
// Upper: Chest, Lats, Upper Back, Lower Back, Front Delts, Side Delts, 
//        Rear Delts, Traps, Biceps, Triceps, Forearms
// Lower: Quadriceps, Hamstrings, Glutes, Calves
// Core:  Abs, Obliques

// ============================================
// EXERCISE CLASSIFICATION RESOURCES
// ============================================

interface MovementPatternResource {
  id: number;
  code: string;              // e.g., "PRESS", "FLY", "ROW", "SQUAT"
  name: string;              // e.g., "Press", "Fly", "Row", "Squat"
  display_order: number;
}

interface TargetRegionResource {
  id: number;
  code: string;              // e.g., "UPPER_PUSH", "UPPER_PULL", "LOWER"
  name: string;              // e.g., "Upper Push", "Upper Pull", "Lower Body"
  display_order: number;
}

interface EquipmentTypeResource {
  id: number;
  code: string;              // e.g., "BARBELL", "DUMBBELL", "MACHINE"
  name: string;              // e.g., "Barbell", "Dumbbell", "Machine"
  display_order: number;
}

interface AngleResource {
  id: number;
  code: string;              // e.g., "FLAT", "INCLINE", "DECLINE"
  name: string;              // e.g., "Flat", "Incline", "Decline"
  display_order: number;
}

// ============================================
// PLAN RESOURCES
// ============================================

interface PlanResource {
  id: number;
  user_id: number | null;
  partner_id: number | null;
  name: string;
  description: string | null;
  cover_image: string | null;  // Full URL to cover image, or null if not set
  is_active: boolean;
  is_auto_generated: boolean;
  type: 'routine' | 'program';
  duration_weeks: number | null;  // For program type only
  workout_templates: WorkoutTemplateResource[] | null;
  created_at: string;
  updated_at: string;
}

// Plan Type Details:
// - 'routine': Flexible workout collection (repeatable, no duration/progress tracking)
//   - User-owned: user_id set, partner_id null (full CRUD)
//   - Partner-provided: partner_id set, user_id null (browsable, start workouts directly)
// - 'program': Structured workout plan with duration and progress tracking
//   - Partner-provided: partner_id set, user_id null (browsable, clone to start)
//   - User-owned: user_id set, partner_id null (cloned from library, toggle active only)

// ============================================
// WORKOUT TEMPLATE RESOURCES
// ============================================

interface WorkoutTemplateResource {
  id: number;
  plan_id: number;
  name: string;
  description: string | null;
  day_of_week: DayOfWeek | null;
  week_number: number;     // For programs: which week (1-52)
  order_index: number;     // For programs: sequence order (0+)
  plan: PlanResource | null;
  exercises: TemplateExercise[] | null;
  created_at: string;
  updated_at: string;
}

interface TemplateExercise {
  id: number;  // Exercise ID
  name: string;
  description: string;
  image: string | null;  // Full URL
  video: string | null;  // Full URL
  muscle_group_image: string | null;  // Full URL
  default_rest_sec: number;
  category: CategoryResource | null;
  muscle_groups: MuscleGroupResource[];
  pivot: TemplateExercisePivot;
}

interface TemplateExercisePivot {
  id: number;              // WorkoutTemplateExercise ID (pivot record)
  order: number;
  target_sets: number | null;
  target_reps: number | null;
  target_weight: number | null;
  rest_seconds: number | null;
}

// ============================================
// WORKOUT SESSION RESOURCES
// ============================================

interface WorkoutSessionResource {
  id: number;
  user_id: number;
  workout_template_id: number | null;
  performed_at: string | null;          // ISO 8601, null for draft sessions
  completed_at: string | null;
  notes: string | null;
  status: 'draft' | 'active' | 'completed' | 'cancelled';
  exercises: WorkoutSessionExerciseResource[];
  set_logs: SetLogResource[];
  created_at: string;
  updated_at: string;
}

interface GeneratedSessionResource extends WorkoutSessionResource {
  is_auto_generated: boolean;           // true for auto-generated sessions
  replaced_session_id: number | null;   // ID of session this replaced (null for first generation)
  rationale: string | null;             // explanation of the workout selection
}

interface WorkoutSessionCalendarResource {
  id: number;
  date: string;                       // YYYY-MM-DD
  completed: boolean;
  workout_template_id: number | null;
  workout_name: string | null;
  duration_minutes: number | null;
}

interface WorkoutSessionExerciseResource {
  id: number;  // WorkoutSessionExercise ID
  workout_session_id: number;
  exercise_id: number;
  exercise: ExerciseResource | null;
  order: number;
  target_sets: number | null;
  target_reps: number | null;
  target_weight: number | null;
  rest_seconds: number | null;
  created_at: string;
  updated_at: string;
}

interface SetLogResource {
  id: number;
  workout_session_id: number;
  exercise_id: number;
  set_number: number;
  weight: number;
  reps: number;
  rest_seconds: number | null;
  created_at: string;
  updated_at: string;
}

// ============================================
// FITNESS METRICS RESOURCES
// ============================================

interface FitnessMetrics {
  strength_score: {
    current: number;
    level: StrengthLevel;
    recent_gain: number;
    gain_period: 'last_30_days';
  };
  strength_balance: {
    percentage: number;
    level: BalanceLevel;
    recent_change: number;
    muscle_groups: Record<string, number>;  // Muscle name -> percentage
  };
  weekly_progress: {
    percentage: number;
    trend: TrendDirection;
    current_week_workouts: number;
    previous_week_workouts: number;
  };
}
```

---

## Error Responses

### 401 Unauthorized
```typescript
interface UnauthorizedError {
  message: "Unauthenticated.";
}
```

### 403 Forbidden
```typescript
interface ForbiddenError {
  message: "Unauthorized";
}
// or
interface ForbiddenError {
  message: "Only system administrators can update exercises.";
}
// or
interface ForbiddenError {
  message: "Exercise does not belong to this session.";
}
```

### 404 Not Found
```typescript
interface NotFoundError {
  message: string;  // e.g., "No query results for model [App\\Models\\Exercise] 123"
}
```

### 422 Validation Error
```typescript
interface ValidationError {
  message: "The given data was invalid.";
  errors: Record<string, string[]>;
}

// Example:
{
  message: "The given data was invalid.",
  errors: {
    name: ["The name field is required."],
    email: ["The email has already been taken."],
    category_id: ["The selected category must be a workout category."]
  }
}
```

---

## API Quick Reference

### Authentication & Invitations
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/invitations/{token}` | Validate invitation token |
| POST | `/api/register` | Register new user (requires invitation) |
| POST | `/api/login` | Login user |
| POST | `/api/logout` | Logout user |

### User & Profile
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/user` | Get current user |
| GET | `/api/profile` | Get user profile |
| PUT/PATCH | `/api/profile` | Update profile |
| DELETE | `/api/profile/photo` | Delete profile photo |
| GET | `/api/user/fitness-metrics` | Get fitness metrics |
| POST | `/api/onboarding/complete` | Complete onboarding and generate welcome plan |

### Exercises
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/exercises` | List all exercises |
| GET | `/api/exercises/{id}` | Get single exercise |
| GET | `/api/exercises/{id}/history` | Get exercise performance history |
| POST | `/api/exercises` | Create exercise (admin) |
| PUT/PATCH | `/api/exercises/{id}` | Update exercise (admin) |
| DELETE | `/api/exercises/{id}` | Delete exercise (admin) |

### Muscle Groups
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/muscle-groups` | List muscle groups |
| GET | `/api/muscle-groups/{id}` | Get muscle group |

### Categories
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/categories` | List all categories |
| GET | `/api/categories/{id}` | Get single category |

### Exercise Classifications
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/movement-patterns` | List movement patterns |
| GET | `/api/target-regions` | List target regions |
| GET | `/api/equipment-types` | List equipment types |
| GET | `/api/angles` | List angles |

### Plans (Generic)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/plans` | List user's custom plans |
| GET | `/api/plans/{id}` | Get single custom plan |
| POST | `/api/plans` | Create custom plan |
| POST | `/api/plans/regenerate` | Regenerate personalized program from profile |
| PUT/PATCH | `/api/plans/{id}` | Update custom plan |
| DELETE | `/api/plans/{id}` | Delete custom plan |

### Routines
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/custom-plans` | List user's routines |
| GET | `/api/custom-plans/{id}` | Get single routine |
| POST | `/api/custom-plans` | Create routine |
| PUT | `/api/custom-plans/{id}` | Update routine |
| DELETE | `/api/custom-plans/{id}` | Delete routine |
| GET | `/api/routines` | List partner browsable routines |
| GET | `/api/routines/{routine}` | Get single browsable routine |

### Programs
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/programs` | List user's cloned programs |
| GET | `/api/programs/library` | Browse partner library programs |
| GET | `/api/programs/{id}` | Get single program |
| PATCH | `/api/programs/{id}` | Toggle program active status |
| DELETE | `/api/programs/{id}` | Delete cloned program |
| POST | `/api/programs/{id}/clone` | Clone library program |
| GET | `/api/programs/{id}/next-workout` | Get next workout in program |

### Workout Templates
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/workout-templates` | List templates |
| GET | `/api/workout-templates/{id}` | Get template |
| POST | `/api/workout-templates` | Create template |
| PUT/PATCH | `/api/workout-templates/{id}` | Update template |
| DELETE | `/api/workout-templates/{id}` | Delete template |
| POST | `/api/workout-templates/{id}/exercises` | Add exercise |
| PUT | `/api/workout-templates/{id}/exercises/{exerciseId}` | Update exercise |
| DELETE | `/api/workout-templates/{id}/exercises/{exerciseId}` | Remove exercise |
| POST | `/api/workout-templates/{id}/order` | Reorder exercises |

### Workout Planner
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/planner/weekly` | Get weekly planner |
| POST | `/api/planner/assign` | Assign template to day |
| POST | `/api/planner/unassign` | Unassign template |

### Workout Sessions
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/workout-sessions/calendar?start_date=&end_date=` | Get calendar view |
| GET | `/api/workout-sessions/today` | Get today's workout |
| POST | `/api/workout-sessions/start` | Start session from template |
| POST | `/api/workout-sessions/generate` | Generate draft workout |
| POST | `/api/workout-sessions/{id}/confirm` | Confirm draft session |
| POST | `/api/workout-sessions/{id}/regenerate` | Regenerate draft session |
| GET | `/api/workout-sessions/{id}` | Get session details |
| POST | `/api/workout-sessions/{id}/complete` | Complete session |
| DELETE | `/api/workout-sessions/{id}/cancel` | Cancel session |
| POST | `/api/workout-sessions/{id}/sets` | Log set |
| PUT | `/api/workout-sessions/{id}/sets/{setId}` | Update set |
| DELETE | `/api/workout-sessions/{id}/sets/{setId}` | Delete set |
| POST | `/api/workout-sessions/{id}/exercises` | Add exercise |
| PUT | `/api/workout-sessions/{id}/exercises/{exerciseId}` | Update exercise |
| DELETE | `/api/workout-sessions/{id}/exercises/{exerciseId}` | Remove exercise |
| POST | `/api/workout-sessions/{id}/exercises/reorder` | Reorder exercises |

---

## Usage Examples

### Example: Complete Authentication Flow with Invitation

```typescript
// 1. User clicks invitation link in email (e.g., https://yourapp.com/register?invitation=abc123...)
// Extract token from URL
const invitationToken = new URLSearchParams(window.location.search).get('invitation');

// 2. Validate the invitation token
const validateResponse = await fetch(`/api/invitations/${invitationToken}`);
const { data: invitation } = await validateResponse.json();

// Show partner branding and pre-fill email
console.log(invitation.partner.name);
console.log(invitation.email);

// 3. Register with invitation token
const registerResponse = await fetch('/api/register', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    name: 'John Doe',
    email: invitation.email,  // Must match invitation email
    password: 'password123',
    password_confirmation: 'password123',
    invitation_token: invitationToken
  })
});
const { token, user } = await registerResponse.json();

// 4. Use token for subsequent requests
const headers = {
  'Authorization': `Bearer ${token}`,
  'Content-Type': 'application/json',
  'Accept': 'application/json'
};
```

### Example: Start and Track a Workout

```typescript
// 1. Start session from template
const startResponse = await fetch('/api/workout-sessions/start', {
  method: 'POST',
  headers,
  body: JSON.stringify({ template_id: 1 })
});
const { data: session } = await startResponse.json();

// 2. Log sets as user completes them
await fetch(`/api/workout-sessions/${session.id}/sets`, {
  method: 'POST',
  headers,
  body: JSON.stringify({
    exercise_id: 1,
    set_number: 1,
    weight: 80,
    reps: 10
  })
});

// 3. Complete the workout
await fetch(`/api/workout-sessions/${session.id}/complete`, {
  method: 'POST',
  headers,
  body: JSON.stringify({ notes: 'Great workout!' })
});
```

### Example: Create a Complete Workout Plan

```typescript
// 1. Create a plan
const planResponse = await fetch('/api/plans', {
  method: 'POST',
  headers,
  body: JSON.stringify({
    name: 'My PPL Program',
    description: 'Push/Pull/Legs split',
    is_active: true
  })
});
const { data: plan } = await planResponse.json();

// 2. Create templates for each day
const pushTemplate = await fetch('/api/workout-templates', {
  method: 'POST',
  headers,
  body: JSON.stringify({
    plan_id: plan.id,
    name: 'Push Day',
    day_of_week: 0  // Monday
  })
});

// 3. Add exercises to template
await fetch(`/api/workout-templates/${pushTemplate.data.id}/exercises`, {
  method: 'POST',
  headers,
  body: JSON.stringify({
    exercise_id: 1,  // Bench Press
    target_sets: 4,
    target_reps: 8,
    target_weight: 80
  })
});
```

---

## Important Notes

1. **Day of Week Mapping**: 0 = Monday through 6 = Sunday
2. **Pivot IDs**: When managing exercises in templates/sessions, use the pivot record ID, not the exercise ID
3. **Set Deletion**: Only the last set for an exercise can be deleted
4. **Session Uniqueness**: Only one active (uncompleted) session per day per user
5. **Authorization**: Users can only access their own plans, templates, and sessions
6. **File Uploads**: Profile photos max 2MB, exercise images max 5MB, videos max 50MB, plan cover images max 5MB
7. **Weight Format**: Stored as decimals, may be returned as strings in JSON
