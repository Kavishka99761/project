# AcadeAlert
A full-stack academic productivity platform for university students, built as four
independently-owned modules on top of one shared platform layer.

| Layer | Technology | Location |
| ----- | ---------- | -------- |
| API | Laravel 12 · Sanctum · PHP 8.2 | `apps/api/` |
| Database | Microsoft SQL Server (LocalDB / SSMS) + Firebase realtime mirror | `apps/api/database/` |
| Web client | React 19 · Vite · Bootstrap 5.3 · TanStack Query · Chart.js | `apps/web/` |
| Mobile client | React Native · Expo SDK 51 | `apps/mobile/` |
| Static demo | HTML · CSS · JavaScript · Bootstrap (mock data, no backend) | `frontend-web/` |
| AI engine (optional) | Python semantic retrieval service | `services/ai-engine/` |
| Docs | Architecture, API, setup, data audit | `docs/` |

---

## The four modules

Each module is owned by one team member. Modules **exchange data**; they never call
each other's functionality, which keeps ownership boundaries clean.

| Module | Owner | Responsibility |
| ------ | ----- | -------------- |
| **Learning Materials & Summaries** | Bethmi | Upload materials, extract text, search, summaries, keywords, flashcards, quizzes, mind maps |
| **Study Sessions & Engagement** | Pasindu | Focus timer, engagement monitoring, breaks, planner, productivity analytics |
| **Academic Assistant** | Kavishka | Knowledge base, chatbot with cited answers, academic date extraction, calendar |
| **Assignments & Deadline Risk** | Jithmi | Assignments, workload, risk scoring, progress and submission history |
| *Common Platform Layer* | shared | Auth, profile, modules, navigation, settings, theme, notifications, calendar, search, exports, audit trail |

**Integration flow:** Kavishka (academic dates) → Jithmi (deadlines & risk) →
Pasindu (study sessions) → Bethmi (materials) → Pasindu → Jithmi (progress & risk update).

---

## Running it

### API + database (`apps/api`)

Needs PHP 8.2 with the `sqlsrv`/`pdo_sqlsrv` drivers (see
`scripts/setup/install-php-extensions.ps1`), Composer and SQL Server LocalDB.

```bash
cd apps/api
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate --seed    # database "AcadeAlert"
php artisan serve             # → http://127.0.0.1:8000/api/v1
```

### Web client (`apps/web`)

```bash
cd apps/web
npm install
npm run dev                   # → http://localhost:5173
```

### Static demo (`frontend-web`), with no install

```bash
cd frontend-web
node serve.js                 # → http://localhost:5173
```

### Mobile (`apps/mobile`)

```bash
cd apps/mobile
npm install
npm start
```

**Demo login:** `student@acadealert.lk` / `password`

---

## Audit trail

Every create, update and delete is saved in SQL Server:

- `activity_logs` gets one row per API action.
- `data_change_log` gets one row per changed database row, written by triggers.

View them in the web app under **Platform → Activity log**, or in SSMS with
`SELECT * FROM dbo.vw_data_changes`. See [`docs/DATA-AUDIT.md`](docs/DATA-AUDIT.md).

---

## Repository layout

```
project/
├─ apps/
│  ├─ api/          # Laravel 12 REST API (SQL Server, Sanctum, exports, audit)
│  ├─ web/          # React 19 web client
│  └─ mobile/       # Expo / React Native client
├─ frontend-web/    # static Bootstrap demo (mock data)
├─ services/
│  └─ ai-engine/    # optional Python retrieval service
├─ scripts/setup/   # PHP SQL Server driver installer (Windows/XAMPP)
└─ docs/            # architecture, API, setup, data audit
```

## License

Built as a university group project. No license asserted.
