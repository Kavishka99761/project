import React, { useEffect, useMemo, useState } from 'react';
import {
  Box,
  Card,
  CardContent,
  Chip,
  Drawer,
  IconButton,
  Stack,
  Typography,
  alpha,
} from '@mui/material';
import {
  addMonths,
  eachDayOfInterval,
  endOfMonth,
  endOfWeek,
  format,
  isSameDay,
  isSameMonth,
  startOfMonth,
  startOfWeek,
  subMonths,
} from 'date-fns';
import ChevronLeftRoundedIcon from '@mui/icons-material/ChevronLeftRounded';
import ChevronRightRoundedIcon from '@mui/icons-material/ChevronRightRounded';
import TodayRoundedIcon from '@mui/icons-material/TodayRounded';
import { getCalendar } from '../api/common';
import { moduleColors } from '../theme';

const SOURCE_COLOR = { kavishka: moduleColors.kavishka, jithmi: moduleColors.jithmi };

export default function CalendarPage() {
  const [cursor, setCursor] = useState(new Date());
  const [events, setEvents] = useState([]);
  const [selectedDay, setSelectedDay] = useState(null);

  const from = format(startOfMonth(subMonths(cursor, 1)), 'yyyy-MM-dd');
  const to = format(endOfMonth(addMonths(cursor, 1)), 'yyyy-MM-dd');

  useEffect(() => {
    getCalendar(from, to)
      .then((data) => setEvents(data.events || []))
      .catch(() => setEvents([]));
  }, [from, to]);

  const days = useMemo(() => {
    const start = startOfWeek(startOfMonth(cursor));
    const end = endOfWeek(endOfMonth(cursor));
    return eachDayOfInterval({ start, end });
  }, [cursor]);

  const eventsFor = (day) => events.filter((e) => isSameDay(new Date(e.date), day));

  return (
    <Box>
      <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 3 }}>
        <Typography variant="h4" fontWeight={800}>
          Calendar
        </Typography>
        <Stack direction="row" alignItems="center" spacing={1}>
          <IconButton onClick={() => setCursor(subMonths(cursor, 1))}>
            <ChevronLeftRoundedIcon />
          </IconButton>
          <Typography variant="subtitle1" fontWeight={700} sx={{ minWidth: 150, textAlign: 'center' }}>
            {format(cursor, 'MMMM yyyy')}
          </Typography>
          <IconButton onClick={() => setCursor(addMonths(cursor, 1))}>
            <ChevronRightRoundedIcon />
          </IconButton>
          <IconButton onClick={() => setCursor(new Date())}>
            <TodayRoundedIcon />
          </IconButton>
        </Stack>
      </Stack>

      <Card>
        <CardContent>
          <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 1, mb: 1 }}>
            {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => (
              <Typography key={d} variant="caption" color="text.secondary" textAlign="center" fontWeight={700}>
                {d}
              </Typography>
            ))}
          </Box>
          <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 1 }}>
            {days.map((day) => {
              const dayEvents = eventsFor(day);
              const inMonth = isSameMonth(day, cursor);
              const isToday = isSameDay(day, new Date());
              return (
                <Box
                  key={day.toISOString()}
                  onClick={() => dayEvents.length && setSelectedDay({ day, dayEvents })}
                  sx={{
                    minHeight: 84,
                    p: 1,
                    borderRadius: 2,
                    cursor: dayEvents.length ? 'pointer' : 'default',
                    opacity: inMonth ? 1 : 0.35,
                    border: isToday ? `2px solid ${moduleColors.primary}` : '1px solid transparent',
                    bgcolor: (theme) => (theme.palette.mode === 'dark' ? '#1c2030' : '#f7f8fc'),
                    transition: 'transform 0.15s ease',
                    '&:hover': dayEvents.length ? { transform: 'scale(1.03)' } : {},
                  }}
                >
                  <Typography variant="caption" fontWeight={isToday ? 800 : 500}>
                    {format(day, 'd')}
                  </Typography>
                  <Stack spacing={0.4} sx={{ mt: 0.5 }}>
                    {dayEvents.slice(0, 2).map((e) => (
                      <Box
                        key={e.id}
                        sx={{
                          fontSize: 10,
                          borderRadius: 1,
                          px: 0.5,
                          py: 0.2,
                          color: '#fff',
                          bgcolor: SOURCE_COLOR[e.source] || moduleColors.primary,
                          whiteSpace: 'nowrap',
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                        }}
                      >
                        {e.title}
                      </Box>
                    ))}
                    {dayEvents.length > 2 && (
                      <Typography variant="caption" color="text.secondary">
                        +{dayEvents.length - 2} more
                      </Typography>
                    )}
                  </Stack>
                </Box>
              );
            })}
          </Box>
        </CardContent>
      </Card>

      <Drawer anchor="right" open={Boolean(selectedDay)} onClose={() => setSelectedDay(null)}>
        <Box sx={{ width: 320, p: 3 }}>
          <Typography variant="h6" fontWeight={700} sx={{ mb: 2 }}>
            {selectedDay ? format(selectedDay.day, 'EEEE, d MMMM yyyy') : ''}
          </Typography>
          <Stack spacing={2}>
            {selectedDay?.dayEvents.map((e) => (
              <Card key={e.id} variant="outlined">
                <CardContent>
                  <Chip
                    size="small"
                    label={e.type}
                    sx={{
                      mb: 1,
                      bgcolor: alpha(SOURCE_COLOR[e.source] || moduleColors.primary, 0.15),
                      color: SOURCE_COLOR[e.source] || moduleColors.primary,
                    }}
                  />
                  <Typography variant="subtitle2" fontWeight={700}>
                    {e.title}
                  </Typography>
                  {e.meta?.reminder && (
                    <Typography variant="caption" color="text.secondary">
                      Reminder: {e.meta.reminder}
                    </Typography>
                  )}
                  {e.meta?.risk_level && (
                    <Typography variant="caption" color="text.secondary" display="block">
                      Risk: {e.meta.risk_level} ({e.meta.risk_score})
                    </Typography>
                  )}
                </CardContent>
              </Card>
            ))}
          </Stack>
        </Box>
      </Drawer>
    </Box>
  );
}
