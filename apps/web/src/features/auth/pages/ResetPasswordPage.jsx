import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { AuthCodeField } from '@/features/auth/components/AuthCodeField';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { api, errorMessage, fieldErrors } from '@/lib/api';

/**
 * Common Platform Layer — the page the "Reset my password" button in the email
 * opens. The link carries ?email= and ?code=, so only the new password is left
 * to type; the same form works if the code is typed by hand.
 */
export default function ResetPasswordPage() {
  const [params] = useSearchParams();
  const { login } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const [form, setForm] = useState({
    email: params.get('email') ?? '',
    code: (params.get('code') ?? '').replace(/\D/g, '').slice(0, 6),
    password: '',
    password_confirmation: '',
  });
  const [errors, setErrors] = useState({});
  const [showPassword, setShowPassword] = useState(false);
  const [busy, setBusy] = useState(false);

  const update = (patch) => setForm((current) => ({ ...current, ...patch }));

  const submit = async (event) => {
    event.preventDefault();
    setBusy(true);
    setErrors({});
    try {
      const { data } = await api.post('/auth/reset-password', form);
      toast.success('Password updated', data.message);
      try {
        const user = await login({ email: form.email, password: form.password, remember: true });
        toast.success(`Welcome back, ${user.name.split(' ')[0]}!`, 'Your new password is in use.');
        navigate('/dashboard', { replace: true });
      } catch {
        navigate('/login', { replace: true });
      }
    } catch (error) {
      setErrors(fieldErrors(error));
      if (error.response?.status !== 422) toast.error('Reset failed', errorMessage(error));
    } finally {
      setBusy(false);
    }
  };

  return (
    <AuthLayout
      title="Choose a new password"
      subtitle="Enter the code from the email and your new password. Your other devices will be signed out."
      footer={<>Code expired? <Link to="/forgot-password" className="fw-bold">Request a new one</Link></>}
    >
      <form onSubmit={submit} noValidate>
        <div className="mb-3">
          <label className="form-label" htmlFor="email">Email</label>
          <input
            id="email"
            type="email"
            className={`form-control ${errors.email ? 'is-invalid' : ''}`}
            value={form.email}
            onChange={(e) => update({ email: e.target.value })}
            autoComplete="username"
            required
          />
          {errors.email && <div className="invalid-feedback">{errors.email}</div>}
        </div>

        <AuthCodeField value={form.code} onChange={(code) => update({ code })} error={errors.code} />

        <label className="form-label" htmlFor="password">New password</label>
        <div className="position-relative">
          <input
            id="password"
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
        <div className={`small mt-1 mb-3 ${errors.password ? 'text-danger' : 'text-3'}`}>
          {errors.password ?? 'At least 8 characters, including letters and numbers.'}
        </div>

        <div className="mb-4">
          <label className="form-label" htmlFor="password_confirmation">Confirm new password</label>
          <input
            id="password_confirmation"
            type={showPassword ? 'text' : 'password'}
            className="form-control"
            value={form.password_confirmation}
            onChange={(e) => update({ password_confirmation: e.target.value })}
            autoComplete="new-password"
            required
          />
        </div>

        <button type="submit" className="btn btn-primary btn-lg w-100" disabled={busy}>
          {busy && <span className="spinner-border spinner-border-sm me-2" />}Update password
        </button>
      </form>
    </AuthLayout>
  );
}
