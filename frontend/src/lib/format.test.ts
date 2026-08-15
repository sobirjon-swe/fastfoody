import { describe, expect, it, vi } from 'vitest'

import { formatClock, formatPrepTime, formatPrice, minutesFromNow } from '@/lib/format'

describe('formatPrice', () => {
  it('minglarni boʻsh joy bilan ajratadi', () => {
    expect(formatPrice('32000.00')).toBe('32 000 soʻm')
    expect(formatPrice('9000.00')).toBe('9 000 soʻm')
    expect(formatPrice('1234567.00')).toBe('1 234 567 soʻm')
  })

  it('tiyin faqat mavjud boʻlsa koʻrsatiladi', () => {
    expect(formatPrice('12500.75')).toBe('12 500,75 soʻm')
    expect(formatPrice('0.01')).toBe('0,01 soʻm')
  })

  it('son boʻlmagan qiymatga yiqilmaydi', () => {
    expect(formatPrice('salom')).toBe('—')
  })
})

describe('formatPrepTime', () => {
  it('qoʻshimcha vaqt boʻlsa qavsda koʻrsatadi', () => {
    expect(formatPrepTime(4, 2)).toBe('4 daq (+2 daq)')
  })

  it('qoʻshimcha vaqt nol boʻlsa qavs boʻlmaydi', () => {
    expect(formatPrepTime(1, 0)).toBe('1 daq')
  })
})

describe('minutesFromNow', () => {
  it('qolgan daqiqalarni yaxlitlaydi', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-08-15T12:00:00Z'))

    expect(minutesFromNow('2026-08-15T12:08:00Z')).toBe(8)
    // Oʻtib ketgan vaqt manfiy emas, nol boʻladi.
    expect(minutesFromNow('2026-08-15T11:00:00Z')).toBe(0)

    vi.useRealTimers()
  })
})

describe('formatClock', () => {
  it('soat va daqiqani qaytaradi', () => {
    expect(formatClock('2026-08-15T12:08:00Z')).toMatch(/^\d{2}:\d{2}$/)
  })
})
