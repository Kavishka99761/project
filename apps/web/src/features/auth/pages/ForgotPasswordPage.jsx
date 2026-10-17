import { Link, useLocation, useNavigate } from 'react-router-dom';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { InlineForgotPassword } from '@/features/auth/components/InlineForgotPassword';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { errorMessage } from '@/lib/api';

/**
 * Common Platform Layer — the standalone password recovery page, for the
 * "Forgot password?" link in an email or a bookmark. It runs exactly the same
 * steps as the panel inside the login form: email → emailed code → new
 * password → signed in.
 */
export default function ForgotPasswordPage() {
  const { login } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const location = useLocation();

  const signIn = async ({ email, password }) => {
    try {
      const user = await login({ email, password, remember: true });
      toast.success(`Welcome back, ${user.name.split(' ')[0]}!`, 'Your new password is in use.');
      navigate(location.state?.from?.pathname ?? '/dashboard', { replace: true });
    } catch (error) {
      toast.error('Password changed, but sign-in failed', errorMessage(error));
      navigate('/login', { replace: true });
    }
  };

  return (
    <AuthLayout
      title="Reset your password"
      subtitle="We email a 6-digit code to prove the account is yours."
      footer={<Link to="/login" className="fw-bold"><i className="bi bi-arrow-left me-1" />Back to sign in</Link>}
    >
      <InlineForgotPassword onReset={signIn} />
    </AuthLayout>
  );
}
