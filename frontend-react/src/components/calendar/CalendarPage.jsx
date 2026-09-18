import { useCallback, useEffect, useState } from 'react';
import {
  Box, Button, Card, CardContent, Chip, CircularProgress, Dialog,
  DialogActions, DialogContent, DialogTitle, FormControl, Grid,
  InputLabel, MenuItem, Paper, Select, Stack, TextField, Typography,
} from '@mui/material';
import { AddRounded, CalendarMonthRounded, DeleteRounded } from '@mui/icons-material';
import { calendarApi, assistantApi } from '../../api/client';
import { riskPalette } from '../../theme';

const MOCK_EVENTS = [
  { id: 'date-1',       title: 'Semester 1 Examinations Begin',    date: '2026-10-15', type: 'Exam',       source: 'kavishka', meta: {} },
  { id: 'date-2',       title: 'Final Year Project Proposal Due',  date: '2026-09-28', type: 'Deadline',   source: 'kavishka', meta: {} },
  { id: 'assignment-1', title: 'Database Project (due)',           date: '2026-09-25', type: 'Assignment', source: 'jithmi',   meta: { risk_level: 'Critical' } },
  { id: 'assignment-2', title: 'Web Assignment (due)',             date: '2026-09-26', type: 'Assignment', source: 'jithmi',   meta: { risk_level: 'High' } },
  { id: 'date-3',       title: 'Software Engineering Milestone',   date: '2026-09-28', type: 'Milestone',  source: 'kavishka', meta: {} },
  { id: 'assignment-3', title: 'Research Report (due)',            date: '2026-10-01', type: 'Assignment', source: 'jithmi',   meta: { risk_level: 'Medium' } },
];

const TYPE_COLOR = { Exam: 'error', Deadline: 'warning', Milestone: 'info', Assignment: 'primary', Event: 'default', Completed: 'success' };
const TYPE_DOT   = { Exam: '#f43f5e', Deadline: '#f97316', Milestone: '#6366f1', Assignment: '#3b82f6', Event: '#94a3b8', Completed: '#22c55e' };

function AddDateDialog({ open, onClose, onAdded }) {
  const [form, setForm] = useState({ title: '', event_date: '', type: 'Milestone', reminder: '1 day before' });
  const [saving, setSaving] = useState(false);
  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

  const submit = async () => {
    if (!form.title.trim() || !form.event_date) return;
    setSaving(true);
    try {
      const res = await assistantApi.createDate(form);
      onAdded(res.data);
    } catch {
      onAdded({ id: Date.now(), ...form });
    } finally {
      setSaving(false);
      onClose();
      setForm({ title: '', event_date: '', type: 'Milestone', reminder: '1 day before' });
    }
  };

  return (
    <Dialog open={open} onClose={onClose} maxWidth="xs" fullWidth>
      <DialogTitle fontWeight={800}>Add Academic Date</DialogTitle>
      <DialogContent>
        <Stack spacing={2.5} sx={{ mt: 1 }}>
          <TextField label="Title" value={form.title} onChange={set('title')} fullWidth required />
          <TextField label="Date" type="date" value={form.event_date} onChange={set('event_date')} fullWidth required InputLabelProps={{ shrink: true }} />
          <FormControl fullWidth>
            <InputLabel>Type</InputLabel>
            <Select value={form.type} label="Type" onChange={set('type')}>
              {['Deadline', 'Milestone', 'Exam', 'Event'].map((t) => <MenuItem key={t} value={t}>{t}</MenuItem>)}
            </Select>
          </FormControl>
          <TextField label="Reminder" value={form.reminder} onChange={set('reminder')} fullWidth />
        </Stack>
      </DialogContent>
      <DialogActions sx={{ p: 2.5 }}>
        <Button onClick={onClose}>Cancel</Button>
        <Button variant="contained" onClick={submit} disabled={saving || !form.title || !form.event_date}>
          {saving ? <CircularProgress size={18} color="inherit" /> : 'Add date'}
        </Button>
      </DialogActions>
    </Dialog>
  );
}

