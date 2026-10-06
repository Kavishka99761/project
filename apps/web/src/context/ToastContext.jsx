import { AnimatePresence, motion } from 'framer-motion';
import { createContext, useCallback, useContext, useMemo, useRef, useState } from 'react';

const ToastContext = createContext(null);

const STYLES = {
  success: { icon: 'check-circle-fill', color: 'var(--es-good-ink)', bg: 'color-mix(in srgb, var(--es-good) 16%, transparent)' },
  error: { icon: 'x-octagon-fill', color: 'var(--es-critical-ink)', bg: 'color-mix(in srgb, var(--es-critical) 16%, transparent)' },
  warning: { icon: 'exclamation-triangle-fill', color: 'var(--es-warning-ink)', bg: 'color-mix(in srgb, var(--es-warning) 20%, transparent)' },
  info: { icon: 'info-circle-fill', color: 'var(--es-primary)', bg: 'var(--es-primary-soft)' },
  reminder: { icon: 'bell-fill', color: 'var(--es-assistant-ink)', bg: 'color-mix(in srgb, var(--es-assistant) 16%, transparent)' },
};

/**
 * Glass toast notifications (bottom-right, stacked, spring animated).
 */
export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const counter = useRef(0);

  const dismiss = useCallback((id) => setToasts((list) => list.filter((t) => t.id !== id)), []);

  const push = useCallback((type, title, body, options = {}) => {
    const id = ++counter.current;
    setToasts((list) => [...list.slice(-3), { id, type, title, body, action: options.action, icon: options.icon }]);
    const duration = options.duration ?? (type === 'error' ? 7000 : 4500);
    if (duration > 0) setTimeout(() => dismiss(id), duration);
    return id;
  }, [dismiss]);

  const toast = useMemo(() => ({
    success: (title, body, options) => push('success', title, body, options),
    error: (title, body, options) => push('error', title, body, options),
    warning: (title, body, options) => push('warning', title, body, options),
    info: (title, body, options) => push('info', title, body, options),
    reminder: (title, body, options) => push('reminder', title, body, options),
    dismiss,
  }), [push, dismiss]);

  return (
    <ToastContext.Provider value={toast}>
      {children}
      <div className="toast-stack" role="region" aria-live="polite" aria-label="Notifications">
        <AnimatePresence initial={false}>
          {toasts.map((t) => {
            const style = STYLES[t.type] ?? STYLES.info;
            return (
              <motion.div
                key={t.id}
                layout
                initial={{ opacity: 0, y: 24, scale: 0.95 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                exit={{ opacity: 0, x: 80, transition: { duration: 0.2 } }}
                transition={{ type: 'spring', stiffness: 420, damping: 32 }}
                className="es-toast glass-strong"
                role="status"
              >
                <div className="toast-icon" style={{ color: style.color, background: style.bg }}>
                  <i className={`bi bi-${t.icon ?? style.icon}`} aria-hidden="true" />
                </div>
                <div className="flex-grow-1 min-w-0">
                  <div className="toast-title">{t.title}</div>
                  {t.body && <div className="toast-body">{t.body}</div>}
                  {t.action && (
                    <button type="button" className="btn btn-link btn-sm p-0 mt-1 fw-semibold" onClick={() => { t.action.onClick(); dismiss(t.id); }}>
                      {t.action.label}
                    </button>
                  )}
                </div>
                <button type="button" className="btn-close btn-sm flex-shrink-0" aria-label="Dismiss" onClick={() => dismiss(t.id)} />
              </motion.div>
            );
          })}
        </AnimatePresence>
      </div>
    </ToastContext.Provider>
  );
}

export function useToast() {
  const context = useContext(ToastContext);
  if (!context) throw new Error('useToast must be used inside <ToastProvider>');
  return context;
}
