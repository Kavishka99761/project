import { useCallback, useEffect, useState } from 'react';
import {
  Alert, Box, Button, Card, CardContent, Chip, CircularProgress,
  Dialog, DialogActions, DialogContent, DialogTitle, FormControl,
  Grid, IconButton, InputLabel, LinearProgress, MenuItem, Paper,
  Select, Slider, Stack, Tab, Tabs, TextField, Typography,
} from '@mui/material';
import {
  AddRounded, DeleteRounded, EditRounded, CheckCircleRounded,
  AutoAwesomeRounded, AssignmentRounded,
} from '@mui/icons-material';
import { assignmentsApi, modulesApi } from '../../api/client';
import { riskPalette } from '../../theme';

const MOCK = [
  { id: 1, title: 'Database Project',  module: { name: 'Database Systems' },    deadline: '2026-09-25', priority: 'High',   est_hours: 20, done_hours: 5,  progress: 25, completed: false, risk: { level: 'Critical', score: 90, reasons: ['Only 3 day(s) left', 'Progress is low (25%)'], hours_per_day: 5.0, days: 3 } },
  { id: 2, title: 'Web Assignment',    module: { name: 'Programming (OOP)' },   deadline: '2026-09-26', priority: 'High',   est_hours: 16, done_hours: 6,  progress: 38, completed: false, risk: { level: 'High',     score: 55, reasons: ['Needs ~2.5h/day to finish'],          hours_per_day: 2.5, days: 4 } },
  { id: 3, title: 'Research Report',   module: { name: 'Software Engineering' },deadline: '2026-10-01', priority: 'Medium', est_hours: 15, done_hours: 5,  progress: 33, completed: false, risk: { level: 'Medium',   score: 40, reasons: ['Progress is low (33%)'],               hours_per_day: 1.1, days: 9 } },
  { id: 4, title: 'ML Mini Project',   module: { name: 'AI & Machine Learning' },deadline: '2026-10-12', priority: 'Low',  est_hours: 12, done_hours: 8,  progress: 70, completed: false, risk: { level: 'Low',      score: 14, reasons: ['On track'],                             hours_per_day: 0.2, days: 20 } },
];
const MOCK_MODS = [
  { id: 1, name: 'Database Systems' }, { id: 2, name: 'Programming (OOP)' },
  { id: 3, name: 'Software Engineering' }, { id: 4, name: 'AI & Machine Learning' },
];

