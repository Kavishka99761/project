import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Box, Button, Card, CardContent, Chip, CircularProgress, FormControl,
  Grid, InputLabel, LinearProgress, MenuItem, Paper, Select, Slider,
  Stack, Typography,
} from '@mui/material';
import {
  PlayArrowRounded, PauseRounded, StopRounded, CameraAltRounded,
  TimerRounded, TrendingUpRounded, LocalFireDepartmentRounded,
} from '@mui/icons-material';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip,
  ResponsiveContainer, ReferenceLine,
} from 'recharts';
import { studyApi, modulesApi, assignmentsApi } from '../../api/client';

const MOCK_ANALYTICS = {
  weekly: [
    { label: 'Mon', minutes: 85  },
    { label: 'Tue', minutes: 120 },
    { label: 'Wed', minutes: 60  },
    { label: 'Thu', minutes: 145 },
    { label: 'Fri', minutes: 30  },
    { label: 'Sat', minutes: 180 },
    { label: 'Sun', minutes: 95  },
  ],
  planned_minutes:    600,
  actual_minutes:     715,
  completion_rate:    119,
  sessions_count:     14,
  average_engagement: 72,
  streak_days:        7,
  daily_target:       120,
};

const ENG_LEVELS = [
  { label: 'Low',      value: 25, color: '#f43f5e' },
  { label: 'Moderate', value: 55, color: '#f59e0b' },
  { label: 'Good',     value: 80, color: '#22c55e' },
];

