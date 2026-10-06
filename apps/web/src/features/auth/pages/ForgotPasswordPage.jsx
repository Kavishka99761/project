import { useState } from 'react';
import { Link } from 'react-router-dom';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { useToast } from '@/context/ToastContext';
import { api, errorMessage } from '@/lib/api';

export default function ForgotPasswordPage() {
  const toast = useToast();
  const [email, setEmail] = useState('');
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState(null);

  const submit = async (event) => {
    event.preventDefault();
    setBusy(true);
    try {
      const { data } = await api.post('/auth/forgot-password', { email });
      setResult(data);
    } catch (error) {
      toast.error('Could not send the link', errorMessage(error));
    } finally {
      setBusy(false);
    }
  };

  return (
    <AuthLayout title="Reset your password" subtitle="We'll send a secure link to your email address." footer={<Link to="/login" className="fw-bold"><i className="bi bi-arrow-left me-1" />Back to sign in</Link>}>
      {result ? (
        <div className="text-center">
          <div className="fs-1 mb-2" style={{ color: 'var(--es-good)' }}><i className="bi bi-envelope-check" aria-hidden="true" /></div>
          <p className="text-2">{result.message}</p>
          {result.dev_reset_link && (
            <div className="glass p-3 text-start small">
              <div className="fw-bold mb-1"><i className="bi bi-tools me-1" />Development mode</div>
              No mail server is configured, so the link is shown here (it is also written to the API log):
              <a href={result.dev_reset_link.replace(/^https?:\/\/[^/]+/, '')} className="d-block mt-2 text-break fw-semibold">Open the reset link</a>
            </div>
          )}
        </div>
      ) : (
        <form onSubmit={submit}>
          <label className="form-label" htmlFor="email">Email</label>
          <input id="email" type="email" className="form-control form-control-lg mb-3" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus />
          <button type="submit" className="btn btn-primary btn-lg w-100" disabled={busy}>
            {busy && <span className="spinner-border spinner-border-sm me-2" />}Send reset link
          </button>
        </form>
      )}
    </AuthLayout>
  );
}
