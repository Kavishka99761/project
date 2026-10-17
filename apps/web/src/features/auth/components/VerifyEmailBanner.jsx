import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { api, errorMessage } from '@/lib/api';

/**
 * Common Platform Layer — the reminder every real service shows until the
 * address is confirmed. Nothing is blocked: reminder emails and password
 * recovery simply cannot reach an unconfirmed address, so it is worth nagging.
 */
export function VerifyEmailBanner() {
  const { user } = useAuth();
  const toast = useToast();
  const [busy, setBusy] = useState(false);
  const [hidden, setHidden] = useState(false);

  if (!user || user.email_verified !== false || hidden) return null;

  const resend = async () => {
    setBusy(true);
    try {
      const { data } = await api.post('/auth/resend-verification', { email: user.email });
      toast.success('Code sent', data.message);
    } catch (error) {
      toast.error('Could not send the code', errorMessage(error));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="px-3 px-lg-4 pt-3">
      <div className="glass d-flex flex-wrap align-items-center gap-2 p-3" role="status">
        <i className="bi bi-envelope-exclamation fs-5" style={{ color: 'var(--es-warning, #f59e0b)' }} aria-hidden="true" />
        <div className="small flex-grow-1">
          <span className="fw-bold">Confirm your email address.</span>{' '}
          <span className="text-2">
            We sent a 6-digit code to {user.email}. Until it is confirmed, deadline reminders and password
            recovery cannot reach you.
          </span>
        </div>
        <Link to="/verify-email" className="btn btn-primary btn-sm">Enter the code</Link>
        <button type="button" className="btn btn-ghost btn-sm" onClick={resend} disabled={busy}>
          {busy && <span className="spinner-border spinner-border-sm me-2" />}Resend
        </button>
        <button type="button" className="btn btn-ghost btn-sm" onClick={() => setHidden(true)} aria-label="Hide this reminder">
          <i className="bi bi-x-lg" aria-hidden="true" />
        </button>
      </div>
    </div>
  );
}
