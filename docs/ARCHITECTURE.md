# EDU-SMART · Architecture

This document explains how the platform is put together, where each module's
boundaries are, and why the design decisions were made.

---

## 1. System overview

```
┌────────────────────────┐        ┌────────────────────────┐
│   frontend-web/        │        │   mobile-app/          │
│   HTML · CSS · JS ·    │        │   React Native · Expo  │
│   Bootstrap            │        │   SDK 51               │
│                        │        │                        │
│   Runs on bundled mock │        │   Offline-first: falls │
│   data — no backend    │        │   back to bundled demo │
│   required             │        │   data                 │
└───────────┬────────────┘        └───────────┬────────────┘
            │                                 │
            │  HTTP + JSON                    │  HTTP + JSON
            │  Authorization: Bearer <token>  │  Authorization: Bearer <token>
            └──────────────┬──────────────────┘
                           ▼
            ┌──────────────────────────────┐
            │   backend-laravel/           │
            │   Laravel 11 + Sanctum       │
            │                              │
            │   routes/api.php             │
            │        │                     │
            │   Controllers (11)           │
            │        │                     │
            │   Services (3)  ◀── domain   │
            │        │            logic    │
            │   Models (14)                │
            └──────────────┬───────────────┘
                           ▼
            ┌──────────────────────────────┐
            │   MySQL 8 — `edu_smart`      │
            │   16 tables + 1 view         │
            └──────────────────────────────┘
```

Both clients are **thin**: they render state and call the API. All authoritative
business rules live in `app/Services/`. Two of those rules are deliberately
mirrored on the clients (see §6) so the UI can react instantly and keep working
offline.

---

## 2. Module ownership

The platform is split by *team ownership*, not by technical layer. Each owner has
their own tables, controller, service (where needed), web page and mobile screen.

| Owner | Module | Tables | Controller | Service | Web page | Mobile screen |
| ----- | ------ | ------ | ---------- | ------- | -------- | ------------- |
| — | Common Platform Layer | `users`, `modules`, `notifications` | `AuthController`, `ModuleController`, `NotificationController`, `DashboardController`, `CalendarController` | — | `index`, `dashboard`, `profile`, `calendar` | `LoginScreen`, `DashboardScreen` |
| **Bethmi** | Smart Notes & Documents | `documents`, `summaries` | `DocumentController`, `SummaryController` | `SummaryGenerator` | `learning.html` | `LearningScreen` |
| **Pasindu** | Study & Engagement | `study_sessions`, `engagement_logs` | `StudySessionController` | — | `study.html` | `StudyScreen` |
| **Kavishka** | AI Academic Assistant | `academic_documents`, `academic_chunks`, `chat_conversations`, `chat_messages`, `academic_dates` | `AssistantController` | `RetrievalService` | `assistant.html` | `AssistantScreen` |
| **Jithmi** | Assignment & Deadline Risk | `assignments`, `risk_assessments` | `AssignmentController` | `RiskCalculator` | `assignments.html` | `AssignmentsScreen` |

### The ownership rule

Modules **exchange data**, they never invoke each other's functionality.

- ✅ Jithmi reads a `study_sessions.actual_minutes` value to recompute risk.
- ❌ Jithmi never calls a Pasindu method to start or stop a timer.

This means one module can be rewritten, redeployed or marked independently as long
as it keeps honouring the shared table contracts. Every cross-module write happens
in exactly one place, documented in §4.

---

## 3. Common Platform Layer

Everything that is not owned by a single module:

| Concern | Implementation |
| ------- | -------------- |
| Authentication | Laravel Sanctum personal access tokens (`personal_access_tokens` table). `AuthController` issues tokens on register/login; `auth:sanctum` guards every other route. |
| Authorisation | Per-record ownership checks. Every controller calls `authorizeOwner()` → `abort_unless($model->user_id === $request->user()->id, 403)`. There are no admin roles; the app is single-tenant per student. |
| Profile | `users.program`, `academic_year`, `dark_mode`, `daily_target_minutes` |
| Modules (subjects) | `modules` table — the student's enrolled subjects, referenced by documents, sessions and assignments |
| Notifications | `notifications` with a `module_source` column (`bethmi|pasindu|kavishka|jithmi|common`) so any module can raise one without owning the feature |
| Overview hub | `DashboardController` aggregates all four modules into one payload so the dashboard needs a single request |
| Calendar | `CalendarController` merges Kavishka's academic dates with Jithmi's deadlines into one chronological feed |

