# Laravel API Skeleton

Basic structure aligned to the PRD. Run `composer create-project laravel/laravel .` inside `backend/` (or use `laravel new api --api`) to pull the full framework, then migrate these stubs into the generated app.

## Folders & Intent
- `app/Models/` — Users, Students, Teachers, Managers, Lessons, LessonMedia, Quizzes, MonthlyTests, Attendance, Payments, etc.
- `app/Http/Controllers/` — Grouped by domain (Auth, Users, Lessons/Streaming, Tests, Attendance, Payments, Guidance).
- `app/Http/Resources/` — API responses for dashboard lists and detail views.
- `app/Services/` — Cloudflare Stream service (live + VOD + webhook), Payments service, Attendance service.
- `database/migrations/` — Migrations that match the PRD schema.
- `routes/api.php` — API-first routes (Sanctum/JWT guarded), no web routes.
- `config/cloudflare.php` — Account, signing key, live input defaults.

## Environment (expected)
- `APP_URL`, `FRONTEND_URL`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_STREAM_TOKEN`, `CLOUDFLARE_SIGNING_KEY`

## Next steps
1) Initialize Laravel, keeping `--api` flag to skip Blade.
2) Create migrations using the PRD tables (users, students, teachers, managers, levels, classes, subjects, specializations, lessons, lesson_media, quizzes, monthly_tests, papers_work, attendance, teacher_time_table, track_teachers, fees, payments, class_activities, student_guidance, marks).
3) Flesh out controllers and services, wire Sanctum/JWT middleware, and add Cloudflare webhook endpoint for VOD completion.
4) Generate OpenAPI docs to keep parity with frontend routes.
