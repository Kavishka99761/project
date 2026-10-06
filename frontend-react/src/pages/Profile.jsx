import React, { useEffect, useRef, useState } from 'react';
import {
  Alert,
  Avatar,
  Badge,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Divider,
  Grid,
  IconButton,
  Slider,
  Stack,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material';
import { motion } from 'framer-motion';
import { useSnackbar } from 'notistack';
import { useTheme } from '@mui/material/styles';
import PhotoCameraRoundedIcon from '@mui/icons-material/PhotoCameraRounded';
import DeleteRoundedIcon from '@mui/icons-material/DeleteRounded';
import LockResetRoundedIcon from '@mui/icons-material/LockResetRounded';
import MenuBookRoundedIcon from '@mui/icons-material/MenuBookRounded';
import DescriptionRoundedIcon from '@mui/icons-material/DescriptionRounded';
import AssignmentRoundedIcon from '@mui/icons-material/AssignmentRounded';
import EventAvailableRoundedIcon from '@mui/icons-material/EventAvailableRounded';
import { useAuth } from '../context/AuthContext';
import { moduleColors, glassSx } from '../theme';
import { changePassword, removeAvatar, uploadAvatar } from '../api/auth';
import { getDashboard, listAssignments, listDocuments, listModules } from '../api/common';
import LiquidBackground from '../components/LiquidBackground';

function FactCard({ icon, label, value, color }) {
  const theme = useTheme();
  return (
    <Card sx={{ ...glassSx(theme.palette.mode), textAlign: 'center', py: 2 }}>
      <CardContent>
        <Box sx={{ color, mb: 1 }}>{icon}</Box>
        <Typography variant="h5" fontWeight={800}>
          {value ?? '\u2014'}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {label}
        </Typography>
      </CardContent>
    </Card>
  );
}

export default function Profile() {
  const { user, updateProfile, setUserData } = useAuth();
  const theme = useTheme();
  const { enqueueSnackbar } = useSnackbar();
  const fileInputRef = useRef(null);

  const [form, setForm] = useState({
    name: '',
    program: '',
    academic_year: '',
    phone: '',
    bio: '',
    daily_target_minutes: 180,
  });
  const [saving, setSaving] = useState(false);

  const [pwd, setPwd] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [pwdSaving, setPwdSaving] = useState(false);
  const [pwdError, setPwdError] = useState('');

  const [avatarBusy, setAvatarBusy] = useState(false);

  const [facts, setFacts] = useState({ modules: null, documents: null, assignments: null, upcoming: null });

  useEffect(() => {
    if (user) {
      setForm({
        name: user.name || '',
        program: user.program || '',
        academic_year: user.academic_year || '',
        phone: user.phone || '',
        bio: user.bio || '',
        daily_target_minutes: user.daily_target_minutes ?? 180,
      });
    }
  }, [user]);

  useEffect(() => {
    (async () => {
      try {
        const [modules, documents, assignments, dashboard] = await Promise.all([
          listModules().catch(() => []),
          listDocuments().catch(() => []),
          listAssignments().catch(() => []),
          getDashboard().catch(() => null),
        ]);
        const upcoming = Array.isArray(assignments)
          ? assignments.filter((a) => a.status !== 'completed').length
          : null;
        setFacts({
          modules: Array.isArray(modules) ? modules.length : dashboard?.modules_count ?? null,
          documents: Array.isArray(documents) ? documents.length : dashboard?.documents_count ?? null,
          assignments: Array.isArray(assignments) ? assignments.length : null,
          upcoming,
        });
      } catch (err) {
        // Non-critical: stats panel simply stays blank on failure.
      }
    })();
  }, []);

  const initials = (user?.name || '?')
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

  const joinedDate = user?.created_at
    ? new Date(user.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
    : null;

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      await updateProfile(form);
      enqueueSnackbar('Profile updated successfully.', { variant: 'success' });
    } catch (err) {
      enqueueSnackbar(err?.response?.data?.message || 'Could not update profile.', { variant: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const handleAvatarPick = () => fileInputRef.current?.click();

  const handleAvatarChange = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setAvatarBusy(true);
    try {
      const me = await uploadAvatar(file);
      setUserData(me);
      enqueueSnackbar('Avatar updated.', { variant: 'success' });
    } catch (err) {
      enqueueSnackbar(err?.response?.data?.message || 'Could not upload avatar.', { variant: 'error' });
    } finally {
      setAvatarBusy(false);
      e.target.value = '';
    }
  };

  const handleAvatarRemove = async () => {
    setAvatarBusy(true);
    try {
      const me = await removeAvatar();
      setUserData(me);
      enqueueSnackbar('Avatar removed.', { variant: 'success' });
    } catch (err) {
      enqueueSnackbar('Could not remove avatar.', { variant: 'error' });
    } finally {
      setAvatarBusy(false);
    }
  };

  const handleChangePassword = async (e) => {
    e.preventDefault();
    setPwdError('');
    if (pwd.password !== pwd.password_confirmation) {
      setPwdError('New password and confirmation do not match.');
      return;
    }
    setPwdSaving(true);
    try {
      await changePassword(pwd);
      setPwd({ current_password: '', password: '', password_confirmation: '' });
      enqueueSnackbar('Password changed successfully.', { variant: 'success' });
    } catch (err) {
      setPwdError(
        err?.response?.data?.message ||
          err?.response?.data?.errors?.current_password?.[0] ||
          'Could not change password.'
      );
    } finally {
      setPwdSaving(false);
    }
  };

  return (
    <Box component={motion.div} initial={{ opacity: 0 }} animate={{ opacity: 1 }} sx={{ position: 'relative' }}>
      <LiquidBackground />
      <Typography variant="h4" fontWeight={800} sx={{ mb: 3, position: 'relative', zIndex: 1 }}>
        Profile
      </Typography>

      <Grid container spacing={3} sx={{ position: 'relative', zIndex: 1 }}>
        {/* Avatar + identity card */}
        <Grid item xs={12} md={4}>
          <Card sx={{ ...glassSx(theme.palette.mode) }}>
            <CardContent sx={{ textAlign: 'center', py: 4 }}>
              <Badge
                overlap="circular"
                anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
                badgeContent={
                  <Tooltip title="Change photo">
                    <IconButton
                      size="small"
                      onClick={handleAvatarPick}
                      disabled={avatarBusy}
                      sx={{ bgcolor: 'background.paper', boxShadow: 1, '&:hover': { bgcolor: 'background.paper' } }}
                    >
                      <PhotoCameraRoundedIcon fontSize="small" />
                    </IconButton>
                  </Tooltip>
                }
              >
                <Avatar
                  src={user?.avatar_url || undefined}
                  sx={{ width: 96, height: 96, mx: 'auto', mb: 1, bgcolor: moduleColors.primary, fontSize: 30 }}
                >
                  {initials}
                </Avatar>
              </Badge>
              <input ref={fileInputRef} type="file" accept="image/*" hidden onChange={handleAvatarChange} />
              {user?.avatar_url && (
                <Box sx={{ mb: 1 }}>
                  <Button
                    size="small"
                    color="error"
                    startIcon={<DeleteRoundedIcon />}
                    onClick={handleAvatarRemove}
                    disabled={avatarBusy}
                  >
                    Remove photo
                  </Button>
                </Box>
              )}
              <Typography variant="h6" fontWeight={700} sx={{ mt: 1 }}>
                {user?.name}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {user?.email}
              </Typography>
              {form.program && <Chip size="small" label={form.program} sx={{ mt: 1, mr: 0.5 }} />}
              {form.academic_year && <Chip size="small" label={`Year ${form.academic_year}`} sx={{ mt: 1 }} />}
              {joinedDate && (
                <Typography variant="caption" display="block" color="text.secondary" sx={{ mt: 2 }}>
                  Member since {joinedDate}
                </Typography>
              )}
            </CardContent>
          </Card>
        </Grid>

        {/* Basic information form */}
        <Grid item xs={12} md={8}>
          <Card component="form" onSubmit={handleSave} sx={{ ...glassSx(theme.palette.mode) }}>
            <CardContent>
              <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 2 }}>
                Basic information
              </Typography>
              <Stack spacing={2}>
                <TextField
                  label="Full name"
                  fullWidth
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                />
                <TextField
                  label="Email"
                  fullWidth
                  value={user?.email || ''}
                  disabled
                  helperText="Email cannot be changed."
                />
                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
                  <TextField
                    label="Program"
                    fullWidth
                    value={form.program}
                    onChange={(e) => setForm({ ...form, program: e.target.value })}
                  />
                  <TextField
                    label="Academic year"
                    fullWidth
                    value={form.academic_year}
                    onChange={(e) => setForm({ ...form, academic_year: e.target.value })}
                  />
                </Stack>
                <TextField
                  label="Phone number"
                  fullWidth
                  value={form.phone}
                  onChange={(e) => setForm({ ...form, phone: e.target.value })}
                />
                <TextField
                  label="Bio"
                  fullWidth
                  multiline
                  minRows={2}
                  value={form.bio}
                  onChange={(e) => setForm({ ...form, bio: e.target.value })}
                  helperText="A short line about yourself, visible only to you."
                />
                <Box>
                  <Typography variant="body2" color="text.secondary" gutterBottom>
                    Daily study target: {form.daily_target_minutes} min
                  </Typography>
                  <Slider
                    value={form.daily_target_minutes}
                    min={30}
                    max={480}
                    step={15}
                    onChange={(e, v) => setForm({ ...form, daily_target_minutes: v })}
                  />
                </Box>
              </Stack>
              <Divider sx={{ my: 3 }} />
              <Stack direction="row" justifyContent="flex-end">
                <Button type="submit" variant="contained" disabled={saving}>
                  {saving ? 'Saving\u2026' : 'Save changes'}
                </Button>
              </Stack>
            </CardContent>
          </Card>
        </Grid>

        {/* Account facts */}
        <Grid item xs={12}>
          <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1.5 }}>
            Account facts
          </Typography>
          <Grid container spacing={2}>
            <Grid item xs={6} sm={3}>
              <FactCard icon={<MenuBookRoundedIcon />} label="Modules" value={facts.modules} color={moduleColors.kavishka} />
            </Grid>
            <Grid item xs={6} sm={3}>
              <FactCard
                icon={<DescriptionRoundedIcon />}
                label="Documents uploaded"
                value={facts.documents}
                color={moduleColors.bethmi}
              />
            </Grid>
            <Grid item xs={6} sm={3}>
              <FactCard
                icon={<AssignmentRoundedIcon />}
                label="Assignments"
                value={facts.assignments}
                color={moduleColors.pasindu}
              />
            </Grid>
            <Grid item xs={6} sm={3}>
              <FactCard
                icon={<EventAvailableRoundedIcon />}
                label="Active assignments"
                value={facts.upcoming}
                color={moduleColors.jithmi}
              />
            </Grid>
          </Grid>
        </Grid>

        {/* Change password */}
        <Grid item xs={12} md={8}>
          <Card component="form" onSubmit={handleChangePassword} sx={{ ...glassSx(theme.palette.mode) }}>
            <CardContent>
              <Stack direction="row" alignItems="center" spacing={1} sx={{ mb: 2 }}>
                <LockResetRoundedIcon color="action" />
                <Typography variant="subtitle1" fontWeight={700}>
                  Change password
                </Typography>
              </Stack>
              {pwdError && (
                <Alert severity="error" sx={{ mb: 2 }} onClose={() => setPwdError('')}>
                  {pwdError}
                </Alert>
              )}
              <Stack spacing={2}>
                <TextField
                  type="password"
                  label="Current password"
                  fullWidth
                  required
                  value={pwd.current_password}
                  onChange={(e) => setPwd({ ...pwd, current_password: e.target.value })}
                />
                <TextField
                  type="password"
                  label="New password"
                  fullWidth
                  required
                  helperText="At least 6 characters"
                  value={pwd.password}
                  onChange={(e) => setPwd({ ...pwd, password: e.target.value })}
                />
                <TextField
                  type="password"
                  label="Confirm new password"
                  fullWidth
                  required
                  value={pwd.password_confirmation}
                  onChange={(e) => setPwd({ ...pwd, password_confirmation: e.target.value })}
                />
              </Stack>
              <Divider sx={{ my: 3 }} />
              <Stack direction="row" justifyContent="flex-end">
                <Button type="submit" variant="outlined" disabled={pwdSaving}>
                  {pwdSaving ? 'Updating\u2026' : 'Update password'}
                </Button>
              </Stack>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}