### Why `DashboardController` aggregates

Without it, the dashboard would fire 4–6 requests on load and each module screen
would need to expose a "summary" endpoint. One aggregation controller keeps the
module APIs focused on their own domain and makes the hub a single round-trip.

---

## 4. Integration points

Four data-exchange seams. Each is a foreign key plus one clearly-named place where
the write happens.

### 4.1 Bethmi → Kavishka (corpus)

Bethmi's `documents.extracted_text` is the raw material Kavishka's assistant
grounds answers in. Kavishka keeps its own `academic_documents` /
`academic_chunks` tables for institutional material (handbooks, regulations), so
neither module has to change the other's schema.

### 4.2 Kavishka → Jithmi + Common (academic dates)

```sql
assignments.academic_date_id  →  academic_dates.id   ON DELETE SET NULL
```

When Kavishka extracts a deadline from a handbook, Jithmi can attach an assignment
to that date. `SET NULL` means deleting the academic date never destroys the
assignment. `CalendarController` then merges both feeds:

```php
// Kavishka events  → ['source' => 'kavishka', 'type' => 'Exam'|'Deadline'|...]
// Jithmi events    → ['source' => 'jithmi',  'meta' => ['risk_level' => ...]]
usort($events, fn ($x, $y) => strcmp($x['date'], $y['date']));
```

### 4.3 Jithmi → Pasindu (urgent task)

```sql
study_sessions.assignment_id  →  assignments.id   ON DELETE SET NULL
```

The "Focus on this first" card (`GET /api/assignments/recommendation`) offers
*Start study session*. The UI navigates to Pasindu's timer carrying the
`assignment_id`; Pasindu stores it on the session.

### 4.4 Pasindu → Jithmi (progress write-back)

The only cross-module **write**. It lives in exactly one method:

```php
// StudySessionController::applyProgressToAssignment()
$hours = round($session->actual_minutes / 60, 1);
$assignment->done_hours = round($assignment->done_hours + $hours, 1);
$assignment->progress   = min(100, round($assignment->done_hours / $assignment->est_hours * 100));
```

Called only when a session transitions to `completed`. Because `done_hours` and
`progress` are the two inputs to the risk formula, finishing a study session
immediately lowers that assignment's risk score — the loop closes without Jithmi
knowing Pasindu exists.

### 4.5 Bethmi → Pasindu (material)

```sql
study_sessions.document_id  →  documents.id   ON DELETE SET NULL
```

A study session can record which learning material was used, feeding Bethmi's
"most-studied documents" statistics.

---

## 5. Backend layering

```
routes/api.php
      │
      ▼
app/Http/Controllers/Api/*Controller.php     ← validation, auth, HTTP shape
      │
      ├──▶ app/Services/*.php                ← domain rules (pure, testable)
      │
      └──▶ app/Models/*.php                  ← Eloquent, relations, casts
                 │
                 ▼
              MySQL
```

**Controllers** never contain domain maths. They validate input, resolve the
authenticated user, delegate to a service, and shape the JSON response.

**Services** are constructor-injected and hold no HTTP or Eloquent state:

| Service | Owner | Responsibility |
| ------- | ----- | -------------- |
| `RiskCalculator` | Jithmi | `assess()` → `{score, level, hours_per_day, reasons, days}`; `rank()`; `recalculate()` persists a `risk_assessments` snapshot |
| `SummaryGenerator` | Bethmi | Extractive summarisation (`generate()`) + keyword extraction (`extractKeywords()`) |
| `RetrievalService` | Kavishka | RAG scoring (`rank`, `best`) and grounded answer composition (`answer`) |

