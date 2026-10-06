import Constants from 'expo-constants';

/**
 * Base URL of the Laravel API (backend-laravel). Override at runtime via
 * app.json -> expo.extra.apiBase, or change the fallback below.
 *
 * Running on a physical device? Replace `localhost` with your computer's LAN IP
 * (e.g. http://192.168.1.10:8000/api) so the phone can reach the dev server.
 */
const extra = Constants.expoConfig?.extra ?? Constants.manifest?.extra ?? {};

export const API_BASE = extra.apiBase || 'http://localhost:8000/api';

/** Milliseconds before a network call is treated as unavailable (offline mode). */
export const NETWORK_TIMEOUT_MS = 4000;

export default { API_BASE, NETWORK_TIMEOUT_MS };
