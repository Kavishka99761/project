import { QueryClientProvider } from '@tanstack/react-query';
import { MotionConfig } from 'framer-motion';
import { BrowserRouter } from 'react-router-dom';
import { AppRoutes } from '@/app/routes';
import { ErrorBoundary } from '@/app/ErrorBoundary';
import { AuthProvider } from '@/context/AuthContext';
import { ConfirmProvider } from '@/context/ConfirmContext';
import { ThemeProvider, useTheme } from '@/context/ThemeContext';
import { ToastProvider } from '@/context/ToastContext';
import { queryClient } from '@/lib/queryClient';

function MotionPreferences({ children }) {
  const { reduceMotion } = useTheme();
  return <MotionConfig reducedMotion={reduceMotion ? 'always' : 'user'}>{children}</MotionConfig>;
}

export function App() {
  return (
    <ErrorBoundary>
      <QueryClientProvider client={queryClient}>
        <ThemeProvider>
          <MotionPreferences>
            <ToastProvider>
              <ConfirmProvider>
                <AuthProvider>
                  <BrowserRouter>
                    <AppRoutes />
                  </BrowserRouter>
                </AuthProvider>
              </ConfirmProvider>
            </ToastProvider>
          </MotionPreferences>
        </ThemeProvider>
      </QueryClientProvider>
    </ErrorBoundary>
  );
}
