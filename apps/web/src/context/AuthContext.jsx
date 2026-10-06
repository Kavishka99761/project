import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, get, tokenStore } from '@/lib/api';
import { queryClient } from '@/lib/queryClient';

const AuthContext = createContext(null);

/**
 * Common Platform Layer — authentication state. The Sanctum token lives in
 * localStorage; the profile (with settings) is loaded on start-up.
 */
export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [status, setStatus] = useState(() => (tokenStore.get() ? 'loading' : 'guest'));

  useEffect(() => {
    if (!tokenStore.get()) return;
    get('/auth/me')
      .then((me) => {
        setUser(me);
        setStatus('authenticated');
      })
      .catch(() => {
        tokenStore.set(null);
        setStatus('guest');
      });
  }, []);

  useEffect(() => {
    const onUnauthorized = () => {
      tokenStore.set(null);
      queryClient.clear();
      setUser(null);
      setStatus('guest');
    };
    window.addEventListener('edusmart:unauthorized', onUnauthorized);
    return () => window.removeEventListener('edusmart:unauthorized', onUnauthorized);
  }, []);

  const acceptSession = useCallback((payload) => {
    tokenStore.set(payload.token);
    queryClient.clear();
    setUser(payload.user);
    setStatus('authenticated');
    return payload.user;
  }, []);

  const login = useCallback(async (credentials) => {
    const { data } = await api.post('/auth/login', credentials);
    return acceptSession(data);
  }, [acceptSession]);

  const register = useCallback(async (details) => {
    const { data } = await api.post('/auth/register', details);
    return acceptSession(data);
  }, [acceptSession]);

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout');
    } catch {
      /* token may already be invalid */
    }
    tokenStore.set(null);
    queryClient.clear();
    setUser(null);
    setStatus('guest');
  }, []);

  const refreshUser = useCallback(async () => {
    const me = await get('/auth/me');
    setUser(me);
    return me;
  }, []);

  const value = useMemo(() => ({
    user,
    status,
    isAuthenticated: status === 'authenticated',
    login,
    register,
    logout,
    setUser,
    refreshUser,
  }), [user, status, login, register, logout, refreshUser]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used inside <AuthProvider>');
  return context;
}
