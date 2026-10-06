import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, get } from '@/lib/api';

// PASINDU — Study sessions & engagement data layer.

export const studyKeys = {
  all: ['study'],
  dashboard: ['study', 'dashboard'],
  active: ['study', 'active'],
  sessions: (params) => ['study', 'sessions', params],
  session: (id) => ['study', 'session', String(id)],
  analytics: (days) => ['study', 'analytics', days],
  engagement: (params) => ['study', 'engagement', params],
  plans: (params) => ['study', 'plans', params],
  reminders: ['study', 'reminders'],
};

export const useStudyDashboard = () => useQuery({ queryKey: studyKeys.dashboard, queryFn: () => get('/study/dashboard') });

export const useActiveSession = () => useQuery({
  queryKey: studyKeys.active,
  queryFn: () => get('/study/sessions/active'),
  refetchInterval: (query) => (query.state.data?.session ? 20_000 : false),
});

export const useSessions = (params) => useQuery({ queryKey: studyKeys.sessions(params), queryFn: () => get('/study/sessions', params), placeholderData: keepPreviousData });
export const useSession = (id) => useQuery({ queryKey: studyKeys.session(id), queryFn: () => get(`/study/sessions/${id}`), enabled: Boolean(id) });
export const useAnalytics = (days) => useQuery({ queryKey: studyKeys.analytics(days), queryFn: () => get('/study/analytics', { days }), placeholderData: keepPreviousData });
export const useEngagementHistory = (params) => useQuery({ queryKey: studyKeys.engagement(params), queryFn: () => get('/study/engagement', params) });
export const usePlans = (params) => useQuery({ queryKey: studyKeys.plans(params), queryFn: () => get('/study/plans', params) });
export const useReminders = () => useQuery({ queryKey: studyKeys.reminders, queryFn: () => get('/study/reminders') });

function useStudyMutation(fn, { invalidateAssignments = false } = {}) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (data) => {
      if (data?.id && data?.is_live !== undefined) {
        queryClient.setQueryData(studyKeys.active, (old) => ({ ...(old ?? {}), session: data.is_live ? data : null, server_time: data.server_time }));
      }
      queryClient.invalidateQueries({ queryKey: studyKeys.all });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      if (invalidateAssignments) queryClient.invalidateQueries({ queryKey: ['assignments'] });
    },
  });
}

export const useStartSession = () => useStudyMutation((payload) => api.post('/study/sessions', payload).then((r) => r.data));
export const usePauseSession = () => useStudyMutation((id) => api.post(`/study/sessions/${id}/pause`).then((r) => r.data));
export const useResumeSession = () => useStudyMutation((id) => api.post(`/study/sessions/${id}/resume`).then((r) => r.data));
export const useBreakSession = () => useStudyMutation(({ id, minutes }) => api.post(`/study/sessions/${id}/break`, { minutes }).then((r) => r.data));
export const useStopSession = () => useStudyMutation(({ id, ...reflection }) => api.post(`/study/sessions/${id}/stop`, reflection).then((r) => r.data), { invalidateAssignments: true });
export const useCancelSession = () => useStudyMutation((id) => api.post(`/study/sessions/${id}/cancel`).then((r) => r.data));
export const useUpdateSession = () => useStudyMutation(({ id, ...payload }) => api.put(`/study/sessions/${id}`, payload).then((r) => r.data));
export const useDeleteSession = () => useStudyMutation((id) => api.delete(`/study/sessions/${id}`).then((r) => r.data));

export function useLogEngagement() {
  return useMutation({ mutationFn: ({ id, ...payload }) => api.post(`/study/sessions/${id}/engagement`, payload).then((r) => r.data) });
}

export const useSavePlan = () => useStudyMutation(({ id, ...payload }) => (id ? api.put(`/study/plans/${id}`, payload) : api.post('/study/plans', payload)).then((r) => r.data));
export const useDeletePlan = () => useStudyMutation((id) => api.delete(`/study/plans/${id}`).then((r) => r.data));
export const useSaveReminder = () => useStudyMutation(({ id, ...payload }) => (id ? api.put(`/study/reminders/${id}`, payload) : api.post('/study/reminders', payload)).then((r) => r.data));
export const useDeleteReminder = () => useStudyMutation((id) => api.delete(`/study/reminders/${id}`).then((r) => r.data));
