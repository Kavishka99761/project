import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { authApi } from '../api/client';
import { requestFcmToken, subscribeToNotifications, onForegroundMessage } from '../firebase/firebase';

const AppCtx = createContext(null);

export function AppProvider({ children }) {
  // ─── Auth state ──────────────────────────────────────────────────────────
  const [user, setUser]         = useState(() => {
    try { return JSON.parse(localStorage.getItem('acadealert-user') || 'null'); }
    catch { return null; }
  });
  const [auth, setAuth]         = useState(() => Boolean(localStorage.getItem('acadealert-token')));
  const [authLoading, setAuthLoading] = useState(false);
  const [authError,   setAuthError]   = useState('');

  // ─── Theme ───────────────────────────────────────────────────────────────
  const [themeMode, setThemeMode] = useState(() =>
    localStorage.getItem('acadealert-theme') || 'light'
  );

  // ─── Notifications ───────────────────────────────────────────────────────
  const [notifications, setNotifications]     = useState([]);
  const [notifCount,    setNotifCount]         = useState(0);
  const [toastMessage,  setToastMessage]       = useState(null);
  const unsubNotifRef = useRef(null);

  // ─── Global search ───────────────────────────────────────────────────────
  const [searchOpen, setSearchOpen] = useState(false);

  // ─── Navigation ──────────────────────────────────────────────────────────
  const [currentPage, setCurrentPage] = useState('dashboard');

  // =========================================================================
  // Auth actions
  // =========================================================================
  const signIn = useCallback(async (email, password) => {
    setAuthLoading(true);
    setAuthError('');
    try {
      const { data } = await authApi.login({ email, password });
      localStorage.setItem('acadealert-token', data.token);
      localStorage.setItem('acadealert-user',  JSON.stringify(data.user));
      setUser(data.user);
      setAuth(true);

      // Register FCM token for push notifications
      const fcmToken = await requestFcmToken();
      if (fcmToken) {
        authApi.registerFcmToken(fcmToken).catch(() => {});
      }

      return true;
    } catch (err) {
      const msg = err.response?.data?.errors?.email?.[0]
               || err.response?.data?.message
               || 'Login failed. Check your credentials.';
      setAuthError(msg);
      return false;
    } finally {
      setAuthLoading(false);
    }
  }, []);

  const signUp = useCallback(async (payload) => {
    setAuthLoading(true);
    setAuthError('');
    try {
      const { data } = await authApi.register(payload);
      localStorage.setItem('acadealert-token', data.token);
      localStorage.setItem('acadealert-user',  JSON.stringify(data.user));
      setUser(data.user);
      setAuth(true);
      return true;
    } catch (err) {
      const errors = err.response?.data?.errors || {};
      const first  = Object.values(errors)[0]?.[0] || err.response?.data?.message || 'Registration failed.';
      setAuthError(first);
      return false;
    } finally {
      setAuthLoading(false);
    }
  }, []);

  const signOut = useCallback(async () => {
    try { await authApi.logout(); } catch {}
    localStorage.removeItem('acadealert-token');
    localStorage.removeItem('acadealert-user');
    setUser(null);
    setAuth(false);
    if (unsubNotifRef.current) { unsubNotifRef.current(); unsubNotifRef.current = null; }
  }, []);

  const updateUser = useCallback((patch) => {
    setUser((prev) => {
      const next = { ...prev, ...patch };
      localStorage.setItem('acadealert-user', JSON.stringify(next));
      return next;
    });
  }, []);

  // =========================================================================
  // Theme toggle
  // =========================================================================
  const toggleTheme = useCallback(() => {
    setThemeMode((m) => {
      const next = m === 'light' ? 'dark' : 'light';
      localStorage.setItem('acadealert-theme', next);
      return next;
    });
  }, []);

  // =========================================================================
  // Real-time notifications via Firebase
  // =========================================================================
  useEffect(() => {
    if (!auth || !user?.id) return;

    unsubNotifRef.current = subscribeToNotifications(String(user.id), (items) => {
      setNotifications(items);
      const unread = items.filter((n) => !n.read).length;
      setNotifCount(unread);
    });

    const unsubForeground = onForegroundMessage((payload) => {
      setToastMessage({
        title: payload.notification?.title || 'Notification',
        body:  payload.notification?.body  || '',
      });
    });

    return () => {
      if (unsubNotifRef.current) unsubNotifRef.current();
      if (typeof unsubForeground === 'function') unsubForeground();
    };
  }, [auth, user?.id]);

  // Listen for 401 logout event from API interceptor
  useEffect(() => {
    const handler = () => {
      setUser(null);
      setAuth(false);
    };
    window.addEventListener('acadealert:logout', handler);
    return () => window.removeEventListener('acadealert:logout', handler);
  }, []);

  // =========================================================================
  // Context value
  // =========================================================================
  const value = useMemo(() => ({
    // Auth
    user, auth, authLoading, authError, setAuthError,
    signIn, signUp, signOut, updateUser,
    // Theme
    themeMode, toggleTheme,
    // Notifications
    notifications, notifCount, toastMessage, setToastMessage,
    // Navigation
    currentPage, setCurrentPage,
    // Search
    searchOpen, setSearchOpen,
  }), [
    user, auth, authLoading, authError,
    signIn, signUp, signOut, updateUser,
    themeMode, toggleTheme,
    notifications, notifCount, toastMessage,
    currentPage,
    searchOpen,
  ]);

  return <AppCtx.Provider value={value}>{children}</AppCtx.Provider>;
}

export const useApp = () => {
  const ctx = useContext(AppCtx);
  if (!ctx) throw new Error('useApp must be used inside AppProvider');
  return ctx;
};
