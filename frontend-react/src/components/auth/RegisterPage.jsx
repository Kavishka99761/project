import { useState } from 'react';
import {
  Box, Button, TextField, Typography, Stack, Alert, CircularProgress,
  InputAdornment, IconButton,
} from '@mui/material';
import {
  PersonRounded, EmailRounded, LockRounded, SchoolRounded,
  VisibilityRounded, VisibilityOffRounded,
} from '@mui/icons-material';
import { useApp } from '../../context/AppContext';

export default function RegisterPage({ onSwitchToLogin }) {
  const { signUp, authLoading, authError, setAuthError } = useApp();
  const [form, setForm]     = useState({ name: '', email: '', program: '', password: '', password_confirmation: '' });
  const [showPwd, setShowPwd] = useState(false);

  const set = (field) => (e) => setForm((f) => ({ ...f, [field]: e.target.value }));

  const handleSubmit = async (e) => {
    e.preventDefault();
    setAuthError('');
    if (form.password !== form.password_confirmation) {
      setAuthError('Passwords do not match.');
      return;
    }
    await signUp(form);
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
              Start your<br />academic journey.
            </Typography>

            <Typography variant="body1" sx={{ color: 'rgba(255,255,255,0.82)', lineHeight: 1.7 }}>
              Create your free account to access AI-powered summaries,
              assignment risk tracking, and a personalised study planner.
            </Typography>

            {[
              '📚 Upload & summarise lecture notes',
              '⚠️ AI-powered deadline risk alerts',
              '🤖 Academic chatbot with source citations',
              '⏱️ Study session tracker & analytics',
            ].map((item) => (
              <Typography key={item} variant="body2" sx={{ color: 'rgba(255,255,255,0.82)' }}>
                {item}
              </Typography>
            ))}
          </Stack>
        </Box>

        {/* ── Form panel ── */}
        <Box className="login-form" component="form" onSubmit={handleSubmit}>
          <Stack spacing={2.5} height="100%" justifyContent="center">
            <Box>
              <Typography variant="h4" fontWeight={800} gutterBottom>Create account</Typography>
              <Typography variant="body2" color="text.secondary">Join AcadeAlert — it's free</Typography>
            </Box>

            <TextField
              label="Full name" value={form.name} onChange={set('name')}
              fullWidth autoComplete="name" required
              InputProps={{ startAdornment: <InputAdornment position="start"><PersonRounded sx={{ color: 'text.secondary', fontSize: 18 }} /></InputAdornment> }}
            />
            <TextField
              label="University email" type="email" value={form.email} onChange={set('email')}
              fullWidth autoComplete="email" required
              InputProps={{ startAdornment: <InputAdornment position="start"><EmailRounded sx={{ color: 'text.secondary', fontSize: 18 }} /></InputAdornment> }}
            />
            <TextField
              label="Programme / Degree" value={form.program} onChange={set('program')}
              fullWidth autoComplete="off"
              InputProps={{ startAdornment: <InputAdornment position="start"><SchoolRounded sx={{ color: 'text.secondary', fontSize: 18 }} /></InputAdornment> }}
            />
            <TextField
              label="Password" type={showPwd ? 'text' : 'password'} value={form.password} onChange={set('password')}
              fullWidth required autoComplete="new-password"
              InputProps={{
                startAdornment: <InputAdornment position="start"><LockRounded sx={{ color: 'text.secondary', fontSize: 18 }} /></InputAdornment>,
                endAdornment: (
                  <InputAdornment position="end">
                    <IconButton size="small" onClick={() => setShowPwd((s) => !s)} edge="end">
                      {showPwd ? <VisibilityOffRounded fontSize="small" /> : <VisibilityRounded fontSize="small" />}
                    </IconButton>
                  </InputAdornment>
                ),
              }}
            />
            <TextField
              label="Confirm password" type={showPwd ? 'text' : 'password'} value={form.password_confirmation} onChange={set('password_confirmation')}
              fullWidth required autoComplete="new-password"
              InputProps={{ startAdornment: <InputAdornment position="start"><LockRounded sx={{ color: 'text.secondary', fontSize: 18 }} /></InputAdornment> }}
            />

            {authError && <Alert severity="error" sx={{ borderRadius: 2 }}>{authError}</Alert>}

            <Button type="submit" variant="contained" size="large" fullWidth disabled={authLoading} sx={{ py: 1.4 }}>
              {authLoading ? <CircularProgress size={22} color="inherit" /> : 'Create account'}
            </Button>

            <Typography variant="body2" textAlign="center" color="text.secondary">
              Already have an account?{' '}
              <Box
                component="span" onClick={onSwitchToLogin}
                sx={{ color: 'primary.main', fontWeight: 700, cursor: 'pointer', '&:hover': { textDecoration: 'underline' } }}
              >
                Sign in
              </Box>
            </Typography>
          </Stack>
        </Box>
      </Box>
    </Box>
  );
}
