import React, { useEffect, useState } from 'react';
import {
  Badge,
  Box,
  Button,
  Divider,
  IconButton,
  List,
  ListItemButton,
  ListItemText,
  Menu,
  Stack,
  Tooltip,
  Typography,
} from '@mui/material';
import NotificationsRoundedIcon from '@mui/icons-material/NotificationsRounded';
import { useNavigate } from 'react-router-dom';
import { useTheme } from '@mui/material/styles';
import { useSnackbar } from 'notistack';
import { listNotifications, markAllNotificationsRead, markNotificationRead } from '../api/common';
import { glassSx } from '../theme';

export default function NotificationsMenu() {
  const [anchorEl, setAnchorEl] = useState(null);
  const [items, setItems] = useState([]);
  const navigate = useNavigate();
  const theme = useTheme();
  const { enqueueSnackbar } = useSnackbar();
  const unreadCount = items.filter((n) => !n.is_read).length;

  const load = async () => {
    try {
      const data = await listNotifications();
      setItems(data.slice(0, 6));
    } catch (err) {
      // Silently ignore \u2014 the bell just shows nothing new.
    }
  };

  useEffect(() => {
    load();
    const interval = setInterval(load, 60000); // light polling, no websocket infra required
    return () => clearInterval(interval);
  }, []);

  const handleOpen = (e) => {
    setAnchorEl(e.currentTarget);
    load();
  };
  const handleClose = () => setAnchorEl(null);

  const handleItemClick = async (n) => {
    if (!n.is_read) {
      try {
        await markNotificationRead(n.id);
        load();
      } catch (err) {
        enqueueSnackbar('Could not update notification', { variant: 'error' });
      }
    }
  };

  const handleMarkAll = async () => {
    try {
      await markAllNotificationsRead();
      load();
      enqueueSnackbar('All notifications marked as read', { variant: 'success' });
    } catch (err) {
      enqueueSnackbar('Could not mark notifications as read', { variant: 'error' });
    }
  };

  return (
    <>
      <Tooltip title="Notifications">
        <IconButton onClick={handleOpen} color="inherit">
          <Badge badgeContent={unreadCount} color="error">
            <NotificationsRoundedIcon />
          </Badge>
        </IconButton>
      </Tooltip>
      <Menu
        anchorEl={anchorEl}
        open={Boolean(anchorEl)}
        onClose={handleClose}
        PaperProps={{ sx: { width: 340, maxHeight: 440, ...glassSx(theme.palette.mode) } }}
      >
        <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ px: 2, py: 1 }}>
          <Typography variant="subtitle1" fontWeight={700}>
            Notifications
          </Typography>
          <Button size="small" onClick={handleMarkAll} disabled={!unreadCount}>
            Mark all read
          </Button>
        </Stack>
        <Divider />
        <List dense sx={{ py: 0 }}>
          {items.length === 0 && (
            <Box sx={{ p: 2 }}>
              <Typography variant="body2" color="text.secondary">
                You're all caught up.
              </Typography>
            </Box>
          )}
          {items.map((n) => (
            <ListItemButton key={n.id} onClick={() => handleItemClick(n)} sx={{ opacity: n.is_read ? 0.6 : 1 }}>
              <ListItemText
                primary={n.title}
                secondary={n.message}
                primaryTypographyProps={{ fontWeight: n.is_read ? 400 : 700, noWrap: true }}
                secondaryTypographyProps={{ noWrap: true }}
              />
            </ListItemButton>
          ))}
        </List>
        <Divider />
        <Box sx={{ p: 1 }}>
          <Button
            fullWidth
            size="small"
            onClick={() => {
              handleClose();
              navigate('/notifications');
            }}
          >
            View all
          </Button>
        </Box>
      </Menu>
    </>
  );
}