function AssignmentDialog({ open, onClose, modules, initial, onSaved }) {
  const blank = { title: '', module_id: '', deadline: '', priority: 'Medium', est_hours: 10, done_hours: 0, progress: 0 };
  const [form, setForm] = useState(blank);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    setForm(initial
      ? { title: initial.title, module_id: initial.module_id || '', deadline: initial.deadline || '', priority: initial.priority || 'Medium', est_hours: initial.est_hours || 10, done_hours: initial.done_hours || 0, progress: initial.progress || 0 }
      : blank);
    setError('');
  }, [open]);

  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

  const submit = async () => {
    if (!form.title.trim() || !form.deadline) { setError('Title and deadline are required.'); return; }
    setSaving(true); setError('');
    try {
      const res = initial ? await assignmentsApi.update(initial.id, form) : await assignmentsApi.create(form);
      onSaved(res.data);
      onClose();
    } catch (err) {
      const msgs = err.response?.data?.errors;
      setError(msgs ? Object.values(msgs).flat().join(' ') : 'Save failed. Using offline mode.');
      if (!initial) {
        onSaved({ assignment: { ...form, id: Date.now(), module: modules.find((m) => String(m.id) === String(form.module_id)) }, risk: { level: 'Medium', score: 40, reasons: [], days: 7 } });
        onClose();
      }
    } finally { setSaving(false); }
  };

  return (
    <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle fontWeight={800}>{initial ? 'Edit Assignment' : 'Add Assignment'}</DialogTitle>
      <DialogContent>
        <Stack spacing={2.5} sx={{ mt: 1 }}>
          <TextField label="Title" value={form.title} onChange={set('title')} fullWidth required />
          <FormControl fullWidth>
            <InputLabel>Module</InputLabel>
            <Select value={form.module_id} label="Module" onChange={set('module_id')}>
              <MenuItem value=""><em>None</em></MenuItem>
              {modules.map((m) => <MenuItem key={m.id} value={m.id}>{m.name}</MenuItem>)}
            </Select>
          </FormControl>
          <TextField label="Deadline" type="date" value={form.deadline} onChange={set('deadline')} fullWidth required InputLabelProps={{ shrink: true }} />
          <FormControl fullWidth>
            <InputLabel>Priority</InputLabel>
            <Select value={form.priority} label="Priority" onChange={set('priority')}>
              {['Low', 'Medium', 'High'].map((p) => <MenuItem key={p} value={p}>{p}</MenuItem>)}
            </Select>
          </FormControl>
          <Stack direction="row" spacing={2}>
            <TextField label="Est. hours" type="number" value={form.est_hours} onChange={set('est_hours')} fullWidth inputProps={{ min: 0, step: 0.5 }} />
            <TextField label="Done hours" type="number" value={form.done_hours} onChange={set('done_hours')} fullWidth inputProps={{ min: 0, step: 0.5 }} />
          </Stack>
          <Box>
            <Typography variant="caption" fontWeight={700} color="text.secondary">PROGRESS: {form.progress}%</Typography>
            <Slider value={Number(form.progress)} onChange={(_, v) => setForm((f) => ({ ...f, progress: v }))} min={0} max={100} step={5} valueLabelDisplay="auto" />
          </Box>
          {error && <Alert severity="error" sx={{ borderRadius: 2 }}>{error}</Alert>}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ p: 2.5 }}>
        <Button onClick={onClose}>Cancel</Button>
        <Button variant="contained" onClick={submit} disabled={saving}>
          {saving ? <CircularProgress size={18} color="inherit" /> : (initial ? 'Save changes' : 'Add assignment')}
        </Button>
      </DialogActions>
    </Dialog>
  );
}

