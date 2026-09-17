# EDU-SMART · API Reference

Base URL: `http://localhost:8000/api`
Format: JSON. Auth: Laravel Sanctum bearer tokens.

```bash
curl http://localhost:8000/api/health
# {"status":"ok","service":"edu-smart-api","time":"2026-09-13T09:14:02+05:30"}
```

---

## Authentication

Three routes are public. Everything else requires
`Authorization: Bearer <token>`.

### POST `/register`

```json
{ "name": "Ayesha Perera", "email": "a@b.lk", "password": "secret", "password_confirmation": "secret", "program": "BSc SE" }
```

`201` → `{ "user": {...}, "token": "1|abc..." }`

### POST `/login`

```json
{ "email": "student@edusmart.lk", "password": "password" }
```

`200` → `{ "user": {...}, "token": "1|abc..." }`
`422` → `{ "message": "...", "errors": { "email": ["The provided credentials are incorrect."] } }`

### POST `/logout` 🔒

Deletes the **current** token only (other devices stay signed in).

### GET `/me` 🔒 · PUT `/me` 🔒

`PUT` accepts `name`, `program`, `academic_year`, `dark_mode`, `daily_target_minutes`.

---

## Common Platform Layer

### GET `/dashboard` 🔒

Single aggregated payload for the overview hub — one request instead of six.

```json
{
  "greeting": "Good morning",
  "user": { "id": 1, "name": "Ayesha Perera", "email": "...", "program": "...", "daily_target_minutes": 180, "dark_mode": false },
  "modules": [ { "id": 1, "code": "SE201", "name": "Software Engineering", "color": "bethmi", "icon": "book" } ],
  "study_today": { "minutes": 155, "target": 180, "percent": 86, "average_engagement": 78, "active_session": null },
  "risk_summary": { "Low": 1, "Medium": 1, "High": 1, "Critical": 1 },
  "upcoming_deadlines": [ { "id": 1, "title": "Database Project", "deadline": "2026-09-16", "days_left": 3, "level": "High", "score": 62 } ],
  "academic_dates": [ { "id": 1, "title": "Project Proposal Submission", "event_date": "2026-09-15", "type": "Milestone", "days_left": 2 } ],
  "counts": { "documents": 6, "assignments": 4, "overdue": 0 }
}
```

`upcoming_deadlines` includes only assignments due within 7 days, sorted by
`days_left`, capped at 5. `academic_dates` is capped at 5.

### GET `/calendar` 🔒

Merges **Kavishka**'s academic dates with **Jithmi**'s assignment deadlines into
one chronological feed — the cross-module integration point.

`?from=YYYY-MM-DD&to=YYYY-MM-DD` (defaults: today −7 days → today +2 months)

```json
{
  "from": "2026-09-06", "to": "2026-11-13",
  "events": [
    { "id": "date-1", "title": "Project Proposal Submission", "date": "2026-09-15",
      "type": "Milestone", "source": "kavishka", "meta": { "reminder": "1 day before", "academic_date_id": 1 } },
    { "id": "assignment-1", "title": "Database Project (due)", "date": "2026-09-16",
      "type": "Assignment", "source": "jithmi",
      "meta": { "assignment_id": 1, "risk_level": "High", "risk_score": 62, "completed": false } }
  ]
}
```

`type` for Kavishka events: `Deadline|Milestone|Exam|Event`. For Jithmi:
`Assignment`, or `Completed` when done.

### `/modules` 🔒

Full `apiResource` — `GET` index/show, `POST`, `PUT`/`PATCH`, `DELETE`.

```json
{ "code": "SE201", "name": "Software Engineering", "color": "primary", "icon": "book" }
```

### Notifications 🔒

| Method | Route | Notes |
| ------ | ----- | ----- |
| GET | `/notifications` | `?unread=1` filter |
| POST | `/notifications` | `{ module_source, title, message }` |
| POST | `/notifications/read-all` | |
| PATCH | `/notifications/{id}` | mark one read |
| DELETE | `/notifications/{id}` | |

`module_source` must be one of `bethmi|pasindu|kavishka|jithmi|common`.

---

## Bethmi — Learning Materials

### GET `/documents` 🔒

`?q=<term>` · `?module_id=<id>` · `?type=PDF|Word|Text`

`q` matches `title`, `topic` **and** `extracted_text`. Returns a bare JSON array
(not wrapped in `data`), newest first, with the `module` relation eager-loaded.

### POST `/documents` 🔒

Accepts **either** an uploaded file **or** raw pasted text.

