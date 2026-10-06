import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { AppShell } from '@/components/layout/AppShell';
import { Spinner } from '@/components/ui/States';
import { useAuth } from '@/context/AuthContext';
import { RealtimeProvider } from '@/context/RealtimeContext';

// Route-level code splitting: each feature module is its own chunk.
const LoginPage = lazy(() => import('@/features/auth/pages/LoginPage'));
const RegisterPage = lazy(() => import('@/features/auth/pages/RegisterPage'));
const ForgotPasswordPage = lazy(() => import('@/features/auth/pages/ForgotPasswordPage'));
const ResetPasswordPage = lazy(() => import('@/features/auth/pages/ResetPasswordPage'));

const DashboardPage = lazy(() => import('@/features/dashboard/DashboardPage'));

const LearningDashboardPage = lazy(() => import('@/features/learning/pages/LearningDashboardPage'));
const LibraryPage = lazy(() => import('@/features/learning/pages/LibraryPage'));
const DocumentPage = lazy(() => import('@/features/learning/pages/DocumentPage'));
const SummariesPage = lazy(() => import('@/features/learning/pages/SummariesPage'));
const SummaryPage = lazy(() => import('@/features/learning/pages/SummaryPage'));
const StudyAidsPage = lazy(() => import('@/features/learning/pages/StudyAidsPage'));
const StudyAidPage = lazy(() => import('@/features/learning/pages/StudyAidPage'));

const StudioPage = lazy(() => import('@/features/study/pages/StudioPage'));
const SessionHistoryPage = lazy(() => import('@/features/study/pages/SessionHistoryPage'));
const SessionDetailPage = lazy(() => import('@/features/study/pages/SessionDetailPage'));
const ProductivityPage = lazy(() => import('@/features/study/pages/ProductivityPage'));
const PlannerPage = lazy(() => import('@/features/study/pages/PlannerPage'));

const AssistantDashboardPage = lazy(() => import('@/features/assistant/pages/AssistantDashboardPage'));
const ChatPage = lazy(() => import('@/features/assistant/pages/ChatPage'));
const KnowledgeBasePage = lazy(() => import('@/features/assistant/pages/KnowledgeBasePage'));
const KnowledgeDocumentPage = lazy(() => import('@/features/assistant/pages/KnowledgeDocumentPage'));
const KnowledgeSearchPage = lazy(() => import('@/features/assistant/pages/KnowledgeSearchPage'));
const AcademicDatesPage = lazy(() => import('@/features/assistant/pages/AcademicDatesPage'));

const RiskDashboardPage = lazy(() => import('@/features/assignments/pages/RiskDashboardPage'));
const AssignmentsPage = lazy(() => import('@/features/assignments/pages/AssignmentsPage'));
const AssignmentDetailPage = lazy(() => import('@/features/assignments/pages/AssignmentDetailPage'));
const WhatIfPage = lazy(() => import('@/features/assignments/pages/WhatIfPage'));
const AssignmentHistoryPage = lazy(() => import('@/features/assignments/pages/AssignmentHistoryPage'));

const CalendarPage = lazy(() => import('@/features/calendar/CalendarPage'));
const NotificationsPage = lazy(() => import('@/features/notifications/NotificationsPage'));
const ActivityPage = lazy(() => import('@/features/activity/ActivityPage'));
const ExportCenterPage = lazy(() => import('@/features/exports/ExportCenterPage'));
const ModulesPage = lazy(() => import('@/features/modules/ModulesPage'));
const TrashPage = lazy(() => import('@/features/trash/TrashPage'));
const SettingsPage = lazy(() => import('@/features/settings/SettingsPage'));
const ProfilePage = lazy(() => import('@/features/profile/ProfilePage'));
const NotFoundPage = lazy(() => import('@/features/NotFoundPage'));

function FullScreenLoader() {
  return (
    <div className="min-vh-100 d-grid" style={{ placeItems: 'center' }}>
      <div className="text-center">
        <Spinner size="lg" className="text-primary" />
        <div className="small text-3 mt-3 fw-semibold">Loading EDU-SMART…</div>
      </div>
    </div>
  );
}

