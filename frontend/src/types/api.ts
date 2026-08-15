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
  /** Ayni damda ish vaqtida va faolmi. */
  is_open_now: boolean
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

export type OrderStatus =
  | 'kutilmoqda'
  | 'tolov_qilindi'
  | 'tayyorlanmoqda'
  | 'tayyor'
  | 'mijoz_qarori_kutilmoqda'
  | 'olib_ketildi'
  | 'bekor_qilindi_mahsulot_yoq'
  | 'muddati_otdi'

export interface OrderItem {
  id: number
  menu_item_id: number | null
  name: string
  unit_price: string
  quantity: number
  line_total: string
  prep_minutes: number
  /** Oshxona shu qatordagi taom tugaganini belgilagan. */
  is_out_of_stock: boolean
}

export interface Order {
  id: number
  restaurant_id: number
  restaurant?: Restaurant
  status: OrderStatus
  total_price: string
  prep_minutes: number
  /** Toʻlangandan keyin qatʼiylashadi. */
  ready_at: string | null
  /** Toʻlanmagan buyurtma uchun jonli baho. */
  estimated_ready_at: string | null
  paid_at: string | null
  refunded_at: string | null
  created_at: string
  items?: OrderItem[]
}

export const ORDER_STATUS_LABELS: Record<OrderStatus, string> = {
  kutilmoqda: 'Toʻlov kutilmoqda',
  tolov_qilindi: 'Toʻlandi',
  tayyorlanmoqda: 'Tayyorlanmoqda',
  tayyor: 'Tayyor',
  mijoz_qarori_kutilmoqda: 'Mahsulot tugadi — javobingiz kutilmoqda',
  olib_ketildi: 'Olib ketildi',
  bekor_qilindi_mahsulot_yoq: 'Bekor qilindi (mahsulot yoʻq)',
  muddati_otdi: 'Muddati oʻtdi',
}

export interface OrderEstimate {
  total_price: string
  /** Buyurtmaning oʻz tayyorlanish vaqti. */
  prep_minutes: number
  /** Oshxona navbati hozir qancha band. */
  queue_minutes: number
  ready_at: string
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
