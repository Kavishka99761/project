import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { useToast } from '@/context/ToastContext';
import { api, errorMessage, fieldErrors } from '@/lib/api';

export default function ResetPasswordPage() {
  const [params] = useSearchParams();
  const toast = useToast();
  const navigate = useNavigate();
  const [form, setForm] = useState({ email: params.get('email') ?? '', token: params.get('token') ?? '', password: '', password_confirmation: '' });
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);

  const submit = async (event) => {
    event.preventDefault();
    setBusy(true);
    setErrors({});
    try {
      const { data } = await api.post('/auth/reset-password', form);
      toast.success('Password updated', data.message);
      navigate('/login', { replace: true });
    } catch (error) {
      setErrors(fieldErrors(error));
      if (error.response?.status !== 422) toast.error('Reset failed', errorMessage(error));
    } finally {
      setBusy(false);
    }
  };

  return (
    <AuthLayout title="Choose a new password" subtitle="Your other devices will be signed out." footer={<Link to="/login" className="fw-bold">Back to sign in</Link>}>
      <form onSubmit={submit}>
        <div className="mb-3">
          <label className="form-label" htmlFor="email">Email</label>
          <input id="email" type="email" className={`form-control ${errors.email ? 'is-invalid' : ''}`} value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
          {errors.email && <div className="invalid-feedback">{errors.email}</div>}
        </div>
        {errors.token && <div className="alert alert-danger py-2 small">{errors.token}</div>}
        <div className="mb-3">
          <label className="form-label" htmlFor="password">New password</label>
          <input id="password" type="password" className={`form-control ${errors.password ? 'is-invalid' : ''}`} value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} autoComplete="new-password" required />
          {errors.password && <div className="invalid-feedback">{errors.password}</div>}
        </div>
        <div className="mb-4">
          <label className="form-label" htmlFor="password_confirmation">Confirm new password</label>
          <input id="password_confirmation" type="password" className="form-control" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} autoComplete="new-password" required />
        </div>
        <button type="submit" className="btn btn-primary btn-lg w-100" disabled={busy}>
          {busy && <span className="spinner-border spinner-border-sm me-2" />}Update password
        </button>
      </form>
    </AuthLayout>
  );
}
