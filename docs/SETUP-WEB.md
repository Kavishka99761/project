# EDU-SMART · Web Frontend Setup

Bootstrap 5 web client. **No build step, no `node_modules`, no transpiler** — plain
HTML, CSS and JavaScript served as static files.

---

## Requirements

| Tool | Needed for | Check |
| ---- | ---------- | ----- |
| A modern browser | Everything | Chrome, Edge, Firefox, Safari |
| Internet connection | Bootstrap + Bootstrap Icons are loaded from jsDelivr CDN | — |
| Node.js *(optional)* | The included zero-dependency dev server | `node -v` |

No backend is required — the app runs entirely on bundled mock data.

---

## Running it

### Option 1 — the included static server (recommended)

```bash
cd frontend-web
node serve.js
```

```
  EDU-SMART web frontend running at  →  http://localhost:5173
```

Open **http://localhost:5173**.

`serve.js` is ~40 lines of plain Node `http` + `fs` with correct MIME types and a
path-traversal guard. It has **zero dependencies**, so there is nothing to install.

Use a different port:

```bash
PORT=8080 node serve.js         # macOS / Linux
$env:PORT=8080; node serve.js   # Windows PowerShell
```

### Option 2 — any static server

```bash
cd frontend-web
npx serve .                      # or
python -m http.server 5173       # Python 3
php -S localhost:5173            # PHP
```

### Option 3 — open the file directly

Double-click `index.html`. Most pages work, but `fetch()`/module behaviour and
client-side routing can be restricted by the browser under the `file://` protocol.
Prefer a local server.

---

## Signing in

The login page accepts **any** credentials in mock mode. Use the seeded demo
account for consistency with the backend:

| Email | Password |
| ----- | -------- |
| `student@edusmart.lk` | `password` |

The session is kept in `localStorage`, so refreshing keeps you signed in.

---

## Pages

| File | Module | What it shows |
| ---- | ------ | ------------- |
| `index.html` | Common | Login |
| `dashboard.html` | Common | Overview hub — day at a glance across all four modules |
| `learning.html` | **Bethmi** | Documents, search/filter, summaries, keywords |
| `study.html` | **Pasindu** | Focus timer, engagement, weekly analytics, streak |
| `assistant.html` | **Kavishka** | RAG chatbot with source citations, academic dates |
| `assignments.html` | **Jithmi** | Risk ranking, reasons, recommendation, what-if simulator |
| `calendar.html` | Common | Merged Kavishka dates + Jithmi deadlines |
| `profile.html` | Common | Profile, daily target, theme toggle |

---

## File layout

```
frontend-web/
├─ index.html · dashboard.html · learning.html · study.html
├─ assistant.html · assignments.html · calendar.html · profile.html
├─ serve.js                    # zero-dependency static dev server
└─ assets/
   ├─ css/style.css            # design tokens, module accents, components
   └─ js/
      ├─ mock-data.js          # ES namespace: datasets + calcRisk + formatters
      ├─ app.js                # shared shell: nav, auth guard, theme, toasts
      ├─ dashboard.js
      ├─ learning.js           # Bethmi
      ├─ study.js              # Pasindu
      ├─ assistant.js          # Kavishka (includes the retrieval scorer)
      ├─ assignments.js        # Jithmi
      ├─ calendar.js
      └─ profile.js
```

**One JS file per page.** `mock-data.js` and `app.js` are loaded by every page
first; the page-specific file loads last.

### The `ES` global namespace

`assets/js/mock-data.js` defines a single global `ES` that is the source of truth
for data shapes and shared helpers:

| Member | Purpose |
| ------ | ------- |
| `ES.calcRisk(assignment)` | Risk score/level/reasons — mirrors `RiskCalculator.php` |
| `ES.daysUntil(dateStr)` | Whole days from today |
| `ES.fmtDuration(minutes)` | `95` → `1h 35m` |
| `ES.moduleName(id)` | Module lookup |
| `ES.user`, `ES.modules`, `ES.documents`, `ES.summaries`, `ES.study*`, `ES.assignments`, `ES.knowledgeBase`, `ES.academicDates`, `ES.notifications` | Datasets |

