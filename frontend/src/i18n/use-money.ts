import { useCallback } from 'react'

import { useT } from '@/i18n/use-i18n'
import { formatPrice } from '@/lib/format'

/**
 * Narxni interfeys tilidagi valyuta nomi bilan chizadi: «32 000 soʻm»,
 * «32 000 сум», «32 000 сўм». Raqam formati hamma tilda bir xil qoladi —
 * probel bilan ajratilgan minglik guruhlar Oʻzbekistonda odatiy koʻrinish.
 */
export function useMoney() {
  const t = useT()

  return useCallback((price: string | number) => formatPrice(price, t('soʻm')), [t])
}

/**
 * Tayyorlash vaqti: «4 daq (+2 daq)» / «4 мин (+2 мин)». Daqiqa qisqartmasi
 * tilga qarab oʻzgaradi, raqamlar esa oʻsha-oʻsha.
 */
export function usePrepTime() {
  const t = useT()

  return useCallback(
    (base: number, extra: number) =>
      extra > 0
        ? t(':minutes daq (+:extra daq)', { minutes: base, extra })
        : t(':minutes daq', { minutes: base }),
    [t],
  )
}
