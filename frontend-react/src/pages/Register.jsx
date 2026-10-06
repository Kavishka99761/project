import React, { useState } from 'react';
import { Link as RouterLink, useNavigate } from 'react-router-dom';
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
import { glassSx } from '../theme';
import LiquidBackground from '../components/LiquidBackground';

export default function Register() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const theme = useTheme();
  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    program: '',
  });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await register(form);
      navigate('/dashboard', { replace: true });
    } catch (err) {
      const errors = err.response?.data?.errors;
      const first = errors ? Object.values(errors)[0]?.[0] : null;
      setError(first || err.response?.data?.message || 'Could not create your account.');
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
        py: 4,
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
            <Typography variant="h5" fontWeight={800}>
              Create your account
            </Typography>
            <Typography variant="body2" color="text.secondary" textAlign="center">
              Join EDU-SMART \u2014 one login for every module
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
                label="Full name"
                required
                fullWidth
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
              />
              <TextField
                label="Email"
                type="email"
                required
                fullWidth
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
              />
              <TextField
                label="Program (optional)"
                fullWidth
                value={form.program}
                onChange={(e) => setForm({ ...form, program: e.target.value })}
              />
              <TextField
                label="Password"
                type="password"
                required
                fullWidth
                helperText="At least 6 characters"
                value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })}
              />
              <TextField
                label="Confirm password"
                type="password"
                required
                fullWidth
                value={form.password_confirmation}
                onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
              />
              <Button type="submit" variant="contained" size="large" disabled={loading}>
                {loading ? <CircularProgress size={22} color="inherit" /> : 'Create account'}
              </Button>
            </Stack>
          </Box>

          <Typography variant="body2" textAlign="center" sx={{ mt: 3 }} color="text.secondary">
            Already have an account? <Link component={RouterLink} to="/login">Sign in</Link>
          </Typography>
        </Card>
      </Container>
    </Box>
  );
}
