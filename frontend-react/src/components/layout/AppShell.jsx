import React, { useEffect, useState } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import {
  AppBar,
  Avatar,
  Box,
  Divider,
  Drawer,
  IconButton,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Menu,
  MenuItem,
  Toolbar,
  Tooltip,
  Typography,
  useMediaQuery,
  useTheme,
} from '@mui/material';
import { AnimatePresence } from 'framer-motion';
import MenuRoundedIcon from '@mui/icons-material/MenuRounded';
import DashboardRoundedIcon from '@mui/icons-material/DashboardRounded';
import MenuBookRoundedIcon from '@mui/icons-material/MenuBookRounded';
import TimerRoundedIcon from '@mui/icons-material/TimerRounded';
import SmartToyRoundedIcon from '@mui/icons-material/SmartToyRounded';
import AssignmentRoundedIcon from '@mui/icons-material/AssignmentRounded';
import CalendarMonthRoundedIcon from '@mui/icons-material/CalendarMonthRounded';
import ViewModuleRoundedIcon from '@mui/icons-material/ViewModuleRounded';
import SettingsRoundedIcon from '@mui/icons-material/SettingsRounded';
import SearchRoundedIcon from '@mui/icons-material/SearchRounded';
import DarkModeRoundedIcon from '@mui/icons-material/DarkModeRounded';
import LightModeRoundedIcon from '@mui/icons-material/LightModeRounded';
import LogoutRoundedIcon from '@mui/icons-material/LogoutRounded';
import PersonRoundedIcon from '@mui/icons-material/PersonRounded';
import { useAuth } from '../../context/AuthContext';
import { useColorMode } from '../../context/ColorModeContext';
import { moduleColors, glassSx } from '../../theme';
import NotificationsMenu from '../NotificationsMenu';
import GlobalSearch from '../GlobalSearch';
import PageTransition from '../PageTransition';
import LiquidBackground from '../LiquidBackground';

const drawerWidth = 264;

const NAV_ITEMS = [
  { label: 'Dashboard', path: '/dashboard', icon: <DashboardRoundedIcon />, color: moduleColors.primary },
  { label: 'Learning Materials', path: '/learning', icon: <MenuBookRoundedIcon />, color: moduleColors.bethmi },
  { label: 'Study Session', path: '/study', icon: <TimerRoundedIcon />, color: moduleColors.pasindu },
  { label: 'AI Assistant', path: '/assistant', icon: <SmartToyRoundedIcon />, color: moduleColors.kavishka },
  { label: 'Assignments', path: '/assignments', icon: <AssignmentRoundedIcon />, color: moduleColors.jithmi },
  { label: 'Calendar', path: '/calendar', icon: <CalendarMonthRoundedIcon />, color: moduleColors.primary },
  { label: 'Modules', path: '/modules', icon: <ViewModuleRoundedIcon />, color: moduleColors.primary },
  { label: 'Settings', path: '/settings', icon: <SettingsRoundedIcon />, color: moduleColors.primary },
];

