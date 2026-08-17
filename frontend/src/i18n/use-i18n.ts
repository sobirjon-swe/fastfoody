import { useContext } from 'react'

import { I18nContext, type I18nContextValue } from '@/i18n/i18n-context'

export function useI18n(): I18nContextValue {
  const context = useContext(I18nContext)

  if (!context) {
    throw new Error('useI18n I18nProvider ichida ishlatilishi kerak.')
  }

  return context
}

/** Faqat tarjima kerak boʻlganda qisqaroq yozuv. */
export function useT() {
  return useI18n().t
}
