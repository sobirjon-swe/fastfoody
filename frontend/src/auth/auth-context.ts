import { createContext } from 'react'

import type { LoginPayload, RegisterPayload, User, UserRole } from '@/types/api'

export interface AuthContextValue {
  user: User | null
  /** True while the stored token is being exchanged for the current user. */
  initialising: boolean
  login: (payload: LoginPayload) => Promise<User>
  register: (payload: RegisterPayload) => Promise<User>
  logout: () => Promise<void>
  /** Profil oʻzgargach seansdagi maʼlumotni yangilaydi. */
  refresh: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

/** Landing route of each role after a successful sign in. */
export const ROLE_HOME: Record<UserRole, string> = {
  customer: '/',
  restaurant_staff: '/staff',
  super_admin: '/admin',
}
