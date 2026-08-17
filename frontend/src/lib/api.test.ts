import { AxiosError, AxiosHeaders } from 'axios'
import { describe, expect, it } from 'vitest'

import { apiErrorMessage } from '@/lib/api'

function axiosErrorWith(status: number, data: unknown): AxiosError {
  const error = new AxiosError('soʼrov xatosi')

  error.response = {
    status,
    data,
    statusText: '',
    headers: new AxiosHeaders(),
    config: { headers: new AxiosHeaders() },
  }

  return error
}

describe('apiErrorMessage', () => {
  it('validatsiya xatolarini bitta xabarga birlashtiradi', () => {
    const message = apiErrorMessage(
      axiosErrorWith(422, { errors: { name: ['Nom kerak.'], price: ['Narx kerak.'] } }),
    )

    expect(message).toBe('Nom kerak. Narx kerak.')
  })

  it('serverning xabarini ishlatadi', () => {
    expect(apiErrorMessage(axiosErrorWith(409, { message: 'Allaqachon toʻlangan.' }))).toBe(
      'Allaqachon toʻlangan.',
    )
  })

  it('401 uchun seans tugagani aytiladi', () => {
    expect(apiErrorMessage(axiosErrorWith(401, {}))).toBe('Seans tugadi. Iltimos, qaytadan kiring.')
  })

  it('nomaʼlum xatoda zaxira xabar qaytadi', () => {
    expect(apiErrorMessage(new Error('nimadir'), 'Zaxira xabar.')).toBe('Zaxira xabar.')
  })
})
