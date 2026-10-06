/**
 * EDU-SMART API client.
 *
 * Design goal: the app must ALWAYS be usable. Every call tries the live Laravel
 * API first; if the backend is unreachable (not started, different LAN, no PHP on
 * the machine) the promise still resolves with `null` instead of throwing, and the
 * caller falls back to the bundled demo data. Real API errors (4xx/5xx with a
 * payload) are the only case that throws, so messages can be shown to the user.
 */
import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_BASE, NETWORK_TIMEOUT_MS } from './config';

const TOKEN_KEY = 'edusmart.token';
const USER_KEY = 'edusmart.user';

/* ------------------------------------------------------------------ session */

export async function getToken() {
  try {
    return await AsyncStorage.getItem(TOKEN_KEY);
  } catch (e) {
    return null;
  }
}

export async function setSession(token, user) {
  try {
    if (token) await AsyncStorage.setItem(TOKEN_KEY, token);
    if (user) await AsyncStorage.setItem(USER_KEY, JSON.stringify(user));
  } catch (e) {
    /* storage unavailable — session stays in memory for this run */
  }
}

export async function getStoredUser() {
  try {
    const raw = await AsyncStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch (e) {
    return null;
  }
}

export async function clearSession() {
  try {
    await AsyncStorage.multiRemove([TOKEN_KEY, USER_KEY]);
  } catch (e) {
    /* ignore */
  }
}

/* ----------------------------------------------------------------- fetching */

/** fetch() with a timeout, so a hung dev server never blocks the UI forever. */
function fetchWithTimeout(url, options = {}, ms = NETWORK_TIMEOUT_MS) {
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('timeout')), ms);
    fetch(url, options).then(
      (res) => {
        clearTimeout(timer);
        resolve(res);
      },
      (err) => {
        clearTimeout(timer);
        reject(err);
      },
    );
  });
}

/**
 * Core request helper.
 * @param {string} path   e.g. '/dashboard' (leading slash required)
 * @param {object} opts   { method, body, timeout }
 * @returns {Promise<any>} parsed payload, or `null` when the API is unreachable
 */
export async function request(path, { method = 'GET', body, timeout } = {}) {
  const token = await getToken();
  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (token) headers.Authorization = `Bearer ${token}`;

  let res;
  try {
    res = await fetchWithTimeout(
      `${API_BASE}${path}`,
      { method, headers, body: body !== undefined ? JSON.stringify(body) : undefined },
      timeout,
    );
  } catch (e) {
    return null; // offline / timeout / DNS — caller falls back to demo data
  }

  let json = null;
  try {
    const text = await res.text();
    json = text ? JSON.parse(text) : null;
  } catch (e) {
    return null;
  }

  if (res.status === 401) {
    await clearSession();
    return null;
  }

  if (!res.ok) {
    const err = new Error(json?.message || `Request failed (${res.status})`);
    err.status = res.status;
    err.errors = json?.errors;
    throw err; // real API error — surface the message
  }

  // Unwrap Laravel resources ({ data: ... }) for convenience.
  return json && typeof json === 'object' && Object.prototype.hasOwnProperty.call(json, 'data')
    ? json.data
    : json;
}

/** True when the backend answered a trivial request — used for the online badge. */
export async function ping() {
  try {
    const res = await fetchWithTimeout(`${API_BASE}/health`, {}, 2500);
    return res.ok;
  } catch (e) {
    return false;
  }
}

/* ---------------------------------------------------------------- endpoints */

export const api = {
  health: () => ping(),

  // Common platform layer
  login: (email, password) => request('/login', { method: 'POST', body: { email, password } }),
  register: (payload) => request('/register', { method: 'POST', body: payload }),
  me: () => request('/me'),
  updateMe: (payload) => request('/me', { method: 'PUT', body: payload }),
  logout: () => request('/logout', { method: 'POST' }),
  dashboard: () => request('/dashboard'),
  calendar: () => request('/calendar'),
  modules: () => request('/modules'),
  notifications: () => request('/notifications'),

  // Bethmi — learning materials
  documents: () => request('/documents'),
  documentKeywords: (id) => request(`/documents/${id}/keywords`),
  summaries: () => request('/summaries'),

  // Pasindu — study & engagement
  studyAnalytics: (params = {}) => request(`/study/analytics${qs(params)}`),
  studySessions: (params = {}) => request(`/study/sessions${qs(params)}`),
  studyCurrent: () => request('/study/current'),
  startSession: (payload) => request('/study/sessions', { method: 'POST', body: payload }),
  // PATCH /study/sessions/{id} — body: { status: active|paused|completed, actual_minutes? }
  updateSession: (id, payload) => request(`/study/sessions/${id}`, { method: 'PATCH', body: payload }),
  pauseSession: (id) => request(`/study/sessions/${id}`, { method: 'PATCH', body: { status: 'paused' } }),
  resumeSession: (id) => request(`/study/sessions/${id}`, { method: 'PATCH', body: { status: 'active' } }),
  stopSession: (id, minutes) =>
    request(`/study/sessions/${id}`, { method: 'PATCH', body: { status: 'completed', actual_minutes: minutes } }),
  logEngagement: (id, payload) => request(`/study/sessions/${id}/engagement`, { method: 'POST', body: payload }),

  // Kavishka — academic assistant
  knowledge: () => request('/assistant/knowledge'),
  storeKnowledge: (payload) => request('/assistant/knowledge', { method: 'POST', body: payload }),
  conversations: () => request('/assistant/conversations'),
  messages: (conversationId) => request(`/assistant/conversations/${conversationId}/messages`),
  chat: (conversationId, message) =>
    request('/assistant/chat', { method: 'POST', body: { conversation_id: conversationId, message } }),
  academicDates: () => request('/assistant/dates'),

  // Jithmi — assignments & risk
  assignments: () => request('/assignments'),
  assignmentRank: () => request('/assignments/rank'),
  assignmentRecommendation: () => request('/assignments/recommendation'),
  assignmentWhatIf: (payload) => request('/assignments/whatif', { method: 'POST', body: payload }),
};

/** Build a query string, skipping null/undefined/empty values. */
function qs(params = {}) {
  const search = Object.entries(params)
    .filter(([, v]) => v !== null && v !== undefined && v !== '')
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
    .join('&');
  return search ? `?${search}` : '';
}

export default api;
