import { QueryClient } from '@tanstack/react-query';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      gcTime: 10 * 60_000,
      retry: (failureCount, error) => failureCount < 2 && ![401, 403, 404, 422].includes(error?.response?.status),
      refetchOnWindowFocus: true,
    },
    mutations: { retry: 0 },
  },
});
