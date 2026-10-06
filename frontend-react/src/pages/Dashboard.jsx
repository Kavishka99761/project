import React, { useEffect, useState } from 'react';
import {
  Box,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Grid,
  Skeleton,
  Stack,
  Typography,
  alpha,
} from '@mui/material';
import { motion } from 'framer-motion';
import { useNavigate } from 'react-router-dom';
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip as ChartTooltip } from 'recharts';
import MenuBookRoundedIcon from '@mui/icons-material/MenuBookRounded';
import TimerRoundedIcon from '@mui/icons-material/TimerRounded';
import SmartToyRoundedIcon from '@mui/icons-material/SmartToyRounded';
import AssignmentRoundedIcon from '@mui/icons-material/AssignmentRounded';
import DescriptionRoundedIcon from '@mui/icons-material/DescriptionRounded';
import WarningAmberRoundedIcon from '@mui/icons-material/WarningAmberRounded';
import EventRoundedIcon from '@mui/icons-material/EventRounded';
import { useAuth } from '../context/AuthContext';
import { getDashboard } from '../api/common';
import { moduleColors, riskColors } from '../theme';

const MODULE_CARDS = [
  { key: 'bethmi', label: 'Learning Materials', path: '/learning', icon: <MenuBookRoundedIcon /> },
  { key: 'pasindu', label: 'Study Session', path: '/study', icon: <TimerRoundedIcon /> },
  { key: 'kavishka', label: 'AI Assistant', path: '/assistant', icon: <SmartToyRoundedIcon /> },
  { key: 'jithmi', label: 'Assignments', path: '/assignments', icon: <AssignmentRoundedIcon /> },
];

function StatCard({ icon, label, value, color, delay }) {
  return (
    <Card
      component={motion.div}
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.35, delay }}
      sx={{ height: '100%' }}
    >
      <CardContent>
        <Stack direction="row" spacing={1.5} alignItems="center">
          <Box
            sx={{
              width: 44,
              height: 44,
              borderRadius: 2.5,
              bgcolor: alpha(color, 0.15),
              color,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            {icon}
          </Box>
          <Box>
            <Typography variant="h5" fontWeight={800}>
              {value}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {label}
            </Typography>
          </Box>
        </Stack>
      </CardContent>
    </Card>
  );
}

