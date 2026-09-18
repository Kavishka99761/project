import { useEffect, useMemo, useState } from 'react';
import {
  AppBar, Avatar, Badge, Box, Button, Chip, CircularProgress,
  Container, CssBaseline, Divider, Drawer, IconButton, InputAdornment,
  List, ListItem, ListItemButton, ListItemIcon, ListItemText,
  Menu, MenuItem, Paper, Stack, TextField, Toolbar, Tooltip, Typography,
} from '@mui/material';
import {
  DashboardRounded, MenuBookRounded, TimerRounded, SmartToyRounded,
  AssignmentRounded, CalendarMonthRounded, PersonRounded, SearchRounded,
  NotificationsRounded, DarkModeRounded, LightModeRounded, SchoolRounded,
  CloseRounded, MenuRounded,
} from '@mui/icons-material';

import { useApp } from './context/AppContext';
import LoginPage      from './components/auth/LoginPage';
import RegisterPage   from './components/auth/RegisterPage';
import DashboardPage  from './components/dashboard/DashboardPage';
import LearningPage   from './components/learning/LearningPage';
import StudyPage      from './components/study/StudyPage';
import AssistantPage  from './components/assistant/AssistantPage';
import AssignmentsPage from './components/assignments/AssignmentsPage';
import CalendarPage   from './components/calendar/CalendarPage';
import ProfilePage    from './components/profile/ProfilePage';
import { notificationsApi, searchApi } from './api/client';

const DRAWER_WIDTH = 264;

const NAV = [
  { id: 'dashboard',   label: 'Overview',           icon: <DashboardRounded />,   color: '#6366f1' },
  { id: 'learning',    label: 'Learning Materials',  icon: <MenuBookRounded />,    color: '#3b82f6' },
  { id: 'study',       label: 'Study & Engagement',  icon: <TimerRounded />,       color: '#14b8a6' },
  { id: 'assistant',   label: 'Academic Assistant',  icon: <SmartToyRounded />,    color: '#8b5cf6' },
  { id: 'assignments', label: 'Assignment Risk',      icon: <AssignmentRounded />,  color: '#f97316' },
  { id: 'calendar',    label: 'Calendar',             icon: <CalendarMonthRounded />, color: '#6366f1' },
  { id: 'profile',     label: 'Profile & Settings',  icon: <PersonRounded />,      color: '#6366f1' },
];

const PAGE_TITLES = {
  dashboard:   'Overview',
  learning:    'Learning Materials',
  study:       'Study & Engagement',
  assistant:   'Academic Assistant',
  assignments: 'Assignment Risk',
  calendar:    'Calendar',
  profile:     'Profile & Settings',
};

function SearchBar({ onClose }) {
  const [q, setQ] = useState('');
  const [results, setResults] = useState(null);
  const [loading, setLoading] = useState(false);
  const { setCurrentPage } = useApp();

  useEffect(() => {
    if (q.length < 2) { setResults(null); return; }
    const t = setTimeout(async () => {
      setLoading(true);
      try {
        const res = await searchApi.query(q);
        setResults(res.data.results || []);
      } catch {
        setResults([]);
      } finally { setLoading(false); }
    }, 350);
    return () => clearTimeout(t);
  }, [q]);

  const typeIcon = { document: '📄', assignment: '📋', date: '📅', module: '📚' };
  const typePage = { document: 'learning', assignment: 'assignments', date: 'calendar', module: 'learning' };

  return (
    <Box sx={{ position: 'relative', width: '100%', maxWidth: 520 }}>
      <TextField
        autoFocus fullWidth size="small" placeholder="Search documents, assignments, dates…"
        value={q} onChange={(e) => setQ(e.target.value)}
        InputProps={{
          startAdornment: <InputAdornment position="start">{loading ? <CircularProgress size={16} /> : <SearchRounded fontSize="small" />}</InputAdornment>,
          endAdornment: q && <InputAdornment position="end"><IconButton size="small" onClick={() => { setQ(''); setResults(null); }}><CloseRounded fontSize="small" /></IconButton></InputAdornment>,
        }}
      />
      {results !== null && (
        <Paper sx={{ position: 'absolute', top: '110%', left: 0, right: 0, zIndex: 1400, maxHeight: 320, overflow: 'auto', boxShadow: '0 8px 32px rgba(0,0,0,0.15)' }}>
          {results.length === 0 ? (
            <Box sx={{ p: 2, textAlign: 'center' }}>
              <Typography variant="body2" color="text.secondary">No results for "{q}"</Typography>
            </Box>
          ) : results.map((r, i) => (
            <Box key={i} sx={{ px: 2, py: 1.2, cursor: 'pointer', '&:hover': { background: 'rgba(99,102,241,0.06)' }, borderBottom: '1px solid rgba(148,163,184,0.1)' }}
              onClick={() => { setCurrentPage(typePage[r.type] || 'dashboard'); setQ(''); setResults(null); onClose?.(); }}>
              <Stack direction="row" spacing={1.5} alignItems="center">
                <Typography fontSize="1rem">{typeIcon[r.type] || '🔍'}</Typography>
                <Box>
                  <Typography fontWeight={700} fontSize="0.85rem">{r.label}</Typography>
                  <Typography variant="caption" color="text.secondary">{r.sub}</Typography>
                </Box>
                <Chip label={r.type} size="small" variant="outlined" sx={{ ml: 'auto', fontSize: '0.65rem' }} />
              </Stack>
            </Box>
          ))}
        </Paper>
      )}
    </Box>
  );
}

