import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { DEMO_CREDENTIALS } from '@/config/env';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { errorMessage, fieldErrors } from '@/lib/api';

export default function LoginPage() {
  const { login } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const location = useLocation();
  const [form, setForm] = useState({ email: '', password: '', remember: true });
  const [errors, setErrors] = useState({});
  const [showPassword, setShowPassword] = useState(false);
  const [busy, setBusy] = useState(false);

  const submit = async (event, credentials = form) => {
    event?.preventDefault();
    setBusy(true);
    setErrors({});
    try {
      const user = await login({ email: credentials.email, password: credentials.password, remember: credentials.remember });
      toast.success(`Welcome back, ${user.name.split(' ')[0]}!`, 'Your workspace is ready.');
      navigate(location.state?.from?.pathname ?? '/dashboard', { replace: true });
    } catch (error) {
      setErrors(fieldErrors(error));
      if (!error.response || error.response.status !== 422) toast.error('Sign-in failed', errorMessage(error));
    } finally {
      setBusy(false);
    }
  };

  const useDemo = () => {
    const demo = { ...DEMO_CREDENTIALS, remember: true };
    setForm(demo);
    submit(null, demo);
  };

  return (
    <AuthLayout
      title="Welcome back"
      subtitle="Sign in to continue to your study workspace."
      footer={<>New to EDU-SMART? <Link to="/register" className="fw-bold">Create an account</Link></>}
    >
      <form onSubmit={submit} noValidate>
        <div className="mb-3">
          <label className="form-label" htmlFor="email">Email</label>
          <input id="email" type="email" className={`form-control form-control-lg ${errors.email ? 'is-invalid' : ''}`} autoComplete="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder="you@university.lk" required autoFocus />
          {errors.email && <div className="invalid-feedback">{errors.email}</div>}
        </div>
        <div className="mb-3">
          <div className="d-flex justify-content-between">
            <label className="form-label" htmlFor="password">Password</label>
            <Link to="/forgot-password" className="small fw-semibold">Forgot password?</Link>
          </div>
          <div className="position-relative">
            <input id="password" type={showPassword ? 'text' : 'password'} className={`form-control form-control-lg pe-5 ${errors.password ? 'is-invalid' : ''}`} autoComplete="current-password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required />
            <button type="button" className="btn btn-ghost btn-sm position-absolute top-50 end-0 translate-middle-y me-1" onClick={() => setShowPassword(!showPassword)} aria-label={showPassword ? 'Hide password' : 'Show password'}>
              <i className={`bi bi-eye${showPassword ? '-slash' : ''}`} aria-hidden="true" />
            </button>
          </div>
        </div>
        <div className="form-check mb-4">
          <input id="remember" className="form-check-input" type="checkbox" checked={form.remember} onChange={(e) => setForm({ ...form, remember: e.target.checked })} />
          <label className="form-check-label small" htmlFor="remember">Keep me signed in on this device</label>
        </div>
        <button type="submit" className="btn btn-primary btn-lg w-100" disabled={busy}>
          {busy ? <span className="spinner-border spinner-border-sm me-2" /> : <i className="bi bi-box-arrow-in-right me-2" aria-hidden="true" />}
          Sign in
        </button>
        <div className="d-flex align-items-center gap-3 my-3 text-3 small">
          <hr className="flex-grow-1" /> or <hr className="flex-grow-1" />
        </div>
        <button type="button" className="btn btn-glass w-100" onClick={useDemo} disabled={busy}>
          <i className="bi bi-stars me-2" aria-hidden="true" />Explore the demo student account
        </button>
        <p className="text-center text-3 small mt-3 mb-0">Demo: {DEMO_CREDENTIALS.email} / {DEMO_CREDENTIALS.password}</p>
      </form>
    </AuthLayout>
  );
}
