import { api } from '@/lib/api'

export interface StatisticsWindow {
  orders: number
  /** Decimal satr, masalan "80000.00". */
  revenue: string
  cancelled: number
  expired: number
  average_prep_minutes: number
}

export interface RestaurantStatistics {
  id: number
  name: string
  is_active: boolean
  today: StatisticsWindow
  week: StatisticsWindow
}

export async function getStaffStatistics() {
  const { data } = await api.get<{
    statistics: { today: StatisticsWindow; week: StatisticsWindow }
  }>('/staff/statistics')

  return data.statistics
}

export async function getAdminStatistics() {
  const { data } = await api.get<{
    statistics: {
      today: StatisticsWindow
      week: StatisticsWindow
      restaurants: RestaurantStatistics[]
    }
  }>('/admin/statistics')

  return data.statistics
}
