// Recent-search history stored in Firestore, keyed by the Laravel user id.
// This is deliberately independent of Firebase Auth \u2014 the app's identity
// system of record is Sanctum/Laravel; Firestore is just used as a document
// store here, addressed by that same numeric user id.
import {
  getFirestore,
  doc,
  getDoc,
  setDoc,
  arrayUnion,
} from 'firebase/firestore';
import { getFirebaseApp, isFirebaseEnabled } from './config';

const MAX_HISTORY = 8;

function historyDoc(userId) {
  const app = getFirebaseApp();
  if (!app) return null;
  const db = getFirestore(app);
  return doc(db, 'searchHistory', String(userId));
}

export async function fetchRecentSearches(userId) {
  if (!isFirebaseEnabled() || !userId) return [];
  try {
    const ref = historyDoc(userId);
    const snap = await getDoc(ref);
    if (!snap.exists()) return [];
    return (snap.data().terms || []).slice(-MAX_HISTORY).reverse();
  } catch (err) {
    // Offline / no network / rules not deployed yet \u2014 fail silently, the
    // Common Search feature still works locally without history.
    console.warn('Firebase search history unavailable:', err.message);
    return [];
  }
}

export async function recordSearch(userId, term) {
  if (!isFirebaseEnabled() || !userId || !term?.trim()) return;
  try {
    const ref = historyDoc(userId);
    await setDoc(
      ref,
      { terms: arrayUnion(term.trim()), updated_at: new Date().toISOString() },
      { merge: true }
    );
  } catch (err) {
    console.warn('Firebase search history write failed:', err.message);
  }
}
