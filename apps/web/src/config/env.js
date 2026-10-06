// Runtime configuration (Vite env vars, with local-development defaults).

export const API_URL = (import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');

export const FIREBASE_WEB_CONFIG = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY || 'demo-key',
  authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN || undefined,
  appId: import.meta.env.VITE_FIREBASE_APP_ID || undefined,
};

export const APP_NAME = 'EDU-SMART';
export const DEMO_CREDENTIALS = { email: 'student@edusmart.lk', password: 'password' };
