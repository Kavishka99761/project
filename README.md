# EDU-SMART

A full-stack academic productivity platform for university students, built as four
independently-owned modules on top of one shared platform layer.

| Layer      | Technology                          | Location          | Status                    |
| ---------- | ----------------------------------- | ----------------- | ------------------------- |
| Web        | HTML · CSS · JavaScript · Bootstrap | `frontend-web/`   | ✅ Runs standalone         |
| Mobile     | React Native · Expo SDK 51          | `mobile-app/`     | ✅ Bundles (822 modules)   |
| Backend    | Laravel 11 · Sanctum                | `backend-laravel/`| ✅ Complete, needs PHP 8.2 |
| Database   | MySQL 8 · SQL schema + migrations   | `backend-laravel/database/` | ✅ 16 tables + view |
| Docs       | Architecture, setup, API reference  | `docs/`           | ✅                        |

---

## The four modules

Each module is owned by one team member. Modules **exchange data**, they never call
each other's functionality — that keeps ownership boundaries clean.

| Module | Owner | Responsibility | Accent |
| ------ | ----- | -------------- | ------ |
| **Smart Notes & Document Management** | Bethmi | Upload learning materials, extract text, search, generate Short/Medium/Detailed revision summaries + keywords | `#3b82f6` |
| **Study Session & Engagement** | Pasindu | Focus timer, engagement monitoring, weekly analytics, streaks, planned-vs-actual | `#14b8a6` |
| **AI Academic Assistant** | Kavishka | RAG chatbot over indexed handbooks/guidelines, answers cite document + section + page; extracts academic dates | `#8b5cf6` |
| **Assignment & Deadline Risk** | Jithmi | Risk scoring (0–100 → Low/Medium/High/Critical), urgency ranking, daily study plan, what-if simulator | `#f97316` |
| *Common Platform Layer* | shared | Auth, profile, modules, navigation, notifications, calendar, overview hub | `#6366f1` |

### Integration points

```
Bethmi  ──document text──▶  Kavishka (grounds its answers in Bethmi's corpus)
Kavishka ──academic dates──▶ Jithmi + Common calendar (merged event feed)
Jithmi  ──urgent task──▶    Pasindu ("Start study session")
Pasindu ──minutes studied──▶ Jithmi (advances done_hours / progress on stop)
```

---

## Fastest way to see it working

### 1. Web frontend — no install required

Only Node.js is needed (a zero-dependency static server is included):

```bash
cd frontend-web
node serve.js
# → http://localhost:5173
```

Sign in with **student@edusmart.lk** / **password** (or any credentials — the web
app runs entirely on bundled mock data, so every module dashboard is fully
interactive without a backend).

### 2. Mobile app — no backend required

```bash
cd mobile-app
npm install
npm start        # press "a" for Android, "i" for iOS, or scan the QR in Expo Go
```

The app is **offline-first**: at launch it pings `GET /api/health`. If Laravel is
not running it automatically signs in as the demo student and renders the bundled
dataset. The header badge tells you which mode you are in (`Live API` vs
`Offline demo`).

### 3. Backend + database

Requires **PHP 8.2+**, **Composer** and **MySQL 8**. Full instructions:
[`docs/SETUP-BACKEND.md`](docs/SETUP-BACKEND.md).

```bash
cd backend-laravel
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate --seed    # or import database/sql/schema.sql — NOT both
php artisan serve             # → http://localhost:8000
```

---

## Documentation

| Document | What it covers |
| -------- | -------------- |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | System design, module boundaries, data flow, why decisions were made |
| [`docs/API.md`](docs/API.md) | Every endpoint, request/response shapes, auth flow, curl examples |
| [`docs/DATABASE.md`](docs/DATABASE.md) | Schema, all 16 tables, relationships, the `v_latest_risk` view |
| [`docs/SETUP-WEB.md`](docs/SETUP-WEB.md) | Running and deploying the web frontend |
| [`docs/SETUP-BACKEND.md`](docs/SETUP-BACKEND.md) | Laravel setup, environment config, seeding, troubleshooting |
| [`docs/SETUP-MOBILE.md`](docs/SETUP-MOBILE.md) | Expo setup, connecting to the API from a device, offline mode |

