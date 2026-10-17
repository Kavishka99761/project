import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { AuthCodeField } from '@/features/auth/components/AuthCodeField';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { api, errorMessage, fieldErrors } from '@/lib/api';

const RESEND_SECONDS = 60;

/**
 * Common Platform Layer — confirm the email address used at registration.
 *
 * Reached straight after creating an account, and from the link in the
 * verification email (which carries ?email= and ?code=, so the form is ready
 * to submit). The account already works; confirming the address is what turns
 * on reminder emails and password recovery.
 */
export default function VerifyEmailPage() {
  const [params] = useSearchParams();
  const { user, isAuthenticated, refreshUser } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const [email, setEmail] = useState(params.get('email') ?? user?.email ?? '');
  const [code, setCode] = useState((params.get('code') ?? '').replace(/\D/g, '').slice(0, 6));
  const [errors, setErrors] = useState({});
  const [cooldown, setCooldown] = useState(0);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const submitted = useRef(false);

  useEffect(() => {
    if (cooldown <= 0) return undefined;
    const timer = setTimeout(() => setCooldown((seconds) => seconds - 1), 1000);
    return () => clearTimeout(timer);
  }, [cooldown]);

  const verify = async (event, payload = { email, code }) => {
    event?.preventDefault();
    setBusy(true);
    setErrors({});
    try {
      const { data } = await api.post('/auth/verify-email', payload);
      setDone(true);
      toast.success('Email confirmed', data.message);
      if (isAuthenticated) await refreshUser();
      setTimeout(() => navigate(isAuthenticated ? '/modules?welcome=1' : '/login', { replace: true }), 1200);
    } catch (error) {
      setErrors(fieldErrors(error));
      if (error.response?.status !== 422) toast.error('Could not confirm the address', errorMessage(error));
    } finally {
      setBusy(false);
    }
  };

  // Arriving from the email link: the code is already in the URL, so confirm
  // the address without making the student retype what they just clicked.
  useEffect(() => {
    const linkEmail = params.get('email');
    const linkCode = (params.get('code') ?? '').replace(/\D/g, '');
    if (!submitted.current && linkEmail && linkCode.length === 6) {
      submitted.current = true;
      verify(null, { email: linkEmail, code: linkCode });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const resend = async () => {
    setBusy(true);
    try {
      const { data } = await api.post('/auth/resend-verification', { email });
      toast.success('Code sent', data.message);
      setCooldown(RESEND_SECONDS);
    } catch (error) {
      toast.error('Could not send the code', errorMessage(error));
    } finally {
      setBusy(false);
    }
  };

  return (
    <AuthLayout
      title="Confirm your email"
      subtitle={`We sent a 6-digit code to ${email || 'your email address'}. Enter it to finish setting up your account.`}
      footer={isAuthenticated
        ? <Link to="/dashboard" className="fw-bold">Skip for now</Link>
        : <>Already confirmed? <Link to="/login" className="fw-bold">Sign in</Link></>}
    >
      {done ? (
        <div className="text-center py-3">
          <div className="fs-1 mb-2" style={{ color: 'var(--es-good)' }}><i className="bi bi-patch-check-fill" aria-hidden="true" /></div>
          <p className="text-2 mb-0">Your email address is confirmed. Taking you to your workspace…</p>
        </div>
      ) : (
        <form onSubmit={verify} noValidate>
          <div className="mb-3">
            <label className="form-label" htmlFor="verify-email">Email address</label>
            <input
              id="verify-email"
              type="email"
              className={`form-control ${errors.email ? 'is-invalid' : ''}`}
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoComplete="email"
              required
              readOnly={isAuthenticated && Boolean(user?.email)}
            />
            {errors.email && <div className="invalid-feedback">{errors.email}</div>}
          </div>

          <AuthCodeField
            value={code}
            onChange={setCode}
            error={errors.code}
            hint="Check your inbox — and the spam folder, codes often land there."
            autoFocus
          />

          <button type="submit" className="btn btn-primary btn-lg w-100" disabled={busy || code.length !== 6}>
            {busy ? <span className="spinner-border spinner-border-sm me-2" /> : <i className="bi bi-patch-check me-2" aria-hidden="true" />}
            Confirm my email
          </button>

          <button type="button" className="btn btn-ghost w-100 mt-2" onClick={resend} disabled={busy || cooldown > 0 || !email}>
            {cooldown > 0 ? `Send a new code in ${cooldown}s` : 'Send a new code'}
          </button>
        </form>
      )}
    </AuthLayout>
  );
}