export default function CalendarPage() {
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');
  const [dialogOpen, setDialogOpen] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await calendarApi.get();
      setEvents(res.data.events || []);
    } catch {
      setEvents(MOCK_EVENTS);
    } finally { setLoading(false); }
  }, []);

  useEffect(() => { load(); }, [load]);

  const deleteDate = async (id) => {
    const numId = String(id).replace('date-', '');
    try { await assistantApi.deleteDate(numId); } catch {}
    setEvents((e) => e.filter((x) => x.id !== id));
  };

  const filtered = filter === 'all' ? events : events.filter((e) => e.type.toLowerCase() === filter);
  const now = new Date();
  const upcoming = [...filtered].filter((e) => new Date(e.date) >= now).sort((a, b) => a.date.localeCompare(b.date));
  const past     = [...filtered].filter((e) => new Date(e.date) < now).sort((a, b) => b.date.localeCompare(a.date));

  const daysLeft = (dateStr) => {
    const diff = Math.ceil((new Date(dateStr) - now) / 86400000);
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Tomorrow';
    if (diff < 0)  return `${Math.abs(diff)}d ago`;
    return `${diff}d left`;
  };

  if (loading) return <Box display="flex" alignItems="center" justifyContent="center" minHeight="50vh"><CircularProgress /></Box>;

  return (
    <Box className="page-enter">
      <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={2} mb={3}>
        <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
          {['all', 'exam', 'deadline', 'milestone', 'assignment'].map((f) => (
            <Chip key={f} label={f === 'all' ? 'All' : f.charAt(0).toUpperCase() + f.slice(1)}
              onClick={() => setFilter(f)} color={filter === f ? 'primary' : 'default'}
              variant={filter === f ? 'filled' : 'outlined'} sx={{ cursor: 'pointer' }} />
          ))}
        </Stack>
        <Button variant="contained" startIcon={<AddRounded />} onClick={() => setDialogOpen(true)}>Add date</Button>
      </Stack>

      <Grid container spacing={3}>
        <Grid item xs={12} lg={8}>
          {upcoming.length > 0 && (
            <Box mb={3}>
              <Typography variant="subtitle2" fontWeight={800} color="text.secondary" mb={1.5} sx={{ textTransform: 'uppercase', letterSpacing: '0.08em' }}>
                Upcoming ({upcoming.length})
              </Typography>
              <Stack spacing={1.5}>
                {upcoming.map((ev) => {
                  const risk = ev.meta?.risk_level;
                  const rp = risk ? riskPalette[risk] : null;
                  const dl = daysLeft(ev.date);
                  const urgent = dl === 'Today' || dl === 'Tomorrow';
                  return (
                    <Paper key={ev.id} sx={{ p: 2, display: 'flex', alignItems: 'center', gap: 2, borderLeft: `4px solid ${TYPE_DOT[ev.type] || '#94a3b8'}`, transition: 'all 0.2s ease', '&:hover': { boxShadow: 3 } }}>
                      <Box flex={1}>
                        <Stack direction="row" alignItems="center" spacing={1} mb={0.3}>
                          <Typography fontWeight={700}>{ev.title}</Typography>
                          {urgent && <Chip label="Urgent" size="small" color="error" sx={{ height: 18, fontSize: '0.65rem' }} />}
                        </Stack>
                        <Typography variant="caption" color="text.secondary">{ev.date}</Typography>
                      </Box>
                      <Stack direction="row" spacing={1} alignItems="center" flexShrink={0}>
                        {rp && <Box component="span" className="risk-badge" sx={{ color: rp.color, background: rp.bg }}>{risk}</Box>}
                        <Chip label={dl} size="small" color={urgent ? 'error' : 'default'} variant="outlined" />
                        <Chip label={ev.type} size="small" color={TYPE_COLOR[ev.type] || 'default'} />
                        {ev.source === 'kavishka' && (
                          <DeleteRounded fontSize="small" sx={{ cursor: 'pointer', color: 'text.secondary', '&:hover': { color: 'error.main' } }} onClick={() => deleteDate(ev.id)} />
                        )}
                      </Stack>
                    </Paper>
                  );
                })}
              </Stack>
            </Box>
          )}
          {past.length > 0 && (
            <Box>
              <Typography variant="subtitle2" fontWeight={800} color="text.secondary" mb={1.5} sx={{ textTransform: 'uppercase', letterSpacing: '0.08em' }}>
                Past ({past.length})
              </Typography>
              <Stack spacing={1.5}>
                {past.slice(0, 5).map((ev) => (
                  <Paper key={ev.id} sx={{ p: 2, display: 'flex', alignItems: 'center', gap: 2, opacity: 0.55, borderLeft: `4px solid ${TYPE_DOT[ev.type] || '#94a3b8'}` }}>
                    <Box flex={1}>
                      <Typography fontWeight={700}>{ev.title}</Typography>
                      <Typography variant="caption" color="text.secondary">{ev.date}</Typography>
                    </Box>
                    <Chip label={ev.type} size="small" color={TYPE_COLOR[ev.type] || 'default'} variant="outlined" />
                  </Paper>
                ))}
              </Stack>
            </Box>
          )}
          {filtered.length === 0 && (
            <Box textAlign="center" py={8}>
              <CalendarMonthRounded sx={{ fontSize: 48, color: 'text.secondary', mb: 1 }} />
              <Typography color="text.secondary">No events found.</Typography>
            </Box>
          )}
        </Grid>

        <Grid item xs={12} lg={4}>
          <Stack spacing={2}>
            <Card>
              <CardContent>
                <Typography variant="h6" fontWeight={800} mb={2}>Summary</Typography>
                {[
                  { label: 'Total events', value: events.length },
                  { label: 'Upcoming',     value: events.filter((e) => new Date(e.date) >= now).length },
                  { label: 'Exams',        value: events.filter((e) => e.type === 'Exam').length },
                  { label: 'Deadlines',    value: events.filter((e) => e.type === 'Deadline' || e.type === 'Assignment').length },
                  { label: 'Milestones',   value: events.filter((e) => e.type === 'Milestone').length },
                ].map((s) => (
                  <Stack key={s.label} direction="row" justifyContent="space-between" alignItems="center" mb={1.5}>
                    <Typography variant="body2" color="text.secondary">{s.label}</Typography>
                    <Typography fontWeight={700}>{s.value}</Typography>
                  </Stack>
                ))}
              </CardContent>
            </Card>
            {upcoming.slice(0, 3).length > 0 && (
              <Card>
                <CardContent>
                  <Typography variant="h6" fontWeight={800} mb={2}>Next 3 events</Typography>
                  <Stack spacing={1.5}>
                    {upcoming.slice(0, 3).map((ev) => (
                      <Box key={ev.id} sx={{ display: 'flex', gap: 1.5, alignItems: 'center' }}>
                        <Box sx={{ width: 10, height: 10, borderRadius: '50%', background: TYPE_DOT[ev.type] || '#94a3b8', flexShrink: 0 }} />
                        <Box flex={1} minWidth={0}>
                          <Typography fontWeight={700} fontSize="0.82rem" noWrap>{ev.title}</Typography>
                          <Typography variant="caption" color="text.secondary">{ev.date}</Typography>
                        </Box>
                      </Box>
                    ))}
                  </Stack>
                </CardContent>
              </Card>
            )}
          </Stack>
        </Grid>
      </Grid>

      <AddDateDialog open={dialogOpen} onClose={() => setDialogOpen(false)}
        onAdded={(d) => setEvents((e) => [...e, { id: `date-${d.id}`, title: d.title, date: d.event_date || d.date, type: d.type, source: 'kavishka', meta: {} }])} />
    </Box>
  );
}
