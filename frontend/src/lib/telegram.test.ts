import { afterEach, describe, expect, it, vi } from 'vitest'

import { getWebApp, initTelegram, isTelegramMiniApp, syncColorScheme } from '@/lib/telegram'
import type { TelegramWebApp } from '@/lib/telegram'

function fakeWebApp(overrides: Partial<TelegramWebApp> = {}): TelegramWebApp {
  return {
    initData: 'auth_date=1&hash=abc',
    colorScheme: 'light',
    MainButton: {} as TelegramWebApp['MainButton'],
    BackButton: {} as TelegramWebApp['BackButton'],
    ready: vi.fn(),
    expand: vi.fn(),
    onEvent: vi.fn(),
    offEvent: vi.fn(),
    ...overrides,
  }
}

afterEach(() => {
  delete window.Telegram
  document.documentElement.classList.remove('dark')
})

describe('isTelegramMiniApp', () => {
  it('is false in a plain browser', () => {
    expect(isTelegramMiniApp()).toBe(false)
    expect(getWebApp()).toBeNull()
  })

  it('is false when the script loaded but the page is not inside Telegram', () => {
    // Telegram skripti har qanday sahifada yuklanadi, lekin tashqarida
    // initData boʻsh boʻladi.
    window.Telegram = { WebApp: fakeWebApp({ initData: '' }) }

    expect(isTelegramMiniApp()).toBe(false)
  })

  it('is true inside a Mini App', () => {
    window.Telegram = { WebApp: fakeWebApp() }

    expect(isTelegramMiniApp()).toBe(true)
  })
})

describe('syncColorScheme', () => {
  it('turns the dark palette on and off with Telegram', () => {
    window.Telegram = { WebApp: fakeWebApp({ colorScheme: 'dark' }) }
    syncColorScheme()
    expect(document.documentElement.classList.contains('dark')).toBe(true)

    window.Telegram = { WebApp: fakeWebApp({ colorScheme: 'light' }) }
    syncColorScheme()
    expect(document.documentElement.classList.contains('dark')).toBe(false)
  })

  it('leaves the page alone outside Telegram', () => {
    document.documentElement.classList.add('dark')
    syncColorScheme()

    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })
})

describe('initTelegram', () => {
  it('announces readiness, expands the sheet and follows theme changes', () => {
    const webApp = fakeWebApp({ colorScheme: 'dark' })
    window.Telegram = { WebApp: webApp }

    initTelegram()

    expect(webApp.ready).toHaveBeenCalled()
    expect(webApp.expand).toHaveBeenCalled()
    expect(webApp.onEvent).toHaveBeenCalledWith('themeChanged', expect.any(Function))
    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })

  it('does nothing in a plain browser', () => {
    expect(() => initTelegram()).not.toThrow()
  })
})
