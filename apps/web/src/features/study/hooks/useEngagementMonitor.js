import { useCallback, useEffect, useRef, useState } from 'react';

const WINDOW_MS = 60_000;
const IDLE_AFTER_MS = 30_000;

/**
 * Engagement monitoring for a running study session — privacy-friendly
 * signals only (no camera): how much of each minute the study tab was
 * visible and focused, interaction density, idle time and tab switches.
 * Every minute the window is reported to the API (`onSample`), which scores
 * it, detects level changes and stores it in SQL Server.
 *
 * The instant client-side estimate uses the same formula as the server.
 */
export function useEngagementMonitor({ active, onSample }) {
  const [estimate, setEstimate] = useState(null);
  const state = useRef(null);
  const sampleRef = useRef(onSample);
  sampleRef.current = onSample;

  const reset = () => {
    const now = Date.now();
    state.current = {
      windowStart: now,
      focusedMs: 0,
      visibleMs: 0,
      lastTick: now,
      lastInteraction: now,
      idleMs: 0,
      interactions: 0,
      tabSwitches: 0,
      focused: document.hasFocus(),
      visible: document.visibilityState === 'visible',
    };
  };

  const score = (s, windowMs) => {
    const focusRatio = Math.min(1, s.focusedMs / windowMs);
    const idle = Math.min(windowMs, s.idleMs);
    let activity = Math.min(1, s.interactions / Math.max(4, windowMs / 10_000)) * (1 - idle / windowMs);
    if (focusRatio > 0.9 && idle < windowMs * 0.8) activity = Math.max(activity, 0.6);
    let value = 100 * (0.7 * focusRatio + 0.3 * activity) - Math.min(20, 4 * s.tabSwitches);
    value = Math.max(0, Math.min(100, Math.round(value)));
    return { value, focusRatio };
  };

  const tick = useCallback(() => {
    const s = state.current;
    if (!s) return;
    const now = Date.now();
    const step = now - s.lastTick;
    if (s.focused && s.visible) s.focusedMs += step;
    if (s.visible) s.visibleMs += step;
    if (now - s.lastInteraction > IDLE_AFTER_MS) s.idleMs += step;
    s.lastTick = now;
  }, []);

  useEffect(() => {
    if (!active) {
      state.current = null;
      setEstimate(null);
      return undefined;
    }
    reset();

    const onInteract = () => {
      if (!state.current) return;
      state.current.interactions += 1;
      state.current.lastInteraction = Date.now();
    };
    let lastMove = 0;
    const onMove = () => {
      const now = Date.now();
      if (now - lastMove > 2000) {
        lastMove = now;
        onInteract();
      }
    };
    const onVisibility = () => {
      tick();
      if (!state.current) return;
      const visible = document.visibilityState === 'visible';
      if (!visible && state.current.visible) state.current.tabSwitches += 1;
      state.current.visible = visible;
    };
    const onFocus = () => { tick(); if (state.current) state.current.focused = true; };
    const onBlur = () => { tick(); if (state.current) state.current.focused = false; };

    window.addEventListener('keydown', onInteract);
    window.addEventListener('click', onInteract);
    window.addEventListener('scroll', onInteract, { passive: true });
    window.addEventListener('pointermove', onMove, { passive: true });
    window.addEventListener('focus', onFocus);
    window.addEventListener('blur', onBlur);
    document.addEventListener('visibilitychange', onVisibility);

    const live = setInterval(() => {
      tick();
      const s = state.current;
      if (!s) return;
      const windowMs = Math.max(1000, Date.now() - s.windowStart);
      const { value, focusRatio } = score(s, windowMs);
      setEstimate({ score: value, focusRatio, tabSwitches: s.tabSwitches, idleSeconds: Math.round(s.idleMs / 1000), interactions: s.interactions });

      if (windowMs >= WINDOW_MS) {
        sampleRef.current?.({
          window_seconds: Math.round(windowMs / 1000),
          focus_ratio: Number(focusRatio.toFixed(3)),
          visible_ratio: Number(Math.min(1, s.visibleMs / windowMs).toFixed(3)),
          interactions: s.interactions,
          idle_seconds: Math.round(Math.min(windowMs, s.idleMs) / 1000),
          tab_switches: s.tabSwitches,
        });
        reset();
      }
    }, 2000);

    return () => {
      clearInterval(live);
      window.removeEventListener('keydown', onInteract);
      window.removeEventListener('click', onInteract);
      window.removeEventListener('scroll', onInteract);
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('focus', onFocus);
      window.removeEventListener('blur', onBlur);
      document.removeEventListener('visibilitychange', onVisibility);
    };
  }, [active, tick]);

  return estimate;
}