**Models** declare relations and `$casts` (JSON columns → arrays, dates → Carbon).

### 5.1 Laravel 11 skeleton

The backend uses the slim Laravel 11 layout — there is no `app/Http/Kernel.php`:

```php
// bootstrap/app.php
Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: ..., api: ..., commands: ..., health: '/up')
    ->withMiddleware(...)
    ->withExceptions(...)
    ->create();
```

The `api` routing entry means every route in `routes/api.php` is automatically
prefixed with `/api` — do not add the prefix again in the route definitions.

### 5.2 Config choices that keep it runnable

| Setting | Value | Why |
| ------- | ----- | --- |
| `CACHE_STORE` | `file` | No `cache` table to create |
| `SESSION_DRIVER` | `file` | No `sessions` table needed at runtime (API is token-based) |
| `QUEUE_CONNECTION` | `sync` | No `jobs` / `failed_jobs` tables, no queue worker |
| `MAIL_MAILER` | `log` | No SMTP credentials needed to boot |

The result: the app boots against **only** the EDU-SMART schema tables.

### 5.3 Route ordering matters

Static segments are declared **before** `apiResource` wildcard routes, otherwise
Laravel binds `rank` as an `{assignment}` parameter and 404s:

```php
Route::get('/assignments/rank', ...);            // ✅ first
Route::get('/assignments/recommendation', ...);  // ✅ first
Route::post('/assignments/whatif', ...);
Route::apiResource('assignments', ...);          // ✅ last
```

Same pattern for `/study/current` and `/study/analytics` before
`/study/sessions/{session}`, and `/documents/{document}/keywords` before the
documents resource.

### 5.4 Response shapes the mobile client depends on

The React Native screens normalise each endpoint into a flat row before
rendering, so the JSON shape is part of the contract — changing one side without
the other silently blanks a list. The shapes are **not** uniform:

| Endpoint | Row shape | Mobile normaliser |
| -------- | --------- | ----------------- |
| `GET /api/assignments` | **nested** `{ assignment, risk, status }` | `AssignmentsScreen.normalise` unwraps `row.assignment ?? row` |
| `GET /api/assignments/recommendation` | `{ assignment, risk, days_left, suggested_per_day, suggested_minutes, message }` | read directly |
| `GET /api/documents`, `/api/summaries` | **flat** Eloquent rows (+ `module` / `document` relation) | `normaliseDoc`, `normaliseSummary` |
| `GET /api/study/analytics` | flat `{ weekly[], planned_minutes, … , daily_target }` | `normaliseAnalytics` |
| `GET /api/assistant/dates` | flat `AcademicDate` rows | inline map |
| `POST /api/assistant/chat` | `{ conversation_id, reply, source }` | read directly |
| `GET /api/notifications` | flat rows keyed `module_source`, `created_at` | `DashboardScreen.normaliseNotification` → `{ source, minutesAgo }` |

`GET /api/assignments` is the one nested list endpoint: it wraps the model so the
computed `risk` and `status` travel with it. The normaliser therefore accepts
both the nested API row and the flat bundled-demo row (`row.assignment ?? row`);
reading the wrapper's fields directly yields `undefined` titles and an empty
list. Every normaliser is written to accept the demo shape too, so online and
offline render identically.

---

## 6. Duplicated-by-design logic

Some rules exist in more than one layer on purpose, because the UI must show a risk
score the instant a slider moves and both clients must stay fully functional with no
backend. They fall into two groups, and the distinction matters: **faithful ports**
must stay arithmetically identical to the PHP, while **independent variants** are
free to differ.

### 6.1 Faithful ports — must match PHP exactly

| Rule | PHP (authoritative) | Ports |
| ---- | ------------------- | ----- |
| Risk scoring | `app/Services/RiskCalculator.php` → `assess()` | `frontend-web/assets/js/mock-data.js` → `ES.calcRisk`<br>`mobile-app/src/api/risk.js` → `calcRisk` |
| RAG retrieval | `app/Services/RetrievalService.php` → `score()` / `answer()` | `mobile-app/src/api/retrieve.js` |
| Risk levels | `RiskCalculator::levelFor()` | both risk scorers above |

