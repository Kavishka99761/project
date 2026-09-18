import { useState } from 'react';
import {
  Alert, Avatar, Box, Button, Card, CardContent, Chip, CircularProgress,
  Divider, FormControl, Grid, InputLabel, MenuItem, Select, Slider,
  Stack, Switch, TextField, Typography,
} from '@mui/material';
import {
  PersonRounded, DarkModeRounded, LightModeRounded, DownloadRounded,
  CloudUploadRounded, NotificationsRounded, SchoolRounded, SaveRounded,
  LogoutRounded, CheckCircleRounded,
} from '@mui/icons-material';
import { authApi } from '../../api/client';
import { exportApi } from '../../api/client';
import { useApp } from '../../context/AppContext';

export default function ProfilePage() {
  const { user, updateUser, signOut, themeMode, toggleTheme } = useApp();

  const [form, setForm] = useState({
    name:                  user?.name || '',
    program:               user?.program || '',
    academic_year:         user?.academic_year || '',
    daily_target_minutes:  user?.daily_target_minutes || 180,
  });
  const [saving,    setSaving]    = useState(false);
  const [saved,     setSaved]     = useState(false);
  const [saveError, setSaveError] = useState('');
  const [exporting, setExporting] = useState(false);
  const [backing,   setBacking]   = useState(false);
  const [backupMsg, setBackupMsg] = useState('');

  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

  const saveProfile = async () => {
    setSaving(true); setSaved(false); setSaveError('');
    try {
      const res = await authApi.updateProfile(form);
      updateUser(res.data);
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } catch (err) {
      const msgs = err.response?.data?.errors;
      setSaveError(msgs ? Object.values(msgs).flat().join(' ') : 'Save failed.');
      // Offline: update local state anyway
      updateUser(form);
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } finally { setSaving(false); }
  };

  const exportData = async () => {
    setExporting(true);
    try {
      const res = await exportApi.download();
      const url = URL.createObjectURL(new Blob([res.data], { type: 'application/json' }));
      const a = document.createElement('a');
      a.href = url;
      a.download = `acadealert-export-${new Date().toISOString().split('T')[0]}.json`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      // Offline fallback: export localStorage data
      const data = { exportedAt: new Date().toISOString(), user: user, note: 'Offline export' };
      const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }));
      const a = document.createElement('a');
      a.href = url;
      a.download = `acadealert-export-${new Date().toISOString().split('T')[0]}.json`;
      a.click();
      URL.revokeObjectURL(url);
    } finally { setExporting(false); }
  };

  const backupFirebase = async () => {
    setBacking(true); setBackupMsg('');
    try {
      const res = await exportApi.backupFirebase();
      setBackupMsg(res.data.message || 'Backup successful.');
    } catch {
      setBackupMsg('Firebase backup skipped — credentials not configured. Use local export instead.');
    } finally { setBacking(false); }
  };

  const initials = (user?.name || 'S').split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2);

  return (
    <Box className="page-enter">
      <Grid container spacing={3}>
        {/* ── Profile card ── */}
        <Grid item xs={12} md={7}>
          <Card>
            <CardContent>
              <Stack direction="row" spacing={2.5} alignItems="center" mb={3}>
                <Avatar sx={{ width: 72, height: 72, background: 'linear-gradient(135deg, #6366f1, #8b5cf6)', fontSize: 26, fontWeight: 800 }}>
                  {initials}
                </Avatar>
                <Box>
                  <Typography variant="h5" fontWeight={800}>{user?.name || 'Student'}</Typography>
                  <Typography color="text.secondary">{user?.email}</Typography>
                  {user?.program && <Chip label={user.program} size="small" sx={{ mt: 0.5 }} />}
                </Box>
              </Stack>

              <Divider sx={{ mb: 3 }} />

              <Stack spacing={2.5}>
                <TextField label="Full name" value={form.name} onChange={set('name')} fullWidth
                  InputProps={{ startAdornment: <PersonRounded sx={{ mr: 1, color: 'text.secondary', fontSize: 18 }} /> }} />
                <TextField label="Programme / Degree" value={form.program} onChange={set('program')} fullWidth
                  InputProps={{ startAdornment: <SchoolRounded sx={{ mr: 1, color: 'text.secondary', fontSize: 18 }} /> }} />
                <TextField label="Academic year" value={form.academic_year} onChange={set('academic_year')} fullWidth placeholder="e.g. Year 2 · Semester 1" />

                <Box>
                  <Typography variant="caption" fontWeight={700} color="text.secondary" mb={1} display="block">
                    DAILY STUDY TARGET: {form.daily_target_minutes} min ({Math.round(form.daily_target_minutes / 60 * 10) / 10}h)
                  </Typography>
                  <Slider
                    value={form.daily_target_minutes}
                    onChange={(_, v) => setForm((f) => ({ ...f, daily_target_minutes: v }))}
                    min={15} max={480} step={15} valueLabelDisplay="auto"
                    marks={[{ value: 60, label: '1h' }, { value: 120, label: '2h' }, { value: 240, label: '4h' }]}
                  />
                </Box>

                {saveError && <Alert severity="error" sx={{ borderRadius: 2 }}>{saveError}</Alert>}
                {saved && <Alert severity="success" sx={{ borderRadius: 2 }} icon={<CheckCircleRounded />}>Profile saved successfully!</Alert>}

                <Button
                  variant="contained" onClick={saveProfile} disabled={saving}
                  startIcon={saving ? <CircularProgress size={16} color="inherit" /> : <SaveRounded />}
                  sx={{ alignSelf: 'flex-start' }}
                >
                  {saving ? 'Saving…' : 'Save profile'}
                </Button>
              </Stack>
            </CardContent>
          </Card>
        </Grid>

        {/* ── Settings & actions ── */}
        <Grid item xs={12} md={5}>
          <Stack spacing={3}>
            {/* Appearance */}
            <Card>
              <CardContent>
                <Typography variant="h6" fontWeight={800} mb={2}>Appearance</Typography>
                <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ p: 2, borderRadius: 2, background: 'rgba(148,163,184,0.06)' }}>
                  <Stack direction="row" spacing={1.5} alignItems="center">
                    {themeMode === 'dark' ? <DarkModeRounded color="primary" /> : <LightModeRounded sx={{ color: '#f59e0b' }} />}
                    <Box>
                      <Typography fontWeight={700}>{themeMode === 'dark' ? 'Dark mode' : 'Light mode'}</Typography>
                      <Typography variant="caption" color="text.secondary">Toggle interface theme</Typography>
                    </Box>
                  </Stack>
                  <Switch checked={themeMode === 'dark'} onChange={toggleTheme} color="primary" />
                </Stack>
              </CardContent>
            </Card>

            {/* Notifications */}
            <Card>
              <CardContent>
                <Typography variant="h6" fontWeight={800} mb={2}>Notifications</Typography>
                {[
                  { label: 'Deadline reminders',    sub: 'Alert before assignment due dates' },
                  { label: 'Study session reminders', sub: 'Daily study goal nudges' },
                  { label: 'Risk level changes',    sub: 'When assignment risk increases' },
                ].map((n) => (
                  <Stack key={n.label} direction="row" justifyContent="space-between" alignItems="center" mb={1.5}>
                    <Box>
                      <Typography fontWeight={600} fontSize="0.88rem">{n.label}</Typography>
                      <Typography variant="caption" color="text.secondary">{n.sub}</Typography>
                    </Box>
                    <Switch defaultChecked size="small" color="primary" />
                  </Stack>
                ))}
              </CardContent>
            </Card>

            {/* Data */}
            <Card>
              <CardContent>
                <Typography variant="h6" fontWeight={800} mb={2}>Data & Backup</Typography>
                <Stack spacing={1.5}>
                  <Button fullWidth variant="outlined" startIcon={exporting ? <CircularProgress size={16} /> : <DownloadRounded />}
                    onClick={exportData} disabled={exporting}>
                    {exporting ? 'Exporting…' : 'Export data as JSON'}
                  </Button>
                  <Button fullWidth variant="outlined" startIcon={backing ? <CircularProgress size={16} /> : <CloudUploadRounded />}
                    onClick={backupFirebase} disabled={backing}>
                    {backing ? 'Backing up…' : 'Backup to Firebase'}
                  </Button>
                  {backupMsg && (
                    <Alert severity={backupMsg.includes('success') ? 'success' : 'info'} sx={{ borderRadius: 2, fontSize: '0.8rem' }}>
                      {backupMsg}
                    </Alert>
                  )}
                </Stack>
              </CardContent>
            </Card>

            {/* Sign out */}
            <Card>
              <CardContent>
                <Typography variant="h6" fontWeight={800} mb={2}>Account</Typography>
                <Button fullWidth variant="outlined" color="error" startIcon={<LogoutRounded />} onClick={signOut}>
                  Sign out
                </Button>
              </CardContent>
            </Card>
          </Stack>
        </Grid>
      </Grid>
    </Box>
  );
}
