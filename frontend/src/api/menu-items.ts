import { api } from '@/lib/api'
import type { MenuItem } from '@/types/api'

export interface MenuItemPayload {
  name: string
  description?: string | null
  price: number
  base_prep_minutes: number
  extra_prep_minutes: number
  is_available?: boolean
}

export async function listMenuItems() {
  const { data } = await api.get<{ menu_items: MenuItem[] }>('/staff/menu-items')

  return data.menu_items
}

export async function createMenuItem(payload: MenuItemPayload) {
  const { data } = await api.post<{ menu_item: MenuItem }>('/staff/menu-items', payload)

  return data.menu_item
}

export async function updateMenuItem(id: number, payload: Partial<MenuItemPayload>) {
  const { data } = await api.patch<{ menu_item: MenuItem }>(`/staff/menu-items/${id}`, payload)

  return data.menu_item
}

export async function deleteMenuItem(id: number) {
  await api.delete(`/staff/menu-items/${id}`)
}
