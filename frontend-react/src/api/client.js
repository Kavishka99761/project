import axios from 'axios';

const TOKEN_KEY = 'edu_smart_token';

export function getToken() {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token);
  else localStorage.removeItem(TOKEN_KEY);
}

const client = axios.create({
  baseURL: process.env.API_BASE_URL || 'http://localhost:8000/api',
  headers: { Accept: 'application/json' },
});

client.interceptors.request.use((config) => {
  const token = getToken();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// Broadcast auth failures so any part of the app (AuthContext) can react
// without every call site needing its own try/catch for 401s.
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      setToken(null);
      window.dispatchEvent(new CustomEvent('edu-smart:unauthorized'));
    }
    return Promise.reject(error);
  }
);

export default client;
