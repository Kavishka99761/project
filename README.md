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
| Assistant model | Qwen3 8B via Ollama (any OpenAI-compatible server) | `docs/AI-ASSISTANT.md` |
| Docs | Architecture, API, setup, data audit, assistant model | `docs/` |

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

### Accounts, email verification and password recovery

Sign-up and recovery work as they do on a deployed service — everything goes
through the mailbox, and no API response ever contains a code:

1. **Create account** → the account is usable at once, `email_verified` is
   `false`, and a 6-digit code is emailed. The app opens `/verify-email`, and
   an "unconfirmed address" reminder stays in the app until it is entered.
2. **Forgot password** (panel inside the login form, or `/forgot-password`) →
   enter the username, a 6-digit code is emailed, then the code plus a new
   password signs you straight in. The existing password is never shown: only a
   bcrypt hash of it is stored, so there is nothing to read back.

Codes are stored hashed, expire (15 minutes for a reset, 24 hours for
verification), die after 5 wrong guesses, and are limited to 5 sends per
10 minutes per address. Resetting a password signs out every other device and
emails the owner that it changed.

By default `MAIL_MAILER=log`, so no mail server is needed — the message is
written to `apps/api/storage/logs/` and the code can be read with:

```bash
cd apps/api
php artisan acadealert:last-code student@acadealert.lk
```

To receive the emails for real, set `MAIL_MAILER=smtp` and the `MAIL_*`
credentials in `apps/api/.env` (Gmail App Password, Mailtrap or Mailpit — see
the comments in `.env.example`). Nothing is logged once a real mailer is set.

Tests for both flows:

```bash
cd apps/api
php artisan test --filter AuthRecoveryTest
```

---

## The Academic Assistant's model

The assistant answers any question, and cites the student's own documents when
they contain the answer. It runs **Qwen3 8B locally through Ollama** — no API
key, nothing leaves the machine:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup\install-qwen-ollama.ps1
```

That installs Ollama, pulls `qwen3:8b` (~5 GB; `-Model qwen3:4b` is faster on a
laptop) and checks it answers. `apps/api/.env` already points at it, and the
static demo calls the same endpoint from the browser.

Without a model running nothing breaks: document questions are still answered by
the built-in extractive engine, and the assistant says that general questions
need the model. Full detail, hosted-provider settings and tests:
[`docs/AI-ASSISTANT.md`](docs/AI-ASSISTANT.md).

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