export default function Dashboard() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    getDashboard()
      .then(setData)
      .catch(() => setError('Could not reach the Laravel API. Is `php artisan serve` running?'));
  }, []);

  if (error) {
    return (
      <Card sx={{ p: 3 }}>
        <Typography color="error" fontWeight={700} sx={{ mb: 1 }}>
          Backend unavailable
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {error}
        </Typography>
      </Card>
    );
  }

  if (!data) {
    return (
      <Grid container spacing={3}>
        {[1, 2, 3, 4].map((i) => (
          <Grid item xs={12} sm={6} md={3} key={i}>
            <Skeleton variant="rounded" height={110} />
          </Grid>
        ))}
      </Grid>
    );
  }

  const riskEntries = Object.entries(data.risk_summary || {}).filter(([, v]) => v > 0);
  const studyPercent = data.study_today?.percent ?? 0;

  return (
    <Box>
      <Box sx={{ mb: 3 }}>
        <Typography variant="h4" fontWeight={800}>
          {data.greeting}, {data.user?.name?.split(' ')[0]}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          Here's your EDU-SMART overview for today.
        </Typography>
      </Box>

      <Grid container spacing={3} sx={{ mb: 1 }}>
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            icon={<DescriptionRoundedIcon />}
            label="Documents"
            value={data.counts?.documents ?? 0}
            color={moduleColors.bethmi}
            delay={0}
          />
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            icon={<AssignmentRoundedIcon />}
            label="Assignments"
            value={data.counts?.assignments ?? 0}
            color={moduleColors.jithmi}
            delay={0.05}
          />
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            icon={<WarningAmberRoundedIcon />}
            label="Overdue"
            value={data.counts?.overdue ?? 0}
            color={riskColors.Critical}
            delay={0.1}
          />
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            icon={<EventRoundedIcon />}
            label="Academic dates"
            value={data.academic_dates?.length ?? 0}
            color={moduleColors.kavishka}
            delay={0.15}
          />
        </Grid>
      </Grid>

      <Grid container spacing={3} sx={{ mt: 0.5 }}>
        <Grid item xs={12} md={4}>
          <Card
            component={motion.div}
            initial={{ opacity: 0, scale: 0.96 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.35, delay: 0.2 }}
            sx={{ height: '100%' }}
          >
            <CardContent sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
              <Typography variant="subtitle1" fontWeight={700} sx={{ alignSelf: 'flex-start' }}>
                Study today
              </Typography>
              <Box sx={{ position: 'relative', display: 'inline-flex', my: 2 }}>
                <CircularProgress
                  variant="determinate"
                  value={Math.min(studyPercent, 100)}
                  size={130}
                  thickness={5}
                  sx={{ color: moduleColors.pasindu }}
                />
                <Box
                  sx={{
                    top: 0,
                    left: 0,
                    bottom: 0,
                    right: 0,
                    position: 'absolute',
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                  }}
                >
                  <Typography variant="h5" fontWeight={800}>
                    {studyPercent}%
                  </Typography>
                  <Typography variant="caption" color="text.secondary">
                    {data.study_today?.minutes ?? 0} / {data.study_today?.target ?? 0} min
                  </Typography>
                </Box>
              </Box>
              <Chip
                label={`Avg. engagement ${data.study_today?.average_engagement ?? 0}%`}
                size="small"
                sx={{ bgcolor: alpha(moduleColors.pasindu, 0.12), color: moduleColors.pasindu }}
              />
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} md={4}>
          <Card
            component={motion.div}
            initial={{ opacity: 0, scale: 0.96 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.35, delay: 0.25 }}
            sx={{ height: '100%' }}
          >
            <CardContent>
              <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                Assignment risk
              </Typography>
              {riskEntries.length === 0 ? (
                <Typography variant="body2" color="text.secondary">
                  No active assignments \u2014 you're all caught up.
                </Typography>
              ) : (
                <Box sx={{ height: 180 }}>
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie
                        data={riskEntries.map(([level, count]) => ({ name: level, value: count }))}
                        dataKey="value"
                        nameKey="name"
                        innerRadius={45}
                        outerRadius={70}
                        paddingAngle={3}
                      >
                        {riskEntries.map(([level]) => (
                          <Cell key={level} fill={riskColors[level] || moduleColors.jithmi} />
                        ))}
                      </Pie>
                      <ChartTooltip />
                    </PieChart>
                  </ResponsiveContainer>
                </Box>
              )}
              <Stack direction="row" flexWrap="wrap" gap={1} sx={{ mt: 1 }}>
                {riskEntries.map(([level, count]) => (
                  <Chip
                    key={level}
                    size="small"
                    label={`${level}: ${count}`}
                    sx={{ bgcolor: alpha(riskColors[level] || moduleColors.jithmi, 0.12), color: riskColors[level] }}
                  />
                ))}
              </Stack>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} md={4}>
          <Card
            component={motion.div}
            initial={{ opacity: 0, scale: 0.96 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.35, delay: 0.3 }}
            sx={{ height: '100%' }}
          >
            <CardContent>
              <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                Upcoming deadlines
              </Typography>
              <Stack spacing={1.25}>
                {(data.upcoming_deadlines || []).length === 0 && (
                  <Typography variant="body2" color="text.secondary">
                    Nothing due in the next 7 days.
                  </Typography>
                )}
                {(data.upcoming_deadlines || []).map((d) => (
                  <Stack key={d.id} direction="row" justifyContent="space-between" alignItems="center">
                    <Typography variant="body2" noWrap sx={{ maxWidth: 160 }}>
                      {d.title}
                    </Typography>
                    <Chip
                      size="small"
                      label={`${d.days_left}d \u00b7 ${d.level}`}
                      sx={{ bgcolor: alpha(riskColors[d.level] || moduleColors.jithmi, 0.12), color: riskColors[d.level] }}
                    />
                  </Stack>
                ))}
              </Stack>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <Typography variant="h6" fontWeight={700} sx={{ mt: 4, mb: 2 }}>
        Your modules
      </Typography>
      <Grid container spacing={3}>
        {MODULE_CARDS.map((m, i) => (
          <Grid item xs={12} sm={6} md={3} key={m.key}>
            <Card
              component={motion.div}
              whileHover={{ y: -4 }}
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.3, delay: 0.1 * i }}
              onClick={() => navigate(m.path)}
              sx={{
                cursor: 'pointer',
                borderTop: `4px solid ${moduleColors[m.key]}`,
              }}
            >
              <CardContent>
                <Box
                  sx={{
                    width: 44,
                    height: 44,
                    borderRadius: 2.5,
                    bgcolor: alpha(moduleColors[m.key], 0.15),
                    color: moduleColors[m.key],
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    mb: 1.5,
                  }}
                >
                  {m.icon}
                </Box>
                <Typography variant="subtitle1" fontWeight={700}>
                  {m.label}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  Open module
                </Typography>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>
    </Box>
  );
}
