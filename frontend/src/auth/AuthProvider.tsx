import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'

import { AuthContext, type AuthContextValue } from '@/auth/auth-context'
import { api, getToken, setToken } from '@/lib/api'
import type { AuthResponse, LoginPayload, RegisterPayload, User } from '@/types/api'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [initialising, setInitialising] = useState(true)

  // A token in localStorage only means "possibly signed in": it is verified
  // against the API once on boot, and dropped if the server rejects it.
  useEffect(() => {
    if (!getToken()) {
      setInitialising(false)

      return
    }

    let active = true

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
    (payload: RegisterPayload) => authenticate('/auth/register', { ...payload, device_name: 'web' }),
    [authenticate],
  )

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout')
    } finally {
      setToken(null)
      setUser(null)
    }
  }, [])

  const value = useMemo<AuthContextValue>(
    () => ({ user, initialising, login, register, logout }),
    [user, initialising, login, register, logout],
  )

  return <AuthContext value={value}>{children}</AuthContext>
}
