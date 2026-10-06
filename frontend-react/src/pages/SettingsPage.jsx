import React, { useState } from 'react';
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Divider,
  FormControlLabel,
  Grid,
  Stack,
  Switch,
  Typography,
} from '@mui/material';
import { motion } from 'framer-motion';
import DarkModeRoundedIcon from '@mui/icons-material/DarkModeRounded';
import DownloadRoundedIcon from '@mui/icons-material/DownloadRounded';
import CloudRoundedIcon from '@mui/icons-material/CloudRounded';
import StorageRoundedIcon from '@mui/icons-material/StorageRounded';
import { useColorMode } from '../context/ColorModeContext';
import { useAuth } from '../context/AuthContext';
import { getDashboard, listModules, listAssignments, listDocuments } from '../api/common';
import { exportAsCsv, exportAsJson } from '../utils/export';
import { isFirebaseEnabled } from '../firebase/config';

const PREFS_KEY = 'edu_smart_notification_prefs';
const DEFAULT_PREFS = { deadlineReminders: true, studyReminders: true, weeklySummary: false };

export default function SettingsPage() {
  const { mode, toggleColorMode } = useColorMode();
  const { user } = useAuth();
  const [prefs, setPrefs] = useState(() => {
    try {
      return { ...DEFAULT_PREFS, ...JSON.parse(localStorage.getItem(PREFS_KEY) || '{}') };
    } catch {
      return DEFAULT_PREFS;
    }
  });
  const [exporting, setExporting] = useState(false);
  const [message, setMessage] = useState('');

  const togglePref = (key) => {
    const next = { ...prefs, [key]: !prefs[key] };
    setPrefs(next);
    localStorage.setItem(PREFS_KEY, JSON.stringify(next));
  };

  const handleExport = async (format) => {
    setExporting(true);
    setMessage('');
    try {
      const [dashboard, modules, assignments, documents] = await Promise.all([
        getDashboard(),
        listModules(),
        listAssignments().catch(() => []),
        listDocuments().catch(() => []),
      ]);
      const stamp = new Date().toISOString().slice(0, 10);

      if (format === 'json') {
        exportAsJson(`edu-smart-backup-${stamp}.json`, { dashboard, modules, assignments, documents });
      } else {
        exportAsCsv(`edu-smart-modules-${stamp}.csv`, modules, ['id', 'code', 'name', 'color']);
        exportAsCsv(`edu-smart-assignments-${stamp}.csv`, assignments, [
          'id',
          'title',
          'deadline',
          'priority',
          'progress',
          'completed',
        ]);
      }
      setMessage(`Exported as ${format.toUpperCase()} \u2014 check your downloads folder.`);
    } finally {
      setExporting(false);
    }
  };

  return (
    <Box component={motion.div} initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
      <Typography variant="h4" fontWeight={800} sx={{ mb: 3 }}>
        Settings
      </Typography>

      <Grid container spacing={3}>
        <Grid item xs={12} md={6}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Stack direction="row" spacing={1.5} alignItems="center" sx={{ mb: 2 }}>
                <DarkModeRoundedIcon color="action" />
                <Typography variant="subtitle1" fontWeight={700}>
                  Appearance
                </Typography>
              </Stack>
              <FormControlLabel
                control={<Switch checked={mode === 'dark'} onChange={toggleColorMode} />}
                label={mode === 'dark' ? 'Dark mode enabled' : 'Light mode enabled'}
              />
              <Typography variant="caption" color="text.secondary" display="block" sx={{ mt: 1 }}>
                Synced to your account ({user?.email}) so it follows you across devices.
              </Typography>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} md={6}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Stack direction="row" spacing={1.5} alignItems="center" sx={{ mb: 2 }}>
                <StorageRoundedIcon color="action" />
                <Typography variant="subtitle1" fontWeight={700}>
                  Data sources
                </Typography>
              </Stack>
              <Stack spacing={1}>
                <Stack direction="row" alignItems="center" spacing={1}>
                  <StorageRoundedIcon fontSize="small" color="success" />
                  <Typography variant="body2">Laravel + MySQL (local, source of truth)</Typography>
                </Stack>
                <Stack direction="row" alignItems="center" spacing={1}>
                  <CloudRoundedIcon fontSize="small" color={isFirebaseEnabled() ? 'success' : 'disabled'} />
                  <Typography variant="body2" color={isFirebaseEnabled() ? 'text.primary' : 'text.secondary'}>
                    Firebase (online search history) \u2014 {isFirebaseEnabled() ? 'connected' : 'not configured'}
                  </Typography>
                </Stack>
              </Stack>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} md={6}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 2 }}>
                Notification preferences
              </Typography>
              <Stack spacing={0.5}>
                <FormControlLabel
                  control={<Switch checked={prefs.deadlineReminders} onChange={() => togglePref('deadlineReminders')} />}
                  label="Assignment deadline reminders"
                />
                <FormControlLabel
                  control={<Switch checked={prefs.studyReminders} onChange={() => togglePref('studyReminders')} />}
                  label="Study session reminders"
                />
                <FormControlLabel
                  control={<Switch checked={prefs.weeklySummary} onChange={() => togglePref('weeklySummary')} />}
                  label="Weekly progress summary"
                />
              </Stack>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} md={6}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 2 }}>
                Backup &amp; export
              </Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                Download a snapshot of your dashboard, modules, assignments and documents straight from
                the local database.
              </Typography>
              {message && (
                <Alert severity="success" sx={{ mb: 2 }} onClose={() => setMessage('')}>
                  {message}
                </Alert>
              )}
              <Stack direction="row" spacing={1.5}>
                <Button
                  variant="contained"
                  startIcon={<DownloadRoundedIcon />}
                  disabled={exporting}
                  onClick={() => handleExport('json')}
                >
                  Export JSON
                </Button>
                <Button
                  variant="outlined"
                  startIcon={<DownloadRoundedIcon />}
                  disabled={exporting}
                  onClick={() => handleExport('csv')}
                >
                  Export CSV
                </Button>
              </Stack>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}
