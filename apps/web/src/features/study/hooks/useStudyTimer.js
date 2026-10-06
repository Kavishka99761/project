import { useMemo, useRef } from 'react';
import { useNow } from '@/hooks/useNow';

/**
 * Live timer derived from the server-authoritative session: the server sends
 * elapsed_seconds at server_time; we add the time passed since that response.
 */
export function useStudyTimer(session) {
  const anchor = useRef({ key: null, at: Date.now() });
  const running = session?.status === 'active';
  const onBreak = session?.status === 'on_break';
  const now = useNow(1000, Boolean(session?.is_live));

  // Re-anchor whenever the server sends a new snapshot.
  const key = session ? `${session.id}:${session.status}:${session.elapsed_seconds}:${session.current_break_seconds}:${session.server_time}` : null;
  if (anchor.current.key !== key) anchor.current = { key, at: Date.now() };
  const anchoredAt = anchor.current.at;

  return useMemo(() => {
    if (!session) return { elapsed: 0, breakElapsed: 0, plannedSeconds: 0, remaining: 0, progress: 0, overtime: false };
    const delta = Math.max(0, (now - anchoredAt) / 1000);
    const elapsed = session.elapsed_seconds + (running ? delta : 0);
    const breakElapsed = session.current_break_seconds + (onBreak ? delta : 0);
    const plannedSeconds = session.planned_minutes * 60;

    return {
      elapsed,
      breakElapsed,
      plannedSeconds,
      remaining: Math.max(0, plannedSeconds - elapsed),
      progress: Math.min(100, (elapsed / Math.max(1, plannedSeconds)) * 100),
      overtime: elapsed > plannedSeconds,
    };
  }, [session, now, running, onBreak, anchoredAt]);
}
