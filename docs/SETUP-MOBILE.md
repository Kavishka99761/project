# EDU-SMART · Mobile Setup (React Native + Expo)

Expo SDK 51 · React Native 0.74 · React Navigation 6. **Offline-first** — the app
is fully usable with no backend running.

---

## Requirements

| Tool | Version | Check |
| ---- | ------- | ----- |
| Node.js | 18+ (20 LTS recommended) | `node -v` |
| npm | 9+ | `npm -v` |
| Expo Go app | latest | on your phone (App Store / Play Store) |

Optional, for native builds:

| Target | Needs |
| ------ | ----- |
| Android emulator | Android Studio + an AVD |
| iOS simulator | Xcode (**macOS only**) |

You do **not** need PHP, MySQL or the Laravel backend to run this app.

---

## Setup

```bash
cd mobile-app
npm install
npm start
```

Metro prints a QR code. Then:

| Platform | How |
| -------- | --- |
| **Physical device** | Open **Expo Go** and scan the QR |
| **Android emulator** | Press `a` in the terminal |
| **iOS simulator** | Press `i` (macOS only) |
| **Browser** | Press `w` (react-native-web) |

Direct shortcuts:

```bash
npm run android
npm run ios
npm run web
```

If Metro serves a stale bundle after a config change:

```bash
npm start -- --clear
```

---

## Signing in

| Mode | Credentials |
| ---- | ----------- |
| Backend running | `student@edusmart.lk` / `password` (the seeded Laravel user) |
| Backend **not** running | Anything — the app auto signs in as the demo student |

The header badge always tells you which mode you are in:

- 🟢 **Live API** — Laravel answered `GET /api/health`
- 🔴 **Offline demo** — bundled `src/data/demo.js` is being rendered

---

## Connecting to the Laravel API

The base URL lives in `app.json`:

```json
{ "expo": { "extra": { "apiBase": "http://localhost:8000/api" } } }
```

`src/api/config.js` reads it via `expo-constants`, falling back to
`http://localhost:8000/api`.

### What to set for your target

| Running on | `apiBase` | Why |
| ---------- | --------- | --- |
| Android emulator | `http://10.0.2.2:8000/api` | `10.0.2.2` is the emulator's alias for the host loopback |
| iOS simulator | `http://localhost:8000/api` | Shares the host network |
| **Physical device** | `http://<your-PC-LAN-IP>:8000/api` | `localhost` on the phone means *the phone* |
| Deployed backend | `https://api.your-domain/api` | |

Find your LAN IP:

```powershell
ipconfig                  # Windows → "IPv4 Address", e.g. 192.168.1.10
```
```bash
ifconfig | grep inet      # macOS / Linux
```

### Start Laravel so the device can reach it

```bash
cd backend-laravel
php artisan serve --host=0.0.0.0 --port=8000
```

`--host=0.0.0.0` binds all interfaces; without it the server only listens on
loopback and a physical device cannot connect.

Then **restart Expo** so the new `app.json` is picked up:

```bash
cd mobile-app
npm start -- --clear
```

### Checklist when it will not connect

1. Phone and computer are on the **same Wi-Fi** (not a guest network / AP isolation).
2. `http://<LAN-IP>:8000/api/health` opens in the **phone's browser**.
3. Windows Firewall allows inbound TCP :8000 for PHP/Node.
4. `app.json` has no trailing slash on `apiBase` (`…/api`, not `…/api/`).
5. Backend seeded — `php artisan migrate --seed` (otherwise login returns 401).
6. CORS: `FRONTEND_URL` in `backend-laravel/.env`, then `php artisan config:clear`.

---

## Offline-first design

At launch, `AuthContext` pings `GET /api/health` (2.5 s timeout):

| Result | Behaviour |
| ------ | --------- |
| Reachable | Real Sanctum login; every screen renders live API data |
| Unreachable | Auto signs in as the demo student; every screen renders bundled data |

The key guarantee is in `src/api/client.js`:

```js
try {
  res = await fetchWithTimeout(`${API_BASE}${path}`, {...}, timeout);
} catch (e) {
  return null;          // offline / timeout / DNS — caller falls back to demo data
}
```

`request()` **returns `null`** rather than throwing when the network fails, so no
screen can crash because the backend is absent. Only genuine HTTP errors (4xx/5xx
with a JSON payload) propagate, because those carry a message worth showing.

Each screen then does:

```js
const live = online ? await api.dashboard() : null;
setData(live || offlineDashboard(user));
```

Demo dates are generated relative to *today* (`iso(offsetDays)` in
`src/data/demo.js`), so deadlines and risk levels always look meaningful no matter
when the app is opened.

---

## Project layout

