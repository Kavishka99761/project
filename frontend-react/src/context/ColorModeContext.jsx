import React, { createContext, useContext, useEffect, useMemo, useState, useCallback } from 'react';
import { ThemeProvider, CssBaseline } from '@mui/material';
import { getAppTheme } from '../theme';
import { useAuth } from './AuthContext';
import { updateMe } from '../api/auth';

const ColorModeContext = createContext(null);
const STORAGE_KEY = 'edu_smart_theme_mode';

export function ColorModeProvider({ children }) {
  const { user, isAuthenticated } = useAuth();
  const [mode, setMode] = useState(() => localStorage.getItem(STORAGE_KEY) || 'light');

  // Once the profile loads, let the server-persisted preference win (keeps
  // dark/light mode consistent across devices, same as the web/mobile clients).
  useEffect(() => {
    if (isAuthenticated && user) {
      setMode(user.dark_mode ? 'dark' : 'light');
    }
  }, [isAuthenticated, user?.dark_mode]);

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, mode);
  }, [mode]);

  const toggleColorMode = useCallback(async () => {
    const next = mode === 'dark' ? 'light' : 'dark';
    setMode(next);
    if (isAuthenticated) {
      try {
        await updateMe({ dark_mode: next === 'dark' });
      } catch (err) {
        // Non-fatal \u2014 the UI already switched, persistence can retry later.
      }
    }
  }, [mode, isAuthenticated]);

  const theme = useMemo(() => getAppTheme(mode), [mode]);
  const value = useMemo(() => ({ mode, toggleColorMode }), [mode, toggleColorMode]);

  return (
    <ColorModeContext.Provider value={value}>
      <ThemeProvider theme={theme}>
        <CssBaseline />
        {children}
      </ThemeProvider>
    </ColorModeContext.Provider>
  );
}

export function useColorMode() {
  const ctx = useContext(ColorModeContext);
  if (!ctx) throw new Error('useColorMode must be used within ColorModeProvider');
  return ctx;
}
