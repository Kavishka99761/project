# EDU-SMART \u00b7 React + MUI Web Client (Common Platform Layer)

This is the **shared Common Platform Layer**, built with React 18, MUI 5 and
Framer Motion, wired to the existing `backend-laravel` API and MySQL/SQL
database. It owns everything every module sits on top of:

- **Authentication** \u2014 login, logout, registration (Sanctum bearer tokens)
- **User profile** \u2014 name, program, academic year, daily study target
- **Modules** \u2014 full CRUD over the student's enrolled subjects
- **Navigation** \u2014 responsive drawer + top bar shell used by every page
- **Notifications** \u2014 bell menu + dedicated page, read/unread/delete
- **Calendar** \u2014 month view merging Kavishka's academic dates with
  Jithmi's assignment deadlines (`GET /api/calendar`)
- **Global search (Ctrl+K)** \u2014 across modules and notifications, with
  recent-search history synced to **Firebase Firestore** as a supplementary
  online store (optional \u2014 disabled automatically with no config)
- **Settings** \u2014 dark/light mode (persisted server-side via `PUT /me`),
  notification preferences, and JSON/CSV backup & export of your data
- **Dashboard** \u2014 aggregated overview (`GET /api/dashboard`) with animated
  stat cards, a study-progress ring, and a risk-summary chart

The four feature modules (Learning Materials, Study Session, AI Assistant,
Assignments) are wired into routing and navigation as themed placeholder
pages, ready for their owners to fill in against the already-documented
endpoints in [`../docs/API.md`](../docs/API.md).

## Stack

React 18 \u00b7 MUI 5 \u00b7 Framer Motion \u00b7 Recharts \u00b7 React Router 7 \u00b7 Axios \u00b7
Firebase (optional) \u00b7 Webpack 5

## Data sources

| Store | Role |
| ----- | ---- |
| **Laravel + MySQL/SQL** (`backend-laravel/`) | Source of truth for every domain record \u2014 users, modules, assignments, documents, sessions, notifications. Everything in this app reads/writes here. |
| **Firebase Firestore** (optional) | Supplementary *online* store used only for recent global-search history, so it survives across devices/browsers. Purely additive: with no Firebase project configured the app runs unchanged, just without synced search history. |

## Getting started

```bash
cd frontend-react
npm install
cp .env.example .env      # set API_BASE_URL and optional Firebase keys
npm run dev                # http://localhost:5173
```

Requires the Laravel API running separately:

```bash
cd ../backend-laravel
php artisan serve           # http://localhost:8000
```

Sign in with the seeded demo account: `student@edusmart.lk` / `password`.

## Scripts

| Command | Purpose |
| ------- | ------- |
| `npm run dev` | Webpack dev server with HMR on port 5173 |
| `npm run build` | Production bundle to `dist/` |

## Project layout

```
src/
\u251c\u2500 api/            axios client + endpoint wrappers (auth, dashboard, modules, notifications, calendar)
\u251c\u2500 components/     ProtectedRoute, GlobalSearch, NotificationsMenu, ModulePlaceholder, layout/AppShell
\u251c\u2500 context/        AuthContext (session), ColorModeContext (theme + dark/light persistence)
\u251c\u2500 firebase/       optional Firestore config + recent-search history
\u251c\u2500 pages/          Login, Register, Dashboard, Profile, ModulesPage, CalendarPage,
\u2502                  NotificationsPage, SettingsPage, modules/{Learning,Study,Assistant,Assignments}
\u251c\u2500 utils/export.js JSON/CSV backup export helpers
\u2514\u2500 theme.js        MUI theme (light/dark) + shared module accent colors
```
