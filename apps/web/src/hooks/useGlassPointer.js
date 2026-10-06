import { useCallback } from 'react';

/**
 * Pointer-tracking specular light for `.glass-interactive` surfaces:
 * writes the pointer position into --mx / --my on the element.
 */
export function useGlassPointer() {
  return useCallback((event) => {
    const element = event.currentTarget;
    const rect = element.getBoundingClientRect();
    element.style.setProperty('--mx', `${event.clientX - rect.left}px`);
    element.style.setProperty('--my', `${event.clientY - rect.top}px`);
  }, []);
}
