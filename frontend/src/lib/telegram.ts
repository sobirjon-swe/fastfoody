/**
 * Telegram Mini App qobigʻi bilan ishlash. Ilova brauzerda ham, Telegram
 * ichida ham bir xil ishlaydi: bu yerdagi hamma funksiya Telegram yoʻq
 * boʻlganda jimgina hech narsa qilmaydi.
 */

interface TelegramButton {
  text: string
  isVisible: boolean
  show(): void
  hide(): void
  enable(): void
  disable(): void
  setText(text: string): void
  onClick(handler: () => void): void
  offClick(handler: () => void): void
  showProgress?(leaveActive?: boolean): void
  hideProgress?(): void
}

interface TelegramBackButton {
  isVisible: boolean
  show(): void
  hide(): void
  onClick(handler: () => void): void
  offClick(handler: () => void): void
}

export interface TelegramWebApp {
  initData: string
  colorScheme: 'light' | 'dark'
  MainButton: TelegramButton
  BackButton: TelegramBackButton
  HapticFeedback?: {
    impactOccurred(style: 'light' | 'medium' | 'heavy'): void
    notificationOccurred(type: 'error' | 'success' | 'warning'): void
  }
  ready(): void
  expand(): void
  onEvent(event: string, handler: () => void): void
  offEvent(event: string, handler: () => void): void
}

declare global {
  interface Window {
    Telegram?: { WebApp?: TelegramWebApp }
  }
}

export function getWebApp(): TelegramWebApp | null {
  return window.Telegram?.WebApp ?? null
}

/**
 * Telegram skripti oddiy brauzerda ham yuklanadi, lekin `initData` faqat
 * haqiqiy Mini App ichida boʻsh boʻlmaydi — kirish shunga qarab hal qilinadi.
 */
export function isTelegramMiniApp(): boolean {
  return Boolean(getWebApp()?.initData)
}

/** Telegram qorongʻi mavzuda boʻlsa, ilova ham qorongʻi palitraga oʻtadi. */
export function syncColorScheme(): void {
  const webApp = getWebApp()

  if (!webApp) {
    return
  }

  document.documentElement.classList.toggle('dark', webApp.colorScheme === 'dark')
}

/**
 * Ilova ochilganda bir marta chaqiriladi: Telegram'ga «tayyorman» deb
 * bildiradi, oynani toʻliq balandlikka yoyadi va mavzuni moslaydi.
 */
export function initTelegram(): void {
  const webApp = getWebApp()

  if (!webApp) {
    return
  }

  webApp.ready()
  webApp.expand()
  syncColorScheme()
  webApp.onEvent('themeChanged', syncColorScheme)
}

export function haptic(type: 'success' | 'error' | 'warning'): void {
  getWebApp()?.HapticFeedback?.notificationOccurred(type)
}
