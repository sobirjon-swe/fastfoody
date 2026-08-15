import { api } from '@/lib/api'
import type { MenuItem, Order, OrderEstimate, Restaurant } from '@/types/api'

export interface CartLine {
  menu_item_id: number
  quantity: number
}

export async function listPublicRestaurants(q?: string) {
  const { data } = await api.get<{ restaurants: Restaurant[] }>('/restaurants', {
    params: { q: q || undefined },
  })

  return data.restaurants
}

export async function getRestaurantMenu(restaurantId: number) {
  const { data } = await api.get<{ restaurant: Restaurant; menu_items: MenuItem[] }>(
    `/restaurants/${restaurantId}`,
  )

  return data
}

/** Toʻlovdan oldin koʻrsatiladigan baho; hech narsa saqlanmaydi. */
export async function estimateOrder(restaurantId: number, items: CartLine[]) {
  const { data } = await api.post<{ estimate: OrderEstimate }>('/orders/estimate', {
    restaurant_id: restaurantId,
    items,
  })

  return data.estimate
}

export async function placeOrder(restaurantId: number, items: CartLine[]) {
  const { data } = await api.post<{ order: Order }>('/orders', {
    restaurant_id: restaurantId,
    items,
  })

  return data.order
}

export async function listMyOrders() {
  const { data } = await api.get<{ orders: Order[] }>('/orders')

  return data.orders
}

export async function getOrder(id: number) {
  const { data } = await api.get<{ order: Order }>(`/orders/${id}`)

  return data.order
}

/** Simulated payment — the MVP has no payment provider yet. */
export async function payOrder(id: number) {
  const { data } = await api.post<{ order: Order }>(`/orders/${id}/pay`)

  return data.order
}

/** Mijoz: tugagan taomni boshqasiga almashtiradi. */
export async function replaceOrderItem(orderId: number, orderItemId: number, menuItemId: number) {
  const { data } = await api.post<{ order: Order }>(`/orders/${orderId}/replace-item`, {
    order_item_id: orderItemId,
    menu_item_id: menuItemId,
  })

  return data.order
}

/** Mijoz: buyurtmani bekor qiladi, pul qaytariladi (simulyatsiya). */
export async function cancelOrder(orderId: number) {
  const { data } = await api.post<{ order: Order }>(`/orders/${orderId}/cancel`)

  return data.order
}