Shared constants — change them in every copy at once:

```
DAILY_CAPACITY   = 5.0      // hours a student can reasonably study per day
MATCH_THRESHOLD  = 0.08     // minimum normalised retrieval score
```

Four porting traps worth knowing, because the first two were live bugs here:

- **`RetrievalService::score()` must not derive content frequencies from
  `tokens()`.** `tokens()` de-duplicates, so `array_count_values()` over its output
  pins every frequency to 1 and silently reduces `1 + log(freq)` to a flat `1 + 0` —
  the log damping stops doing anything and the API can rank chunks differently from
  the mobile port. Use `RetrievalService::termFrequency()` (raw counts) instead.
- **Keyword entries may be phrases, so tokenise them before matching.** Both
  scorers compare a *single* query token against the keyword list by exact equality
  (`in_array($token, $keywords, true)` in PHP, `keywords.has(token)` in JS). Seeded
  phrases such as `special consideration`, `late submission` and `medical
  certificate` can therefore never equal a one-word token: 8 of the seeder's
  keyword entries were inert, quietly denying those chunks the 3.0 keyword bonus.
  Both `score()` implementations now pass the keyword list through their own
  `tokens()` first, which keeps authored phrases working and strips stop words out
  of them. Change one and you must change the other, or the API and the offline
  app will rank the same question differently.
- **Rounding.** PHP `round()` and JS `Math.round()` only disagree on exact `.5`
  negatives; every score here is non-negative, so they coincide.
- **Dates are local-calendar, never UTC.** `ES.isoDate()` (web) and `isoDate()`
  (`mobile-app/src/api/risk.js`) build `YYYY-MM-DD` from *local* components.
  `new Date().toISOString().slice(0, 10)` looks equivalent but returns the **UTC**
  date, which is the previous calendar day for any positive UTC offset. Since
  `daysUntil()` parses `YYYY-MM-DD` as local midnight, mixing the two shifts every
  deadline by a day: the mobile what-if panel reported `[100, 65, 42, 15]` instead
  of `[90, 55, 40, 14]` at *zero* delta for anyone east of UTC. Use `isoDate()`,
  which takes an optional base date and is the exact inverse of `daysUntil()`.

### 6.2 Independent variants — intentionally different

| Concern | Implementation | Why it differs |
| ------- | -------------- | -------------- |
| Web assistant retrieval | `frontend-web/assets/js/assistant.js` → `retrieve()` | Matches *whole documents* by substring keyword overlap (raw threshold `>= 2`) and returns a hand-written answer per knowledge-base entry. Keeps the web layer build-free and backend-free. It is **not** a port of `RetrievalService` and shares neither its constants nor its scoring shape. |
| Risk pill colours | `frontend-web/assets/css/style.css`<br>`mobile-app/src/theme.js` → `riskColor` | Same four level names and hex values, expressed as CSS custom properties vs. a JS token object. |

### 6.3 Demo-dataset parity

The mobile app flips between the live API and its bundled offline demo, so the two
must show the *same* demo. The **module catalogue** and the **assignment rows** are
therefore held identical across all three layers, and mobile and the API additionally
share the same three Kavishka knowledge-base documents. The assignments are tuned so
a fresh demo exercises every risk level instead of collapsing onto one:

| Assignment | Module | days left | est h | done h | progress | score | level |
| ---------- | ------ | --------- | ----- | ------ | -------- | ----- | ----- |
| Database Project | Database Systems | 3 | 20 | 5 | 25 % | **90** | Critical |
| Web Assignment | Programming (OOP) | 4 | 16 | 6 | 38 % | **55** | High |
| Research Report | Software Engineering | 9 | 15 | 5 | 33 % | **40** | Medium |
| ML Mini Project | AI & Machine Learning | 20 | 12 | 8 | 70 % | **14** | Low |