```json
{ "title": "OOP Lecture 05", "topic": "Inheritance", "module_id": 3,
  "type": "PDF", "pages": 34, "extracted_text": "Inheritance lets a subclass…" }
```

Multipart variant: field `file` (max **20 MB**). `.txt`/`.md` uploads have their
text extracted automatically; `type` is inferred from the extension when omitted.
`201` → the created document.

### GET `/documents/{id}` 🔒

Loads `module` and `summaries`.

### PUT `/documents/{id}` 🔒 · DELETE `/documents/{id}` 🔒

`DELETE` also removes the stored file from disk.

### GET `/documents/{id}/keywords` 🔒

```json
{ "keywords": ["inheritance", "superclass", "override", "polymorphism", "subclass", "coupling"] }
```

Falls back to the title when no extracted text exists. Up to 10 keywords.

### POST `/documents/{id}/summaries` 🔒

```json
{ "length_type": "Medium", "title": "Optional custom title" }
```

`length_type`: `Short` (2 sentences) · `Medium` (4) · `Detailed` (7). Default `Medium`.

`201` →
```json
{ "id": 4, "document_id": 1, "title": "OOP Lecture 05 — Summary",
  "length_type": "Medium", "body": "Inheritance lets a subclass reuse…",
  "keywords": ["inheritance", "override"] }
```

`422` → `{ "message": "This document has no extracted text to summarise yet." }`

### Summaries 🔒

| Method | Route |
| ------ | ----- |
| GET | `/summaries` (all, with `document`, newest first) |
| GET | `/summaries/{id}` |
| DELETE | `/summaries/{id}` |

---

## Pasindu — Study & Engagement

### POST `/study/sessions` 🔒 — start

```json
{ "module_id": 2, "assignment_id": 1, "document_id": 4, "activity": "Revision", "planned_minutes": 25 }
```

All fields optional (`planned_minutes` defaults to 25). **Only one live session at
a time** — any stray `active`/`paused` session is auto-completed first.
`201` → the session with `module`, `assignment`, `document` loaded.

### PATCH `/study/sessions/{id}` 🔒 — transition

```json
{ "status": "paused" }
```

`status`: `active` (resume) · `paused` · `completed` (stop).
Optional `actual_minutes` (0–1440); when stopping without it, elapsed time is
derived from `started_at`.

> **On `completed`, the linked assignment's `done_hours` and `progress` are
> advanced** — this is the Pasindu → Jithmi write-back. The response reflects the
> session only; re-fetch `/assignments` to see the new risk score.

### GET `/study/sessions` 🔒

`?from=YYYY-MM-DD&to=YYYY-MM-DD` · newest first · capped at 100.

### GET `/study/current` 🔒

The `active`/`paused` session, or `null`.

### POST `/study/sessions/{id}/engagement` 🔒

```json
{ "level": "Good", "percent": 82, "source": "manual" }
```

`level`: `Low|Moderate|Good` (required) · `percent`: 0–100 (required) ·
`source`: `auto|manual`.

### GET `/study/analytics` 🔒

`?days=7` (default 7)

```json
{
  "weekly": [ { "date": "2026-09-07", "label": "Mon", "minutes": 120 } ],
  "planned_minutes": 270,
  "actual_minutes": 257,
  "completion_rate": 95,
  "sessions_count": 3,
  "average_engagement": 78,
  "streak_days": 4,
  "daily_target": 180
}
```

`weekly` always returns exactly `days` entries, zero-filled. `completion_rate` is
actual ÷ planned over **completed** sessions only. `streak_days` counts consecutive
days with a completed session ending today (a streak from yesterday is still alive).

---

## Kavishka — AI Academic Assistant

### POST `/assistant/chat` 🔒

```json
{ "message": "What is the attendance requirement?", "conversation_id": 1 }
```

`conversation_id` is optional — omit it to start a new conversation (titled from
the first 48 characters of the question).

`201` →
```json
{
  "conversation_id": 2,
  "reply": "Based on Attendance Requirement (p. 9): Students must maintain a minimum of 80% attendance…",
  "source": {
    "document": "University Student Handbook 2026",
    "category": "Handbook",
    "section": "Attendance Requirement",
    "page": 9,
    "score": 1.3333,
    "chunk_id": 2
  },
  "message": { "id": 7, "conversation_id": 2, "role": "bot", "content": "…", "source_chunk_id": 2 }
}
```

When nothing matches (`score < 0.08`), `source` is `null` and `reply` explains that
the corpus has no answer — the assistant never fabricates.

Both the question and the grounded reply are persisted to `chat_messages`.

