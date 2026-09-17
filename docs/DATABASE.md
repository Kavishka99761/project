# EDU-SMART · Database

MySQL 8.0+ · database `edu_smart` · `utf8mb4` / `utf8mb4_unicode_ci` · InnoDB.

**16 tables** (14 domain + 2 Laravel infrastructure) and **1 view**.

---

## Provisioning: choose ONE path

There are two ways to build the schema. **Do not use both.**

### Path A — Laravel migrations (recommended)

```bash
cd backend-laravel
php artisan migrate --seed
```

Runs the 7 migrations in order and then `DatabaseSeeder`, which creates the demo
student plus realistic data for all four modules (generated through the real
`SummaryGenerator`, `RiskCalculator` and `RetrievalService` services — not
hard-coded values).

| # | Migration | Creates |
| - | --------- | ------- |
| 1 | `0001_01_01_000000_create_users_table` | `users`, `password_reset_tokens`, `sessions` (Laravel base) |
| 2 | `2026_01_01_000001_create_common_tables` | **alters** `users` (+`program`, `academic_year`, `dark_mode`, `daily_target_minutes`); `modules`, `notifications` |
| 3 | `2026_01_01_000002_create_learning_tables` | `documents`, `summaries` — *Bethmi* |
| 4 | `2026_01_01_000003_create_study_tables` | `study_sessions`, `engagement_logs` — *Pasindu* |
| 5 | `2026_01_01_000004_create_assistant_tables` | `academic_documents`, `academic_chunks`, `chat_conversations`, `chat_messages`, `academic_dates` — *Kavishka* |
| 6 | `2026_01_01_000005_create_assignment_tables` | `assignments`, `risk_assessments` — *Jithmi* |
| 7 | `2026_01_01_000006_add_integration_foreign_keys` | `study_sessions.assignment_id`, `study_sessions.document_id` |

Sanctum contributes `personal_access_tokens` automatically (it ships its own
migration) — **do not create that table yourself**.

> Migration 2 *alters* `users` rather than creating it, which is why migration 1
> must exist and must sort first.

### Path B — raw SQL schema

```bash
mysql -u root -p < backend-laravel/database/sql/schema.sql
```

Creates the `edu_smart` database, all 16 tables, the integration foreign keys and
the `v_latest_risk` view in one pass.

Then seed the demo data separately:

```bash
cd backend-laravel
php artisan db:seed
```

### Why not both?

`schema.sql` ends with an **empty** `migrations` table. If you import it and then
run `php artisan migrate`, Laravel believes nothing has run and tries to
`CREATE TABLE users` again → *"table already exists"*.

| You imported | Then run | Result |
| ------------ | -------- | ------ |
| migrations | `php artisan migrate --seed` | ✅ correct |
| `schema.sql` | `php artisan db:seed` | ✅ correct |
| `schema.sql` | `php artisan migrate` | ❌ duplicate table error |
| migrations | `schema.sql` | ⚠️ works, but `DROP TABLE IF EXISTS` wipes your data |

To recover from a mixed state:

```bash
php artisan migrate:fresh --seed     # drops all tables, re-migrates, re-seeds
```

---

## Schema differences between the two paths

Both converge on the same domain schema. The only deltas:

| Object | Path A (migrations) | Path B (`schema.sql`) |
| ------ | ------------------- | --------------------- |
| `password_reset_tokens` | created | not created |
| `sessions` | created | not created |
| `v_latest_risk` view | not created | created |

Neither delta affects the API: sessions/password-resets are unused because
`SESSION_DRIVER=file` and auth is token-based, and the view is a reporting
convenience.

---

## Tables by module

### Common Platform Layer

#### `users`
| Column | Type | Notes |
| ------ | ---- | ----- |
| `id` | BIGINT UNSIGNED PK | |
| `name` | VARCHAR(120) | |
| `email` | VARCHAR(160) | **UNIQUE** |
| `password` | VARCHAR(255) | bcrypt hash |
| `program` | VARCHAR(160) | e.g. "BSc (Hons) Software Engineering" |
| `academic_year` | VARCHAR(80) | |
| `dark_mode` | TINYINT(1) | default 0 |
| `daily_target_minutes` | INT UNSIGNED | default 180 — drives Pasindu's target ring |
| `email_verified_at`, `remember_token`, `created_at`, `updated_at` | | Laravel standard |