Sources — all three must be edited together:

| Concern | Web | Mobile | API |
| ------- | --- | ------ | --- |
| Modules | `mock-data.js` → `ES.modules` | `demo.js` → `demoModules` | `DatabaseSeeder.php` → `$subjects` |
| Assignments | `mock-data.js` → `ES.assignments` | `demo.js` → `demoAssignments` | `DatabaseSeeder.php` → `$assignmentDefs` |
| Knowledge base | `assistant.js` → `KB` — **independent**, see §6.2 | `demo.js` → `demoKnowledgeBase` | `DatabaseSeeder.php` → `$chunks` |

All three paths are relative to `frontend-web/assets/js/`, `mobile-app/src/data/` and
`backend-laravel/database/seeders/`. The module codes, names, order, icons and accent
colours match; the seeder stores the accent as hex (`bethmi #3b82f6`,
`pasindu #14b8a6`, `kavishka #8b5cf6`, `jithmi #f97316`) where the clients store the
accent name.

Mobile and the seeder carry the *same three* knowledge-base documents — University
Student Handbook 2026, Final Year Project Guidelines, Examination & Grading
Regulations, with matching categories and page counts — but the seeder indexes them
into 7 chunks against the bundle's 5, so the offline corpus is a subset. The web
assistant is neither: it has its own five pre-written documents and its own scoring
shape (§6.2).

The seeder carries a fifth, already-submitted row (`Programming Practice Set`,
2 days overdue, 100 % done) to exercise the completed state; `RiskCalculator` clamps
negative day counts to 0, so it scores 0 and drops out of the ranking.

**Deliberately not aligned:** Bethmi's document list is a per-layer sample (web 7,
mobile 5, seeder 6) and so are the academic-calendar entries. Each layer's set is
internally consistent and module-linked, but they are illustrative content rather
than a shared contract — unlike the three concerns above.

Every demo date — deadlines, upload dates, session history and the academic
calendar — is stored as an **offset from today** (`ES.isoDate(n)` in the web layer,
`isoDate(n)` from `mobile-app/src/api/risk.js` in mobile, `now()->addDays(n)` in the
seeder), never as an absolute date, so the demo cannot decay into "everything
overdue" next month. See §6.1 for why those helpers must not use `toISOString()`.

> **If you change `RiskCalculator::assess()` or `RetrievalService::score()`, update
> every port in §6.1 and every dataset in §6.3 in the same commit.**

Reproduce the parity check with no PHP, no Composer and no database — run the real
client code in Node against the real seeder source and assert 90/55/40/14:

```js
// scratch file check.mjs — run `node check.mjs`, then delete it.
// Node picks CJS vs ESM from the nearest package.json "type", so copy the two
// ESM sources to *.mjs first (or run from a folder with no package.json).
globalThis.window = globalThis;                    // mock-data.js is a browser script
await import('./mock.mjs');                        // = frontend-web/assets/js/mock-data.js
const { calcRisk } = await import('./risk.mjs');   // = mobile-app/src/api/risk.js
const { demoAssignments } = await import('./demo.mjs'); // = mobile-app/src/data/demo.js

console.log(ES.assignments.map((a) => ES.calcRisk(a).score));    // [90, 55, 40, 14]
console.log(demoAssignments.map((a) => calcRisk(a).score));      // [90, 55, 40, 14]
console.log(ES.assignments.map((a) => ES.calcRisk(a).level));    // Critical…Low
```

---

## 7. Client architecture

### 7.1 Web (`frontend-web/`)

Deliberately **build-free**: plain HTML pages, one JS file per page, Bootstrap from
CDN. No bundler, no `node_modules`, no transpile step — open the file and it works.

```
assets/js/app.js        ← shared shell: nav, theme, toast, auth guard
assets/js/mock-data.js  ← ES namespace: datasets + calcRisk + formatters
assets/js/<page>.js     ← one controller per page, reads ES.*
```

`ES` (the global namespace in `mock-data.js`) is the single source of truth for
shapes, so a page never invents its own field names.

