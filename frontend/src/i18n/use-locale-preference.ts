import { useCallback } from 'react'
import { toast } from 'sonner'

import { updateProfile } from '@/api/account'
import { useAuth } from '@/auth/use-auth'
import type { Locale } from '@/i18n/locales'
import { useI18n, useT } from '@/i18n/use-i18n'
import { apiErrorMessage } from '@/lib/api'

/**
 * Tilni almashtiradi va kirgan foydalanuvchida uni hisobga ham yozadi.
 *
 * Bu bitta joyda turishi shart: ilgari saqlash faqat profil sahifasidagi
 * almashtirgichga ulangan edi, sarlavhadagisiga esa yoʻq. Natijada sarlavhadan
 * tanlangan til faqat brauzerda qolar, sahifa qayta yuklanganda `AuthProvider`
 * uni hisobdagi eski til bilan almashtirib qoʻyardi.
 */
export function useLocalePreference(): (locale: Locale) => Promise<void> {
  const { locale: current, setLocale } = useI18n()
  const { user, refresh } = useAuth()
  const t = useT()

  return useCallback(
    async (next: Locale) => {
      if (next === current) {
        return
      }

      // Interfeys darhol almashadi — soʻrov javobini kutib turmaydi.
      setLocale(next)

      if (!user) {
        return
      }

      try {
        await updateProfile({ locale: next })
        await refresh()
        toast.success(t('Til oʻzgartirildi.'))
      } catch (caught) {
        // Saqlanmasa ham interfeys almashgan, lekin keyingi yuklanishda
        // hisobdagi til qaytadi — shuning uchun jim qolib boʻlmaydi.
        setLocale(current)
        toast.error(apiErrorMessage(caught, t('Tilni oʻzgartirib boʻlmadi.')))
      }
    },
    [current, refresh, setLocale, t, user],
  )
}