function NotificationsMenu({ anchorEl, onClose }) {
  const { notifications, notifCount } = useApp();
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!anchorEl) return;
    notificationsApi.list().then((r) => setItems(r.data)).catch(() => setItems(notifications)).finally(() => setLoading(false));
  }, [anchorEl]);

  const markAllRead = async () => {
    try { await notificationsApi.markAllRead(); } catch {}
    setItems((i) => i.map((n) => ({ ...n, is_read: true })));
  };

  const dotColor = (src) => ({ jithmi: '#f97316', kavishka: '#8b5cf6', bethmi: '#3b82f6', pasindu: '#14b8a6', common: '#6366f1' }[src] || '#94a3b8');

  return (
    <Menu anchorEl={anchorEl} open={Boolean(anchorEl)} onClose={onClose} PaperProps={{ sx: { width: 340, maxHeight: 440, borderRadius: 3 } }}>
      <Box sx={{ px: 2, py: 1.5, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Typography fontWeight={800}>Notifications</Typography>
        {items.some((n) => !n.is_read) && <Button size="small" onClick={markAllRead}>Mark all read</Button>}
      </Box>
      <Divider />
      {loading ? (
        <Box sx={{ p: 3, textAlign: 'center' }}><CircularProgress size={24} /></Box>
      ) : items.length === 0 ? (
        <Box sx={{ p: 3, textAlign: 'center' }}><Typography variant="body2" color="text.secondary">No notifications</Typography></Box>
      ) : items.map((n) => (
        <MenuItem key={n.id} sx={{ py: 1.5, px: 2, alignItems: 'flex-start', gap: 1.5, background: n.is_read ? 'transparent' : 'rgba(99,102,241,0.04)' }}>
          <Box sx={{ width: 8, height: 8, borderRadius: '50%', background: dotColor(n.module_source), mt: 0.8, flexShrink: 0 }} />
          <Box>
            <Typography fontWeight={700} fontSize="0.85rem">{n.title}</Typography>
            {n.message && <Typography variant="caption" color="text.secondary" display="block">{n.message}</Typography>}
          </Box>
        </MenuItem>
      ))}
    </Menu>
  );
}

function AppShell({ page, setPage }) {
  const { user, themeMode, toggleTheme, notifCount } = useApp();
  const [notifAnchor, setNotifAnchor] = useState(null);
  const [searchOpen, setSearchOpen] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  const initials = (user?.name || 'S').split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2);

  const PageComponent = useMemo(() => ({
    dashboard:   DashboardPage,
    learning:    LearningPage,
    study:       StudyPage,
    assistant:   AssistantPage,
    assignments: AssignmentsPage,
    calendar:    CalendarPage,
    profile:     ProfilePage,
  }[page] || DashboardPage), [page]);

  const drawerContent = (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <Toolbar />
      <Box sx={{ p: 2, flex: 1 }}>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 2.5, p: 1.5, borderRadius: 3, background: 'rgba(99,102,241,0.07)' }}>
          <Box sx={{ width: 38, height: 38, borderRadius: 2, display: 'grid', placeItems: 'center', background: 'linear-gradient(135deg, #6366f1, #8b5cf6)', color: '#fff' }}>
            <SchoolRounded fontSize="small" />
          </Box>
          <Box>
            <Typography variant="subtitle2" fontWeight={800}>AcadeAlert</Typography>
            <Typography variant="caption" color="text.secondary">Student workspace</Typography>
          </Box>
        </Box>
        <List disablePadding>
          {NAV.map((item) => {
            const active = item.id === page;
            return (
              <ListItem key={item.id} disablePadding sx={{ mb: 0.5 }}>
                <ListItemButton
                  selected={active}
                  onClick={() => { setPage(item.id); setMobileOpen(false); }}
                  sx={{
                    borderRadius: 2.5, px: 1.5,
                    '&.Mui-selected': { background: `${item.color}14`, color: item.color },
                    '&.Mui-selected .MuiListItemIcon-root': { color: item.color },
                    '&:hover': { transform: 'translateX(2px)' },
                    transition: 'all 0.15s ease',
                  }}
                >
                  <ListItemIcon sx={{ minWidth: 36, color: active ? item.color : 'text.secondary' }}>{item.icon}</ListItemIcon>
                  <ListItemText primary={<Typography fontWeight={active ? 700 : 500} fontSize="0.88rem">{item.label}</Typography>} />
                  {active && <Box sx={{ width: 3, height: 20, borderRadius: 99, background: item.color, ml: 1 }} />}
                </ListItemButton>
              </ListItem>
            );
          })}
        </List>
      </Box>
      <Box sx={{ p: 2, borderTop: '1px solid rgba(148,163,184,0.12)' }}>
        <Stack direction="row" spacing={1.5} alignItems="center">
          <Avatar sx={{ width: 34, height: 34, background: 'linear-gradient(135deg, #6366f1, #8b5cf6)', fontSize: 13, fontWeight: 700 }}>{initials}</Avatar>
          <Box flex={1} minWidth={0}>
            <Typography fontWeight={700} fontSize="0.82rem" noWrap>{user?.name || 'Student'}</Typography>
            <Typography variant="caption" color="text.secondary" noWrap>{user?.email}</Typography>
          </Box>
        </Stack>
      </Box>
    </Box>
  );

  return (
    <Box sx={{ display: 'flex', minHeight: '100vh' }}>
      {/* AppBar */}
      <AppBar position="fixed" sx={{ zIndex: 1300, background: themeMode === 'dark' ? 'rgba(17,24,39,0.92)' : 'rgba(255,255,255,0.88)', color: themeMode === 'dark' ? '#f1f5f9' : '#0f172a', backdropFilter: 'blur(20px)', borderBottom: '1px solid rgba(148,163,184,0.15)', boxShadow: 'none' }}>
        <Toolbar sx={{ minHeight: 68, gap: 1 }}>
          <IconButton sx={{ display: { md: 'none' }, mr: 0.5 }} onClick={() => setMobileOpen((o) => !o)}>
            <MenuRounded />
          </IconButton>
          <Typography variant="h6" fontWeight={800} sx={{ letterSpacing: '-0.04em', mr: 2, display: { xs: 'none', sm: 'block' } }}>
            {PAGE_TITLES[page] || 'AcadeAlert'}
          </Typography>

          {/* Search */}
          <Box sx={{ flex: 1, maxWidth: 480, display: { xs: searchOpen ? 'block' : 'none', sm: 'block' } }}>
            <SearchBar onClose={() => setSearchOpen(false)} />
          </Box>

          <Box sx={{ ml: 'auto', display: 'flex', alignItems: 'center', gap: 0.5 }}>
            <IconButton sx={{ display: { xs: 'flex', sm: 'none' } }} onClick={() => setSearchOpen((o) => !o)}>
              <SearchRounded />
            </IconButton>
            <Tooltip title={themeMode === 'dark' ? 'Light mode' : 'Dark mode'}>
              <IconButton onClick={toggleTheme}>
                {themeMode === 'dark' ? <LightModeRounded /> : <DarkModeRounded />}
              </IconButton>
            </Tooltip>
            <Tooltip title="Notifications">
              <IconButton onClick={(e) => setNotifAnchor(e.currentTarget)}>
                <Badge badgeContent={notifCount || 0} color="error" max={9}>
                  <NotificationsRounded />
                </Badge>
              </IconButton>
            </Tooltip>
            <Avatar
              onClick={() => setPage('profile')}
              sx={{ ml: 1, width: 36, height: 36, background: 'linear-gradient(135deg, #6366f1, #8b5cf6)', fontSize: 13, fontWeight: 700, cursor: 'pointer' }}
            >
              {initials}
            </Avatar>
          </Box>
        </Toolbar>
      </AppBar>

      {/* Sidebar — desktop */}
      <Drawer variant="permanent" sx={{ width: DRAWER_WIDTH, flexShrink: 0, display: { xs: 'none', md: 'block' }, '& .MuiDrawer-paper': { width: DRAWER_WIDTH, boxSizing: 'border-box', borderRight: '1px solid rgba(148,163,184,0.12)', background: themeMode === 'dark' ? '#0f172a' : '#f8fafc' } }}>
        {drawerContent}
      </Drawer>

      {/* Sidebar — mobile */}
      <Drawer variant="temporary" open={mobileOpen} onClose={() => setMobileOpen(false)} sx={{ display: { xs: 'block', md: 'none' }, '& .MuiDrawer-paper': { width: DRAWER_WIDTH } }}>
        {drawerContent}
      </Drawer>

      {/* Main content */}
      <Box component="main" sx={{ flex: 1, pt: '68px', minHeight: '100vh', background: themeMode === 'dark' ? '#0b0f1a' : 'linear-gradient(180deg, #eef2ff 0%, #f8fafc 100%)' }}>
        <Container maxWidth="xl" sx={{ py: 3 }}>
          <PageComponent />
        </Container>
      </Box>

      <NotificationsMenu anchorEl={notifAnchor} onClose={() => setNotifAnchor(null)} />
    </Box>
  );
}

export default function App() {
  const { auth } = useApp();
  const [authView, setAuthView] = useState('login');
  const [page, setPage] = useState('dashboard');

  if (!auth) {
    return authView === 'login'
      ? <LoginPage onSwitchToRegister={() => setAuthView('register')} />
      : <RegisterPage onSwitchToLogin={() => setAuthView('login')} />;
  }

  return <AppShell page={page} setPage={setPage} />;
}
