# EDU-SMART · Backend (Laravel 11)

REST API powering the web and mobile clients. Laravel 11 slim skeleton, Sanctum
token auth, MySQL 8.

```bash
composer install
copy .env.example .env          # macOS/Linux: cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve               # → http://localhost:8000
```

Verify:

```bash
curl http://localhost:8000/api/health
# {"status":"ok","service":"edu-smart-api","time":"..."}
```

Full instructions: [`../docs/SETUP-BACKEND.md`](../docs/SETUP-BACKEND.md)

**Requires PHP 8.2+, Composer 2 and MySQL 8.**

---

## Two provisioning paths — pick ONE

| Path | Commands | Notes |
| ---- | -------- | ----- |
| **A — migrations** (recommended) | `php artisan migrate --seed` | 7 migrations + demo data |
| **B — raw SQL** | `mysql -u root -p < database/sql/schema.sql` then `php artisan db:seed` | Also creates the `v_latest_risk` view |

> ⚠️ **Never mix them.** `schema.sql` leaves the `migrations` table empty, so
> running `php artisan migrate` afterwards fails with *"table already exists"*.
> Recover with `php artisan migrate:fresh --seed`.

> ⚠️ **Do not run `php artisan install:api`.** Sanctum ships its own
> `personal_access_tokens` migration; the scaffolding command would duplicate it and
> rewrite `bootstrap/app.php`.

Details: [`../docs/DATABASE.md`](../docs/DATABASE.md)

---

## Layout

```
backend-laravel/
├─ artisan                          # CLI entry
├─ composer.json                    # laravel/framework ^11, sanctum ^4, tinker ^2.9
├─ .env.example
├─ bootstrap/
│  ├─ app.php                       # Application::configure(...) — Laravel 11, no Kernel.php
│  └─ providers.php
├─ public/
│  ├─ index.php                     # web entry point (document root goes HERE)
│  └─ .htaccess
├─ routes/
│  ├─ api.php                       # ← every endpoint (auto-prefixed with /api)
│  ├─ web.php
│  └─ console.php
├─ config/                          # 14 files: app, auth, database, cors, sanctum…
├─ app/
│  ├─ Http/Controllers/
│  │  ├─ Controller.php             # abstract base
│  │  └─ Api/                       # 11 controllers
│  ├─ Models/                       # 14 Eloquent models
│  ├─ Providers/AppServiceProvider.php
│  └─ Services/                     # RiskCalculator · SummaryGenerator · RetrievalService
├─ database/
│  ├─ migrations/                   # 7 files
│  ├─ seeders/DatabaseSeeder.php
│  └─ sql/schema.sql                # standalone schema (16 tables + view)
└─ storage/                         # logs, uploads, framework caches
```

---

## Layering

```
routes/api.php
      ▼
Controllers        ← validation, auth, HTTP shape
      ├──▶ Services    ← domain rules (pure, no HTTP/Eloquent state)
      └──▶ Models      ← Eloquent relations + casts
                ▼
             MySQL
```

Controllers never contain domain maths — they validate, resolve the authenticated
user, delegate to a service, and shape the response.

### Controllers by owner

| Owner | Controllers |
| ----- | ----------- |
| Common | `AuthController`, `ModuleController`, `NotificationController`, `DashboardController`, `CalendarController` |
| **Bethmi** | `DocumentController`, `SummaryController` |
| **Pasindu** | `StudySessionController` |
| **Kavishka** | `AssistantController` |
| **Jithmi** | `AssignmentController` |

### Services

| Service | Owner | Responsibility |
| ------- | ----- | -------------- |
| `RiskCalculator` | Jithmi | `assess()` → `{score, level, hours_per_day, reasons, days}` · `rank()` · `recalculate()` persists a snapshot |
| `SummaryGenerator` | Bethmi | Extractive summarisation (`generate()`) + `extractKeywords()` |
| `RetrievalService` | Kavishka | RAG scoring (`rank`, `best`) and grounded answer composition (`answer`) |

