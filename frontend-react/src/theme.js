import { createTheme, alpha } from '@mui/material/styles';

// ─── Brand palette ────────────────────────────────────────────────────────────
const indigo  = '#6366f1';
const violet  = '#8b5cf6';
const teal    = '#14b8a6';
const amber   = '#f59e0b';
const rose    = '#f43f5e';
const slate50 = '#f8fafc';
const slate100= '#f1f5f9';

// ─── Shared component overrides ──────────────────────────────────────────────
const sharedOverrides = (mode) => ({
  MuiCssBaseline: {
    styleOverrides: `
      @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap');
      *, *::before, *::after { box-sizing: border-box; }
      html { scroll-behavior: smooth; }
      body { font-family: 'Inter', sans-serif !important; }
      ::-webkit-scrollbar { width: 6px; height: 6px; }
      ::-webkit-scrollbar-track { background: transparent; }
      ::-webkit-scrollbar-thumb { background: rgba(148,163,184,0.4); border-radius: 99px; }
      ::-webkit-scrollbar-thumb:hover { background: rgba(148,163,184,0.7); }
    `,
  },
  MuiButton: {
    styleOverrides: {
      root: {
        borderRadius: 10,
        textTransform: 'none',
        fontWeight: 600,
        letterSpacing: '-0.01em',
        transition: 'all 0.2s cubic-bezier(0.34,1.56,0.64,1)',
        '&:hover': { transform: 'translateY(-1px)', boxShadow: '0 4px 16px rgba(0,0,0,0.12)' },
        '&:active': { transform: 'translateY(0)' },
      },
      containedPrimary: {
        background: `linear-gradient(135deg, ${indigo}, ${violet})`,
        boxShadow: `0 2px 12px ${alpha(indigo, 0.3)}`,
        '&:hover': { boxShadow: `0 6px 20px ${alpha(indigo, 0.4)}` },
      },
    },
  },
  MuiCard: {
    styleOverrides: {
      root: {
        borderRadius: 16,
        border: mode === 'light' ? '1px solid rgba(148,163,184,0.15)' : '1px solid rgba(255,255,255,0.06)',
        transition: 'box-shadow 0.2s ease, transform 0.2s ease',
        '&:hover': {
          boxShadow: mode === 'light'
            ? '0 8px 32px rgba(0,0,0,0.08)'
            : '0 8px 32px rgba(0,0,0,0.4)',
        },
      },
    },
  },
  MuiPaper: {
    styleOverrides: {
      root: { borderRadius: 12 },
      elevation1: {
        boxShadow: mode === 'light'
          ? '0 1px 4px rgba(0,0,0,0.06)'
          : '0 1px 4px rgba(0,0,0,0.4)',
      },
    },
  },
  MuiChip: {
    styleOverrides: {
      root: { borderRadius: 8, fontWeight: 600, fontSize: '0.72rem' },
    },
  },
  MuiTextField: {
    styleOverrides: {
      root: {
        '& .MuiOutlinedInput-root': {
          borderRadius: 10,
          transition: 'box-shadow 0.15s ease',
          '&.Mui-focused': { boxShadow: `0 0 0 3px ${alpha(indigo, 0.15)}` },
        },
      },
    },
  },
  MuiLinearProgress: {
    styleOverrides: {
      root: { borderRadius: 99, overflow: 'hidden' },
      bar:  { borderRadius: 99, transition: 'transform 0.4s cubic-bezier(0.34,1.56,0.64,1)' },
    },
  },
  MuiListItemButton: {
    styleOverrides: {
      root: {
        borderRadius: 10,
        marginBottom: 4,
        transition: 'all 0.15s ease',
        '&:hover': { transform: 'translateX(2px)' },
      },
    },
  },
  MuiDrawer: {
    styleOverrides: {
      paper: { border: 'none' },
    },
  },
  MuiAppBar: {
    styleOverrides: {
      root: { boxShadow: 'none' },
    },
  },
  MuiDialog: {
    styleOverrides: {
      paper: { borderRadius: 20 },
    },
  },
  MuiTooltip: {
    styleOverrides: {
      tooltip: { borderRadius: 8, fontWeight: 500, fontSize: '0.75rem' },
    },
  },
});

// ─── Light theme ─────────────────────────────────────────────────────────────
export const lightTheme = createTheme({
  palette: {
    mode: 'light',
    primary:   { main: indigo, light: '#818cf8', dark: '#4f46e5' },
    secondary: { main: violet },
    success:   { main: '#22c55e', light: '#86efac', dark: '#15803d' },
    warning:   { main: amber,     light: '#fcd34d', dark: '#d97706' },
    error:     { main: rose,      light: '#fb7185', dark: '#e11d48' },
    info:      { main: teal },
    background: { default: '#eef2ff', paper: '#ffffff' },
    text: { primary: '#0f172a', secondary: '#64748b' },
    divider: 'rgba(148,163,184,0.18)',
  },
  typography: {
    fontFamily: "'Inter', 'Helvetica Neue', sans-serif",
    h1: { fontWeight: 800, letterSpacing: '-0.05em' },
    h2: { fontWeight: 800, letterSpacing: '-0.04em' },
    h3: { fontWeight: 800, letterSpacing: '-0.04em' },
    h4: { fontWeight: 800, letterSpacing: '-0.04em' },
    h5: { fontWeight: 700, letterSpacing: '-0.03em' },
    h6: { fontWeight: 700, letterSpacing: '-0.02em' },
    button: { fontWeight: 600 },
  },
  shape: { borderRadius: 12 },
  components: sharedOverrides('light'),
});

// ─── Dark theme ──────────────────────────────────────────────────────────────
export const darkTheme = createTheme({
  palette: {
    mode: 'dark',
    primary:   { main: '#818cf8', light: '#a5b4fc', dark: indigo },
    secondary: { main: '#a78bfa' },
    success:   { main: '#4ade80' },
    warning:   { main: '#fbbf24' },
    error:     { main: '#fb7185' },
    info:      { main: '#2dd4bf' },
    background: { default: '#0b0f1a', paper: '#111827' },
    text: { primary: '#f1f5f9', secondary: '#94a3b8' },
    divider: 'rgba(255,255,255,0.06)',
  },
  typography: {
    fontFamily: "'Inter', 'Helvetica Neue', sans-serif",
    h1: { fontWeight: 800, letterSpacing: '-0.05em' },
    h2: { fontWeight: 800, letterSpacing: '-0.04em' },
    h3: { fontWeight: 800, letterSpacing: '-0.04em' },
    h4: { fontWeight: 800, letterSpacing: '-0.04em' },
    h5: { fontWeight: 700, letterSpacing: '-0.03em' },
    h6: { fontWeight: 700, letterSpacing: '-0.02em' },
    button: { fontWeight: 600 },
  },
  shape: { borderRadius: 12 },
  components: sharedOverrides('dark'),
});

export const riskPalette = {
  Low:      { color: '#22c55e', bg: 'rgba(34,197,94,0.12)',   border: 'rgba(34,197,94,0.3)' },
  Medium:   { color: '#f59e0b', bg: 'rgba(245,158,11,0.12)',  border: 'rgba(245,158,11,0.3)' },
  High:     { color: '#f97316', bg: 'rgba(249,115,22,0.12)',  border: 'rgba(249,115,22,0.3)' },
  Critical: { color: '#f43f5e', bg: 'rgba(244,63,94,0.12)',   border: 'rgba(244,63,94,0.3)' },
};
