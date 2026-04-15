import { Navigate } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';
import AppShell from '@/components/layout/AppShell';

export default function ProtectedRoute() {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <AppShell />;
}
