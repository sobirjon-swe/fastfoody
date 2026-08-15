import { api } from '@/lib/api'
import type { PaginationMeta, Restaurant, User } from '@/types/api'

export interface RestaurantListParams {
  q?: string
  status?: 'active' | 'inactive' | ''
  page?: number
}

export interface RestaurantPayload {
  name: string
  address: string
  phone?: string | null
  opens_at: string
  closes_at: string
  is_active?: boolean
}

export interface StaffPayload {
  name: string
  email: string
  phone?: string | null
  password: string
  password_confirmation: string
}

export async function listRestaurants(params: RestaurantListParams) {
  const { data } = await api.get<{ data: Restaurant[]; meta: PaginationMeta }>(
    '/admin/restaurants',
    { params: { q: params.q || undefined, status: params.status || undefined, page: params.page } },
  )

  return data
}

export async function createRestaurant(payload: RestaurantPayload) {
  const { data } = await api.post<{ restaurant: Restaurant }>('/admin/restaurants', payload)

  return data.restaurant
}

export async function updateRestaurant(id: number, payload: Partial<RestaurantPayload>) {
  const { data } = await api.patch<{ restaurant: Restaurant }>(`/admin/restaurants/${id}`, payload)

  return data.restaurant
}

export async function listRestaurantStaff(restaurantId: number) {
  const { data } = await api.get<{ staff: User[] }>(`/admin/restaurants/${restaurantId}/staff`)

  return data.staff
}

export async function createRestaurantStaff(restaurantId: number, payload: StaffPayload) {
  const { data } = await api.post<{ user: User }>(
    `/admin/restaurants/${restaurantId}/staff`,
    payload,
  )

  return data.user
}

/** Xodim hisobi oʻchirilmaydi — faolsizlantiriladi (tokenlari bekor qilinadi). */
export async function deactivateStaff(restaurantId: number, staffId: number) {
  const { data } = await api.delete<{ user: User }>(
    `/admin/restaurants/${restaurantId}/staff/${staffId}`,
  )

  return data.user
}

export async function restoreStaff(restaurantId: number, staffId: number) {
  const { data } = await api.post<{ user: User }>(
    `/admin/restaurants/${restaurantId}/staff/${staffId}/restore`,
  )

  return data.user
}