export default function AppShell() {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('md'));
  const [mobileOpen, setMobileOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [anchorEl, setAnchorEl] = useState(null);
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout } = useAuth();
  const { mode, toggleColorMode } = useColorMode();

  useEffect(() => {
    const handler = (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setSearchOpen(true);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);

  const initials = (user?.name || '?')
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

  const drawerContent = (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <Toolbar sx={{ gap: 1.5 }}>
        <Box
          sx={{
            width: 36,
            height: 36,
            borderRadius: 2,
            bgcolor: moduleColors.primary,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            color: '#fff',
            fontWeight: 800,
          }}
        >
          E
        </Box>
        <Typography variant="h6" fontWeight={800}>
          EDU-SMART
        </Typography>
      </Toolbar>
      <Divider />
      <List sx={{ flexGrow: 1, py: 1 }}>
        {NAV_ITEMS.map((item) => {
          const active = location.pathname.startsWith(item.path);
          return (
            <ListItemButton
              key={item.path}
              selected={active}
              onClick={() => {
                navigate(item.path);
                if (isMobile) setMobileOpen(false);
              }}
              sx={{
                mx: 1,
                mb: 0.5,
                borderRadius: 2,
                '&.Mui-selected': {
                  bgcolor: `${item.color}22`,
                  color: item.color,
                  '& .MuiListItemIcon-root': { color: item.color },
                },
              }}
            >
              <ListItemIcon sx={{ minWidth: 40 }}>{item.icon}</ListItemIcon>
              <ListItemText primary={item.label} primaryTypographyProps={{ fontWeight: active ? 700 : 500 }} />
            </ListItemButton>
          );
        })}
      </List>
      <Divider />
      <Box sx={{ p: 2 }}>
        <Typography variant="caption" color="text.secondary">
          Signed in as
        </Typography>
        <Typography variant="body2" fontWeight={700} noWrap>
          {user?.email}
        </Typography>
      </Box>
    </Box>
  );

  return (
    <Box
      sx={{
        display: 'flex',
        minHeight: '100vh',
        position: 'relative',
        background: (t) =>
          t.palette.mode === 'dark'
            ? 'linear-gradient(160deg, #0f1117 0%, #14161f 100%)'
            : 'linear-gradient(160deg, #eef1fb 0%, #f4f6fb 100%)',
      }}
    >
      <LiquidBackground />
      <AppBar
        position="fixed"
        color="inherit"
        elevation={0}
        sx={{
          width: { md: `calc(100% - ${drawerWidth}px)` },
          ml: { md: `${drawerWidth}px` },
          ...glassSx(theme.palette.mode),
          borderRadius: 0,
          borderTop: 'none',
          borderLeft: 'none',
          borderRight: 'none',
        }}
      >
        <Toolbar sx={{ gap: 1 }}>
          <IconButton edge="start" onClick={() => setMobileOpen(true)} sx={{ display: { md: 'none' } }}>
            <MenuRoundedIcon />
          </IconButton>
          <Box sx={{ flexGrow: 1 }} />
          <Tooltip title="Search (Ctrl+K)">
            <IconButton onClick={() => setSearchOpen(true)}>
              <SearchRoundedIcon />
            </IconButton>
          </Tooltip>
          <Tooltip title={mode === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}>
            <IconButton onClick={toggleColorMode}>
              {mode === 'dark' ? <LightModeRoundedIcon /> : <DarkModeRoundedIcon />}
            </IconButton>
          </Tooltip>
          <NotificationsMenu />
          <Tooltip title="Account">
            <IconButton onClick={(e) => setAnchorEl(e.currentTarget)} sx={{ ml: 0.5 }}>
              <Avatar
                src={user?.avatar_url || undefined}
                sx={{ width: 32, height: 32, bgcolor: moduleColors.primary, fontSize: 14 }}
              >
                {initials}
              </Avatar>
            </IconButton>
          </Tooltip>
          <Menu
            anchorEl={anchorEl}
            open={Boolean(anchorEl)}
            onClose={() => setAnchorEl(null)}
            PaperProps={{ sx: { ...glassSx(theme.palette.mode) } }}
          >
            <MenuItem
              onClick={() => {
                setAnchorEl(null);
                navigate('/profile');
              }}
            >
              <ListItemIcon>
                <PersonRoundedIcon fontSize="small" />
              </ListItemIcon>
              Profile
            </MenuItem>
            <MenuItem
              onClick={() => {
                setAnchorEl(null);
                navigate('/settings');
              }}
            >
              <ListItemIcon>
                <SettingsRoundedIcon fontSize="small" />
              </ListItemIcon>
              Settings
            </MenuItem>
            <Divider />
            <MenuItem
              onClick={async () => {
                setAnchorEl(null);
                await logout();
                navigate('/login');
              }}
            >
              <ListItemIcon>
                <LogoutRoundedIcon fontSize="small" />
              </ListItemIcon>
              Logout
            </MenuItem>
          </Menu>
        </Toolbar>
      </AppBar>

      <Box component="nav" sx={{ width: { md: drawerWidth }, flexShrink: { md: 0 } }}>
        <Drawer
          variant="temporary"
          open={mobileOpen}
          onClose={() => setMobileOpen(false)}
          ModalProps={{ keepMounted: true }}
          sx={{
            display: { xs: 'block', md: 'none' },
            '& .MuiDrawer-paper': { width: drawerWidth, ...glassSx(theme.palette.mode), border: 'none' },
          }}
        >
          {drawerContent}
        </Drawer>
        <Drawer
          variant="permanent"
          sx={{
            display: { xs: 'none', md: 'block' },
            '& .MuiDrawer-paper': { width: drawerWidth, ...glassSx(theme.palette.mode), borderRadius: 0 },
          }}
          open
        >
          {drawerContent}
        </Drawer>
      </Box>

      <Box
        component="main"
        sx={{ flexGrow: 1, p: { xs: 2, md: 4 }, width: { md: `calc(100% - ${drawerWidth}px)` }, position: 'relative', zIndex: 1 }}
      >
        <Toolbar />
        <AnimatePresence mode="wait">
          <PageTransition key={location.pathname}>
            <Outlet />
          </PageTransition>
        </AnimatePresence>
      </Box>

      <GlobalSearch open={searchOpen} onClose={() => setSearchOpen(false)} />
    </Box>
  );
}
