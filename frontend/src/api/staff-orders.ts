import { api } from '@/lib/api'
import type { OrderItem, OrderStatus } from '@/types/api'

export interface StaffOrder {
  id: number
  status: OrderStatus
  /** Bu holatdan oʻtish mumkin boʻlgan holatlar (serverdan). */
  next_statuses: OrderStatus[]
  total_price: string
  prep_minutes: number
  ready_at: string | null
  paid_at: string | null
  created_at: string
  customer: { name?: string; phone?: string | null }
  items?: OrderItem[]
}

export async function listStaffOrders(status?: OrderStatus | '') {
  const { data } = await api.get<{ orders: StaffOrder[] }>('/staff/orders', {
    params: { status: status || undefined },
  })

  return data.orders
}

export async function updateStaffOrderStatus(id: number, status: OrderStatus) {
  const { data } = await api.patch<{ order: StaffOrder }>(`/staff/orders/${id}`, { status })

  return data.order
}
