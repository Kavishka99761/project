import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import { fetchMe, login as apiLogin, logout as apiLogout, register as apiRegister, updateMe } from '../api/auth';
import { getToken, setToken } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  const loadUser = useCallback(async () => {
    if (!getToken()) {
      setUser(null);
      setLoading(false);
      return;
    }
    try {
      const me = await fetchMe();
      setUser(me);
    } catch (err) {
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadUser();
    const onUnauthorized = () => setUser(null);
    window.addEventListener('edu-smart:unauthorized', onUnauthorized);
    return () => window.removeEventListener('edu-smart:unauthorized', onUnauthorized);
  }, [loadUser]);

  const login = useCallback(async (email, password) => {
    const me = await apiLogin(email, password);
    setUser(me);
    return me;
  }, []);

  const register = useCallback(async (payload) => {
    const me = await apiRegister(payload);
    setUser(me);
    return me;
  }, []);

  const logout = useCallback(async () => {
    await apiLogout();
    setUser(null);
  }, []);

  const updateProfile = useCallback(async (payload) => {
    const me = await updateMe(payload);
    setUser(me);
    return me;
  }, []);

  // Lets pages that already have a fresh user object from another endpoint
  // (avatar upload/remove) update the shared session without a refetch.
  const setUserData = useCallback((me) => setUser(me), []);

  const value = useMemo(
    () => ({
      user,
      loading,
      isAuthenticated: Boolean(user) && Boolean(getToken()),
      login,
      register,
      logout,
      updateProfile,
      setUserData,
      setToken,
    }),
    [user, loading, login, register, logout, updateProfile, setUserData]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