Each layer also has its own README: [`frontend-web/`](frontend-web/),
[`mobile-app/README.md`](mobile-app/README.md), [`backend-laravel/`](backend-laravel/).

---

## Repository layout

```
project/
├─ frontend-web/           # Bootstrap web client (8 pages)
│  ├─ index.html           # login
│  ├─ dashboard.html       # Common — overview hub
│  ├─ learning.html        # Bethmi
│  ├─ study.html           # Pasindu
│  ├─ assistant.html       # Kavishka
│  ├─ assignments.html     # Jithmi
│  ├─ calendar.html        # Common — merged Kavishka + Jithmi feed
│  ├─ profile.html         # Common
│  ├─ serve.js             # zero-dependency static dev server
│  └─ assets/{css,js}/     # style.css + one JS file per page + mock-data.js
│
├─ mobile-app/             # React Native (Expo) client
│  ├─ App.js, index.js, app.json, package.json
│  └─ src/{api,components,context,data,navigation,screens}/
│
├─ backend-laravel/        # Laravel 11 API
│  ├─ app/Http/Controllers/Api/   # 11 controllers
│  ├─ app/Models/                 # 14 Eloquent models
│  ├─ app/Services/               # RiskCalculator, SummaryGenerator, RetrievalService
│  ├─ config/                     # 14 config files
│  ├─ database/migrations/        # 7 migrations
│  ├─ database/seeders/           # DatabaseSeeder (demo data via real services)
│  ├─ database/sql/schema.sql     # standalone MySQL schema (16 tables + view)
│  ├─ routes/{api,web,console}.php
│  └─ bootstrap/, public/, storage/
│
└─ docs/                   # architecture, API, database, per-layer setup
```

---

## Demo credentials

Seeded by `DatabaseSeeder` and used by both clients:

| Email | Password |
| ----- | -------- |
| `student@edusmart.lk` | `password` |

---

## Verified

| Check | Result |
| ----- | ------ |
| Web frontend served and rendered in a browser | ✅ all 8 pages |
| Mobile app Metro bundle | ✅ `Android Bundled — 822 modules`, no errors |
| Backend PHP syntax / route table | ⚠️ not runnable here — PHP & Composer are not installed on the build machine |

The backend is a complete, hand-written Laravel 11 skeleton (composer.json,
bootstrap, config, artisan, public, storage). It has not been executed because
this machine has no PHP toolchain; run `composer install` then
`php artisan migrate --seed` on a machine with PHP 8.2+ to bring it up.
See [`docs/SETUP-BACKEND.md`](docs/SETUP-BACKEND.md) for the full checklist.

---

## Tech notes

- **Risk formula** (identical in PHP, web JS and mobile JS — `DAILY_CAPACITY = 5.0h`):
  ```
  days         = max(deadline - today, 0)
  remaining    = max(estHours - doneHours, 0)
  hoursPerDay  = days > 0 ? remaining / days : remaining
  score        = days == 0 ? (remaining > 0 ? 100 : 0)
                           : min(100, round(hoursPerDay/5*60 + (100-progress)*0.4))
  level        = score>=75 Critical | >=50 High | >=25 Medium | else Low
  ```
- **Assistant retrieval** is a transparent lexical scorer (keyword hit = 3.0,
  content term frequency = `1 + log(freq)`, normalised by query length,
  `MATCH_THRESHOLD = 0.08`). No external LLM or vector database is required, so
  the project runs anywhere while still demonstrating real RAG with citations.
- **Summarisation** is extractive: sentences are scored by keyword frequency and
  the top N are kept in original order (Short = 2, Medium = 4, Detailed = 7).

## License

Built as a university group project. No license asserted.