**Scoring** (transparent lexical RAG, no external LLM):
```
per query token:  keyword hit      → +3.0
                  content hit      → +1.0 + log(term frequency)
score = sum / (token count + 1)     // normalised so long queries don't dominate
match  = score >= 0.08
```

### Knowledge base 🔒

| Method | Route | Notes |
| ------ | ----- | ----- |
| GET | `/assistant/knowledge` | documents with their chunks |
| POST | `/assistant/knowledge` | create + index a document |
| DELETE | `/assistant/knowledge/{doc}` | cascades to chunks |

### Conversations 🔒

| Method | Route |
| ------ | ----- |
| GET | `/assistant/conversations` (with `messages_count`) |
| GET | `/assistant/conversations/{id}/messages` (with `sourceChunk.document`) |

### Academic dates 🔒

| Method | Route |
| ------ | ----- |
| GET | `/assistant/dates` (ordered by `event_date`) |
| POST | `/assistant/dates` |
| PUT | `/assistant/dates/{id}` |
| DELETE | `/assistant/dates/{id}` |

```json
{ "title": "Semester 1 Examinations Begin", "event_date": "2026-10-04",
  "type": "Exam", "reminder": "1 week before", "academic_document_id": 1 }
```

`type`: `Deadline|Milestone|Exam|Event`.

---

## Jithmi — Assignment & Deadline Risk

### GET `/assignments` 🔒

Returns each assignment with its live `assessment`:

```json
[ { "id": 1, "title": "Database Project", "deadline": "2026-09-16", "priority": "High",
    "est_hours": "20.0", "done_hours": "12.0", "progress": 85, "completed": false,
    "module": { "id": 2, "code": "DB202", "name": "Database Systems" },
    "assessment": { "score": 62, "level": "High", "hours_per_day": 2.7, "days": 3,
                    "reasons": ["Only 3 day(s) left"] } } ]
```

### POST `/assignments` 🔒 · PUT/PATCH `/assignments/{id}` 🔒

```json
{ "title": "Database Project", "module_id": 2, "academic_date_id": 2,
  "deadline": "2026-09-16", "priority": "High",
  "est_hours": 20, "done_hours": 12, "progress": 85, "completed": false }
```

`title` and `deadline` required on create, optional on update. Writing recalculates
and persists a `risk_assessments` snapshot. `DELETE` removes the assignment and its
risk history.

### GET `/assignments/rank` 🔒

All **active** (not completed) assignments, highest risk first:

```json
[ { "rank": 1, "assignment": { "id": 1, "title": "Database Project" },
    "risk": { "score": 62, "level": "High", "hours_per_day": 2.7, "days": 3, "reasons": ["…"] } } ]
```

Ties break on the earliest deadline.

### GET `/assignments/recommendation` 🔒

The single most urgent task plus a suggested daily plan — ready to hand to Pasindu.

```json
{
  "assignment": { "id": 1, "title": "Database Project" },
  "risk": { "score": 62, "level": "High", "hours_per_day": 2.7, "days": 3, "reasons": ["…"] },
  "remaining_hours": 8.0,
  "days_left": 3,
  "suggested_per_day": 2.7,
  "suggested_minutes": 160,
  "message": "Focus on \"Database Project\" — high risk. Aim for ~2.7h/day over the next 3 day(s)."
}
```

`suggested_per_day` is capped at 6.0 h/day. When nothing is active:
`{ "message": "No active assignments — you are all caught up." }`

### POST `/assignments/whatif` 🔒

Simulate risk **without persisting anything**.

```json
{ "assignment_id": 1, "deadline": "2026-09-23", "est_hours": 20, "done_hours": 12, "progress": 85 }
```

Every field optional. Omit `assignment_id` to simulate from a blank slate (defaults:
deadline = today +7 days, zeros elsewhere).

```json
{
  "simulated": { "deadline": "2026-09-23", "est_hours": 20.0, "done_hours": 12.0, "progress": 85 },
  "risk": { "score": 31, "level": "Medium", "hours_per_day": 1.1, "days": 10, "reasons": ["On track — workload fits available time"] },
  "note": "Scenario only — nothing was saved."
}
```

---

## The risk formula

Implemented once in `app/Services/RiskCalculator.php`, mirrored in
`frontend-web/assets/js/mock-data.js` and `mobile-app/src/api/risk.js`.

