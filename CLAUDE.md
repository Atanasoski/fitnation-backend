# muscle-hustle

Fitness tracking web app + mobile API. Laravel 12, Blade templates, Tailwind CSS 4 (TailAdmin-based admin panel) + Alpine.js, MySQL.

## Tech stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Blade + Tailwind CSS 4 + Alpine.js + Vite (admin panel derived from the TailAdmin template)
- **Database**: MySQL (SQLite optional for local dev)
- **Auth**: Laravel Breeze + Laravel Sanctum (API tokens)
- **Queue**: database driver (queue:listen in dev)
- **Payments**: RevenueCat webhooks (via spatie/laravel-webhook-client)
- **Storage**: AWS S3 (league/flysystem-aws-s3-v3)
- **Testing**: PHPUnit 11

## Key commands

```bash
composer dev        # Start everything: server + queue + logs + vite (concurrently)
composer test       # Run the full test suite (php artisan test)
php artisan tinker  # REPL for interactive debugging
php artisan migrate # Run migrations
./vendor/bin/pint   # Code style fixer (Laravel Pint)
```

## Project structure

```
app/
  Http/Controllers/       # Web controllers (auth, dashboard, exercises, plans…)
  Http/Controllers/Api/   # API controllers (mobile clients)
  Models/                 # Eloquent models
  Services/               # Business logic (FitnessMetricsService, PlanService, WorkoutGenerator…)
  Enums/                  # PHP enums (SubscriptionStatus, Entitlement, PartnerPlan…)
  Webhooks/RevenueCat/    # RevenueCat webhook processing
tests/
  Feature/                # Integration tests (hit real DB via RefreshDatabase)
  Unit/                   # Unit tests for isolated logic
```

## Branding

The admin panel must match the marketing site (`front-end/apps/landing_page`):

- Colors live in `resources/css/app.css` `@theme`: `brand-*` is the teal ramp (`brand-500 = #14b3c1`), `orange-500 = #f86f33` is the accent. Use these tokens, never raw hex.
- Fonts: Inter (body, `font-sans`) and Poppins (headings, `font-display`).
- Logo is the globe icon: `public/images/logo/logo.png`, shown round (`rounded-full`); favicon/apple-touch-icon are next to it. Wordmark is "Fit Nation" (with a space), from `config('app.name')`.
- There is no Blade landing page; the marketing site is deployed separately. `/get` is only a device-aware store redirect.

## Domain concepts

- **Plan** — a structured training program with one or more splits
- **WorkoutSplit** — a weekly schedule of workout days within a plan
- **WorkoutTemplate** — a reusable template of exercises for a session
- **WorkoutSession** — a logged instance of a completed workout
- **Exercise** — a catalogued movement (with muscle groups, category, equipment)
- **SetLog** — a logged set within a session (reps, weight)
- **Partner** — a gym/business account that manages users
- **Subscription** — RevenueCat subscription record (status, entitlements)
- **Entitlement** — feature access level (premium, etc.)

## Testing conventions

- Feature tests extend `Tests\TestCase` and use `RefreshDatabase`
- Use model factories for seeding test data
- API tests use `actingAs($user, 'sanctum')`
- Test files live alongside what they test: `tests/Feature/WorkoutSessionGenerationTest.php`

## Branch convention

- `feat/<description>` → main
- `fix/<description>` → main
- Never commit directly to `main`
