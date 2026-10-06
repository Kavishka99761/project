import React, { useState } from 'react';
import { Link as RouterLink, useLocation, useNavigate } from 'react-router-dom';
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
import { useAuth } from '../context/AuthContext';
import { moduleColors, glassSx } from '../theme';
import LiquidBackground from '../components/LiquidBackground';

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const theme = useTheme();
  const [form, setForm] = useState({ email: 'student@edusmart.lk', password: 'password' });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await login(form.email, form.password);
      navigate(location.state?.from?.pathname || '/dashboard', { replace: true });
    } catch (err) {
      setError(err.response?.data?.message || 'Invalid email or password.');
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
                fontWeight: 800,
                mb: 1,
              }}
            >
              E
            </Box>
            <Typography variant="h5" fontWeight={800}>
              Welcome back
            </Typography>
            <Typography variant="body2" color="text.secondary" textAlign="center">
              Sign in to your EDU-SMART account
            </Typography>
          </Stack>

          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
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
                label="Password"
                type="password"
                required
                fullWidth
                value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })}
              />
              <Stack direction="row" justifyContent="flex-end">
                <Link component={RouterLink} to="/forgot-password" variant="body2">
                  Forgot password?
                </Link>
              </Stack>
              <Button type="submit" variant="contained" size="large" disabled={loading}>
                {loading ? <CircularProgress size={22} color="inherit" /> : 'Sign in'}
              </Button>
            </Stack>
          </Box>

          <Typography variant="body2" textAlign="center" sx={{ mt: 3 }} color="text.secondary">
            No account? <Link component={RouterLink} to="/register">Create one</Link>
          </Typography>
          <Typography variant="caption" display="block" textAlign="center" sx={{ mt: 1.5 }} color="text.secondary">
            Demo: student@edusmart.lk / password
          </Typography>
        </Card>
      </Container>
    </Box>
  );
}