```
mobile-app/
├─ App.js                        # SafeAreaProvider → AuthProvider → RootNavigator
├─ index.js                      # registerRootComponent(App)
├─ app.json                      # Expo config + extra.apiBase
├─ babel.config.js               # babel-preset-expo
├─ package.json
└─ src/
   ├─ theme.js                   # colours, module accents, risk colours, spacing
   ├─ api/
   │  ├─ config.js               # API_BASE, NETWORK_TIMEOUT_MS
   │  ├─ client.js               # fetch wrapper, bearer token, offline fallback
   │  ├─ risk.js                 # JITHMI engine (port of RiskCalculator.php)
   │  └─ retrieve.js             # KAVISHKA engine (port of RetrievalService.php)
   ├─ data/demo.js               # bundled offline dataset
   ├─ context/AuthContext.js     # session, login/logout, online flag
   ├─ components/
   │  ├─ ui.js                   # Card, SectionTitle, RiskPill, Stat, Progress, Chip…
   │  └─ Screen.js               # shared header + safe-area scaffold
   ├─ navigation/RootNavigator.js
   └─ screens/                   # Login, Dashboard, Learning, Study, Assistant, Assignments
```

### Screen → module → endpoints

| Screen | Module | Endpoints |
| ------ | ------ | --------- |
| `LoginScreen` | Common | `/login`, `/logout` |
| `DashboardScreen` | Common | `/dashboard`, `/notifications` |
| `LearningScreen` | **Bethmi** | `/documents`, `/documents/{id}/keywords`, `/summaries` |
| `StudyScreen` | **Pasindu** | `/study/analytics`, `/study/sessions`, `PATCH /study/sessions/{id}` |
| `AssistantScreen` | **Kavishka** | `/assistant/chat`, `/assistant/dates`, `/assistant/knowledge` |
| `AssignmentsScreen` | **Jithmi** | `/assignments`, `/assignments/rank`, `/assignments/recommendation` |

---

## Duplicated-by-design logic

Two services are ported to the client so the UI stays correct with no round-trip:

| Client file | Laravel source | Constant shared |
| ----------- | -------------- | --------------- |
| `src/api/risk.js` | `app/Services/RiskCalculator.php` | `DAILY_CAPACITY = 5.0` |
| `src/api/retrieve.js` | `app/Services/RetrievalService.php` | `MATCH_THRESHOLD = 0.08` |

Both use identical formulae, so risk scores and citations match the API exactly.
**If you change the PHP, change the matching JS in the same commit.**

---

## Verified

```
$ npx expo export --platform android --output-dir dist-verify --clear
Android Bundled 8782ms  index.js (822 modules)
App exported to: dist-verify
```

Zero errors, zero unresolved modules. Re-run this check any time — it is the
fastest way to confirm the app still compiles:

```bash
npx expo export --platform android --output-dir dist-verify --clear
```

`dist-verify/` is a throwaway artifact and is gitignored.

---

## Building a distributable app

### Expo EAS (cloud build)

```bash
npm install -g eas-cli
eas login
eas build:configure
eas build --platform android        # APK / AAB
eas build --platform ios            # requires an Apple Developer account
```

### Local preview build

```bash
npx expo run:android                # needs Android Studio + SDK
npx expo run:ios                    # needs Xcode (macOS)
```

Before a production build, set `extra.apiBase` to your deployed HTTPS API and
flip `APP_DEBUG=false` on the backend.

---

## Troubleshooting

| Symptom | Fix |
| ------- | --- |
| Header always says `Offline demo` | Backend not running, or wrong `apiBase` for your target (see table above) |
| `Network request failed` on a device | Different Wi-Fi, AP isolation, or firewall blocking :8000 |
| Works on the emulator but not the phone | Emulator uses `10.0.2.2`; a phone needs the LAN IP |
| Login returns 401 | Run `php artisan migrate --seed` — the demo user must exist |
| `app.json` changes ignored | Restart with `npm start -- --clear` |
| `Unable to resolve module …` | Delete `node_modules` + `package-lock.json`, then `npm install` |
| `npm install` fails with `ECONNRESET` | Transient registry drop — just re-run it; npm resumes from its cache |
| `EPERM … rmdir node_modules` on Windows | Close editors/terminals holding files, disable antivirus scanning of `node_modules`, re-run `npm install` |
| Blank white screen in Expo Go | Read the Metro terminal — it holds the real stack trace |
| `Invariant Violation: requires native module` | In Expo Go, reload; for a dev build, run `npx expo prebuild` then rebuild |
| iOS build fails on Windows | Expected — iOS builds require macOS + Xcode |
| Font/icon glyphs missing | `@expo/vector-icons` assets are bundled automatically; `npm start -- --clear` |

---

## See also

- [`mobile-app/README.md`](../mobile-app/README.md) — app-level reference
- [`SETUP-BACKEND.md`](SETUP-BACKEND.md) — getting the API running
- [`API.md`](API.md) — endpoint contracts
- [`ARCHITECTURE.md`](ARCHITECTURE.md) — offline-first rationale