#### `modules`
The student's enrolled subjects. `color` holds a theme key
(`bethmi|pasindu|kavishka|jithmi|primary`), `icon` an icon name.
FK `user_id` → `users` **CASCADE**.

Referenced by `documents`, `study_sessions` and `assignments`.

#### `notifications`
| Column | Notes |
| ------ | ----- |
| `module_source` | `bethmi|pasindu|kavishka|jithmi|common` — lets any module raise a notification without owning the feature |
| `title`, `message` | |
| `is_read` | default 0 |

No `updated_at` — notifications are immutable once raised.

---

### Bethmi — Learning Materials

#### `documents`
| Column | Notes |
| ------ | ----- |
| `module_id` | FK → `modules` **SET NULL** |
| `title`, `topic` | |
| `type` | `PDF|Word|Text` |
| `pages`, `size_bytes` | |
| `file_path` | storage path under `documents/{user_id}/` |
| `extracted_text` | LONGTEXT — the corpus for summaries **and** for Kavishka's grounding |

`FULLTEXT (title, topic, extracted_text)` supports real text search.

#### `summaries`
| Column | Notes |
| ------ | ----- |
| `document_id` | FK → `documents` **SET NULL** (deleting a document keeps the summary) |
| `length_type` | `Short|Medium|Detailed` → 2 / 4 / 7 sentences |
| `body` | LONGTEXT |
| `keywords` | JSON array, e.g. `["inheritance","override"]` |

---

### Pasindu — Study & Engagement

#### `study_sessions`
| Column | Notes |
| ------ | ----- |
| `module_id` | FK → `modules` **SET NULL** |
| `assignment_id` | FK → `assignments` **SET NULL** — *integration: Jithmi's recommended task* |
| `document_id` | FK → `documents` **SET NULL** — *integration: Bethmi's material* |
| `activity` | default `'Revision'` |
| `planned_minutes` / `actual_minutes` | drives planned-vs-actual analytics |
| `status` | `active|paused|completed` — only one non-completed session per user at a time |
| `started_at` / `ended_at` | |

Only one live session at a time is enforced in `StudySessionController::store()`,
which auto-completes any stray `active`/`paused` row before inserting.

#### `engagement_logs`
| Column | Notes |
| ------ | ----- |
| `study_session_id` | FK → `study_sessions` **SET NULL** |
| `level` | `Low|Moderate|Good` |
| `percent` | TINYINT UNSIGNED 0–100 |
| `source` | `auto|manual` |
| `logged_at` | |

Append-only time series; averaged by `GET /api/study/analytics`.

---

### Kavishka — AI Academic Assistant

#### `academic_documents`
Institutional material (handbook, project guidelines, regulations) — separate from
Bethmi's `documents` so neither module owns the other's data.
`category` = `Handbook|Project|Regulation`; `is_indexed` marks whether chunks exist.

#### `academic_chunks`
The RAG unit of retrieval.
| Column | Notes |
| ------ | ----- |
| `academic_document_id` | FK → `academic_documents` **CASCADE** |
| `section`, `page` | become the citation shown to the student |
| `content` | LONGTEXT passage |
| `keywords` | JSON — strongest scoring signal (weight 3.0) |

`FULLTEXT (content, section)`.

#### `chat_conversations` / `chat_messages`
`chat_messages.role` = `user|bot`; `source_chunk_id` → `academic_chunks`
**SET NULL** records *which* chunk grounded each bot answer, so citations remain
auditable even if the corpus changes.

#### `academic_dates`
| Column | Notes |
| ------ | ----- |
| `academic_document_id` | FK **SET NULL** — where the date was extracted from |
| `title`, `event_date` | `event_date` is indexed |
| `type` | `Deadline|Milestone|Exam|Event` |
| `reminder` | e.g. `'1 day before'` |

Consumed by Jithmi (`assignments.academic_date_id`) and by the shared calendar.

