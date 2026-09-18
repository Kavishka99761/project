import { useEffect, useState, useCallback } from 'react';
import {
  Box, Card, CardContent, Grid, Typography, Stack, Chip,
  LinearProgress, CircularProgress, Button, Avatar, Paper,
} from '@mui/material';
import {
  TrendingUpRounded, WarningRounded, CalendarMonthRounded,
  BookRounded, TimerRounded, AutoAwesomeRounded,
  ArrowForwardRounded, LocalFireDepartmentRounded,
  EmojiEventsRounded,
} from '@mui/icons-material';
import { dashboardApi } from '../../api/client';
import { useApp } from '../../context/AppContext';
import { riskPalette } from '../../theme';

// Fallback mock data when API is not yet connected
const MOCK = {
  documents_count: 8,
  active_assignments: 5,
  risk_score: 62,
  upcoming_dates: 4,
  streak_days: 7,
  sessions_today: 2,
  studied_today_minutes: 95,
  daily_target_minutes: 180,
  top_assignments: [
    { id: 1, title: 'Database Design Report',  module: { name: 'Database Systems' }, progress: 40, deadline: '2026-09-25', risk: { level: 'High',   score: 74 } },
    { id: 2, title: 'React Portfolio Website', module: { name: 'Web Development'  }, progress: 65, deadline: '2026-09-28', risk: { level: 'Medium', score: 48 } },
    { id: 3, title: 'ML Classification Model', module: { name: 'Machine Learning' }, progress: 10, deadline: '2026-10-02', risk: { level: 'Critical',score: 88 } },
    { id: 4, title: 'Network Analysis Essay',  module: { name: 'Computer Networks'}, progress: 80, deadline: '2026-09-30', risk: { level: 'Low',    score: 22 } },
  ],
  upcoming_events: [
    { id: 1, title: 'DB Systems Exam',        event_date: '2026-09-26', type: 'Exam'      },
    { id: 2, title: 'Project Milestone 2',    event_date: '2026-09-30', type: 'Milestone' },
    { id: 3, title: 'Web Dev Assignment Due', event_date: '2026-10-01', type: 'Deadline'  },
  ],
};

const eventTypeColor = { Exam: 'error', Deadline: 'warning', Milestone: 'info', Event: 'primary' };

