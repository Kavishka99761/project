import { useState } from 'react';
import {
  Alert, Box, Button, Dialog, DialogActions, DialogContent,
  DialogTitle, IconButton, Stack, TextField, Typography,
  Table, TableBody, TableCell, TableContainer, TableHead, TableRow,
  Paper, CircularProgress, Chip,
} from '@mui/material';
import Grid from '@mui/material/Unstable_Grid2';
import {
  AddRounded, DeleteRounded, EditRounded, SaveRounded,
  CloseRounded, SchoolRounded,
} from '@mui/icons-material';
import { modulesApi } from '../../api/client';

export default function ModuleManagementDialog({ open, onClose, modules = [], onModulesChanged = () => {} }) {
  const [editingId, setEditingId] = useState(null);
  const [form, setForm] = useState({ code: '', name: '', color: '#6366f1' });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const resetForm = () => {
    setEditingId(null);
    setForm({ code: '', name: '', color: '#6366f1' });
    setError('');
  };

  const handleStartEdit = (mod) => {
    setEditingId(mod.id);
    setForm({
      code: mod.code || '',
      name: mod.name || '',
      color: mod.color || '#6366f1',
    });
    setError('');
  };

  const handleSave = async (e) => {
    e?.preventDefault();
    if (!form.name.trim()) {
      setError('Module name is required.');
      return;
    }
    setLoading(true);
    setError('');
    try {
      if (editingId) {
        const res = await modulesApi.update(editingId, form);
        const updated = modules.map((m) => (m.id === editingId ? res.data : m));
        onModulesChanged?.(updated);
      } else {
        const res = await modulesApi.create(form);
        onModulesChanged?.([...modules, res.data]);
      }
      resetForm();
    } catch (err) {
      const msg = err.response?.data?.message || 'Failed to save module.';
      setError(msg);
      // Offline fallback
      if (editingId) {
        const updated = modules.map((m) => (m.id === editingId ? { ...m, ...form } : m));
        onModulesChanged?.(updated);
      } else {
        const newMod = { id: Date.now(), ...form };
        onModulesChanged?.([...modules, newMod]);
      }
      resetForm();
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Are you sure you want to delete this module?')) return;
    setLoading(true);
    try {
      await modulesApi.destroy(id);
      onModulesChanged?.(modules.filter((m) => m.id !== id));
    } catch {
      // Offline fallback
      onModulesChanged?.(modules.filter((m) => m.id !== id));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => { resetForm(); onClose(); }} maxWidth="md" fullWidth>
      <DialogTitle sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <Stack direction="row" spacing={1.5} alignItems="center">
          <SchoolRounded color="primary" />
          <Typography variant="h6" fontWeight={800}>Module Management</Typography>
        </Stack>
        <IconButton size="small" onClick={() => { resetForm(); onClose(); }}>
          <CloseRounded fontSize="small" />
        </IconButton>
      </DialogTitle>

      <DialogContent dividers>
        <Stack spacing={3}>
          {/* Create or Edit form */}
          <Paper sx={{ p: 2.5, borderRadius: 2, background: 'rgba(99,102,241,0.04)', border: '1px solid rgba(99,102,241,0.15)' }}>
            <Typography variant="subtitle2" fontWeight={800} mb={1.5}>
              {editingId ? 'Edit Module' : 'Add New Module'}
            </Typography>
            <Grid container spacing={2} alignItems="center">
              <Grid xs={12} sm={3}>
                <TextField
                  label="Module Code"
                  size="small"
                  fullWidth
                  placeholder="e.g. CS201"
                  value={form.code}
                  onChange={(e) => setForm({ ...form, code: e.target.value })}
                />
              </Grid>
              <Grid xs={12} sm={5}>
                <TextField
                  label="Module Name"
                  size="small"
                  fullWidth
                  placeholder="e.g. Database Systems"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  required
                />
              </Grid>
              <Grid xs={12} sm={2}>
                <TextField
                  label="Color"
                  type="color"
                  size="small"
                  fullWidth
                  value={form.color}
                  onChange={(e) => setForm({ ...form, color: e.target.value })}
                  InputLabelProps={{ shrink: true }}
                />
              </Grid>
              <Grid xs={12} sm={2}>
                <Stack direction="row" spacing={1}>
                  <Button
                    variant="contained"
                    size="medium"
                    fullWidth
                    onClick={handleSave}
                    disabled={loading}
                    startIcon={loading ? <CircularProgress size={16} color="inherit" /> : editingId ? <SaveRounded /> : <AddRounded />}
                  >
                    {editingId ? 'Save' : 'Add'}
                  </Button>
                  {editingId && (
                    <Button variant="outlined" size="small" onClick={resetForm}>
                      Cancel
                    </Button>
                  )}
                </Stack>
              </Grid>
            </Grid>
            {error && <Alert severity="error" sx={{ mt: 1.5, borderRadius: 2 }}>{error}</Alert>}
          </Paper>

          {/* Module List Table */}
          <TableContainer component={Paper} sx={{ borderRadius: 2 }}>
            <Table size="small">
              <TableHead sx={{ background: 'rgba(148,163,184,0.08)' }}>
                <TableRow>
                  <TableCell sx={{ fontWeight: 700 }}>Code</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Name</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Color</TableCell>
                  <TableCell align="right" sx={{ fontWeight: 700 }}>Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {modules.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={4} align="center" sx={{ py: 3, color: 'text.secondary' }}>
                      No modules registered yet. Add one above!
                    </TableCell>
                  </TableRow>
                ) : (
                  modules.map((mod) => (
                    <TableRow key={mod.id} hover>
                      <TableCell>
                        <Chip label={mod.code || 'N/A'} size="small" variant="outlined" sx={{ fontWeight: 700 }} />
                      </TableCell>
                      <TableCell sx={{ fontWeight: 600 }}>{mod.name}</TableCell>
                      <TableCell>
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                          <Box sx={{ width: 14, height: 14, borderRadius: '50%', background: mod.color || '#6366f1' }} />
                          <Typography variant="caption" color="text.secondary">{mod.color || '#6366f1'}</Typography>
                        </Box>
                      </TableCell>
                      <TableCell align="right">
                        <IconButton size="small" onClick={() => handleStartEdit(mod)} color="primary">
                          <EditRounded fontSize="small" />
                        </IconButton>
                        <IconButton size="small" onClick={() => handleDelete(mod.id)} color="error">
                          <DeleteRounded fontSize="small" />
                        </IconButton>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </Stack>
      </DialogContent>

      <DialogActions sx={{ p: 2 }}>
        <Button onClick={() => { resetForm(); onClose(); }}>Close</Button>
      </DialogActions>
    </Dialog>
  );
}