export default function AssignmentsPage() {
  const [tab, setTab] = useState(0);
  const [assignments, setAssignments] = useState([]);
  const [modules, setModules] = useState([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [recommendation, setRecommendation] = useState(null);
  const [whatIfHours, setWhatIfHours] = useState(3);

  const load = useCallback(async () => {
    try {
      const [aRes, mRes] = await Promise.all([assignmentsApi.list(), modulesApi.list()]);
      setAssignments(aRes.data);
      setModules(mRes.data);
      assignmentsApi.recommendation().then((r) => setRecommendation(r.data)).catch(() => {});
    } catch {
      setAssignments(MOCK);
      setModules(MOCK_MODS);
    } finally { setLoading(false); }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleSaved = (data) => {
    const item = data.assignment || data;
    const withRisk = { ...item, risk: data.risk };
    setAssignments((prev) => {
      const idx = prev.findIndex((a) => a.id === item.id);
      if (idx >= 0) { const next = [...prev]; next[idx] = { ...next[idx], ...withRisk }; return next; }
      return [withRisk, ...prev];
    });
  };

  const deleteAssignment = async (id) => {
    try { await assignmentsApi.destroy(id); } catch {}
    setAssignments((a) => a.filter((x) => x.id !== id));
  };

  const markComplete = async (a) => {
    try {
      const res = await assignmentsApi.update(a.id, { progress: 100, completed: true });
      handleSaved(res.data);
    } catch {
      setAssignments((prev) => prev.map((x) => x.id === a.id ? { ...x, progress: 100, completed: true } : x));
    }
  };

  const active = assignments.filter((a) => !a.completed);
  const completed = assignments.filter((a) => a.completed);
  const list = tab === 0 ? active : completed;

  if (loading) return <Box display="flex" alignItems="center" justifyContent="center" minHeight="50vh"><CircularProgress /></Box>;

  return (
    <Box className="page-enter">
      <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={2} mb={3}>
        <Tabs value={tab} onChange={(_, v) => setTab(v)}>
          <Tab label={`Active (${active.length})`} />
          <Tab label={`Completed (${completed.length})`} />
          <Tab label="What-if planner" />
        </Tabs>
        <Button variant="contained" startIcon={<AddRounded />} onClick={() => { setEditing(null); setDialogOpen(true); }}>
          Add assignment
        </Button>
      </Stack>

      {tab < 2 && (
        <Grid container spacing={3}>
          <Grid item xs={12} lg={8}>
            <Card>
              <CardContent>
                <Stack spacing={2}>
                  {list.length === 0 ? (
                    <Box textAlign="center" py={6}>
                      <AssignmentRounded sx={{ fontSize: 48, color: 'text.secondary', mb: 1 }} />
                      <Typography color="text.secondary">{tab === 0 ? 'No active assignments.' : 'No completed assignments yet.'}</Typography>
                    </Box>
                  ) : list.map((a) => {
                    const risk = a.risk || { level: 'Low', score: 0, reasons: [], days: 0 };
                    const p = riskPalette[risk.level] || riskPalette.Low;
                    return (
                      <Paper key={a.id} sx={{ p: 2.5, border: `1px solid ${p.border}`, transition: 'all 0.2s ease', '&:hover': { boxShadow: 4 } }}>
                        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'flex-start' }} spacing={1.5}>
                          <Box flex={1}>
                            <Stack direction="row" alignItems="center" spacing={1} mb={0.5}>
                              <Typography fontWeight={800}>{a.title}</Typography>
                              {a.completed && <CheckCircleRounded sx={{ color: '#22c55e', fontSize: 18 }} />}
                            </Stack>
                            <Typography variant="caption" color="text.secondary">
                              {a.module?.name} · Due {a.deadline} · {a.est_hours}h est · {a.done_hours}h done
                            </Typography>
                            <Box mt={1.5}>
                              <Stack direction="row" justifyContent="space-between" mb={0.5}>
                                <Typography variant="caption" fontWeight={700}>Progress</Typography>
                                <Typography variant="caption" fontWeight={700}>{a.progress}%</Typography>
                              </Stack>
                              <LinearProgress variant="determinate" value={a.progress} sx={{ height: 8, borderRadius: 99, background: 'rgba(148,163,184,0.15)', '& .MuiLinearProgress-bar': { background: p.color } }} />
                            </Box>
                            {risk.reasons?.length > 0 && (
                              <Stack direction="row" spacing={0.5} mt={1} flexWrap="wrap" useFlexGap>
                                {risk.reasons.map((r) => <Chip key={r} label={r} size="small" variant="outlined" sx={{ fontSize: '0.68rem' }} />)}
                              </Stack>
                            )}
                          </Box>
                          <Stack alignItems="flex-end" spacing={1} flexShrink={0}>
                            <Box component="span" className="risk-badge" sx={{ color: p.color, background: p.bg }}>
                              {risk.level} · {risk.score}%
                            </Box>
                            <Stack direction="row" spacing={0.5}>
                              {!a.completed && <IconButton size="small" onClick={() => markComplete(a)} title="Mark complete"><CheckCircleRounded fontSize="small" sx={{ color: '#22c55e' }} /></IconButton>}
                              <IconButton size="small" onClick={() => { setEditing(a); setDialogOpen(true); }}><EditRounded fontSize="small" /></IconButton>
                              <IconButton size="small" onClick={() => deleteAssignment(a.id)}><DeleteRounded fontSize="small" /></IconButton>
                            </Stack>
                          </Stack>
                        </Stack>
                      </Paper>
                    );
                  })}
                </Stack>
              </CardContent>
            </Card>
          </Grid>

          <Grid item xs={12} lg={4}>
            <Stack spacing={3}>
              {recommendation && (
                <Card sx={{ background: 'linear-gradient(135deg, rgba(249,115,22,0.06), rgba(239,68,68,0.06))', border: '1px solid rgba(249,115,22,0.2)' }}>
                  <CardContent>
                    <Stack direction="row" spacing={1.5} alignItems="flex-start">
                      <AutoAwesomeRounded sx={{ color: '#f97316', mt: 0.3 }} />
                      <Box>
                        <Typography fontWeight={800} mb={0.5}>AI Recommendation</Typography>
                        <Typography variant="body2" color="text.secondary" lineHeight={1.6}>{recommendation.message}</Typography>
                        {recommendation.suggested_per_day && (
                          <Chip label={`~${recommendation.suggested_per_day}h/day`} size="small" color="warning" sx={{ mt: 1 }} />
                        )}
                      </Box>
                    </Stack>
                  </CardContent>
                </Card>
              )}
              <Card>
                <CardContent>
                  <Typography variant="h6" fontWeight={800} mb={2}>Risk summary</Typography>
                  {['Critical', 'High', 'Medium', 'Low'].map((level) => {
                    const count = active.filter((a) => a.risk?.level === level).length;
                    const p = riskPalette[level];
                    return (
                      <Stack key={level} direction="row" justifyContent="space-between" alignItems="center" mb={1.5}>
                        <Stack direction="row" spacing={1} alignItems="center">
                          <Box sx={{ width: 10, height: 10, borderRadius: '50%', background: p.color }} />
                          <Typography variant="body2" fontWeight={600}>{level}</Typography>
                        </Stack>
                        <Chip label={count} size="small" sx={{ background: p.bg, color: p.color, fontWeight: 700 }} />
                      </Stack>
                    );
                  })}
                </CardContent>
              </Card>
            </Stack>
          </Grid>
        </Grid>
      )}

      {tab === 2 && (
        <Grid container spacing={3}>
          <Grid item xs={12} md={5}>
            <Card>
              <CardContent>
                <Typography variant="h6" fontWeight={800} mb={1}>What-if scenario planner</Typography>
                <Typography variant="body2" color="text.secondary" mb={3}>
                  Simulate how changing your daily study hours affects your workload.
                </Typography>
                <Typography variant="caption" fontWeight={700} color="text.secondary" mb={1} display="block">
                  DAILY STUDY HOURS: {whatIfHours}h
                </Typography>
                <Slider value={whatIfHours} onChange={(_, v) => setWhatIfHours(v)} min={1} max={10} step={0.5} valueLabelDisplay="auto"
                  marks={[{ value: 2, label: '2h' }, { value: 5, label: '5h' }, { value: 8, label: '8h' }]} />
                <Box sx={{ mt: 3, p: 2.5, borderRadius: 2, background: 'rgba(99,102,241,0.06)', border: '1px solid rgba(99,102,241,0.15)' }}>
                  <Typography fontWeight={700} mb={1}>Projected outcome</Typography>
                  <Typography variant="body2" color="text.secondary">
                    At <strong>{whatIfHours}h/day</strong>, you could clear approximately{' '}
                    <strong>{Math.min(active.length, Math.ceil(whatIfHours / 2))} assignment(s)</strong> before their next deadline.
                  </Typography>
                  <Typography variant="body2" color="text.secondary" mt={1}>
                    Total remaining: <strong>{active.reduce((s, a) => s + Math.max((a.est_hours || 0) - (a.done_hours || 0), 0), 0).toFixed(1)}h</strong>
                  </Typography>
                </Box>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} md={7}>
            <Card>
              <CardContent>
                <Typography variant="h6" fontWeight={800} mb={2}>Per-assignment projection</Typography>
                <Stack spacing={2}>
                  {active.map((a) => {
                    const risk = a.risk || { level: 'Low', score: 0 };
                    const p = riskPalette[risk.level] || riskPalette.Low;
                    const remaining = Math.max((a.est_hours || 0) - (a.done_hours || 0), 0);
                    const daysNeeded = whatIfHours > 0 ? (remaining / whatIfHours).toFixed(1) : '∞';
                    return (
                      <Box key={a.id}>
                        <Stack direction="row" justifyContent="space-between" mb={0.5}>
                          <Typography fontWeight={700} fontSize="0.85rem">{a.title}</Typography>
                          <Box component="span" className="risk-badge" sx={{ color: p.color, background: p.bg }}>{risk.level}</Box>
                        </Stack>
                        <Typography variant="caption" color="text.secondary">
                          {remaining}h remaining · needs {daysNeeded} days at {whatIfHours}h/day
                        </Typography>
                        <LinearProgress variant="determinate" value={a.progress} sx={{ mt: 0.8, height: 6, borderRadius: 99, background: 'rgba(148,163,184,0.15)', '& .MuiLinearProgress-bar': { background: p.color } }} />
                      </Box>
                    );
                  })}
                </Stack>
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      )}

      <AssignmentDialog open={dialogOpen} onClose={() => setDialogOpen(false)} modules={modules} initial={editing} onSaved={handleSaved} />
    </Box>
  );
}
