import { createContext, useCallback, useContext, useRef, useState } from 'react';
import { Modal } from 'react-bootstrap';

const ConfirmContext = createContext(null);

/** Promise-based confirmation dialog: `if (await confirm({...})) …`. */
export function ConfirmProvider({ children }) {
  const [state, setState] = useState(null);
  const resolver = useRef(null);

  const confirm = useCallback((options) => new Promise((resolve) => {
    resolver.current = resolve;
    setState({ title: 'Are you sure?', confirmLabel: 'Confirm', variant: 'danger', ...options });
  }), []);

  const close = (result) => {
    resolver.current?.(result);
    resolver.current = null;
    setState(null);
  };

  return (
    <ConfirmContext.Provider value={confirm}>
      {children}
      <Modal show={Boolean(state)} onHide={() => close(false)} centered size="sm">
        <Modal.Body className="text-center p-4">
          <div className="mx-auto mb-3 d-grid rounded-4" style={{ width: 56, height: 56, placeItems: 'center', fontSize: '1.5rem', color: state?.variant === 'danger' ? 'var(--es-critical-ink)' : 'var(--es-primary)', background: state?.variant === 'danger' ? 'color-mix(in srgb, var(--es-critical) 14%, transparent)' : 'var(--es-primary-soft)' }}>
            <i className={`bi bi-${state?.icon ?? (state?.variant === 'danger' ? 'trash3' : 'question-circle')}`} aria-hidden="true" />
          </div>
          <h5 className="fw-bold mb-2">{state?.title}</h5>
          {state?.body && <p className="text-2 small mb-4">{state.body}</p>}
          <div className="d-flex gap-2 justify-content-center">
            <button type="button" className="btn btn-glass" onClick={() => close(false)}>Cancel</button>
            <button type="button" className={`btn btn-${state?.variant === 'danger' ? 'danger' : 'primary'}`} onClick={() => close(true)} autoFocus>
              {state?.confirmLabel}
            </button>
          </div>
        </Modal.Body>
      </Modal>
    </ConfirmContext.Provider>
  );
}

export function useConfirm() {
  const context = useContext(ConfirmContext);
  if (!context) throw new Error('useConfirm must be used inside <ConfirmProvider>');
  return context;
}
