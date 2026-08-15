import { Navigate, Outlet, useLocation } from 'react-router'

import { ROLE_HOME } from '@/auth/auth-context'
import { useAuth } from '@/auth/use-auth'
import { Spinner } from '@/components/Spinner'
import type { UserRole } from '@/types/api'

/**
 * Guards a branch of the router: signed out visitors are sent to the login
 * page, and a signed in user with the wrong role is sent to their own home
 * instead of seeing another role's screens.
 */
export function ProtectedRoute({ roles }: { roles?: UserRole[] }) {
  const { user, initialising } = useAuth()
  const location = useLocation()

  if (initialising) {
    return <Spinner />
  }

  if (!user) {
    return <Navigate to="/login" state={{ from: location.pathname }} replace />
  }

  if (roles && !roles.includes(user.role)) {
    return <Navigate to={ROLE_HOME[user.role]} replace />
  }

  return <Outlet />
}
