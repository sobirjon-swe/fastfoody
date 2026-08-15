import { api } from '@/lib/api'
import type { OrderItem, OrderStatus, PaginationMeta } from '@/types/api'

export interface StaffOrder {
  id: number
  status: OrderStatus
  pickup_code: string | null
  /** Bu holatdan oʻtish mumkin boʻlgan holatlar (serverdan). */
  next_statuses: OrderStatus[]
  total_price: string
  prep_minutes: number
  ready_at: string | null
  paid_at: string | null
  can_report_out_of_stock: boolean
  created_at: string
  customer: { name?: string; phone?: string | null }
  items?: OrderItem[]
}

export async function listStaffOrders(status?: OrderStatus | '', page = 1, code?: string) {
  const { data } = await api.get<{ orders: StaffOrder[]; meta: PaginationMeta }>('/staff/orders', {
    params: { status: status || undefined, page, code: code || undefined },
  })

  return data
}

export async function updateStaffOrderStatus(id: number, status: OrderStatus) {
  const { data } = await api.patch<{ order: StaffOrder }>(`/staff/orders/${id}`, { status })

  return data.order
}

/** Oshxona: shu qatordagi taom tugadi. */
export async function reportOutOfStock(
  orderId: number,
  orderItemId: number,
  markMenuItemUnavailable: boolean,
) {
  const { data } = await api.post<{ order: StaffOrder }>(`/staff/orders/${orderId}/out-of-stock`, {
    order_item_id: orderItemId,
    mark_menu_item_unavailable: markMenuItemUnavailable,
  })

  return data.order
}
