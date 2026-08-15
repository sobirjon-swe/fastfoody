import axios, { AxiosError } from 'axios'

const TOKEN_KEY = 'fastfoody.token'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
  headers: {
    Accept: 'application/json',
  },
})

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string | null): void {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
  }
}

api.interceptors.request.use((config) => {
  const token = getToken()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

interface LaravelErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

/**
 * Turns a Laravel error response into a single readable message; validation
 * errors (422) are joined so every field problem is shown at once.
 */
export function apiErrorMessage(error: unknown, fallback = 'Nomaʼlum xatolik yuz berdi.'): string {
  if (error instanceof AxiosError) {
    const data = error.response?.data as LaravelErrorBody | undefined

    if (data?.errors) {
      const messages = Object.values(data.errors).flat()

      if (messages.length > 0) {
        return messages.join(' ')
      }
    }

    if (data?.message) {
      return data.message
    }

    if (error.code === AxiosError.ERR_NETWORK) {
      return 'Serverga ulanib boʻlmadi. Backend ishga tushganini tekshiring.'
    }
  }

  return fallback
}
