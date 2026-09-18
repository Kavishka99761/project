import axios from 'axios';

const BASE_URL = (typeof window !== 'undefined' && window.__ACADEALERT_API_URL__)
  || 'http://localhost:8000/api';

const client = axios.create({
  baseURL: BASE_URL,
  headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
  withCredentials: false,
});

// ─── Request interceptor: attach Bearer token ────────────────────────────────
client.interceptors.request.use((config) => {
  const token = localStorage.getItem('acadealert-token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// ─── Response interceptor: handle 401 → clear session ───────────────────────
client.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('acadealert-token');
      localStorage.removeItem('acadealert-user');
      window.dispatchEvent(new CustomEvent('acadealert:logout'));
    }
    return Promise.reject(err);
  }
);

// ─── Auth ────────────────────────────────────────────────────────────────────
export const authApi = {
  register: (data)  => client.post('/register', data),
  login:    (data)  => client.post('/login', data),
  logout:   ()      => client.post('/logout'),
  me:       ()      => client.get('/me'),
  updateProfile: (data) => client.put('/me', data),
  registerFcmToken: (token) => client.put('/me/firebase-token', { token }),
};

// ─── Dashboard ───────────────────────────────────────────────────────────────
export const dashboardApi = {
  get: () => client.get('/dashboard'),
};

// ─── Modules ─────────────────────────────────────────────────────────────────
export const modulesApi = {
  list:    ()       => client.get('/modules'),
  create:  (data)   => client.post('/modules', data),
  update:  (id, d)  => client.put(`/modules/${id}`, d),
  destroy: (id)     => client.delete(`/modules/${id}`),
};

// ─── Documents ───────────────────────────────────────────────────────────────
export const documentsApi = {
  list:     (params) => client.get('/documents', { params }),
  show:     (id)     => client.get(`/documents/${id}`),
  upload:   (formData) => client.post('/documents', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
  update:   (id, data) => client.put(`/documents/${id}`, data),
  destroy:  (id)       => client.delete(`/documents/${id}`),
  keywords: (id)       => client.get(`/documents/${id}/keywords`),
};

// ─── Summaries ───────────────────────────────────────────────────────────────
export const summariesApi = {
  list:     (params)      => client.get('/summaries', { params }),
  generate: (docId, data) => client.post(`/documents/${docId}/summaries`, data),
  show:     (id)          => client.get(`/summaries/${id}`),
  destroy:  (id)          => client.delete(`/summaries/${id}`),
};

// ─── Study Sessions ──────────────────────────────────────────────────────────
export const studyApi = {
  current:        ()             => client.get('/study/current'),
  analytics:      ()             => client.get('/study/analytics'),
  sessions:       (params)       => client.get('/study/sessions', { params }),
  start:          (data)         => client.post('/study/sessions', data),
  update:         (id, data)     => client.patch(`/study/sessions/${id}`, data),
  logEngagement:  (id, data)     => client.post(`/study/sessions/${id}/engagement`, data),
};

// ─── Assignments ─────────────────────────────────────────────────────────────
export const assignmentsApi = {
  list:           (params)   => client.get('/assignments', { params }),
  show:           (id)       => client.get(`/assignments/${id}`),
  create:         (data)     => client.post('/assignments', data),
  update:         (id, data) => client.put(`/assignments/${id}`, data),
  destroy:        (id)       => client.delete(`/assignments/${id}`),
  rank:           ()         => client.get('/assignments/rank'),
  recommendation: ()         => client.get('/assignments/recommendation'),
  whatIf:         (data)     => client.post('/assignments/whatif', data),
};

// ─── Academic Assistant ──────────────────────────────────────────────────────
export const assistantApi = {
  knowledge:         ()             => client.get('/assistant/knowledge'),
  addKnowledge:      (data)         => client.post('/assistant/knowledge', data),
  deleteKnowledge:   (id)           => client.delete(`/assistant/knowledge/${id}`),
  conversations:     ()             => client.get('/assistant/conversations'),
  messages:          (convId)       => client.get(`/assistant/conversations/${convId}/messages`),
  chat:              (data)         => client.post('/assistant/chat', data),
  dates:             ()             => client.get('/assistant/dates'),
  createDate:        (data)         => client.post('/assistant/dates', data),
  updateDate:        (id, data)     => client.put(`/assistant/dates/${id}`, data),
  deleteDate:        (id)           => client.delete(`/assistant/dates/${id}`),
};

// ─── Notifications ───────────────────────────────────────────────────────────
export const notificationsApi = {
  list:        ()   => client.get('/notifications'),
  markRead:    (id) => client.patch(`/notifications/${id}`),
  markAllRead: ()   => client.post('/notifications/read-all'),
  destroy:     (id) => client.delete(`/notifications/${id}`),
};

// ─── Calendar ────────────────────────────────────────────────────────────────
export const calendarApi = {
  get: () => client.get('/calendar'),
};

// ─── Search ──────────────────────────────────────────────────────────────────
export const searchApi = {
  query: (q, limit = 8) => client.get('/search', { params: { q, limit } }),
};

// ─── Export / Backup ─────────────────────────────────────────────────────────
export const exportApi = {
  download:        () => client.get('/export', { responseType: 'blob' }),
  backupFirebase:  () => client.post('/backup/firebase'),
};

export default client;
