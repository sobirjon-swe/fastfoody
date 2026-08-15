import { Route, Routes } from 'react-router'

import { ProtectedRoute } from '@/auth/ProtectedRoute'
import { AppLayout } from '@/components/AppLayout'
import { AdminDashboardPage } from '@/pages/AdminDashboardPage'
import { CustomerHomePage } from '@/pages/CustomerHomePage'
import { LoginPage } from '@/pages/LoginPage'
import { NotFoundPage } from '@/pages/NotFoundPage'
import { RegisterPage } from '@/pages/RegisterPage'
import { StaffDashboardPage } from '@/pages/StaffDashboardPage'

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />

      <Route element={<ProtectedRoute />}>
        <Route element={<AppLayout />}>
          <Route element={<ProtectedRoute roles={['customer']} />}>
            <Route index element={<CustomerHomePage />} />
          </Route>

          <Route element={<ProtectedRoute roles={['restaurant_staff']} />}>
            <Route path="staff" element={<StaffDashboardPage />} />
          </Route>

          <Route element={<ProtectedRoute roles={['super_admin']} />}>
            <Route path="admin" element={<AdminDashboardPage />} />
          </Route>

          <Route path="*" element={<NotFoundPage />} />
        </Route>
      </Route>
    </Routes>
  )
}