Both `RiskCalculator` and `RetrievalService` are mirrored in the clients
(`mobile-app/src/api/risk.js`, `mobile-app/src/api/retrieve.js`,
`frontend-web/assets/js/mock-data.js`). **If you change the PHP formula, update the
client copies in the same commit.**

---

## Models

`User`, `Module`, `Notification` · `Document`, `Summary` · `StudySession`,
`EngagementLog` · `AcademicDocument`, `AcademicChunk`, `ChatConversation`,
`ChatMessage`, `AcademicDate` · `Assignment`, `RiskAssessment`

Every model declares `$fillable` and the needed `$casts` (JSON → array, dates →
Carbon). Every controller that resolves a model by id calls `authorizeOwner()`:

```php
abort_unless($model->user_id === $request->user()->id, 403, '…belongs to another user.');
```

---

## Environment

`.env.example` ships with working local defaults.

| Variable | Default | Notes |
| -------- | ------- | ----- |
| `APP_KEY` | *(empty)* | Set via `php artisan key:generate` |
| `APP_DEBUG` | `true` | `false` in production |
| `FRONTEND_URL` | `http://localhost:5173` | Origin allowed by `config/cors.php` |
| `DB_DATABASE` | `edu_smart` | |
| `DB_USERNAME` / `DB_PASSWORD` | `root` / *(empty)* | Set your MySQL credentials |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:5173,…` | Only matters for cookie-based SPA auth |

Drivers are set so the app boots against **only** the EDU-SMART tables:

| Setting | Value | Avoids needing |
| ------- | ----- | -------------- |
| `CACHE_STORE` | `file` | a `cache` table |
| `SESSION_DRIVER` | `file` | a `sessions` table |
| `QUEUE_CONNECTION` | `sync` | `jobs` / `failed_jobs` tables + a worker |
| `MAIL_MAILER` | `log` | SMTP credentials |

After editing `.env`: `php artisan config:clear`.

---

## Demo data

`DatabaseSeeder` truncates every table (FK checks off), then creates:

| Data | Volume |
| ---- | ------ |
| Demo student | `student@edusmart.lk` / `password` |
| Subject modules | 4 |
| Learning documents (with `extracted_text`) | 6 |
| Academic documents + chunks | 3 documents, 7 chunks |
| Academic dates | 4 |
| Assignments | 4 |
| Study sessions + engagement logs | 7 sessions |
| Chat conversation | 1 |
| Notifications | 4 |

Summaries, risk scores and the chat answer are **generated by the real services** at
seed time — not hard-coded — so they always match what the API returns. Deadlines
are relative to today, so risk levels stay meaningful whenever you run it.

Reset: `php artisan migrate:fresh --seed`

---

## Commands

```bash
php artisan route:list --path=api      # every endpoint
php artisan tinker                     # REPL: App\Models\User::first()
php artisan migrate:fresh --seed       # reset the database
php artisan db:seed                    # seed only
php artisan config:clear               # after editing .env
php artisan serve --host=0.0.0.0       # reachable from a physical mobile device
./vendor/bin/pint                      # format PHP (dev dependency)
```

---

## Production

1. `APP_ENV=production`, `APP_DEBUG=false`.
2. `php artisan config:cache route:cache view:cache`.
3. Document root → `public/` — **never** the project root.
4. Restrict `allowed_origins` in `config/cors.php`.
5. Serve over HTTPS — Sanctum tokens are bearer credentials.
6. Add a real `QUEUE_CONNECTION` / `MAIL_MAILER` if you add background work.

---

## Status

Complete and self-contained, but **not executed here** — the machine this was
authored on has no PHP toolchain. Run `composer install` then
`php artisan migrate --seed` on a machine with PHP 8.2+ to bring it up.

---

## See also

- [`../docs/SETUP-BACKEND.md`](../docs/SETUP-BACKEND.md) — full setup + troubleshooting
- [`../docs/API.md`](../docs/API.md) — endpoint reference and smoke tests
- [`../docs/DATABASE.md`](../docs/DATABASE.md) — schema, relationships, seeding
- [`../docs/ARCHITECTURE.md`](../docs/ARCHITECTURE.md) — layering and integration points
