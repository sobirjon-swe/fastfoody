export type UserRole = 'customer' | 'restaurant_staff' | 'super_admin'

export interface Restaurant {
  id: number
  name: string
  address: string
  phone: string | null
  /** "HH:MM" */
  opens_at: string
  /** "HH:MM" */
  closes_at: string
  is_active: boolean
  menu_items_count?: number
  staff_count?: number
}

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  role: UserRole
  restaurant_id: number | null
  restaurant?: Restaurant
  created_at: string
}

export interface MenuItem {
  id: number
  restaurant_id: number
  name: string
  description: string | null
  /** Decimal string from the API, e.g. "32000.00". */
  price: string
  base_prep_minutes: number
  extra_prep_minutes: number
  is_available: boolean
  created_at: string
  updated_at: string
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface AuthResponse {
  token: string
  user: User
}

export interface RegisterPayload {
  name: string
  email: string
  phone?: string
  password: string
  password_confirmation: string
}

export interface LoginPayload {
  email: string
  password: string
}

export const ROLE_LABELS: Record<UserRole, string> = {
  customer: 'Mijoz',
  restaurant_staff: 'Oshxona xodimi',
  super_admin: 'Tizim egasi',
}
