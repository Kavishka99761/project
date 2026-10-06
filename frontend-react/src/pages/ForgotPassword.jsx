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
import MarkEmailReadRoundedIcon from '@mui/icons-material/MarkEmailReadRounded';
import { forgotPassword } from '../api/auth';
import { glassSx, moduleColors } from '../theme';
import LiquidBackground from '../components/LiquidBackground';

export default function ForgotPassword() {
  const theme = useTheme();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      const data = await forgotPassword(email);
      setResult(data);
      // In local/demo mode (no SMTP configured) the API includes a dev token
      // so the flow is fully testable; jump straight to reset with it prefilled.
      if (data.dev_reset_token) {
        navigate(`/reset-password?email=${encodeURIComponent(email)}&token=${data.dev_reset_token}`);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Something went wrong. Please try again.');
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
              <MarkEmailReadRoundedIcon />
            </Box>
            <Typography variant="h5" fontWeight={800}>
              Forgot your password?
            </Typography>
            <Typography variant="body2" color="text.secondary" textAlign="center">
              Enter your account email and we'll send you a reset link.
            </Typography>
          </Stack>

          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
            </Alert>
          )}
          {result && !result.dev_reset_token && (
            <Alert severity="success" sx={{ mb: 2 }}>
              {result.message}
            </Alert>
          )}

          <Box component="form" onSubmit={handleSubmit}>
            <Stack spacing={2}>
              <TextField
                label="Email"
                type="email"
                required
                fullWidth
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
              <Button type="submit" variant="contained" size="large" disabled={loading}>
                {loading ? <CircularProgress size={22} color="inherit" /> : 'Send reset link'}
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
