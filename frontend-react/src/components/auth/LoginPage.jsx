import { useState } from 'react';
import {
  Box, Button, TextField, Typography, Stack, Alert, Chip,
  InputAdornment, IconButton, CircularProgress,
} from '@mui/material';
import {
  EmailRounded, LockRounded, VisibilityRounded, VisibilityOffRounded,
  SchoolRounded, AutoAwesomeRounded,
} from '@mui/icons-material';
import { useApp } from '../../context/AppContext';

const features = ['Assignment Risk AI', 'Smart Summaries', 'Academic Chatbot', 'Study Tracker'];

export default function LoginPage({ onSwitchToRegister }) {
  const { signIn, authLoading, authError, setAuthError } = useApp();
  const [email,    setEmail]    = useState('student@acadealert.lk');
  const [password, setPassword] = useState('password');
  const [showPwd,  setShowPwd]  = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setAuthError('');
    if (!email.trim() || !password) {
      setAuthError('Please enter your email and password.');
      return;
    }
    await signIn(email, password);
  };

  return (
    <Box className="login-wrap">
      <Box className="login-card" display="grid" gridTemplateColumns={{ xs: '1fr', md: '1.1fr 1fr' }}>

        {/* ── Hero panel ── */}
        <Box className="login-hero">
          <Stack spacing={3}>
            <Stack direction="row" alignItems="center" spacing={1.5}>
              <Box sx={{
                width: 44, height: 44, borderRadius: 3,
                background: 'rgba(255,255,255,0.2)',
                display: 'grid', placeItems: 'center',
              }}>
                <SchoolRounded sx={{ color: '#fff', fontSize: 22 }} />
              </Box>
              <Typography variant="h6" fontWeight={800} color="#fff">AcadeAlert</Typography>
            </Stack>

            <Typography variant="h3" fontWeight={800} color="#fff" lineHeight={1.15}>
              Your campus<br />workflow, unified.
            </Typography>

            <Typography variant="body1" sx={{ color: 'rgba(255,255,255,0.82)', lineHeight: 1.7 }}>
              Track assignments, study focus, academic dates and learning
              materials in one intelligent student dashboard.
            </Typography>

            <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
              {features.map((f) => (
                <Chip
                  key={f} label={f} size="small"
                  sx={{
                    background: 'rgba(255,255,255,0.12)',
                    color: '#fff',
                    border: '1px solid rgba(255,255,255,0.2)',
                    fontWeight: 600,
                  }}
                />
              ))}
            </Stack>

            <Box sx={{
              mt: 2, p: 2.5, borderRadius: 3,
              background: 'rgba(255,255,255,0.1)',
              border: '1px solid rgba(255,255,255,0.2)',
            }}>
              <Stack direction="row" spacing={1} alignItems="center" mb={0.5}>
                <AutoAwesomeRounded sx={{ color: '#fbbf24', fontSize: 16 }} />
                <Typography variant="caption" color="rgba(255,255,255,0.7)" fontWeight={600}>
                  DEMO ACCOUNT
                </Typography>
              </Stack>
              <Typography variant="body2" color="#fff" fontWeight={600}>student@acadealert.lk</Typography>
              <Typography variant="caption" color="rgba(255,255,255,0.6)">password: password</Typography>
            </Box>
          </Stack>
        </Box>

        {/* ── Form panel ── */}
        <Box className="login-form" component="form" onSubmit={handleSubmit}>
          <Stack spacing={3} height="100%" justifyContent="center">
            <Box>
              <Typography variant="h4" fontWeight={800} gutterBottom>Welcome back</Typography>
              <Typography variant="body2" color="text.secondary">
                Sign in to continue to AcadeAlert
              </Typography>
            </Box>

            <TextField
              label="Email address"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              fullWidth
              autoComplete="email"
              InputProps={{
                startAdornment: (
                  <InputAdornment position="start">
                    <EmailRounded sx={{ color: 'text.secondary', fontSize: 18 }} />
                  </InputAdornment>
                ),
              }}
            />

            <TextField
              label="Password"
              type={showPwd ? 'text' : 'password'}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              fullWidth
              autoComplete="current-password"
              InputProps={{
                startAdornment: (
                  <InputAdornment position="start">
                    <LockRounded sx={{ color: 'text.secondary', fontSize: 18 }} />
                  </InputAdornment>
                ),
                endAdornment: (
                  <InputAdornment position="end">
                    <IconButton size="small" onClick={() => setShowPwd((s) => !s)} edge="end">
                      {showPwd ? <VisibilityOffRounded fontSize="small" /> : <VisibilityRounded fontSize="small" />}
                    </IconButton>
                  </InputAdornment>
                ),
              }}
            />

            {authError && <Alert severity="error" sx={{ borderRadius: 2 }}>{authError}</Alert>}

            <Button
              type="submit" variant="contained" size="large" fullWidth
              disabled={authLoading}
              sx={{ py: 1.4, fontSize: '1rem' }}
            >
              {authLoading ? <CircularProgress size={22} color="inherit" /> : 'Sign in'}
            </Button>

            <Button
              variant="outlined" size="large" fullWidth
              onClick={() => signIn('student@acadealert.lk', 'password')}
              disabled={authLoading}
            >
              Use demo access
            </Button>

            <Typography variant="body2" textAlign="center" color="text.secondary">
              Don't have an account?{' '}
              <Box
                component="span"
                onClick={onSwitchToRegister}
                sx={{ color: 'primary.main', fontWeight: 700, cursor: 'pointer', '&:hover': { textDecoration: 'underline' } }}
              >
                Register here
              </Box>
            </Typography>
          </Stack>
        </Box>
      </Box>
    </Box>
  );
}
