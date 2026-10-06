/**
 * EDU-SMART mobile app — root component.
 *
 * Wiring only: safe-area provider (navigation), auth state (Common Platform
 * Layer) and the navigator. All business logic lives in src/api and src/screens.
 *
 * Offline-first: if the Laravel API at app.json -> expo.extra.apiBase is not
 * reachable, the app signs in with the bundled demo account and renders the
 * bundled dataset, so it is always demonstrable without PHP or MySQL.
 */
import React from 'react';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { LogBox } from 'react-native';

import { AuthProvider } from './src/context/AuthContext';
import RootNavigator from './src/navigation/RootNavigator';

// Non-fatal dev warnings (animated/native-stack internals) — keep the console clean.
LogBox.ignoreLogs(['Non-serializable values were found in the navigation state']);

export default function App() {
  return (
    <SafeAreaProvider>
      <AuthProvider>
        <StatusBar style="light" />
        <RootNavigator />
      </AuthProvider>
    </SafeAreaProvider>
  );
}
