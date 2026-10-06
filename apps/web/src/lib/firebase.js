// Firebase realtime layer (optional). The API mints a custom token for the
// signed-in student; we sign in with it and subscribe to that student's own
// Firestore subtree. Works against the local Emulator Suite or a real project.

import { deleteApp, initializeApp } from 'firebase/app';
import { connectAuthEmulator, getAuth, signInWithCustomToken, signOut } from 'firebase/auth';
import { collection, connectFirestoreEmulator, doc, getFirestore, limit, onSnapshot, orderBy, query } from 'firebase/firestore';
import { FIREBASE_WEB_CONFIG } from '@/config/env';

let session = null; // { app, auth, db, uid }

export async function connectRealtime(config) {
  if (!config?.enabled || !config.token) return null;

  if (!session || session.projectId !== config.project_id) {
    await disconnectRealtime();
    const app = initializeApp({ ...FIREBASE_WEB_CONFIG, projectId: config.project_id }, `edusmart-${Date.now()}`);
    const auth = getAuth(app);
    const db = getFirestore(app);
    if (config.emulators?.auth) {
      connectAuthEmulator(auth, `http://${config.emulators.auth}`, { disableWarnings: true });
    }
    if (config.emulators?.firestore) {
      const [host, port] = config.emulators.firestore.split(':');
      connectFirestoreEmulator(db, host, Number(port));
    }
    session = { app, auth, db, projectId: config.project_id, uid: null };
  }

  await signInWithCustomToken(session.auth, config.token);
  session.uid = config.uid;

  return session;
}

export async function disconnectRealtime() {
  if (!session) return;
  const { app, auth } = session;
  session = null;
  try {
    await signOut(auth);
  } catch {
    /* ignore */
  }
  try {
    await deleteApp(app);
  } catch {
    /* ignore */
  }
}

function userPath(...segments) {
  return [session.db, 'users', session.uid, ...segments];
}

export function watchNotifications(callback, onError) {
  if (!session?.uid) return () => {};
  const q = query(collection(...userPath('notifications')), orderBy('created_at', 'desc'), limit(15));

  return onSnapshot(q, (snapshot) => callback(snapshot), onError);
}

export function watchActivity(callback, onError) {
  if (!session?.uid) return () => {};
  const q = query(collection(...userPath('activity')), orderBy('created_at', 'desc'), limit(10));

  return onSnapshot(q, (snapshot) => callback(snapshot.docs.map((d) => ({ id: d.id, ...d.data() }))), onError);
}

export function watchLiveStudy(callback, onError) {
  if (!session?.uid) return () => {};

  return onSnapshot(doc(...userPath('live', 'study')), (snapshot) => callback(snapshot.exists() ? snapshot.data() : null), onError);
}
