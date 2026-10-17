import { useEffect, useRef, useState } from 'react';
import { motion } from 'framer-motion';
import { AuthCodeField } from '@/features/auth/components/AuthCodeField';
import { useToast } from '@/context/ToastContext';
import { api, errorMessage, fieldErrors } from '@/lib/api';

const RULES = 'At least 8 characters, including letters and numbers.';
const RESEND_SECONDS = 60;

/**
 * Common Platform Layer — "Forgot password?" handled inside the login form,
 * the way a deployed service does it:
 *
 *   1. type the username (email)      → the API emails a 6-digit code
 *   2. type the code + a new password → the API checks the code and saves it
 *   3. the login form signs you in with the new password
 *
 * The current password is never shown anywhere: only a one-way hash of it is
 * stored, and no API response ever carries a secret — the code exists only in
 * the mailbox, which is what proves the account is yours.
 *
 * @param {object} props
 * @param {string} props.initialEmail   email already typed in the login form
 * @param {() => void} [props.onCancel] close the panel (omit to hide the X)
 * @param {(credentials: {email: string, password: string}) => void} props.onReset
 *        called after a successful reset so the caller can sign the user in
 */
export function InlineForgotPassword({ initialEmail = '', onCancel, onReset }) {
  const toast = useToast();
  const [stage, setStage] = useState('request'); // request → code
  const [form, setForm] = useState({ email: initialEmail, code: '', password: '', password_confirmation: '' });
  const [sentTo, setSentTo] = useState('');
  const [expiresIn, setExpiresIn] = useState(15);
  const [cooldown, setCooldown] = useState(0);
  const [errors, setErrors] = useState({});
  const [showPassword, setShowPassword] = useState(false);
  const [busy, setBusy] = useState(false);
  const codeRef = useRef(null);

  const update = (patch) => setForm((current) => ({ ...current, ...patch }));

  // "Resend code" stays disabled for a minute, like every service that emails
  // codes — the client-side half of the API's per-address rate limit.
  useEffect(() => {
    if (cooldown <= 0) return undefined;
    const timer = setTimeout(() => setCooldown((seconds) => seconds - 1), 1000);
    return () => clearTimeout(timer);
  }, [cooldown]);

  /** Step 1 — ask the API to email a code. The answer never says whether the account exists. */
  const sendCode = async (event, { resend = false } = {}) => {
    event?.preventDefault();
    setBusy(true);
    setErrors({});
    try {
      const { data } = await api.post('/auth/forgot-password', { email: form.email });
      setExpiresIn(Number(/(\d+)\s*minutes?/.exec(data.message ?? '')?.[1]) || 15);
      setSentTo(form.email);
      setStage('code');
      setCooldown(RESEND_SECONDS);
      if (resend) toast.success('Code sent again', `Check ${form.email} for the new code.`);
      setTimeout(() => codeRef.current?.focus(), 80);
    } catch (error) {
      setErrors(fieldErrors(error));
      if (error.response?.status !== 422) toast.error('Could not send the code', errorMessage(error));
    } finally {
      setBusy(false);
    }
  };

  /** Step 2 — the code plus the new password. */
  const savePassword = async (event) => {
    event.preventDefault();
    setBusy(true);
    setErrors({});
    try {
      await api.post('/auth/reset-password', form);
      toast.success('Password updated', 'Signing you in with your new password…');
      onReset?.({ email: form.email, password: form.password });
    } catch (error) {
      setErrors(fieldErrors(error));
      if (error.response?.status !== 422) toast.error('Could not update the password', errorMessage(error));
    } finally {
      setBusy(false);
    }
  };

  return (
    <motion.div
      id="inline-forgot-password"
      className="glass p-3 p-md-4 mt-3"
      initial={{ opacity: 0, height: 0 }}
      animate={{ opacity: 1, height: 'auto' }}
      exit={{ opacity: 0, height: 0 }}
      transition={{ duration: 0.25 }}
      style={{ overflow: 'hidden' }}
    >
      <div className="d-flex align-items-start justify-content-between gap-2 mb-3">
        <div>
          <div className="fw-bold"><i className="bi bi-key me-2" aria-hidden="true" />Forgot your password?</div>
          <div className="small text-3">
            {stage === 'request'
              ? 'Enter your username — the email you sign in with.'
              : `We emailed a 6-digit code to ${sentTo}. It expires in ${expiresIn} minutes.`}
          </div>
        </div>
        {onCancel && (
          <button type="button" className="btn btn-ghost btn-sm" onClick={onCancel} aria-label="Close password recovery">
            <i className="bi bi-x-lg" aria-hidden="true" />
          </button>
        )}
      </div>

      {stage === 'request' ? (
        <form onSubmit={sendCode} noValidate>
          <label className="form-label" htmlFor="recover-email">Username (email)</label>
          <input
            id="recover-email"
            type="email"
            className={`form-control ${errors.email ? 'is-invalid' : ''}`}
            value={form.email}
            onChange={(e) => update({ email: e.target.value })}
            placeholder="you@university.lk"
            autoComplete="username"
            required
            autoFocus
          />
          {errors.email && <div className="invalid-feedback d-block">{errors.email}</div>}
          <div className="small text-3 mt-2">
            <i className="bi bi-shield-lock me-1" aria-hidden="true" />
            Your existing password cannot be shown — it is stored as a one-way hash, so nobody can read it back.
            The emailed code lets you set a new one.
          </div>
          <button type="submit" className="btn btn-primary w-100 mt-3" disabled={busy}>
            {busy ? <span className="spinner-border spinner-border-sm me-2" /> : <i className="bi bi-envelope-arrow-up me-2" aria-hidden="true" />}
            Email me a reset code
          </button>
        </form>
      ) : (
        <form onSubmit={savePassword} noValidate>
          <AuthCodeField id="recover-code" ref={codeRef} value={form.code} onChange={(code) => update({ code })} error={errors.code} />

          <label className="form-label" htmlFor="recover-password">New password</label>
          <div className="position-relative">
            <input
              id="recover-password"
              type={showPassword ? 'text' : 'password'}
              className={`form-control pe-5 ${errors.password ? 'is-invalid' : ''}`}
              value={form.password}
              onChange={(e) => update({ password: e.target.value })}
              autoComplete="new-password"
              required
            />
            <button
              type="button"
              className="btn btn-ghost btn-sm position-absolute top-50 end-0 translate-middle-y me-1"
              onClick={() => setShowPassword(!showPassword)}
              aria-label={showPassword ? 'Hide password' : 'Show password'}
            >
              <i className={`bi bi-eye${showPassword ? '-slash' : ''}`} aria-hidden="true" />
            </button>
          </div>
          <div className={`small mt-1 mb-3 ${errors.password ? 'text-danger' : 'text-3'}`}>{errors.password ?? RULES}</div>

          <label className="form-label" htmlFor="recover-confirm">Confirm new password</label>
          <input
            id="recover-confirm"
            type={showPassword ? 'text' : 'password'}
            className="form-control mb-3"
            value={form.password_confirmation}
            onChange={(e) => update({ password_confirmation: e.target.value })}
            autoComplete="new-password"
            required
          />

          <button type="submit" className="btn btn-primary w-100" disabled={busy}>
            {busy ? <span className="spinner-border spinner-border-sm me-2" /> : <i className="bi bi-check2-circle me-2" aria-hidden="true" />}
            Set new password &amp; sign in
          </button>

          <div className="d-flex justify-content-between align-items-center mt-3 small">
            <button type="button" className="btn btn-link btn-sm p-0 text-decoration-none" onClick={() => { setStage('request'); setErrors({}); }}>
              <i className="bi bi-arrow-left me-1" aria-hidden="true" />Different email
            </button>
            <button
              type="button"
              className="btn btn-link btn-sm p-0 text-decoration-none"
              onClick={(event) => sendCode(event, { resend: true })}
              disabled={busy || cooldown > 0}
            >
              {cooldown > 0 ? `Resend code in ${cooldown}s` : 'Resend code'}
            </button>
          </div>
        </form>
      )}
    </motion.div>
  );
}
