# EDU-SMART · Mobile (React Native + Expo)

The mobile client for the EDU-SMART academic platform. It mirrors the four module
dashboards from the web frontend and talks to the Laravel API — but it is built
**offline-first**, so it is fully demonstrable even when PHP/MySQL are not running.

```
mobile-app/
├─ App.js                     # root: SafeAreaProvider → AuthProvider → RootNavigator
├─ index.js                   # Expo entry (registerRootComponent)
├─ app.json                   # Expo config; expo.extra.apiBase points at Laravel
├─ package.json               # Expo SDK 51 + React Navigation 6
└─ src/
   ├─ theme.js                # design tokens (module accents, risk colours)
   ├─ api/
   │  ├─ config.js            # API_BASE + network timeout
   │  ├─ client.js            # fetch wrapper, bearer token, offline fallback
   │  ├─ risk.js              # JITHMI risk engine (port of RiskCalculator.php)
   │  └─ retrieve.js          # KAVISHKA RAG engine (port of RetrievalService.php)
   ├─ data/demo.js            # bundled dataset used when the API is unreachable
   ├─ context/AuthContext.js  # session, login/logout, online flag
   ├─ components/
   │  ├─ ui.js                # Card, SectionTitle, RiskPill, Stat, Progress…
   │  └─ Screen.js            # shared header + safe-area scaffold
   ├─ navigation/RootNavigator.js
   └─ screens/
      ├─ LoginScreen.js       # Common Platform Layer
      ├─ DashboardScreen.js   # Common Platform Layer (hub)
      ├─ LearningScreen.js    # BETHMI
      ├─ StudyScreen.js       # PASINDU
      ├─ AssistantScreen.js   # KAVISHKA
      └─ AssignmentsScreen.js # JITHMI
```

## Quick start

```bash
cd mobile-app
npm install
npm start          # then press "a" (Android), "i" (iOS) or scan the QR in Expo Go
```

Other targets:

```bash
npm run android    # physical device / emulator
npm run ios        # macOS only
npm run web        # browser preview via react-native-web
```

## Pointing at the Laravel API

`app.json → expo.extra.apiBase` defaults to `http://localhost:8000/api`.

| Running on           | Set `apiBase` to                                   |
| -------------------- | -------------------------------------------------- |
| Android emulator     | `http://10.0.2.2:8000/api`                         |
| iOS simulator        | `http://localhost:8000/api`                        |
| Physical device      | `http://<your-PC-LAN-IP>:8000/api` (same Wi-Fi)     |
| Deployed backend     | `https://api.your-domain/api`                      |

Find your LAN IP with `ipconfig` (Windows) or `ifconfig` (macOS/Linux). The
Laravel dev server must be reachable from the device — start it with
`php artisan serve --host=0.0.0.0 --port=8000` and confirm `CORS` allows the
origin (see `backend-laravel/config/cors.php`).

After editing `app.json`, restart Expo with `npm start -- --clear`.

## Offline-first behaviour

At launch `AuthContext` calls `GET /api/health`:

| Backend state  | What happens                                                                 |
| -------------- | ---------------------------------------------------------------------------- |
| **Reachable**  | Real Sanctum login, all screens render live API data. Header shows `Live API`. |
| **Unreachable**| Auto signs in as the demo student, screens render `src/data/demo.js`. Header shows `Offline demo`. |

No screen ever crashes on a missing backend: `client.js#request()` returns `null`
on timeout/DNS failure and each screen substitutes the bundled dataset. Only real
HTTP errors (4xx/5xx with a payload) are surfaced to the user.

Demo credentials: **student@edusmart.lk** / **password** (same as the Laravel
seeder and the web frontend).

## Duplicated-by-design logic

Two services are ported to the client so the UI stays correct with no round-trip:

| Client file        | Laravel source                     | Why duplicated                          |
| ------------------ | ---------------------------------- | --------------------------------------- |
| `src/api/risk.js`  | `app/Services/RiskCalculator.php`  | Instant risk scores, works offline      |
| `src/api/retrieve.js` | `app/Services/RetrievalService.php` | Grounded, source-cited answers offline |

Both use the same constants (`DAILY_CAPACITY = 5.0`, `MATCH_THRESHOLD = 0.08`) and
the same scoring formulae, so scores and citations match the API exactly. **If you
change the Laravel formula, update the matching client file too.**

`src/api/risk.js` also owns the date helpers: `daysUntil(dateStr)` and its exact
inverse `isoDate(offsetDays, from = today)`. Both work in **local** calendar terms.
Never use `new Date().toISOString().slice(0, 10)` instead — that returns the UTC
date, which is the previous day east of UTC and would shift every deadline by one
(the what-if simulator used to report a higher risk than the assignment actually
had, even with all sliders at zero). `src/data/demo.js` delegates its own `iso()`
to `isoDate`, so the bundled dataset and the risk engine cannot disagree about what
"today" means.

## Module → screen → API map

| Module     | Screen                | Endpoints used                                                        |
| ---------- | --------------------- | --------------------------------------------------------------------- |
| Common     | Login, Dashboard      | `/login`, `/logout`, `/dashboard`, `/notifications`, `/calendar`        |
| **Bethmi** | LearningScreen        | `/documents`, `/documents/{id}/keywords`, `/summaries`                 |
| **Pasindu**| StudyScreen           | `/study/analytics`, `/study/sessions`, `/study/sessions/{id}` (stop)   |
| **Kavishka**| AssistantScreen      | `/assistant/chat`, `/assistant/dates`, `/assistant/knowledge`         |
| **Jithmi** | AssignmentsScreen     | `/assignments`, `/assignments/rank`, `/assignments/{id}/recommendation`|

### Integration points (data exchange, not shared code)

1. **Jithmi → Pasindu** — the "Focus on this first" card has *Start study session*,
   which navigates to the timer. Stopping a session makes the backend advance that
   assignment's `done_hours`/`progress` (`StudySessionController::applyProgressToAssignment`).
2. **Kavishka → Jithmi/Common** — extracted academic dates feed the shared calendar
   and the assignment deadline picker (`CalendarController` merges both feeds).
3. **Bethmi → Kavishka** — document text is the corpus the assistant grounds answers in.

## Verified

Bundled successfully with Metro (Expo SDK 51):

```
Android Bundled — index.js (822 modules) — no errors
```

Re-run the check any time with:

```bash
npx expo export --platform android --output-dir dist-verify --clear
```

## Troubleshooting

| Symptom                              | Fix                                                                     |
| ------------------------------------ | ----------------------------------------------------------------------- |
| Header always says `Offline demo`    | Backend not running, or wrong `apiBase` for your device (see table above) |
| `Network request failed` on device   | Phone and PC on different networks, or Windows Firewall blocking :8000    |
| Login returns 401                    | Run `php artisan db:seed` — the demo user must exist                     |
| Changes to `app.json` ignored        | Restart with `npm start -- --clear`                                      |
| `Unable to resolve module …`         | Delete `node_modules` + `package-lock.json`, then `npm install`          |
| Blank white screen in Expo Go        | Check the Metro terminal for the real stack trace                        |