### 7.2 Mobile (`mobile-app/`)

```
App.js
 └─ SafeAreaProvider
     └─ AuthProvider            ← src/context/AuthContext.js
         └─ RootNavigator       ← Login  |  Tabs(Home, Learning, Study, Assistant, Risk)
```

**Offline-first is the key decision.** On launch `AuthContext` calls
`GET /api/health`:

| Result | Behaviour |
| ------ | --------- |
| Reachable | Real Sanctum login; screens render live API data; header shows `Live API` |
| Unreachable | Auto signs in as the demo student; screens render `src/data/demo.js`; header shows `Offline demo` |

`client.js#request()` returns `null` on timeout/DNS failure rather than throwing, so
no screen can crash because the backend is absent. Only genuine HTTP errors (4xx/5xx
with a JSON payload) propagate, because those carry a message worth showing.

Demo dates are generated relative to *today* (`iso(offsetDays)`), so risk levels
always look meaningful no matter when the app is opened.

---

## 8. Database design notes

| Decision | Rationale |
| -------- | --------- |
| `ON DELETE CASCADE` from every table to `users` | Deleting a student removes their data completely; no orphaned rows |
| `ON DELETE SET NULL` for `module_id`, `document_id`, `assignment_id`, `academic_date_id` | Cross-module references must never destroy the referencing record |
| `FULLTEXT` on `documents(title, topic, extracted_text)` and `academic_chunks(content, section)` | Real search over document text without an external search engine |
| `JSON` columns for `summaries.keywords` and `risk_assessments.reasons` | Variable-length lists that are always read whole, never queried by element |
| `risk_assessments` is append-only | Keeps the risk history so trends can be charted; `v_latest_risk` view exposes the current snapshot |
| `DECIMAL(5,1)` for hours | Avoids float rounding when summing study time |
| `utf8mb4` everywhere | Sinhala/Tamil/emoji-safe |

Two provisioning paths exist and **must not be mixed** — see
[`DATABASE.md`](DATABASE.md#provisioning-choose-one-path).

---

## 9. Security model

| Control | Where |
| ------- | ----- |
| Password hashing | `User` model casts `password` → `hashed` (bcrypt via Laravel) |
| Token auth | Sanctum `personal_access_tokens`; `auth:sanctum` on every non-public route |
| Per-record ownership | `authorizeOwner()` in every controller that touches a model by id |
| Input validation | `$request->validate([...])` at the top of every write action |
| SQL injection | Eloquent query builder + bound parameters throughout |
| Path traversal | `serve.js` checks `filePath.startsWith(ROOT)` |
| CORS | `config/cors.php` — restrict `allowed_origins` before deploying |
| Mass assignment | `$fillable` on every model |

Public routes are exactly three: `GET /api/health`, `POST /api/register`,
`POST /api/login`.

---

## 10. Known limitations & next steps

| Area | Current state | Suggested next step |
| ---- | ------------- | ------------------- |
| Web ↔ API | Web frontend runs on mock data only | Add an `ES.api` fetch layer mirroring `mobile-app/src/api/client.js` |
| Document text extraction | Plain-text (`.txt`/`.md`) only | Add a PDF parser (e.g. `smalot/pdfparser`) behind `DocumentController::store` |
| Assistant retrieval | Lexical scoring | Swap in embeddings + a vector index behind the same `RetrievalService` interface |
| Notifications | Pull-based (polled) | Push via queue + FCM/APNs |
| Tests | None included | Feature tests per controller; unit tests for the three services |
| CI | None | Lint (Pint, ESLint) + `expo export` bundle check + `php artisan test` |
| Roles | Single student role | Add lecturer/admin roles with policies |

---

## See also

- [`API.md`](API.md) — endpoint reference
- [`DATABASE.md`](DATABASE.md) — schema and provisioning
- [`SETUP-WEB.md`](SETUP-WEB.md) · [`SETUP-BACKEND.md`](SETUP-BACKEND.md) · [`SETUP-MOBILE.md`](SETUP-MOBILE.md)