A page never invents its own field names — it reads from `ES`.

---

## External dependencies (CDN)

Loaded from jsDelivr in every page's `<head>`:

| Library | Version | Purpose |
| ------- | ------- | ------- |
| Bootstrap CSS | 5.3.3 | Layout, grid, components |
| Bootstrap Icons | 1.11.3 | Icon font |
| Bootstrap JS bundle | 5.3.3 | Modals, dropdowns, tabs (includes Popper) |

**Working offline / on a restricted network?** Download the three files and change
the `<link>`/`<script>` tags to local paths — nothing else needs to change.

---

## Connecting to the Laravel API

The web client currently runs on mock data only. To wire it to the backend, add an
`ES.api` fetch layer mirroring `mobile-app/src/api/client.js`:

```js
// assets/js/api.js  (loaded after mock-data.js)
const API_BASE = 'http://localhost:8000/api';

ES.api = {
  token: () => localStorage.getItem('edusmart.token'),

  async request(path, { method = 'GET', body } = {}) {
    const headers = { Accept: 'application/json' };
    if (body) headers['Content-Type'] = 'application/json';
    const token = ES.api.token();
    if (token) headers.Authorization = `Bearer ${token}`;

    const res = await fetch(`${API_BASE}${path}`, {
      method, headers, body: body ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) throw new Error((await res.json())?.message || `HTTP ${res.status}`);
    return res.json();
  },

  login: (email, password) => ES.api.request('/login', { method: 'POST', body: { email, password } }),
  dashboard: () => ES.api.request('/dashboard'),
  documents: () => ES.api.request('/documents'),
  assignments: () => ES.api.request('/assignments'),
};
```

Then, in a page controller, try the API first and fall back to the mock dataset:

```js
let data;
try {
  data = await ES.api.dashboard();
} catch (e) {
  data = offlineDashboard();   // existing mock-data path
}
render(data);
```

The backend must allow the origin — set `FRONTEND_URL=http://localhost:5173` in
`backend-laravel/.env`, then `php artisan config:clear`. See
[`SETUP-BACKEND.md` § CORS](SETUP-BACKEND.md#cors).

Endpoint reference: [`API.md`](API.md).

---

## Deploying

Any static host works — the site is pure HTML/CSS/JS.

```bash
# Netlify / Vercel / GitHub Pages / S3 / nginx
# publish root: frontend-web/
```

| Host | Notes |
| ---- | ----- |
| nginx / Apache | Point the document root at `frontend-web/` |
| GitHub Pages | Push `frontend-web/` contents to the `gh-pages` branch |
| Netlify / Vercel | Build command: *(none)* · Publish directory: `frontend-web` |

Because there is no build step, deployment is just copying files. If you later add
the API layer, make `API_BASE` environment-driven so the same bundle works in dev
and production.

---

## Verified

Served with `node serve.js` and rendered in a browser — all 8 pages load, the
module dashboards render their datasets, and navigation between pages works.

---

## Troubleshooting

| Symptom | Fix |
| ------- | --- |
| Page loads with no styling | CDN blocked — check the network tab, or vendor Bootstrap locally |
| Blank page, `ES is not defined` | Script order: `mock-data.js` → `app.js` → page script |
| Redirected back to `index.html` | No session in `localStorage` — sign in first |
| `EADDRINUSE :5173` | Another server holds the port → `PORT=8080 node serve.js` |
| Changes not appearing | Hard refresh (Ctrl+Shift+R) — static assets are cached |
| Icons show as squares | Bootstrap Icons font failed to load from CDN |
| `file://` page behaves oddly | Use `node serve.js` instead of opening the file directly |

---

## See also

- [`ARCHITECTURE.md`](ARCHITECTURE.md) — client design and the `ES` duplication rationale
- [`API.md`](API.md) — endpoints to call once wired up
- [`SETUP-MOBILE.md`](SETUP-MOBILE.md) — the React Native client
