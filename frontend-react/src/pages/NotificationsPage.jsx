import React, { useEffect, useState } from 'react';
import {
  Box,
  Button,
  Card,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  IconButton,
  List,
  ListItem,
  ListItemText,
  Stack,
  Tab,
  Tabs,
  Typography,
} from '@mui/material';
import { motion, AnimatePresence } from 'framer-motion';
import DeleteRoundedIcon from '@mui/icons-material/DeleteRounded';
import DoneAllRoundedIcon from '@mui/icons-material/DoneAllRounded';
import { useSnackbar } from 'notistack';
import { useTheme } from '@mui/material/styles';
import {
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  deleteNotification,
} from '../api/common';
import { glassSx } from '../theme';

const SOURCE_LABEL = {
  bethmi: 'Learning Materials',
  pasindu: 'Study Session',
  kavishka: 'AI Assistant',
  jithmi: 'Assignments',
  common: 'Common',
};

export default function NotificationsPage() {
  const [tab, setTab] = useState(0);
  const [items, setItems] = useState([]);
  const [confirmDelete, setConfirmDelete] = useState(null);
  const { enqueueSnackbar } = useSnackbar();
  const theme = useTheme();

  const load = () => listNotifications(tab === 1).then(setItems).catch(() => {});
  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tab]);

  const handleRead = async (id) => {
    try {
      await markNotificationRead(id);
      load();
    } catch (err) {
      enqueueSnackbar('Could not update notification.', { variant: 'error' });
    }
  };
  const handleDelete = async () => {
    if (!confirmDelete) return;
    try {
      await deleteNotification(confirmDelete.id);
      setConfirmDelete(null);
      load();
      enqueueSnackbar('Notification deleted.', { variant: 'success' });
    } catch (err) {
      enqueueSnackbar('Could not delete notification.', { variant: 'error' });
    }
  };
  const handleMarkAll = async () => {
    try {
      await markAllNotificationsRead();
      load();
      enqueueSnackbar('All notifications marked as read.', { variant: 'success' });
    } catch (err) {
      enqueueSnackbar('Could not mark notifications as read.', { variant: 'error' });
    }
  };

  return (
    <Box>
      <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 2 }}>
        <Typography variant="h4" fontWeight={800}>
          Notifications
        </Typography>
        <Button startIcon={<DoneAllRoundedIcon />} onClick={handleMarkAll}>
          Mark all read
        </Button>
      </Stack>

      <Tabs value={tab} onChange={(e, v) => setTab(v)} sx={{ mb: 2 }}>
        <Tab label="All" />
        <Tab label="Unread" />
      </Tabs>

      <Card sx={{ ...glassSx(theme.palette.mode) }}>
        <List sx={{ py: 0 }}>
          <AnimatePresence>
            {items.map((n, idx) => (
              <React.Fragment key={n.id}>
                <ListItem
                  component={motion.div}
                  initial={{ opacity: 0, x: -8 }}
                  animate={{ opacity: 1, x: 0 }}
                  exit={{ opacity: 0, x: 8 }}
                  sx={{ opacity: n.is_read ? 0.6 : 1 }}
                  secondaryAction={
                    <Stack direction="row" spacing={0.5}>
                      {!n.is_read && (
                        <IconButton size="small" onClick={() => handleRead(n.id)}>
                          <DoneAllRoundedIcon fontSize="small" />
                        </IconButton>
                      )}
                      <IconButton size="small" onClick={() => setConfirmDelete(n)}>
                        <DeleteRoundedIcon fontSize="small" />
                      </IconButton>
                    </Stack>
                  }
                >
                  <ListItemText
                    primary={
                      <Stack direction="row" spacing={1} alignItems="center">
                        <Typography fontWeight={n.is_read ? 500 : 700}>{n.title}</Typography>
                        <Chip size="small" label={SOURCE_LABEL[n.module_source] || n.module_source} />
                      </Stack>
                    }
                    secondary={n.message}
                  />
                </ListItem>
                {idx < items.length - 1 && <Divider component="li" />}
              </React.Fragment>
            ))}
          </AnimatePresence>
          {items.length === 0 && (
            <Box sx={{ p: 4, textAlign: 'center' }}>
              <Typography color="text.secondary">Nothing here.</Typography>
            </Box>
          )}
        </List>
      </Card>

      <Dialog open={Boolean(confirmDelete)} onClose={() => setConfirmDelete(null)} maxWidth="xs" fullWidth>
        <DialogTitle>Delete notification?</DialogTitle>
        <DialogContent>
          <Typography variant="body2" color="text.secondary">
            This notification will be permanently removed.
          </Typography>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2 }}>
          <Button onClick={() => setConfirmDelete(null)}>Cancel</Button>
          <Button color="error" variant="contained" onClick={handleDelete}>
            Delete
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
