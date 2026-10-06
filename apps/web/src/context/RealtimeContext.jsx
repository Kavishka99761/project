import { useQuery, useQueryClient } from '@tanstack/react-query';
import { createContext, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { get } from '@/lib/api';
import { connectRealtime, disconnectRealtime, watchActivity, watchLiveStudy, watchNotifications } from '@/lib/firebase';

const RealtimeContext = createContext(null);

/**
 * Realtime notifications, live activity feed and the live study timer.
 *
 * Primary channel: Firestore listeners on the student's own subtree.
 * Fallback: polling the notifications API every 30 s (which also lets the
 * server dispatch due reminders when no scheduler is running).
 * New notifications raise a toast and — with permission — a desktop
 * notification.
 */
export function RealtimeProvider({ children }) {
  const { user, isAuthenticated } = useAuth();
  const queryClient = useQueryClient();
  const toast = useToast();
  const navigate = useNavigate();
  const [status, setStatus] = useState('offline');
  const [liveStudy, setLiveStudy] = useState(null);
  const [activity, setActivity] = useState([]);
  const seen = useRef(new Set());
  const primed = useRef(false);

  const notifications = useQuery({
    queryKey: ['notifications', 'feed'],
    queryFn: () => get('/notifications', { per_page: 10 }),
    enabled: isAuthenticated,
    refetchInterval: status === 'live' ? 120_000 : 30_000,
  });

  const announce = (item) => {
    if (!item || seen.current.has(item.id)) return;
    seen.current.add(item.id);
    if (!primed.current) return;
    const kind = item.type === 'danger' ? 'error' : item.type === 'reminder' ? 'reminder' : item.type === 'warning' ? 'warning' : item.type === 'success' ? 'success' : 'info';
    toast[kind](item.title, item.message, item.action_url ? { action: { label: 'Open', onClick: () => navigate(item.action_url) } } : undefined);

    const settings = user?.settings;
    if (settings?.notify_browser && 'Notification' in window && Notification.permission === 'granted' && document.visibilityState !== 'visible') {
      try {
        new Notification(item.title, { body: item.message ?? '', icon: '/favicon.svg', tag: `edusmart-${item.id}` });
      } catch {
        /* some browsers only allow notifications from a service worker */
      }
    }
  };

  // Polling path: detect notifications we have not seen yet.
  useEffect(() => {
    const items = notifications.data?.data;
    if (!items) return;
    items.slice().reverse().forEach((item) => {
      if (!item.read) announce(item);
      else seen.current.add(item.id);
    });
    primed.current = true;
  }, [notifications.data]); // eslint-disable-line react-hooks/exhaustive-deps

  // Firestore path.
  useEffect(() => {
    if (!isAuthenticated) {
      disconnectRealtime();
      setStatus('offline');
      return undefined;
    }
    let unsubscribers = [];
    let cancelled = false;

    (async () => {
      try {
        setStatus('connecting');
        const config = await get('/firebase/token');
        if (!config.enabled) {
          setStatus('disabled');
          return;
        }
        await connectRealtime(config);
        if (cancelled) return;
        setStatus('live');
        unsubscribers = [
          watchNotifications((snapshot) => {
            snapshot.docChanges().forEach((change) => {
              if (change.type === 'added') announce(change.doc.data());
            });
            queryClient.invalidateQueries({ queryKey: ['notifications'] });
          }, () => setStatus('offline')),
          watchActivity((items) => setActivity(items), () => {}),
          watchLiveStudy((state) => {
            setLiveStudy(state && state.status !== 'idle' ? state : null);
            queryClient.invalidateQueries({ queryKey: ['study', 'active'] });
          }, () => {}),
        ];
      } catch {
        if (!cancelled) setStatus('offline');
      }
    })();

    return () => {
      cancelled = true;
      unsubscribers.forEach((unsubscribe) => unsubscribe?.());
    };
  }, [isAuthenticated, user?.id]); // eslint-disable-line react-hooks/exhaustive-deps

  const value = useMemo(() => ({
    status,
    liveStudy,
    activity,
    unreadCount: notifications.data?.unread_count ?? 0,
    latest: notifications.data?.data ?? [],
    refreshNotifications: () => notifications.refetch(),
  }), [status, liveStudy, activity, notifications]);

  return <RealtimeContext.Provider value={value}>{children}</RealtimeContext.Provider>;
}

export function useRealtime() {
  const context = useContext(RealtimeContext);
  if (!context) throw new Error('useRealtime must be used inside <RealtimeProvider>');
  return context;
}
