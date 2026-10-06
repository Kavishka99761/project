import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { flushSync } from 'react-dom';

const ThemeContext = createContext(null);
const KEY = 'edusmart.theme';
const MOTION_KEY = 'edusmart.reduceMotion';
const GLASS_KEY = 'edusmart.glass';

const readStorage = (key, fallback) => {
  try {
    return localStorage.getItem(key) ?? fallback;
  } catch {
    return fallback;
  }
};
const writeStorage = (key, value) => {
  try {
    localStorage.setItem(key, value);
  } catch {
    /* ignore */
  }
};

const systemDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

/**
 * Common UI — light / dark / system theme, reduced motion and glass effects.
 * Theme switches animate as an expanding circle from the toggle using the
 * View Transitions API (when supported and motion is allowed).
 */
export function ThemeProvider({ children }) {
  const [preference, setPreference] = useState(() => readStorage(KEY, 'system'));
  const [systemIsDark, setSystemIsDark] = useState(systemDark);
  const [reduceMotion, setReduceMotionState] = useState(() => readStorage(MOTION_KEY, 'false') === 'true');
  const [glass, setGlassState] = useState(() => readStorage(GLASS_KEY, 'true') !== 'false');

  const resolved = preference === 'system' ? (systemIsDark ? 'dark' : 'light') : preference;

  useEffect(() => {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const onChange = (event) => setSystemIsDark(event.matches);
    media.addEventListener('change', onChange);
    return () => media.removeEventListener('change', onChange);
  }, []);

  useEffect(() => {
    document.documentElement.setAttribute('data-bs-theme', resolved);
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', resolved === 'dark' ? '#090b12' : '#eef1f8');
  }, [resolved]);

  useEffect(() => {
    document.documentElement.classList.toggle('reduce-motion', reduceMotion);
    document.documentElement.classList.toggle('no-glass', !glass);
  }, [reduceMotion, glass]);

  useEffect(() => {
    // Progressive enhancement: real refraction where SVG backdrop filters work.
    const supported = typeof CSS !== 'undefined' && CSS.supports('backdrop-filter', 'url(#es-liquid)') && /Chrome\//.test(navigator.userAgent);
    document.documentElement.classList.toggle('has-liquid-refraction', supported);
  }, []);

  const setTheme = useCallback((next, origin) => {
    const nextResolved = next === 'system' ? (systemDark() ? 'dark' : 'light') : next;
    const apply = () => {
      writeStorage(KEY, next);
      setPreference(next);
      document.documentElement.setAttribute('data-bs-theme', nextResolved);
    };

    const motionOk = !reduceMotion && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!document.startViewTransition || !motionOk || nextResolved === document.documentElement.getAttribute('data-bs-theme')) {
      apply();
      return;
    }

    const x = origin?.clientX ?? window.innerWidth - 80;
    const y = origin?.clientY ?? 40;
    const radius = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));
    const transition = document.startViewTransition(() => flushSync(apply));
    transition.ready.then(() => {
      document.documentElement.animate(
        { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${radius}px at ${x}px ${y}px)`] },
        { duration: 650, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)', pseudoElement: '::view-transition-new(root)' },
      );
    }).catch(() => {});
  }, [reduceMotion]);

  const setReduceMotion = useCallback((value) => {
    writeStorage(MOTION_KEY, String(value));
    setReduceMotionState(value);
  }, []);

  const setGlass = useCallback((value) => {
    writeStorage(GLASS_KEY, String(value));
    setGlassState(value);
  }, []);

  const value = useMemo(() => ({
    preference, theme: resolved, setTheme, reduceMotion, setReduceMotion, glass, setGlass,
  }), [preference, resolved, setTheme, reduceMotion, setReduceMotion, glass, setGlass]);

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
  const context = useContext(ThemeContext);
  if (!context) throw new Error('useTheme must be used inside <ThemeProvider>');
  return context;
}
