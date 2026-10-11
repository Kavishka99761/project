import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { get } from '@/lib/api';

// Common Platform — audit trail: API requests (activity_logs) and the
// row-level database changes written by the SQL Server triggers.

const clean = (params) => Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null));

export const useDataChanges = (params) => useQuery({
  queryKey: ['audit', 'changes', params],
  queryFn: () => get('/data-changes', clean(params)),
  placeholderData: keepPreviousData,
});

export const useDataChangeStats = () => useQuery({ queryKey: ['audit', 'changes', 'stats'], queryFn: () => get('/data-changes/stats') });

export const useDataChange = (id) => useQuery({ queryKey: ['audit', 'change', id], queryFn: () => get(`/data-changes/${id}`), enabled: Boolean(id) });

export const useRecordHistory = (table, recordId) => useQuery({
  queryKey: ['audit', 'history', table, recordId],
  queryFn: () => get(`/data-changes/history/${table}/${encodeURIComponent(recordId)}`),
  enabled: Boolean(table && recordId),
});

export const useActivity = (params) => useQuery({
  queryKey: ['audit', 'activity', params],
  queryFn: () => get('/activity', clean(params)),
  placeholderData: keepPreviousData,
});