export default function DashboardPage() {
  const { user, setCurrentPage } = useApp();
  const [data,    setData]    = useState(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    try {
      const res = await dashboardApi.get();
      setData(res.data);
    } catch {
      setData(MOCK);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const d = data || MOCK;

  const studyPct = d.daily_target_minutes > 0
    ? Math.min(100, Math.round((d.studied_today_minutes / d.daily_target_minutes) * 100))
    : 0;

  const statCards = [
    {
      label:  'Documents',
      value:  d.documents_count ?? 0,
      icon:   <BookRounded />,
      color:  '#3b82f6',
      page:   'learning',
    },
    {
      label:  'Study today',
      value:  `${d.studied_today_minutes ?? 0}m / ${d.daily_target_minutes ?? 180}m`,
      icon:   <TimerRounded />,
      color:  '#14b8a6',
      page:   'study',
    },
    {
      label:  'Risk score',
      value:  `${d.risk_score ?? 0}%`,
      icon:   <WarningRounded />,
      color:  (d.risk_score ?? 0) > 70 ? '#f43f5e' : (d.risk_score ?? 0) > 45 ? '#f97316' : '#22c55e',
      page:   'assignments',
    },
    {
      label:  'Upcoming events',
      value:  d.upcoming_dates ?? 0,
      icon:   <CalendarMonthRounded />,
      color:  '#8b5cf6',
      page:   'calendar',
    },
  ];

  if (loading) {
    return (
      <Box display="flex" alignItems="center" justifyContent="center" minHeight="60vh">
        <CircularProgress size={40} />
      </Box>
    );
  }

  return (
    <Box className="page-enter">
      {/* ── Greeting ── */}
      <Box sx={{
        mb: 4, p: 3.5, borderRadius: 4,
        background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #3b82f6 100%)',
        backgroundSize: '200% 200%',
        animation: 'gradientShift 8s ease infinite',
        color: '#fff',
        position: 'relative',
        overflow: 'hidden',
      }}>
        <Box sx={{ position: 'absolute', top: -30, right: -30, width: 160, height: 160, borderRadius: '50%', background: 'rgba(255,255,255,0.06)' }} />
        <Box sx={{ position: 'absolute', bottom: -40, right: 60, width: 100, height: 100, borderRadius: '50%', background: 'rgba(255,255,255,0.04)' }} />
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={2}>
          <Box>
            <Typography variant="h4" fontWeight={800}>
              Good {new Date().getHours() < 12 ? 'morning' : new Date().getHours() < 18 ? 'afternoon' : 'evening'},{' '}
              {user?.name?.split(' ')[0] || 'Student'} 👋
            </Typography>
            <Typography variant="body1" sx={{ color: 'rgba(255,255,255,0.82)', mt: 0.5 }}>
              Here's your academic overview for today
            </Typography>
          </Box>
          <Stack direction="row" spacing={2}>
            <Box sx={{ textAlign: 'center', p: 1.5, borderRadius: 2, background: 'rgba(255,255,255,0.12)' }}>
              <LocalFireDepartmentRounded sx={{ color: '#fbbf24' }} />
              <Typography variant="h6" fontWeight={800}>{d.streak_days ?? 0}</Typography>
              <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.7)' }}>Day streak</Typography>
            </Box>
            <Box sx={{ textAlign: 'center', p: 1.5, borderRadius: 2, background: 'rgba(255,255,255,0.12)' }}>
              <EmojiEventsRounded sx={{ color: '#fbbf24' }} />
              <Typography variant="h6" fontWeight={800}>{d.sessions_today ?? 0}</Typography>
              <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.7)' }}>Sessions today</Typography>
            </Box>
          </Stack>
        </Stack>

        {/* Study progress bar */}
        <Box sx={{ mt: 3 }}>
          <Stack direction="row" justifyContent="space-between" mb={0.8}>
            <Typography variant="body2" sx={{ color: 'rgba(255,255,255,0.8)', fontWeight: 600 }}>
              Daily study goal
            </Typography>
            <Typography variant="body2" sx={{ color: 'rgba(255,255,255,0.8)', fontWeight: 700 }}>
              {studyPct}%
            </Typography>
          </Stack>
          <LinearProgress
            variant="determinate" value={studyPct}
            sx={{
              height: 8, borderRadius: 99,
              background: 'rgba(255,255,255,0.2)',
              '& .MuiLinearProgress-bar': { background: '#fff', borderRadius: 99 },
            }}
          />
        </Box>
      </Box>

      {/* ── Stat Cards ── */}
      <Grid container spacing={3} sx={{ mb: 3 }}>
        {statCards.map((card, i) => (
          <Grid item xs={12} sm={6} md={3} key={card.label}>
            <Card
              className="card-hover-lift"
              sx={{ cursor: 'pointer', animationDelay: `${i * 60}ms` }}
              onClick={() => setCurrentPage(card.page)}
            >
              <CardContent>
                <Stack direction="row" alignItems="center" spacing={2}>
                  <Box sx={{
                    width: 52, height: 52, borderRadius: 3,
                    display: 'grid', placeItems: 'center',
                    background: `${card.color}18`, color: card.color,
                  }}>
                    {card.icon}
                  </Box>
                  <Box>
                    <Typography variant="h5" fontWeight={800}>{card.value}</Typography>
                    <Typography variant="caption" color="text.secondary">{card.label}</Typography>
                  </Box>
                </Stack>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      {/* ── Main content ── */}
      <Grid container spacing={3}>
        {/* Assignment priority list */}
        <Grid item xs={12} lg={8}>
          <Card>
            <CardContent>
              <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2.5}>
                <Typography variant="h6" fontWeight={800}>Priority assignments</Typography>
                <Button
                  size="small" endIcon={<ArrowForwardRounded />}
                  onClick={() => setCurrentPage('assignments')}
                >
                  View all
                </Button>
              </Stack>
              <Stack spacing={2.5}>
                {(d.top_assignments || []).map((item) => {
                  const risk = item.risk || { level: 'Medium', score: 50 };
                  const p    = riskPalette[risk.level] || riskPalette.Medium;
                  return (
                    <Box key={item.id}>
                      <Stack direction="row" justifyContent="space-between" alignItems="center" mb={1}>
                        <Box>
                          <Typography fontWeight={700} fontSize="0.9rem">{item.title}</Typography>
                          <Typography variant="caption" color="text.secondary">
                            {item.module?.name} · due {item.deadline}
                          </Typography>
                        </Box>
                        <Box component="span" className="risk-badge" sx={{ color: p.color, background: p.bg }}>
                          {risk.level} · {risk.score}%
                        </Box>
                      </Stack>
                      <LinearProgress
                        variant="determinate"
                        value={item.progress}
                        sx={{
                          height: 8, borderRadius: 99,
                          background: 'rgba(148,163,184,0.15)',
                          '& .MuiLinearProgress-bar': { background: p.color },
                        }}
                      />
                      <Typography variant="caption" color="text.secondary" sx={{ mt: 0.5, display: 'block' }}>
                        {item.progress}% complete
                      </Typography>
                    </Box>
                  );
                })}
              </Stack>
            </CardContent>
          </Card>
        </Grid>

        {/* Upcoming events + AI suggestion */}
        <Grid item xs={12} lg={4}>
          <Stack spacing={3}>
            <Card>
              <CardContent>
                <Typography variant="h6" fontWeight={800} mb={2}>Upcoming events</Typography>
                <Stack spacing={1.5}>
                  {(d.upcoming_events || []).map((ev) => (
                    <Paper
                      key={ev.id}
                      sx={{ p: 1.5, display: 'flex', alignItems: 'center', gap: 1.5, cursor: 'pointer' }}
                      onClick={() => setCurrentPage('calendar')}
                    >
                      <Box sx={{
                        width: 8, height: 8, borderRadius: '50%', flexShrink: 0,
                        background: ev.type === 'Exam' ? '#f43f5e' : ev.type === 'Deadline' ? '#f97316' : '#6366f1',
                      }} />
                      <Box flex={1} minWidth={0}>
                        <Typography fontWeight={700} fontSize="0.82rem" noWrap>{ev.title}</Typography>
                        <Typography variant="caption" color="text.secondary">{ev.event_date}</Typography>
                      </Box>
                      <Chip label={ev.type} size="small" color={eventTypeColor[ev.type] || 'default'} variant="outlined" />
                    </Paper>
                  ))}
                </Stack>
              </CardContent>
            </Card>

            <Card sx={{ background: 'linear-gradient(135deg, rgba(99,102,241,0.06), rgba(139,92,246,0.06))' }}>
              <CardContent>
                <Stack direction="row" spacing={1.5} alignItems="flex-start">
                  <Avatar sx={{ background: 'linear-gradient(135deg, #6366f1, #8b5cf6)', width: 36, height: 36 }}>
                    <AutoAwesomeRounded fontSize="small" />
                  </Avatar>
                  <Box>
                    <Typography fontWeight={700} mb={0.5}>AI Suggestion</Typography>
                    <Typography variant="body2" color="text.secondary" lineHeight={1.6}>
                      Your highest risk assignment needs attention today.
                      Start with a focused 25-minute session to make meaningful progress.
                    </Typography>
                    <Button
                      size="small" variant="outlined" sx={{ mt: 1.5 }}
                      onClick={() => setCurrentPage('study')}
                    >
                      Start study session
                    </Button>
                  </Box>
                </Stack>
              </CardContent>
            </Card>
          </Stack>
        </Grid>
      </Grid>
    </Box>
  );
}
