import { createTheme } from '@mui/material/styles';

// Shared accent palette \u2014 one color per module, matching the rest of the
// EDU-SMART clients (frontend-web, mobile-app) so the brand is consistent
// everywhere. `common` styling (this app) uses `primary`.
export const moduleColors = {
  primary: '#6366f1', // Common Platform Layer
  bethmi: '#3b82f6', // Smart Notes & Document Management
  pasindu: '#14b8a6', // Study Session & Engagement
  kavishka: '#8b5cf6', // AI Academic Assistant
  jithmi: '#f97316', // Assignment & Deadline Risk
};

export const riskColors = {
  Low: '#22c55e',
  Medium: '#eab308',
  High: '#f97316',
  Critical: '#ef4444',
};

// "Liquid Glass" surface — a frosted, translucent panel with a soft inner
// highlight, used for the app bar, drawer, dialogs and auth cards. Falls back
// gracefully (solid background) in browsers without backdrop-filter support.
export function glassSx(mode) {
  const isDark = mode === 'dark';
  return {
    backgroundColor: isDark ? 'rgba(23, 26, 35, 0.55)' : 'rgba(255, 255, 255, 0.6)',
    backgroundImage: isDark
      ? 'linear-gradient(135deg, rgba(255,255,255,0.06), rgba(255,255,255,0))'
      : 'linear-gradient(135deg, rgba(255,255,255,0.75), rgba(255,255,255,0.15))',
    backdropFilter: 'blur(20px) saturate(180%)',
    WebkitBackdropFilter: 'blur(20px) saturate(180%)',
    border: `1px solid ${isDark ? 'rgba(255,255,255,0.08)' : 'rgba(255,255,255,0.5)'}`,
    boxShadow: isDark
      ? '0 8px 32px rgba(0,0,0,0.45), inset 0 1px 0 rgba(255,255,255,0.05)'
      : '0 8px 32px rgba(99,102,241,0.12), inset 0 1px 0 rgba(255,255,255,0.6)',
  };
}

export function getAppTheme(mode) {
  const isDark = mode === 'dark';

  return createTheme({
    palette: {
      mode,
      primary: { main: moduleColors.primary },
      secondary: { main: moduleColors.kavishka },
      background: isDark
        ? { default: '#0f1117', paper: '#171a23' }
        : { default: '#f4f6fb', paper: '#ffffff' },
      success: { main: riskColors.Low },
      warning: { main: riskColors.Medium },
      error: { main: riskColors.Critical },
    },
    shape: { borderRadius: 14 },
    typography: {
      fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
      h1: { fontWeight: 800 },
      h2: { fontWeight: 800 },
      h3: { fontWeight: 700 },
      h4: { fontWeight: 700 },
      h5: { fontWeight: 700 },
      h6: { fontWeight: 700 },
      button: { textTransform: 'none', fontWeight: 600 },
    },
    components: {
      MuiPaper: {
        styleOverrides: {
          root: {
            backgroundImage: 'none',
            transition: 'box-shadow 0.25s ease, transform 0.25s ease',
          },
        },
      },
      MuiCard: {
        styleOverrides: {
          root: {
            transition: 'box-shadow 0.25s ease, transform 0.2s ease',
            '&:hover': {
              transform: 'translateY(-2px)',
            },
          },
        },
      },
      MuiButton: {
        styleOverrides: {
          root: {
            borderRadius: 10,
            transition: 'transform 0.15s ease, box-shadow 0.15s ease',
            '&:active': { transform: 'scale(0.97)' },
          },
        },
      },
      MuiChip: {
        styleOverrides: { root: { fontWeight: 600 } },
      },
      MuiDrawer: {
        styleOverrides: {
          paper: {
            backgroundImage: 'none',
          },
        },
      },
    },
  });
}
