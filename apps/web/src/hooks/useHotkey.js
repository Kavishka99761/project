import { useEffect, useRef } from 'react';

/**
 * Global keyboard shortcut. `combo` like "mod+k" (Ctrl on Windows/Linux,
 * ⌘ on macOS), "shift+/", "escape".
 */
export function useHotkey(combo, handler, { enabled = true, allowInInputs = false } = {}) {
  const saved = useRef(handler);
  saved.current = handler;

  useEffect(() => {
    if (!enabled) return undefined;
    const parts = combo.toLowerCase().split('+');
    const key = parts.pop();
    const needsMod = parts.includes('mod');
    const needsShift = parts.includes('shift');

    const onKeyDown = (event) => {
      const target = event.target;
      const typing = target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
      if (typing && !allowInInputs && !needsMod) return;
      const mod = event.ctrlKey || event.metaKey;
      if (needsMod !== mod || needsShift !== event.shiftKey) return;
      if (event.key.toLowerCase() !== key) return;
      event.preventDefault();
      saved.current(event);
    };

    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [combo, enabled, allowInInputs]);
}