function PageLoader() {
  return <div className="d-grid py-5" style={{ placeItems: 'center' }}><Spinner className="text-primary" /></div>;
}

function RequireAuth({ children }) {
  const { status } = useAuth();
  const location = useLocation();
  if (status === 'loading') return <FullScreenLoader />;
  if (status !== 'authenticated') return <Navigate to="/login" replace state={{ from: location }} />;
  return children;
}

function GuestOnly({ children }) {
  const { status } = useAuth();
  if (status === 'loading') return <FullScreenLoader />;
  if (status === 'authenticated') return <Navigate to="/dashboard" replace />;
  return children;
}

const page = (Component) => <Suspense fallback={<PageLoader />}><Component /></Suspense>;

export function AppRoutes() {
  return (
    <Suspense fallback={<FullScreenLoader />}>
      <Routes>
        <Route path="/login" element={<GuestOnly><LoginPage /></GuestOnly>} />
        <Route path="/register" element={<GuestOnly><RegisterPage /></GuestOnly>} />
        <Route path="/forgot-password" element={<GuestOnly><ForgotPasswordPage /></GuestOnly>} />
        <Route path="/reset-password" element={<ResetPasswordPage />} />

        <Route element={<RequireAuth><RealtimeProvider><AppShell /></RealtimeProvider></RequireAuth>}>
          <Route path="/dashboard" element={page(DashboardPage)} />

          <Route path="/learning" element={page(LearningDashboardPage)} />
          <Route path="/learning/library" element={page(LibraryPage)} />
          <Route path="/learning/documents/:id" element={page(DocumentPage)} />
          <Route path="/learning/summaries" element={page(SummariesPage)} />
          <Route path="/learning/summaries/:id" element={page(SummaryPage)} />
          <Route path="/learning/study-aids" element={page(StudyAidsPage)} />
          <Route path="/learning/study-aids/:id" element={page(StudyAidPage)} />

          <Route path="/study" element={page(StudioPage)} />
          <Route path="/study/history" element={page(SessionHistoryPage)} />
          <Route path="/study/sessions/:id" element={page(SessionDetailPage)} />
          <Route path="/study/analytics" element={page(ProductivityPage)} />
          <Route path="/study/planner" element={page(PlannerPage)} />

          <Route path="/assistant" element={page(AssistantDashboardPage)} />
          <Route path="/assistant/chat" element={page(ChatPage)} />
          <Route path="/assistant/chat/:conversationId" element={page(ChatPage)} />
          <Route path="/assistant/knowledge" element={page(KnowledgeBasePage)} />
          <Route path="/assistant/knowledge/:id" element={page(KnowledgeDocumentPage)} />
          <Route path="/assistant/search" element={page(KnowledgeSearchPage)} />
          <Route path="/assistant/dates" element={page(AcademicDatesPage)} />

          <Route path="/assignments" element={page(RiskDashboardPage)} />
          <Route path="/assignments/list" element={page(AssignmentsPage)} />
          <Route path="/assignments/what-if" element={page(WhatIfPage)} />
          <Route path="/assignments/history" element={page(AssignmentHistoryPage)} />
          <Route path="/assignments/:id" element={page(AssignmentDetailPage)} />

          <Route path="/calendar" element={page(CalendarPage)} />
          <Route path="/notifications" element={page(NotificationsPage)} />
          <Route path="/activity" element={page(ActivityPage)} />
          <Route path="/exports" element={page(ExportCenterPage)} />
          <Route path="/modules" element={page(ModulesPage)} />
          <Route path="/trash" element={page(TrashPage)} />
          <Route path="/settings" element={page(SettingsPage)} />
          <Route path="/profile" element={page(ProfilePage)} />
          <Route path="*" element={page(NotFoundPage)} />
        </Route>

        <Route path="/" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </Suspense>
  );
}