```
DAILY_CAPACITY = 5.0                       // hours a student can study per day

days        = max(today → deadline, 0)
remaining   = max(est_hours − done_hours, 0)
hoursPerDay = days > 0 ? remaining / days : remaining

score = days == 0
      ? (remaining > 0 ? 100 : 0)          // due today: all-or-nothing
      : min(100, round( hoursPerDay/5 × 60  +  (100 − progress) × 0.4 ))
                       └─ workload pressure ─┘  └─── how unfinished ───┘

level = score ≥ 75 → Critical
        score ≥ 50 → High
        score ≥ 25 → Medium
        else         Low
```

`reasons` are added when: `hoursPerDay > 3`, `days <= 3`, `progress < 40`, or
`remaining <= 0`. With none of those, the reason is
`"On track — workload fits available time"`.

**Worked example** — 8 h remaining, 3 days left, 85 % done:
```
hoursPerDay = 8/3 = 2.67
pressure    = 2.67/5 × 60 = 32.0
incomplete  = (100−85) × 0.4 = 6.0
score       = round(38.0) = 38  →  Medium
```

---

## Conventions

| Concern | Convention |
| ------- | ---------- |
| Response wrapping | Collections return a **bare array**. Objects with a `data` key are unwrapped by the mobile client. |
| Errors | `422` validation → `{ message, errors: { field: [ … ] } }`. `403` → not your record. `401` → missing/expired token. |
| Ownership | Every `{id}` route runs `abort_unless($model->user_id === $request->user()->id, 403)`. |
| Dates | `YYYY-MM-DD` for `deadline`/`event_date`; ISO-8601 for timestamps. |
| Decimals | `est_hours`/`done_hours` serialise as strings (`"20.0"`) — cast with `Number()` in JS. |
| Pagination | Not implemented; lists are capped (sessions 100, dashboard deadlines 5). |

---

## Quick smoke test

```bash
# 1. health
curl http://localhost:8000/api/health

# 2. login, capture the token
TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"student@edusmart.lk","password":"password"}' | grep -o '"token":"[^"]*' | cut -d'"' -f4)

# 3. authenticated calls
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" http://localhost:8000/api/dashboard
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" http://localhost:8000/api/assignments/rank
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" http://localhost:8000/api/study/analytics

# 4. ask the assistant
curl -X POST http://localhost:8000/api/assistant/chat \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"message":"What is the attendance requirement?"}'
```

On Windows PowerShell use `Invoke-RestMethod` instead:

```powershell
$login = Invoke-RestMethod -Method Post -Uri http://localhost:8000/api/login `
  -ContentType 'application/json' -Body '{"email":"student@edusmart.lk","password":"password"}'
$h = @{ Authorization = "Bearer $($login.token)"; Accept = 'application/json' }
Invoke-RestMethod -Uri http://localhost:8000/api/dashboard -Headers $h
```

---

## Route table

| Method | Path | Owner |
| ------ | ---- | ----- |
| GET | `/health` | public |
| POST | `/register`, `/login` | public |
| POST | `/logout` · GET/PUT `/me` | Common |
| GET | `/dashboard`, `/calendar` | Common |
| * | `/modules` (apiResource) | Common |
| GET/POST | `/notifications`, `/notifications/read-all` | Common |
| PATCH/DELETE | `/notifications/{id}` | Common |
| GET | `/documents/{id}/keywords` | Bethmi |
| POST | `/documents/{id}/summaries` | Bethmi |
| * | `/documents` (apiResource) | Bethmi |
| GET | `/summaries`, `/summaries/{id}` · DELETE `/summaries/{id}` | Bethmi |
| GET | `/study/current`, `/study/analytics`, `/study/sessions` | Pasindu |
| POST | `/study/sessions` | Pasindu |
| PATCH | `/study/sessions/{id}` | Pasindu |
| POST | `/study/sessions/{id}/engagement` | Pasindu |
| GET/POST | `/assistant/knowledge` · DELETE `/assistant/knowledge/{doc}` | Kavishka |
| GET | `/assistant/conversations`, `/assistant/conversations/{id}/messages` | Kavishka |
| POST | `/assistant/chat` | Kavishka |
| GET/POST | `/assistant/dates` · PUT/DELETE `/assistant/dates/{id}` | Kavishka |
| GET | `/assignments/rank`, `/assignments/recommendation` | Jithmi |
| POST | `/assignments/whatif` | Jithmi |
| * | `/assignments` (apiResource) | Jithmi |

🔒 = requires `Authorization: Bearer <token>`

---

## See also

- [`ARCHITECTURE.md`](ARCHITECTURE.md) — layering and integration points
- [`DATABASE.md`](DATABASE.md) — tables behind each endpoint
- [`SETUP-BACKEND.md`](SETUP-BACKEND.md) — getting the API running
