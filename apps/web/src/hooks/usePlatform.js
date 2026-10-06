import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useAuth } from '@/context/AuthContext';
import { api, get } from '@/lib/api';

// Shared Common-Platform queries used across every feature module.

/** Enum options (labels, colours, icons) — single source of truth: the API. */
export function useOptions() {
  return useQuery({ queryKey: ['meta', 'options'], queryFn: () => get('/meta/options'), staleTime: Infinity });
}

export function useModules(params) {
  return useQuery({ queryKey: ['modules', params ?? {}], queryFn: () => get('/modules', params), staleTime: 60_000 });
}

export function useSettings() {
  return useQuery({ queryKey: ['settings'], queryFn: () => get('/settings'), staleTime: 60_000 });
}

export function useUpdateSettings() {
  const queryClient = useQueryClient();
  const { setUser } = useAuth();

  return useMutation({
    mutationFn: (payload) => api.put('/settings', payload).then((r) => r.data),
    onSuccess: (settings) => {
      queryClient.setQueryData(['settings'], settings);
      setUser((user) => (user ? { ...user, settings } : user));
      queryClient.invalidateQueries({ queryKey: ['assignments'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });
}

/** Look up an option ({value,label,color,icon}) by value. */
export function optionFor(options, group, value) {
  return options?.[group]?.find((option) => option.value === value) ?? null;
}
