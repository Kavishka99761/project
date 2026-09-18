# AcadeAlert React + Material UI

This folder contains the component-based React web client for AcadeAlert. It preserves the static prototype's demo data and workflows while moving the interface to React and Material UI. The build uses webpack and Babel for reliable Windows/Chrome development.

## Run

```bash
cd frontend-react
npm install
npm run dev
```

On Windows, you can also double-click `start-chrome.cmd`. It installs dependencies when needed, starts webpack development server, and opens the React client at `http://localhost:5173` in the default browser.

Build for production with:

```bash
npm run build
```

## Included workflows

- Overview dashboard with priority assignments and notifications
- Learning materials search, filtering, upload dialog, and summary generation
- Focus timer and engagement controls
- Academic assistant workspace and academic dates
- Assignment risk table, progress updates, deletion, and what-if planner
- Calendar and profile/settings views
- Light/dark theme preference and sign-out flow

The current client uses the same relative demo dataset as `frontend-web/assets/js/mock-data.js`. Laravel API integration can be added behind the existing component state without changing the navigation surface.
