import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { Meter } from '@/components/ui/Progress';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { errorMessage, fieldErrors } from '@/lib/api';
import { STATUS } from '@/config/palette';

export function passwordStrength(password) {
  let score = 0;
  if (password.length >= 8) score += 1;
  if (password.length >= 12) score += 1;
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score += 1;
  if (/\d/.test(password)) score += 1;
  if (/[^A-Za-z0-9]/.test(password)) score += 1;
  const labels = ['Too weak', 'Weak', 'Fair', 'Good', 'Strong', 'Excellent'];
  const colors = [STATUS.critical, STATUS.critical, STATUS.serious, STATUS.warning, STATUS.good, STATUS.good];
  return { score, label: labels[score], color: colors[score] };
}

export default function RegisterPage() {
  const { register } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: '', email: '', program: '', password: '', password_confirmation: '' });
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const strength = passwordStrength(form.password);
  const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });

  const submit = async (event) => {
    event.preventDefault();
    setBusy(true);
    setErrors({});
    try {
      await register(form);
      toast.success('Account created', 'Start by adding your modules.');
      navigate('/modules?welcome=1', { replace: true });
    } catch (error) {
      setErrors(fieldErrors(error));
      if (error.response?.status !== 422) toast.error('Registration failed', errorMessage(error));
    } finally {
      setBusy(false);
    }
  };

  const input = (key, label, type = 'text', extra = {}) => (
    <div className="mb-3">
      <label className="form-label" htmlFor={key}>{label}</label>
      <input id={key} type={type} className={`form-control ${errors[key] ? 'is-invalid' : ''}`} value={form[key]} onChange={set(key)} {...extra} />
      {errors[key] && <div className="invalid-feedback">{errors[key]}</div>}
    </div>
  );

  return (
    <AuthLayout title="Create your account" subtitle="One workspace for notes, focus, questions and deadlines." footer={<>Already registered? <Link to="/login" className="fw-bold">Sign in</Link></>}>
      <form onSubmit={submit} noValidate>
        {input('name', 'Full name', 'text', { autoComplete: 'name', required: true, autoFocus: true })}
        {input('email', 'University email', 'email', { autoComplete: 'email', required: true })}
        {input('program', 'Degree programme (optional)', 'text', { placeholder: 'BSc (Hons) in Software Engineering' })}
        <div className="row g-2">
          <div className="col-sm-6">{input('password', 'Password', 'password', { autoComplete: 'new-password', required: true })}</div>
          <div className="col-sm-6">{input('password_confirmation', 'Confirm password', 'password', { autoComplete: 'new-password', required: true })}</div>
        </div>
        {form.password && (
          <div className="mb-3">
            <Meter value={strength.score} max={5} color={strength.color} label="Password strength" />
            <div className="small mt-1 text-2">Strength: <strong>{strength.label}</strong> · use 8+ characters with letters and numbers</div>
          </div>
        )}
        <button type="submit" className="btn btn-primary btn-lg w-100 mt-2" disabled={busy}>
          {busy && <span className="spinner-border spinner-border-sm me-2" />}Create account
        </button>
      </form>
    </AuthLayout>
  );
}
