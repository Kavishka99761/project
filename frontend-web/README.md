# EDU-SMART · Web Frontend

Bootstrap 5 client for the EDU-SMART academic platform. **No build step** — plain
HTML, CSS and JavaScript served as static files.

```bash
node serve.js
# → http://localhost:5173
```

Sign in with **student@edusmart.lk** / **password** (any credentials work — the app
runs on bundled mock data, so every module dashboard is fully interactive with no
backend).

Full instructions: [`../docs/SETUP-WEB.md`](../docs/SETUP-WEB.md)

---

## Pages

| File | Module | Contents |
| ---- | ------ | -------- |
| `index.html` | Common | Login |
| `dashboard.html` | Common | Overview hub — day at a glance across all four modules |
| `learning.html` | **Bethmi** | Documents, search/filter, summaries, keywords |
| `study.html` | **Pasindu** | Focus timer, engagement, weekly analytics, streak |
| `assistant.html` | **Kavishka** | RAG chatbot with source citations, academic dates |
| `assignments.html` | **Jithmi** | Risk ranking, reasons, recommendation, what-if simulator |
| `calendar.html` | Common | Merged Kavishka dates + Jithmi deadlines |
| `profile.html` | Common | Profile, daily target, theme toggle |

---

## Layout

```
frontend-web/
├─ *.html                        # 8 pages
├─ serve.js                      # zero-dependency static dev server (~40 lines)
└─ assets/
   ├─ css/style.css              # design tokens, module accents, components
   └─ js/
      ├─ mock-data.js            # ES namespace — datasets + calcRisk + formatters
      ├─ app.js                  # shared shell: nav, auth guard, theme, toasts
      ├─ dashboard.js · calendar.js · profile.js
      ├─ learning.js             # Bethmi
      ├─ study.js                # Pasindu
      ├─ assistant.js            # Kavishka (includes the retrieval scorer)
      └─ assignments.js          # Jithmi
```

**One JS file per page.** `mock-data.js` and `app.js` load first on every page; the
page-specific script loads last.

### The `ES` global namespace

`assets/js/mock-data.js` defines one global, `ES`, as the single source of truth for
data shapes and shared helpers — a page never invents its own field names.

| Member | Purpose |
| ------ | ------- |
| `ES.calcRisk(assignment)` | Risk score/level/reasons — mirrors `RiskCalculator.php` |
| `ES.daysUntil(dateStr)` | Whole days from today |
| `ES.isoDate(offsetDays)` | `YYYY-MM-DD` that many days from today — the inverse of `daysUntil`. Built from local components, never `toISOString()`; every demo date uses it so nothing can decay |
| `ES.fmtDuration(minutes)` | `95` → `1h 35m` |
| `ES.moduleName(id)` | Module lookup |
| `ES.user`, `ES.modules`, `ES.documents`, `ES.summaries`, `ES.study*`, `ES.assignments`, `ES.knowledgeBase`, `ES.academicDates`, `ES.notifications` | Datasets |

---

## External dependencies

Loaded from jsDelivr in each page's `<head>`:

| Library | Version |
| ------- | ------- |
| Bootstrap CSS | 5.3.3 |
| Bootstrap Icons | 1.11.3 |
| Bootstrap JS bundle | 5.3.3 |

Working offline or behind a restricted network? Download the three files and point
the `<link>`/`<script>` tags at local paths — nothing else changes.

---

## Module colours

Kept in sync with `mobile-app/src/theme.js` so both clients feel like one product.

| Module | Hex |
| ------ | --- |
| Common / primary | `#6366f1` |
| Bethmi | `#3b82f6` |
| Pasindu | `#14b8a6` |
| Kavishka | `#8b5cf6` |
| Jithmi | `#f97316` |

Risk levels: Low `#22c55e` · Medium `#eab308` · High `#f97316` · Critical `#ef4444`

---

## Connecting to the Laravel API

The web client currently runs on mock data only. To wire it up, add an `ES.api`
fetch layer mirroring `mobile-app/src/api/client.js` — see
[`../docs/SETUP-WEB.md` § Connecting to the Laravel API](../docs/SETUP-WEB.md#connecting-to-the-laravel-api)
for a ready-to-paste implementation and the fallback pattern.

Set `FRONTEND_URL=http://localhost:5173` in `backend-laravel/.env`, then
`php artisan config:clear`.

---

## Deploying

Any static host — deployment is just copying files, since there is no build step.

| Host | Config |
| ---- | ------ |
| nginx / Apache | Document root → `frontend-web/` |
| GitHub Pages | Push contents to a `gh-pages` branch |
| Netlify / Vercel | Build command: *(none)* · Publish directory: `frontend-web` |

---

## See also

- [`../docs/SETUP-WEB.md`](../docs/SETUP-WEB.md) — running, wiring to the API, deploying
- [`../docs/ARCHITECTURE.md`](../docs/ARCHITECTURE.md) — client design
- [`../docs/API.md`](../docs/API.md) — endpoint reference
