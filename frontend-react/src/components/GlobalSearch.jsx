import React, { useEffect, useMemo, useState, useCallback } from 'react';
import {
  Chip,
  Dialog,
  Divider,
  InputAdornment,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  TextField,
  Typography,
  Box,
} from '@mui/material';
import SearchRoundedIcon from '@mui/icons-material/SearchRounded';
import HistoryRoundedIcon from '@mui/icons-material/HistoryRounded';
import ViewModuleRoundedIcon from '@mui/icons-material/ViewModuleRounded';
import NotificationsRoundedIcon from '@mui/icons-material/NotificationsRounded';
import { useNavigate } from 'react-router-dom';
import { useTheme } from '@mui/material/styles';
import { listModules, listNotifications } from '../api/common';
import { useAuth } from '../context/AuthContext';
import { fetchRecentSearches, recordSearch } from '../firebase/searchHistory';
import { isFirebaseEnabled } from '../firebase/config';
import { glassSx } from '../theme';

export default function GlobalSearch({ open, onClose }) {
  const { user } = useAuth();
  const theme = useTheme();
  const navigate = useNavigate();
  const [term, setTerm] = useState('');
  const [modules, setModules] = useState([]);
  const [notifications, setNotifications] = useState([]);
  const [recent, setRecent] = useState([]);

  useEffect(() => {
    if (!open) return;
    setTerm('');
    Promise.all([listModules(), listNotifications()])
      .then(([m, n]) => {
        setModules(m);
        setNotifications(n);
      })
      .catch(() => {});
    if (user) fetchRecentSearches(user.id).then(setRecent);
  }, [open, user]);

  const results = useMemo(() => {
    const q = term.trim().toLowerCase();
    if (!q) return { modules: [], notifications: [] };
    return {
      modules: modules.filter(
        (m) => m.name?.toLowerCase().includes(q) || m.code?.toLowerCase().includes(q)
      ),
      notifications: notifications.filter(
        (n) => n.title?.toLowerCase().includes(q) || n.message?.toLowerCase().includes(q)
      ),
    };
  }, [term, modules, notifications]);

  const commit = useCallback(
    (t) => {
      if (user && t?.trim()) recordSearch(user.id, t);
    },
    [user]
  );

  const handleClose = () => {
    if (term.trim()) commit(term);
    onClose();
  };

  const goto = (path, t) => {
    commit(t);
    onClose();
    navigate(path);
  };

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      fullWidth
      maxWidth="sm"
      PaperProps={{ sx: { ...glassSx(theme.palette.mode), borderRadius: 3 } }}
    >
      <Box sx={{ p: 2 }}>
        <TextField
          autoFocus
          fullWidth
          placeholder="Search modules, notifications\u2026"
          value={term}
          onChange={(e) => setTerm(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') commit(term);
          }}
          InputProps={{
            startAdornment: (
              <InputAdornment position="start">
                <SearchRoundedIcon />
              </InputAdornment>
            ),
          }}
        />
      </Box>
      <Divider />
      <Box sx={{ maxHeight: 420, overflowY: 'auto' }}>
        {!term.trim() && (
          <Box sx={{ px: 2, pt: 1.5 }}>
            <Typography variant="caption" color="text.secondary">
              {isFirebaseEnabled() ? 'Recent searches (synced via Firebase)' : 'Recent searches'}
            </Typography>
            {recent.length === 0 && (
              <Typography variant="body2" color="text.secondary" sx={{ py: 1 }}>
                Start typing to search across your modules and notifications.
              </Typography>
            )}
            <List dense>
              {recent.map((r) => (
                <ListItemButton key={r} onClick={() => setTerm(r)}>
                  <ListItemIcon sx={{ minWidth: 32 }}>
                    <HistoryRoundedIcon fontSize="small" />
                  </ListItemIcon>
                  <ListItemText primary={r} />
                </ListItemButton>
              ))}
            </List>
          </Box>
        )}

        {term.trim() && (
          <>
            <Typography variant="caption" color="text.secondary" sx={{ px: 2 }}>
              Modules
            </Typography>
            <List dense>
              {results.modules.length === 0 && (
                <Typography variant="body2" color="text.secondary" sx={{ px: 2, pb: 1 }}>
                  No matching modules.
                </Typography>
              )}
              {results.modules.map((m) => (
                <ListItemButton key={`m-${m.id}`} onClick={() => goto('/modules', term)}>
                  <ListItemIcon sx={{ minWidth: 32 }}>
                    <ViewModuleRoundedIcon fontSize="small" />
                  </ListItemIcon>
                  <ListItemText primary={m.name} secondary={m.code} />
                  <Chip size="small" label={m.color} />
                </ListItemButton>
              ))}
            </List>
            <Divider />
            <Typography variant="caption" color="text.secondary" sx={{ px: 2 }}>
              Notifications
            </Typography>
            <List dense>
              {results.notifications.length === 0 && (
                <Typography variant="body2" color="text.secondary" sx={{ px: 2, pb: 1 }}>
                  No matching notifications.
                </Typography>
              )}
              {results.notifications.map((n) => (
                <ListItemButton key={`n-${n.id}`} onClick={() => goto('/notifications', term)}>
                  <ListItemIcon sx={{ minWidth: 32 }}>
                    <NotificationsRoundedIcon fontSize="small" />
                  </ListItemIcon>
                  <ListItemText primary={n.title} secondary={n.message} />
                </ListItemButton>
              ))}
            </List>
          </>
        )}
      </Box>
    </Dialog>
  );
}
