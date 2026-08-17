import { createContext } from 'react'

import type { Locale } from '@/i18n/locales'
import type { TranslationKey } from '@/i18n/uz'

export type Translate = (key: TranslationKey, params?: Record<string, string | number>) => string

export interface I18nContextValue {
  locale: Locale
  /** Tilni almashtiradi va tanlovni brauzerda saqlaydi. */
  setLocale: (locale: Locale) => void
  t: Translate
}

export const I18nContext = createContext<I18nContextValue | null>(null)

export const LOCALE_STORAGE_KEY = 'fastfoody.locale'