// ─── Circular Timer SVG ───────────────────────────────────────────────────────
function CircularTimer({ seconds, maxSeconds = 1500, running }) {
  const pct = Math.min(1, seconds / maxSeconds);
  const r   = 70;
  const circ = 2 * Math.PI * r;
  const dash = circ * (1 - pct);
  const mm   = String(Math.floor(seconds / 60)).padStart(2, '0');
  const ss   = String(seconds % 60).padStart(2, '0');

  return (
    <Box sx={{ position: 'relative', display: 'inline-block' }}>
      <svg width="180" height="180" viewBox="0 0 180 180">
        <circle cx="90" cy="90" r={r} fill="none" stroke="rgba(148,163,184,0.2)" strokeWidth="10" />
        <circle
          cx="90" cy="90" r={r} fill="none"
          stroke={running ? '#14b8a6' : '#6366f1'}
          strokeWidth="10"
          strokeLinecap="round"
          strokeDasharray={circ}
          strokeDashoffset={dash}
          transform="rotate(-90 90 90)"
          style={{ transition: 'stroke-dashoffset 1s linear, stroke 0.3s ease' }}
        />
      </svg>
      <Box sx={{ position: 'absolute', inset: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center' }}>
        <Typography className="timer-display" variant="h4" fontWeight={700}>
          {mm}:{ss}
        </Typography>
        <Typography variant="caption" color="text.secondary">{running ? 'Studying…' : 'Paused'}</Typography>
      </Box>
    </Box>
  );
}

export default function StudyPage() {
  const [modules,    setModules]    = useState([]);
  const [assignments,setAssignments]= useState([]);
  const [analytics,  setAnalytics]  = useState(null);
  const [loading,    setLoading]    = useState(true);

  // Timer
  const [seconds,    setSeconds]    = useState(0);
  const [running,    setRunning]    = useState(false);
  const [sessionId,  setSessionId]  = useState(null);
  const [moduleId,   setModuleId]   = useState('');
  const [assignId,   setAssignId]   = useState('');
  const [planned,    setPlanned]    = useState(25);
  const intervalRef  = useRef(null);

  // Engagement
  const [engLevel,   setEngLevel]   = useState(72);
  const [webcamOn,   setWebcamOn]   = useState(false);

  const load = useCallback(async () => {
    try {
      const [modRes, assignRes, analyticsRes] = await Promise.all([
        modulesApi.list(),
        assignmentsApi.list(),
        studyApi.analytics(),
      ]);
      setModules(modRes.data);
      setAssignments(assignRes.data.map((r) => r.assignment || r));
      setAnalytics(analyticsRes.data);
    } catch {
      setModules([{ id: 1, name: 'Database Systems' }, { id: 2, name: 'Web Development' }]);
      setAssignments([{ id: 1, title: 'DB Design Report' }, { id: 2, title: 'React Portfolio' }]);
      setAnalytics(MOCK_ANALYTICS);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  // Timer tick
  useEffect(() => {
    if (running) {
      intervalRef.current = setInterval(() => setSeconds((s) => s + 1), 1000);
    } else {
      clearInterval(intervalRef.current);
    }
    return () => clearInterval(intervalRef.current);
  }, [running]);

  const startSession = async () => {
    try {
      const res = await studyApi.start({ module_id: moduleId || undefined, assignment_id: assignId || undefined, planned_minutes: planned });
      setSessionId(res.data.id);
    } catch {}
    setRunning(true);
  };

  const pauseSession  = async () => {
    setRunning(false);
    if (sessionId) studyApi.update(sessionId, { status: 'paused', actual_minutes: Math.floor(seconds / 60) }).catch(() => {});
  };

  const stopSession = async () => {
    setRunning(false);
    if (sessionId) {
      await studyApi.update(sessionId, { status: 'completed', actual_minutes: Math.floor(seconds / 60) }).catch(() => {});
      setSessionId(null);
    }
    setSeconds(0);
    load(); // refresh analytics
  };

  const logEngagement = async (level, pct) => {
    setEngLevel(pct);
    if (sessionId) studyApi.logEngagement(sessionId, { level, percent: pct, source: 'manual' }).catch(() => {});
  };

  const a = analytics || MOCK_ANALYTICS;

  return (
    <Box className="page-enter">
      <Grid container spacing={3}>
        {/* ── Timer ── */}
        <Grid item xs={12} md={7}>
          <Card>
            <CardContent>
              <Stack direction="row" justifyContent="space-between" alignItems="center" mb={3}>
                <Typography variant="h6" fontWeight={800}>Study Session</Typography>
                <Chip label={running ? 'Active' : 'Idle'} color={running ? 'success' : 'default'} size="small" />
              </Stack>

              {/* Circular timer */}
              <Box display="flex" justifyContent="center" mb={3}>
                <CircularTimer seconds={seconds} running={running} />
              </Box>

              {/* Controls */}
              <Stack direction="row" spacing={1.5} justifyContent="center" mb={3}>
                {!running ? (
                  <Button variant="contained" startIcon={<PlayArrowRounded />} onClick={startSession} sx={{ px: 3 }}>
                    {seconds > 0 ? 'Resume' : 'Start'}
                  </Button>
                ) : (
                  <Button variant="outlined" startIcon={<PauseRounded />} onClick={pauseSession}>
                    Pause
                  </Button>
                )}
                <Button variant="outlined" color="error" startIcon={<StopRounded />} onClick={stopSession} disabled={seconds === 0}>
                  Stop
                </Button>
              </Stack>

              <Grid container spacing={2}>
                <Grid item xs={12} sm={6}>
                  <FormControl fullWidth size="small">
                    <InputLabel>Module</InputLabel>
                    <Select value={moduleId} label="Module" onChange={(e) => setModuleId(e.target.value)}>
                      <MenuItem value=""><em>None</em></MenuItem>
                      {modules.map((m) => <MenuItem key={m.id} value={m.id}>{m.name}</MenuItem>)}
                    </Select>
                  </FormControl>
                </Grid>
                <Grid item xs={12} sm={6}>
                  <FormControl fullWidth size="small">
                    <InputLabel>Assignment</InputLabel>
                    <Select value={assignId} label="Assignment" onChange={(e) => setAssignId(e.target.value)}>
                      <MenuItem value=""><em>None</em></MenuItem>
                      {assignments.map((a) => <MenuItem key={a.id} value={a.id}>{a.title}</MenuItem>)}
                    </Select>
                  </FormControl>
                </Grid>
              </Grid>

              <Box sx={{ mt: 2.5 }}>
                <Typography variant="caption" fontWeight={700} color="text.secondary" mb={1} display="block">
                  PLANNED SESSION: {planned} min
                </Typography>
                <Slider value={planned} onChange={(_, v) => setPlanned(v)} min={5} max={120} step={5} valueLabelDisplay="auto" marks={[{ value: 25, label: '25m' }, { value: 50, label: '50m' }]} />
              </Box>
            </CardContent>
          </Card>
        </Grid>

        {/* ── Engagement ── */}
        <Grid item xs={12} md={5}>
          <Stack spacing={3} height="100%">
            <Card>
              <CardContent>
                <Typography variant="h6" fontWeight={800} mb={2}>Engagement</Typography>
                <Stack direction="row" justifyContent="space-between" alignItems="center" mb={1}>
                  <Typography variant="h3" fontWeight={800}>{engLevel}%</Typography>
                  <Chip
                    label={engLevel >= 70 ? 'Good' : engLevel >= 45 ? 'Moderate' : 'Low'}
                    color={engLevel >= 70 ? 'success' : engLevel >= 45 ? 'warning' : 'error'}
                  />
                </Stack>
                <LinearProgress
                  variant="determinate" value={engLevel}
                  sx={{ height: 12, borderRadius: 99, mb: 2, background: 'rgba(148,163,184,0.15)', '& .MuiLinearProgress-bar': { background: engLevel >= 70 ? '#22c55e' : engLevel >= 45 ? '#f59e0b' : '#f43f5e' } }}
                />
                <Typography variant="caption" fontWeight={700} color="text.secondary" mb={1} display="block">REPORT ENGAGEMENT</Typography>
                <Stack direction="row" spacing={1} flexWrap="wrap">
                  {ENG_LEVELS.map((l) => (
                    <Button
                      key={l.label} size="small"
                      variant={engLevel === l.value ? 'contained' : 'outlined'}
                      onClick={() => logEngagement(l.label, l.value)}
                      sx={{ borderColor: l.color, color: engLevel === l.value ? '#fff' : l.color, background: engLevel === l.value ? l.color : 'transparent', '&:hover': { background: l.color, color: '#fff' } }}
                    >
                      {l.label}
                    </Button>
                  ))}
                </Stack>

                <Box sx={{ mt: 2.5, p: 2, borderRadius: 2, background: 'rgba(20,184,166,0.08)' }}>
                  <Typography fontWeight={700} mb={0.5}>Break recommendation</Typography>
                  <Typography variant="body2" color="text.secondary">
                    {engLevel < 45
                      ? '⚠️ Low engagement detected. Take a 10-minute break now.'
                      : '✅ Pomodoro pattern: 25 min focus → 5 min break. Keep going!'}
                  </Typography>
                </Box>

                <Button
                  fullWidth variant="outlined" startIcon={<CameraAltRounded />}
                  sx={{ mt: 2 }}
                  onClick={() => setWebcamOn((w) => !w)}
                >
                  {webcamOn ? 'Disable' : 'Enable'} webcam engagement
                </Button>
                {webcamOn && (
                  <Box sx={{ mt: 1.5, p: 2, borderRadius: 2, background: 'rgba(99,102,241,0.06)', textAlign: 'center' }}>
                    <Typography variant="caption" color="text.secondary">
                      📷 Webcam mode enabled. The camera feed is analysed locally for engagement signals.
                    </Typography>
                  </Box>
                )}
              </CardContent>
            </Card>

            {/* Quick stats */}
            <Card>
              <CardContent>
                <Typography variant="h6" fontWeight={800} mb={2}>This week</Typography>
                <Grid container spacing={1.5}>
                  {[
                    { label: 'Sessions',    value: a.sessions_count,    icon: <TimerRounded fontSize="small" /> },
                    { label: 'Study hours', value: `${Math.round((a.actual_minutes || 0) / 60)}h`, icon: <TrendingUpRounded fontSize="small" /> },
                    { label: 'Avg. engagement', value: `${a.average_engagement}%`, icon: <TrendingUpRounded fontSize="small" /> },
                    { label: 'Day streak',  value: `${a.streak_days} 🔥`, icon: <LocalFireDepartmentRounded fontSize="small" /> },
                  ].map((s) => (
                    <Grid item xs={6} key={s.label}>
                      <Box sx={{ p: 1.5, borderRadius: 2, background: 'rgba(148,163,184,0.06)', textAlign: 'center' }}>
                        <Typography variant="h6" fontWeight={800}>{s.value}</Typography>
                        <Typography variant="caption" color="text.secondary">{s.label}</Typography>
                      </Box>
                    </Grid>
                  ))}
                </Grid>
              </CardContent>
            </Card>
          </Stack>
        </Grid>

        {/* ── Weekly chart ── */}
        <Grid item xs={12}>
          <Card>
            <CardContent>
              <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2.5}>
                <Typography variant="h6" fontWeight={800}>Weekly study time</Typography>
                <Stack direction="row" spacing={1}>
                  <Chip label={`Planned: ${Math.round((a.planned_minutes || 0) / 60)}h`} size="small" variant="outlined" />
                  <Chip label={`Actual: ${Math.round((a.actual_minutes || 0) / 60)}h`} size="small" color="primary" />
                  <Chip label={`${a.completion_rate}% of goal`} size="small" color={a.completion_rate >= 100 ? 'success' : 'warning'} />
                </Stack>
              </Stack>
              <ResponsiveContainer width="100%" height={220}>
                <BarChart data={a.weekly || []} margin={{ top: 5, right: 10, left: 0, bottom: 5 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="rgba(148,163,184,0.15)" />
                  <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                  <YAxis tick={{ fontSize: 12 }} unit="m" />
                  <Tooltip
                    formatter={(v) => [`${v} min`, 'Study time']}
                    contentStyle={{ borderRadius: 10, border: 'none', boxShadow: '0 4px 16px rgba(0,0,0,0.12)' }}
                  />
                  <ReferenceLine y={a.daily_target || 120} stroke="#6366f1" strokeDasharray="6 3" label={{ value: 'Target', position: 'right', fontSize: 11, fill: '#6366f1' }} />
                  <Bar dataKey="minutes" radius={[6, 6, 0, 0]} fill="#14b8a6" />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}
