import { useEffect, useState } from 'react';

/** Current time, re-rendering every `interval` ms (pausable). */
export function useNow(interval = 1000, enabled = true) {
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (!enabled) return undefined;
    const id = setInterval(() => setNow(Date.now()), interval);
    return () => clearInterval(id);
  }, [interval, enabled]);

  return now;
}
