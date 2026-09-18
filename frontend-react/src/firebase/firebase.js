// Firebase client SDK initialization
// Replace the config values below with your Firebase project's config
// (Firebase Console → Project Settings → Your apps → SDK setup and configuration)

import { initializeApp } from 'firebase/app';
import { getFirestore, collection, onSnapshot } from 'firebase/firestore';
import { getMessaging, getToken, onMessage } from 'firebase/messaging';

const getEnv = (viteKey, reactKey) => {
  if (typeof window !== 'undefined' && window.__ENV__?.[viteKey]) return window.__ENV__[viteKey];
  if (typeof process !== 'undefined' && process?.env?.[reactKey]) return process.env[reactKey];
  try {
    if (typeof import.meta !== 'undefined' && import.meta?.env?.[viteKey]) return import.meta.env[viteKey];
  } catch {}
  return '';
};

const firebaseConfig = {
  apiKey:            getEnv('VITE_FIREBASE_API_KEY', 'REACT_APP_FIREBASE_API_KEY'),
  authDomain:        getEnv('VITE_FIREBASE_AUTH_DOMAIN', 'REACT_APP_FIREBASE_AUTH_DOMAIN'),
  projectId:         getEnv('VITE_FIREBASE_PROJECT_ID', 'REACT_APP_FIREBASE_PROJECT_ID'),
  storageBucket:     getEnv('VITE_FIREBASE_STORAGE_BUCKET', 'REACT_APP_FIREBASE_STORAGE_BUCKET'),
  messagingSenderId: getEnv('VITE_FIREBASE_MESSAGING_SENDER_ID', 'REACT_APP_FIREBASE_MESSAGING_SENDER_ID'),
  appId:             getEnv('VITE_FIREBASE_APP_ID', 'REACT_APP_FIREBASE_APP_ID'),
};

// Check if Firebase is actually configured (has required keys)
const isConfigured = Boolean(firebaseConfig.apiKey && firebaseConfig.projectId);

let app = null;
let db  = null;
let messaging = null;

if (isConfigured) {
  try {
    app       = initializeApp(firebaseConfig);
    db        = getFirestore(app);
    messaging = getMessaging(app);
  } catch (err) {
    console.warn('[Firebase] Init failed:', err.message);
  }
}

/**
 * Request FCM notification permission and return the device token.
 * Returns null if permission denied or Firebase not configured.
 */
export async function requestFcmToken(vapidKey = '') {
  if (!messaging) return null;
  try {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return null;
    const token = await getToken(messaging, { vapidKey });
    return token || null;
  } catch {
    return null;
  }
}

/**
 * Listen to real-time Firestore notifications for a user.
 * @param {string} userId
 * @param {(items: any[]) => void} callback
 * @returns Unsubscribe function
 */
export function subscribeToNotifications(userId, callback) {
  if (!db || !userId) return () => {};
  const colRef = collection(db, 'notifications', userId, 'items');
  return onSnapshot(colRef, (snap) => {
    const items = snap.docs.map((d) => ({ id: d.id, ...d.data() }));
    callback(items.sort((a, b) => (b.createdAt || '') - (a.createdAt || '')));
  });
}

/**
 * Listen to real-time academic calendar events for a user.
 */
export function subscribeToAcademicDates(userId, callback) {
  if (!db || !userId) return () => {};
  const colRef = collection(db, 'academicDates', userId, 'events');
  return onSnapshot(colRef, (snap) => {
    const items = snap.docs.map((d) => ({ id: d.id, ...d.data() }));
    callback(items);
  });
}

/**
 * Register a foreground message handler.
 */
export function onForegroundMessage(handler) {
  if (!messaging) return () => {};
  return onMessage(messaging, handler);
}

export { db, messaging, isConfigured };
