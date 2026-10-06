import React, { useEffect, useState } from 'react';
import {
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Grid,
  IconButton,
  MenuItem,
  Stack,
  TextField,
  Typography,
  alpha,
} from '@mui/material';
import { motion, AnimatePresence } from 'framer-motion';
import AddRoundedIcon from '@mui/icons-material/AddRounded';
import EditRoundedIcon from '@mui/icons-material/EditRounded';
import DeleteRoundedIcon from '@mui/icons-material/DeleteRounded';
import { useSnackbar } from 'notistack';
import { useTheme } from '@mui/material/styles';
import { listModules, createModule, updateModule, deleteModule } from '../api/common';
import { moduleColors, glassSx } from '../theme';

const COLOR_OPTIONS = Object.keys(moduleColors);

const EMPTY = { code: '', name: '', color: 'primary', icon: 'book' };

export default function ModulesPage() {
  const [modules, setModules] = useState([]);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(EMPTY);
  const [saving, setSaving] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(null);
  const [deleting, setDeleting] = useState(false);
  const { enqueueSnackbar } = useSnackbar();
  const theme = useTheme();

  const load = () => listModules().then(setModules).catch(() => {});
  useEffect(() => {
    load();
  }, []);

  const openCreate = () => {
    setEditing(null);
    setForm(EMPTY);
    setDialogOpen(true);
  };
  const openEdit = (m) => {
    setEditing(m);
    setForm({ code: m.code || '', name: m.name || '', color: m.color || 'primary', icon: m.icon || 'book' });
    setDialogOpen(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      if (editing) await updateModule(editing.id, form);
      else await createModule(form);
      setDialogOpen(false);
      load();
      enqueueSnackbar(editing ? 'Module updated.' : 'Module created.', { variant: 'success' });
    } catch (err) {
      enqueueSnackbar(err?.response?.data?.message || 'Could not save module.', { variant: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setDeleting(true);
    try {
      await deleteModule(confirmDelete.id);
      setConfirmDelete(null);
      load();
      enqueueSnackbar('Module deleted.', { variant: 'success' });
    } catch (err) {
      enqueueSnackbar(err?.response?.data?.message || 'Could not delete module.', { variant: 'error' });
    } finally {
      setDeleting(false);
    }
  };

  return (
    <Box>
      <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 3 }}>
        <Box>
          <Typography variant="h4" fontWeight={800}>
            Modules
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Your enrolled subjects \u2014 shared across every EDU-SMART feature.
          </Typography>
        </Box>
        <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={openCreate}>
          Add module
        </Button>
      </Stack>

      <Grid container spacing={3}>
        <AnimatePresence>
          {modules.map((m) => {
            const accent = moduleColors[m.color] || moduleColors.primary;
            return (
              <Grid item xs={12} sm={6} md={4} key={m.id} component={motion.div} layout exit={{ opacity: 0, scale: 0.9 }}>
                <Card sx={{ ...glassSx(theme.palette.mode), borderTop: `4px solid ${accent}`, height: '100%' }}>
                  <CardContent>
                    <Stack direction="row" justifyContent="space-between" alignItems="flex-start">
                      <Box>
                        <Chip
                          size="small"
                          label={m.code}
                          sx={{ bgcolor: alpha(accent, 0.15), color: accent, mb: 1 }}
                        />
                        <Typography variant="subtitle1" fontWeight={700}>
                          {m.name}
                        </Typography>
                      </Box>
                      <Stack direction="row">
                        <IconButton size="small" onClick={() => openEdit(m)}>
                          <EditRoundedIcon fontSize="small" />
                        </IconButton>
                        <IconButton size="small" onClick={() => setConfirmDelete(m)}>
                          <DeleteRoundedIcon fontSize="small" />
                        </IconButton>
                      </Stack>
                    </Stack>
                  </CardContent>
                </Card>
              </Grid>
            );
          })}
        </AnimatePresence>
        {modules.length === 0 && (
          <Grid item xs={12}>
            <Typography color="text.secondary">No modules yet \u2014 add your first one.</Typography>
          </Grid>
        )}
      </Grid>

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <Box component="form" onSubmit={handleSubmit}>
          <DialogTitle>{editing ? 'Edit module' : 'Add module'}</DialogTitle>
          <DialogContent>
            <Stack spacing={2} sx={{ mt: 1 }}>
              <TextField
                label="Code"
                required
                fullWidth
                value={form.code}
                onChange={(e) => setForm({ ...form, code: e.target.value })}
              />
              <TextField
                label="Name"
                required
                fullWidth
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
              />
              <TextField
                label="Color"
                select
                fullWidth
                value={form.color}
                onChange={(e) => setForm({ ...form, color: e.target.value })}
              >
                {COLOR_OPTIONS.map((c) => (
                  <MenuItem key={c} value={c}>
                    {c}
                  </MenuItem>
                ))}
              </TextField>
            </Stack>
          </DialogContent>
          <DialogActions sx={{ px: 3, pb: 2 }}>
            <Button onClick={() => setDialogOpen(false)}>Cancel</Button>
            <Button type="submit" variant="contained" disabled={saving}>
              {saving ? 'Saving\u2026' : editing ? 'Save' : 'Create'}
            </Button>
          </DialogActions>
        </Box>
      </Dialog>

      <Dialog open={Boolean(confirmDelete)} onClose={() => setConfirmDelete(null)} maxWidth="xs" fullWidth>
        <DialogTitle>Delete module?</DialogTitle>
        <DialogContent>
          <Typography variant="body2" color="text.secondary">
            This will remove <strong>{confirmDelete?.name}</strong> and unlink it from documents, assignments, and
            study sessions. This cannot be undone.
          </Typography>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2 }}>
          <Button onClick={() => setConfirmDelete(null)}>Cancel</Button>
          <Button color="error" variant="contained" onClick={handleDelete} disabled={deleting}>
            {deleting ? 'Deleting\u2026' : 'Delete'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