---

### Jithmi — Assignment & Deadline Risk

#### `assignments`
| Column | Notes |
| ------ | ----- |
| `module_id` | FK → `modules` **SET NULL** |
| `academic_date_id` | FK → `academic_dates` **SET NULL** — *integration: Kavishka* |
| `title`, `deadline` (DATE, indexed) | |
| `priority` | `Low|Medium|High` — the student's own label, independent of computed risk |
| `est_hours`, `done_hours` | DECIMAL(5,1) — avoids float drift when summing |
| `progress` | TINYINT 0–100 |
| `completed` | TINYINT(1) |

#### `risk_assessments`
Append-only snapshots written by `RiskCalculator::recalculate()`.
| Column | Notes |
| ------ | ----- |
| `assignment_id` | FK → `assignments` **CASCADE** |
| `score` | 0–100 |
| `level` | `Low|Medium|High|Critical` |
| `hours_per_day` | DECIMAL(5,2) |
| `reasons` | JSON array of human-readable explanations |
| `calculated_at` | |

Keeping history (rather than overwriting) means risk *trends* can be charted later.

---

### Laravel infrastructure

| Table | Purpose |
| ----- | ------- |
| `personal_access_tokens` | Sanctum bearer tokens (`tokenable_type` + `tokenable_id` polymorphic) |
| `migrations` | Laravel's migration ledger |

---

## Relationship map

```
users ─┬─< modules ─┬─< documents ───< summaries
       │            ├─< study_sessions >── assignments (SET NULL)
       │            └─< assignments
       ├─< notifications
       ├─< documents >────────────────── study_sessions.document_id
       ├─< study_sessions ──< engagement_logs
       ├─< assignments ──< risk_assessments
       │        └── academic_dates.academic_date_id (SET NULL)
       ├─< academic_documents ──< academic_chunks
       │            └── academic_dates.academic_document_id
       ├─< chat_conversations ──< chat_messages >── academic_chunks.source_chunk_id
       └─< academic_dates

Legend:  ──<  one-to-many      >──  cross-module reference (SET NULL)
```

**Two rules hold everywhere:**
1. Every table cascades from `users` — deleting a student removes their data completely.
2. Every *cross-module* reference is `SET NULL` — no module can cascade-delete
   another module's records.

---

## The `v_latest_risk` view

Created by `schema.sql` only. Returns the most recent risk snapshot per assignment:

```sql
CREATE OR REPLACE VIEW `v_latest_risk` AS
SELECT r.assignment_id, r.score, r.level, r.hours_per_day, r.reasons, r.calculated_at
FROM `risk_assessments` r
INNER JOIN (
  SELECT assignment_id, MAX(calculated_at) AS latest
  FROM `risk_assessments` GROUP BY assignment_id
) t ON r.assignment_id = t.assignment_id AND r.calculated_at = t.latest;
```

Useful for reporting/BI. The API does **not** depend on it — `RiskCalculator`
computes risk live and `DashboardController` aggregates in PHP, so Path A works
without the view.

---

## Seeded demo data

`php artisan db:seed` (or `migrate --seed`) truncates every table with FK checks
disabled, then creates:

| Data | Volume |
| ---- | ------ |
| Demo student | `student@edusmart.lk` / `password` |
| Subject modules | 4 |
| Academic documents + chunks | 3 documents, 7 chunks |
| Academic dates | 4 |
| Learning documents (with `extracted_text`) | 6 |
| Summaries | generated by `SummaryGenerator` |
| Assignments | 4, risk computed by `RiskCalculator` |
| Study sessions + engagement logs | 7 sessions |
| Chat conversation | 1, grounded by `RetrievalService` |
| Notifications | 4 |

Deadlines are seeded relative to the current date, so risk levels are meaningful
whenever you run it.

Reset at any time:

```bash
php artisan migrate:fresh --seed
```

---

## See also

- [`ARCHITECTURE.md`](ARCHITECTURE.md) — why the schema is split this way
- [`SETUP-BACKEND.md`](SETUP-BACKEND.md) — `.env` database configuration
