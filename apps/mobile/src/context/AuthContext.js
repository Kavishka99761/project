/**
 * AuthContext — Common Platform Layer.
 *
 * Holds the signed-in user, the Sanctum bearer token (persisted via AsyncStorage)
 * and whether the backend was reachable at launch. Every screen reads from here,
 * so switching between "live API" and "offline demo" is a single decision made
 * once at startup rather than per-screen logic.
 */
import React, { createContext, useContext, useEffect, useMemo, useState, useCallback } from 'react';
import { api, ping, setSession, getStoredUser, clearSession, getToken } from '../api/client';
import { demoUser } from '../data/demo';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [booting, setBooting] = useState(true);
  const [online, setOnline] = useState(false);
  const [busy, setBusy] = useState(false);

  // On launch: check backend health, then restore any persisted session.
  useEffect(() => {
    let mounted = true;
    (async () => {
      const isUp = await ping();
      const [token, stored] = await Promise.all([getToken(), getStoredUser()]);

      if (!mounted) return;
      setOnline(isUp);

      if (token && stored) {
        setUser(stored);
      } else if (!isUp) {
        // No backend → allow the bundled demo account so the app is always usable.
        setUser(demoUser);
      }
      setBooting(false);
    })();
    return () => {
      mounted = false;
    };
  }, []);

  const login = useCallback(
    async (email, password) => {
      setBusy(true);
      try {
        if (online) {
          const res = await api.login(email, password);
          if (res?.token && res?.user) {
            await setSession(res.token, res.user);
            setUser(res.user);
            return { ok: true };
          }
          return { ok: false, message: 'Invalid email or password.' };
        }

        // Offline demo login — accept the seeded credentials (or any input) so
        // the app can be demonstrated without PHP/MySQL running.
        const ok =
          !email ||
          !password ||
          (email.trim().toLowerCase() === demoUser.email && password === 'password');
        if (!ok) return { ok: false, message: 'Use student@edusmart.lk / password' };
        await setSession('demo-token', demoUser);
        setUser(demoUser);
        return { ok: true };
      } catch (e) {
        return { ok: false, message: e.message || 'Unable to sign in.' };
      } finally {
        setBusy(false);
      }
    },
    [online],
  );

  const logout = useCallback(async () => {
    if (online) {
      try {
        await api.logout();
      } catch (e) {
        /* best effort */
      }
    }
    await clearSession();
    setUser(online ? null : demoUser);
  }, [online]);

  const value = useMemo(
    () => ({ user, booting, online, busy, login, logout, setUser }),
    [user, booting, online, busy, login, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>');
  return ctx;
}

export default AuthContext;
