// Optional Firebase layer \u2014 the *online* supplementary data store.
//
// Source of truth for all domain data (users, modules, assignments,
// documents, sessions, notifications...) is the local Laravel + MySQL/SQL
// stack (see backend-laravel/). Firebase Firestore is used here purely as a
// second, cloud-hosted store for lightweight, cross-device conveniences:
//   - recent global-search history (per user)
//   - a realtime mirror of the unread notification count
//
// The app must keep working with zero Firebase config (e.g. on a machine
// with no internet / no Firebase project provisioned), so every export
// below degrades to a safe no-op when `isFirebaseEnabled()` is false.
import { initializeApp, getApps } from 'firebase/app';

const firebaseConfig = {
  apiKey: process.env.FIREBASE_API_KEY,
  authDomain: process.env.FIREBASE_AUTH_DOMAIN,
  projectId: process.env.FIREBASE_PROJECT_ID,
  storageBucket: process.env.FIREBASE_STORAGE_BUCKET,
  messagingSenderId: process.env.FIREBASE_MESSAGING_SENDER_ID,
  appId: process.env.FIREBASE_APP_ID,
};

export function isFirebaseEnabled() {
  return Boolean(firebaseConfig.apiKey && firebaseConfig.projectId);
}

let app = null;
export function getFirebaseApp() {
  if (!isFirebaseEnabled()) return null;
  if (!app) {
    app = getApps().length ? getApps()[0] : initializeApp(firebaseConfig);
  }
  return app;
}
