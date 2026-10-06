import React, { useState } from 'react';
import { Link as RouterLink, useNavigate, useSearchParams } from 'react-router-dom';
import {
  Alert,
  Box,
  Button,
  Card,
  CircularProgress,
  Container,
  Link,
  Stack,
  TextField,
  Typography,
  useTheme,
} from '@mui/material';
import { motion } from 'framer-motion';
import LockResetRoundedIcon from '@mui/icons-material/LockResetRounded';
import { resetPassword } from '../api/auth';
import { glassSx, moduleColors } from '../theme';
import LiquidBackground from '../components/LiquidBackground';

export default function ResetPassword() {
  const theme = useTheme();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const [form, setForm] = useState({
    email: params.get('email') || '',
    token: params.get('token') || '',
    password: '',
    password_confirmation: '',
  });
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      const data = await resetPassword(form);
      setSuccess(data.message);
      setTimeout(() => navigate('/login'), 1500);
    } catch (err) {
      const errors = err.response?.data?.errors;
      const first = errors ? Object.values(errors)[0]?.[0] : null;
      setError(first || err.response?.data?.message || 'Could not reset your password.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Box
      sx={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        position: 'relative',
        overflow: 'hidden',
        background: (t) =>
          t.palette.mode === 'dark'
            ? 'linear-gradient(160deg, #0f1117 0%, #171a23 100%)'
            : 'linear-gradient(160deg, #eef1fb 0%, #f7f8fd 100%)',
      }}
    >
      <LiquidBackground />
      <Container maxWidth="xs" sx={{ position: 'relative', zIndex: 1 }}>
        <Card
          component={motion.div}
          initial={{ opacity: 0, y: 24 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4 }}
          sx={{ p: 4, ...glassSx(theme.palette.mode) }}
        >
          <Stack spacing={0.5} sx={{ mb: 3 }} alignItems="center">
            <Box
              sx={{
                width: 48,
                height: 48,
                borderRadius: 2,
                bgcolor: moduleColors.primary,
                color: '#fff',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                mb: 1,
              }}
            >
              <LockResetRoundedIcon />
            </Box>
            <Typography variant="h5" fontWeight={800}>
              Reset your password
            </Typography>
            <Typography variant="body2" color="text.secondary" textAlign="center">
              Choose a new password for your account.
            </Typography>
          </Stack>

          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
            </Alert>
          )}
          {success && (
            <Alert severity="success" sx={{ mb: 2 }}>
              {success} Redirecting to sign in\u2026
            </Alert>
          )}

          <Box component="form" onSubmit={handleSubmit}>
            <Stack spacing={2}>
              <TextField
                label="Email"
                type="email"
                required
                fullWidth
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
              />
              <TextField
                label="Reset token"
                required
                fullWidth
                helperText="From your email, or auto-filled in local/demo mode"
                value={form.token}
                onChange={(e) => setForm({ ...form, token: e.target.value })}
              />
              <TextField
                label="New password"
                type="password"
                required
                fullWidth
                value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })}
              />
              <TextField
                label="Confirm new password"
                type="password"
                required
                fullWidth
                value={form.password_confirmation}
                onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
              />
              <Button type="submit" variant="contained" size="large" disabled={loading}>
                {loading ? <CircularProgress size={22} color="inherit" /> : 'Reset password'}
              </Button>
            </Stack>
          </Box>

          <Typography variant="body2" textAlign="center" sx={{ mt: 3 }} color="text.secondary">
            <Link component={RouterLink} to="/login">Back to sign in</Link>
          </Typography>
        </Card>
      </Container>
    </Box>
  );
}
