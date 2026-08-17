import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'

import { AuthContext, type AuthContextValue } from '@/auth/auth-context'
import { api, getToken, setToken, setUnauthorizedHandler } from '@/lib/api'
import { getWebApp, isTelegramMiniApp } from '@/lib/telegram'
import type { AuthResponse, LoginPayload, RegisterPayload, User } from '@/types/api'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [initialising, setInitialising] = useState(true)

  // Har qanday soʻrov 401 bilan qaytsa, foydalanuvchi seansi shu yerda
  // tozalanadi — sahifalar buni alohida oʻylab oʻtirmaydi.
  useEffect(() => {
    setUnauthorizedHandler(() => setUser(null))

    return () => setUnauthorizedHandler(null)
  }, [])

  // A token in localStorage only means "possibly signed in": it is verified
  // against the API once on boot, and dropped if the server rejects it.
  useEffect(() => {
    let active = true

    if (!getToken()) {
      // Telegram ichida kirish sahifasi koʻrsatilmaydi: Mini App ochilishi
      // bilan initData imzosi orqali oʻzi kiradi.
      if (!isTelegramMiniApp()) {
        setInitialising(false)

        return
      }

      api
        .post<AuthResponse>('/auth/telegram', {
          init_data: getWebApp()?.initData,
          device_name: 'telegram',
        })
        .then((response) => {
          if (active) {
            setToken(response.data.token)
            setUser(response.data.user)
          }
        })
        .catch(() => {
          // Imzo rad etilsa oddiy kirish sahifasi koʻrsatiladi.
        })
        .finally(() => {
          if (active) {
            setInitialising(false)
          }
        })

      return () => {
        active = false
      }
    }

    api
      .get<{ user: User }>('/auth/me')
      .then((response) => {
        if (active) {
          setUser(response.data.user)
        }
      })
      .catch(() => {
        setToken(null)
      })
      .finally(() => {
        if (active) {
          setInitialising(false)
        }
      })

    return () => {
      active = false
    }
  }, [])

  const authenticate = useCallback(async (url: string, payload: object) => {
    const { data } = await api.post<AuthResponse>(url, payload)

    setToken(data.token)
    setUser(data.user)

    return data.user
  }, [])

  const login = useCallback(
    (payload: LoginPayload) => authenticate('/auth/login', { ...payload, device_name: 'web' }),
    [authenticate],
  )

  const register = useCallback(
    (payload: RegisterPayload) =>
      authenticate('/auth/register', { ...payload, device_name: 'web' }),
    [authenticate],
  )

  const refresh = useCallback(async () => {
    const { data } = await api.get<{ user: User }>('/auth/me')

    setUser(data.user)
  }, [])

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout')
    } finally {
      setToken(null)
      setUser(null)
    }
  }, [])

  const value = useMemo<AuthContextValue>(
    () => ({ user, initialising, login, register, logout, refresh }),
    [user, initialising, login, register, logout, refresh],
  )

  return <AuthContext value={value}>{children}</AuthContext>
}
