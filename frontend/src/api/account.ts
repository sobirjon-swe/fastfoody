import { api } from '@/lib/api'
import type { User } from '@/types/api'

export interface ProfilePayload {
  name?: string
  email?: string
  phone?: string | null
}

export async function updateProfile(payload: ProfilePayload) {
  const { data } = await api.patch<{ user: User }>('/auth/profile', payload)

  return data.user
}

export async function updatePassword(payload: {
  current_password: string
  password: string
  password_confirmation: string
}) {
  await api.put('/auth/password', payload)
}

/** Javob har doim bir xil — hisob bor-yoʻqligi oshkor qilinmaydi. */
export async function requestPasswordReset(email: string) {
  const { data } = await api.post<{ message: string }>('/auth/forgot-password', { email })

  return data.message
}

export async function resetPassword(payload: {
  token: string
  email: string
  password: string
  password_confirmation: string
}) {
  const { data } = await api.post<{ message: string }>('/auth/reset-password', payload)

  return data.message
}
