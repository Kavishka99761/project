import React from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { SnackbarProvider } from 'notistack';
import { AuthProvider } from './context/AuthContext';
import { ColorModeProvider } from './context/ColorModeContext';
import ProtectedRoute from './components/ProtectedRoute';
import AppShell from './components/layout/AppShell';

import Login from './pages/Login';
import Register from './pages/Register';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import Dashboard from './pages/Dashboard';
import Profile from './pages/Profile';
import ModulesPage from './pages/ModulesPage';
import CalendarPage from './pages/CalendarPage';
import NotificationsPage from './pages/NotificationsPage';
import SettingsPage from './pages/SettingsPage';
import NotFound from './pages/NotFound';

import Learning from './pages/modules/Learning';
import Study from './pages/modules/Study';
import Assistant from './pages/modules/Assistant';
import Assignments from './pages/modules/Assignments';

export default function App() {
  return (
    <AuthProvider>
      <ColorModeProvider>
        <SnackbarProvider maxSnack={3} autoHideDuration={3500} anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/register" element={<Register />} />
            <Route path="/forgot-password" element={<ForgotPassword />} />
            <Route path="/reset-password" element={<ResetPassword />} />

            <Route
              element={
                <ProtectedRoute>
                  <AppShell />
                </ProtectedRoute>
              }
            >
              <Route path="/dashboard" element={<Dashboard />} />
              <Route path="/profile" element={<Profile />} />
              <Route path="/modules" element={<ModulesPage />} />
              <Route path="/calendar" element={<CalendarPage />} />
              <Route path="/notifications" element={<NotificationsPage />} />
              <Route path="/settings" element={<SettingsPage />} />
              <Route path="/learning" element={<Learning />} />
              <Route path="/study" element={<Study />} />
              <Route path="/assistant" element={<Assistant />} />
              <Route path="/assignments" element={<Assignments />} />
            </Route>

            <Route path="/" element={<Navigate to="/dashboard" replace />} />
            <Route path="*" element={<NotFound />} />
          </Routes>
        </SnackbarProvider>
      </ColorModeProvider>
    </AuthProvider>
  );
}
