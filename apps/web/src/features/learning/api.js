import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, get } from '@/lib/api';

// BETHMI — Learning materials data layer.

export const learningKeys = {
  all: ['learning'],
  dashboard: ['learning', 'dashboard'],
  documents: (params) => ['learning', 'documents', params],
  document: (id) => ['learning', 'document', String(id)],
  analysis: (id) => ['learning', 'analysis', String(id)],
  topics: ['learning', 'topics'],
  summaries: (params) => ['learning', 'summaries', params],
  summary: (id) => ['learning', 'summary', String(id)],
  aids: (params) => ['learning', 'aids', params],
  aid: (id) => ['learning', 'aid', String(id)],
};

export const useLearningDashboard = () => useQuery({ queryKey: learningKeys.dashboard, queryFn: () => get('/learning/dashboard') });

export const useDocuments = (params) => useQuery({
  queryKey: learningKeys.documents(params),
  queryFn: () => get('/documents', params),
  placeholderData: keepPreviousData,
});

export const useDocument = (id) => useQuery({ queryKey: learningKeys.document(id), queryFn: () => get(`/documents/${id}`), enabled: Boolean(id) });

export const useDocumentAnalysis = (id, enabled = true) => useQuery({
  queryKey: learningKeys.analysis(id),
  queryFn: () => get(`/documents/${id}/analysis`),
  enabled: Boolean(id) && enabled,
  staleTime: 5 * 60_000,
});

export const useTopics = () => useQuery({ queryKey: learningKeys.topics, queryFn: () => get('/documents/topics'), staleTime: 60_000 });

export const useSummaries = (params) => useQuery({ queryKey: learningKeys.summaries(params), queryFn: () => get('/summaries', params), placeholderData: keepPreviousData });

export const useSummary = (id) => useQuery({ queryKey: learningKeys.summary(id), queryFn: () => get(`/summaries/${id}`), enabled: Boolean(id) });

export const useStudyAids = (params) => useQuery({ queryKey: learningKeys.aids(params), queryFn: () => get('/study-aids', params) });

export const useStudyAid = (id) => useQuery({ queryKey: learningKeys.aid(id), queryFn: () => get(`/study-aids/${id}`), enabled: Boolean(id) });

function useInvalidate() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: learningKeys.all });
    queryClient.invalidateQueries({ queryKey: ['dashboard'] });
    queryClient.invalidateQueries({ queryKey: ['notifications'] });
  };
}

export function useUploadDocument() {
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: ({ file, onProgress, ...fields }) => {
      const form = new FormData();
      form.append('file', file);
      Object.entries(fields).forEach(([key, value]) => value !== undefined && value !== null && value !== '' && form.append(key, value));
      return api.post('/documents', form, {
        onUploadProgress: (event) => onProgress?.(event.total ? Math.round((event.loaded / event.total) * 100) : 0),
      }).then((r) => r.data);
    },
    onSuccess: invalidate,
  });
}

export function useCreateNote() {
  const invalidate = useInvalidate();
  return useMutation({ mutationFn: (payload) => api.post('/documents/notes', payload).then((r) => r.data), onSuccess: invalidate });
}

export function useUpdateDocument() {
  const invalidate = useInvalidate();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, ...payload }) => api.put(`/documents/${id}`, payload).then((r) => r.data),
    onSuccess: (document) => {
      queryClient.setQueryData(learningKeys.document(document.id), document);
      invalidate();
    },
  });
}

export function useDeleteDocument() {
  const invalidate = useInvalidate();
  return useMutation({ mutationFn: (id) => api.delete(`/documents/${id}`).then((r) => r.data), onSuccess: invalidate });
}

export function useReextract() {
  const invalidate = useInvalidate();
  return useMutation({ mutationFn: (id) => api.post(`/documents/${id}/extract`).then((r) => r.data), onSuccess: invalidate });
}

export function useGenerateSummary() {
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: ({ documentId, ...payload }) => api.post(`/documents/${documentId}/summaries`, payload).then((r) => r.data),
    onSuccess: (data) => data.saved && invalidate(),
  });
}

export function useUpdateSummary() {
  const invalidate = useInvalidate();
  return useMutation({ mutationFn: ({ id, ...payload }) => api.put(`/summaries/${id}`, payload).then((r) => r.data), onSuccess: invalidate });
}

export function useDeleteSummary() {
  const invalidate = useInvalidate();
  return useMutation({ mutationFn: (id) => api.delete(`/summaries/${id}`).then((r) => r.data), onSuccess: invalidate });
}

export function useGenerateStudyAid() {
  const invalidate = useInvalidate();
  return useMutation({ mutationFn: ({ documentId, type }) => api.post(`/documents/${documentId}/study-aids`, { type }).then((r) => r.data), onSuccess: invalidate });
}

export function useDeleteStudyAid() {
  const invalidate = useInvalidate();
  return useMutation({ mutationFn: (id) => api.delete(`/study-aids/${id}`).then((r) => r.data), onSuccess: invalidate });
}

export function useRecordAttempt() {
  return useMutation({ mutationFn: ({ id, score, total }) => api.post(`/study-aids/${id}/attempt`, { score, total }).then((r) => r.data) });
}
