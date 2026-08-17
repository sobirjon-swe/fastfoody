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
