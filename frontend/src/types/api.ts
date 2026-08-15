export type UserRole = 'customer' | 'restaurant_staff' | 'super_admin'

export interface Restaurant {
  id: number
  name: string
  address: string
  phone: string | null
  opens_at: string
  closes_at: string
  is_active: boolean
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
