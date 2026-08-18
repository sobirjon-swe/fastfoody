import { api } from '@/lib/api'
import type { MenuItem } from '@/types/api'

/** Til kodi → shu tildagi matnlar. Asosiy til bu yerga yozilmaydi. */
export type MenuItemTranslations = Record<
  string,
  { name?: string; description?: string; category?: string }
>

export interface MenuItemPayload {
  name: string
  description?: string | null
  category?: string | null
  price: number
  base_prep_minutes: number
  extra_prep_minutes: number
  is_available?: boolean
  translations?: MenuItemTranslations | null
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

/** Rasm alohida multipart soʻrov bilan yuboriladi. */
export async function uploadMenuItemImage(id: number, file: File) {
  const body = new FormData()

  body.append('image', file)

  const { data } = await api.post<{ menu_item: MenuItem }>(`/staff/menu-items/${id}/image`, body)

  return data.menu_item
}

export async function deleteMenuItemImage(id: number) {
  const { data } = await api.delete<{ menu_item: MenuItem }>(`/staff/menu-items/${id}/image`)

  return data.menu_item
}

/** Bitta variant: «Smetana», narx va vaqt qoʻshimchasi bilan. */
export interface OptionInput {
  id?: number
  name: string
  price_delta: number
  prep_delta_minutes: number
  is_available?: boolean
  translations?: Record<string, { name?: string }>
}

/** Bitta savol: «Sous». min/max tanlov turini belgilaydi. */
export interface OptionGroupInput {
  id?: number
  name: string
  min_select: number
  max_select: number | null
  options: OptionInput[]
  translations?: Record<string, { name?: string }>
}

/**
 * Guruhlarning yakuniy holatini saqlaydi: roʻyxatda yoʻq guruh yoki variant
 * serverda oʻchiriladi.
 */
export async function syncMenuItemOptions(menuItemId: number, groups: OptionGroupInput[]) {
  const { data } = await api.put<{ menu_item: MenuItem }>(
    `/staff/menu-items/${menuItemId}/options`,
    { groups },
  )

  return data.menu_item
}
