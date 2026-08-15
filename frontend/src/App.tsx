import { Route, Routes } from 'react-router'

import { ProtectedRoute } from '@/auth/ProtectedRoute'
import { AppLayout } from '@/components/AppLayout'
import { AdminDashboardPage } from '@/pages/AdminDashboardPage'
import { CustomerHomePage } from '@/pages/CustomerHomePage'
import { LoginPage } from '@/pages/LoginPage'
import { MyOrdersPage } from '@/pages/MyOrdersPage'
import { NotFoundPage } from '@/pages/NotFoundPage'
import { OrderDetailPage } from '@/pages/OrderDetailPage'
import { RegisterPage } from '@/pages/RegisterPage'
import { RestaurantMenuPage } from '@/pages/RestaurantMenuPage'
import { StaffMenuPage } from '@/pages/StaffMenuPage'
import { StaffOrdersPage } from '@/pages/StaffOrdersPage'

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />

      <Route element={<ProtectedRoute />}>
        <Route element={<AppLayout />}>
          <Route element={<ProtectedRoute roles={['customer']} />}>
            <Route index element={<CustomerHomePage />} />
            <Route path="restaurants/:restaurantId" element={<RestaurantMenuPage />} />
            <Route path="orders" element={<MyOrdersPage />} />
            <Route path="orders/:orderId" element={<OrderDetailPage />} />
          </Route>

          <Route element={<ProtectedRoute roles={['restaurant_staff']} />}>
            <Route path="staff" element={<StaffOrdersPage />} />
            <Route path="staff/menu" element={<StaffMenuPage />} />
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
