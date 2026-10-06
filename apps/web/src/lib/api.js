import axios from 'axios';
import { API_URL } from '@/config/env';

// Axios instance for the EDU-SMART API (Sanctum bearer tokens).

const TOKEN_KEY = 'edusmart.token';

export const tokenStore = {
  get: () => {
    try {
      return localStorage.getItem(TOKEN_KEY);
    } catch {
      return null;
    }
  },
  set: (token) => {
    try {
      if (token) localStorage.setItem(TOKEN_KEY, token);
      else localStorage.removeItem(TOKEN_KEY);
    } catch {
      /* storage unavailable (private mode) */
    }
  },
};

export const api = axios.create({
  baseURL: API_URL,
  timeout: 120000,
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = tokenStore.get();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    const url = error.config?.url ?? '';
    if (status === 401 && !url.includes('/auth/login')) {
      window.dispatchEvent(new CustomEvent('edusmart:unauthorized'));
    }
    return Promise.reject(error);
  },
);

/** Human-readable message from any API/network error. */
export function errorMessage(error, fallback = 'Something went wrong. Please try again.') {
  if (!error) return fallback;
  if (error.code === 'ECONNABORTED') return 'The server took too long to respond.';
  if (!error.response) return 'Cannot reach the EDU-SMART API. Is the backend running?';
  const data = error.response.data;
  if (data?.errors) {
    const first = Object.values(data.errors)[0];
    if (Array.isArray(first) && first[0]) return first[0];
  }
  return data?.message || fallback;
}

/** Field errors for forms: { field: 'message' }. */
export function fieldErrors(error) {
  const errors = error?.response?.data?.errors ?? {};
  return Object.fromEntries(Object.entries(errors).map(([key, messages]) => [key, messages[0]]));
}

/** Unwrap data from a GET. */
export async function get(url, params) {
  const { data } = await api.get(url, { params });
  return data;
}

function filenameFrom(disposition, fallback) {
  if (!disposition) return fallback;
  const star = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
  if (star) return decodeURIComponent(star[1]);
  const plain = /filename="?([^";]+)"?/i.exec(disposition);
  return plain ? plain[1] : fallback;
}

export function saveBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 2000);
}

/** Download a file from an authenticated endpoint. */
export async function download(url, params, fallbackName = 'download') {
  const response = await api.get(url, { params, responseType: 'blob' });
  const name = filenameFrom(response.headers['content-disposition'], fallbackName);
  saveBlob(response.data, name);
  return { name, rows: Number(response.headers['x-export-rows'] ?? 0) };
}

/** Fetch an authenticated file as an object URL (inline viewers). */
export async function objectUrl(url) {
  const response = await api.get(url, { responseType: 'blob' });
  return URL.createObjectURL(response.data);
}
