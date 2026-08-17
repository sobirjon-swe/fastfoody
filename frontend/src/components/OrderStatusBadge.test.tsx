import { render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'

import { OrderStatusBadge } from '@/components/OrderStatusBadge'
import { I18nProvider } from '@/i18n/I18nProvider'
import { ru } from '@/i18n/ru'
import { ORDER_STATUS_LABELS, type OrderStatus } from '@/types/api'

// jsdom brauzeri inglizcha, shuning uchun til aniq belgilanadi: aks holda
// testlar qaysi tilda yurishi muhitga bogʻliq boʻlib qolardi.
beforeEach(() => localStorage.setItem('fastfoody.locale', 'uz'))
afterEach(() => localStorage.clear())

/** Komponent tarjima kontekstini talab qiladi. */
function renderBadge(status: OrderStatus) {
  return render(
    <I18nProvider>
      <OrderStatusBadge status={status} />
    </I18nProvider>,
  )
}

describe('OrderStatusBadge', () => {
  it('har bir holat uchun oʻzbekcha nom koʻrsatadi', () => {
    const statuses = Object.keys(ORDER_STATUS_LABELS) as OrderStatus[]

    for (const status of statuses) {
      const { unmount } = renderBadge(status)

      expect(screen.getByText(ORDER_STATUS_LABELS[status])).toBeDefined()
      unmount()
    }
  })

  it('til almashsa yorliq ham tarjima qilinadi', () => {
    localStorage.setItem('fastfoody.locale', 'ru')
    renderBadge('tayyor')

    expect(screen.getByText(ru['Tayyor'])).toBeDefined()
  })

  it('bekor qilingan holat ogohlantiruvchi koʻrinishda', () => {
    renderBadge('bekor_qilindi_mahsulot_yoq')

    expect(screen.getByText(ORDER_STATUS_LABELS.bekor_qilindi_mahsulot_yoq).className).toContain(
      'destructive',
    )
  })
})
