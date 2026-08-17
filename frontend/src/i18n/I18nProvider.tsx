import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'

import { en } from '@/i18n/en'
import { I18nContext, LOCALE_STORAGE_KEY, type I18nContextValue } from '@/i18n/i18n-context'
import { DEFAULT_LOCALE, isLocale, matchLocale, type Locale } from '@/i18n/locales'
import { ru } from '@/i18n/ru'
import { uz, type TranslationKey } from '@/i18n/uz'
import { uzCyrl } from '@/i18n/uz-cyrl'
import { setAcceptLanguage } from '@/lib/api'
import { getWebApp } from '@/lib/telegram'

const DICTIONARIES: Record<Locale, Record<TranslationKey, string>> = {
  uz,
  uz_Cyrl: uzCyrl,
  ru,
  en,
}

/**
 * Boshlangʻich til: saqlangan tanlov → Telegram tili → brauzer tili →
 * oʻzbekcha. Foydalanuvchi kirgach, profilidagi til ustun boʻladi.
 */
function initialLocale(): Locale {
  const stored = localStorage.getItem(LOCALE_STORAGE_KEY)

  if (isLocale(stored)) {
    return stored
  }

  const webApp = getWebApp()

  if (webApp?.initData) {
    // Telegram tilni initData ichidagi `user.language_code` da beradi.
    const match = /"language_code":"([^"]+)"/.exec(decodeURIComponent(webApp.initData))
    const fromTelegram = matchLocale(match?.[1])

    if (fromTelegram) {
      return fromTelegram
    }
  }

  return matchLocale(navigator.language) ?? DEFAULT_LOCALE
}

export function I18nProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>(initialLocale)

  // Backend xabarlari ham shu tilda kelishi uchun har soʻrovga sarlavha
  // qoʻshiladi va sahifa tili ekran oʻqigichlar uchun belgilanadi.
  useEffect(() => {
    setAcceptLanguage(locale.replace('_', '-'))
    document.documentElement.lang = locale === 'uz_Cyrl' ? 'uz-Cyrl' : locale
  }, [locale])

  const setLocale = useCallback((next: Locale) => {
    localStorage.setItem(LOCALE_STORAGE_KEY, next)
    setLocaleState(next)
  }, [])

  const t = useCallback<I18nContextValue['t']>(
    (key, params) => {
      const dictionary = DICTIONARIES[locale]
      // Tarjima tushib qolsa oʻzbekcha matn koʻrsatiladi — boʻsh joy emas.
      let text: string = dictionary[key] ?? uz[key] ?? key

      if (params) {
        for (const [name, value] of Object.entries(params)) {
          text = text.replaceAll(`:${name}`, String(value))
        }
      }

      return text
    },
    [locale],
  )

  const value = useMemo<I18nContextValue>(() => ({ locale, setLocale, t }), [locale, setLocale, t])

  return <I18nContext value={value}>{children}</I18nContext>
}
