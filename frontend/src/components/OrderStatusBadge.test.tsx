import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import { OrderStatusBadge } from '@/components/OrderStatusBadge'
import { ORDER_STATUS_LABELS, type OrderStatus } from '@/types/api'

describe('OrderStatusBadge', () => {
  it('har bir holat uchun oʻzbekcha nom koʻrsatadi', () => {
    const statuses = Object.keys(ORDER_STATUS_LABELS) as OrderStatus[]

    for (const status of statuses) {
      const { unmount } = render(<OrderStatusBadge status={status} />)

      expect(screen.getByText(ORDER_STATUS_LABELS[status])).toBeDefined()
      unmount()
    }
  })

  it('bekor qilingan holat ogohlantiruvchi koʻrinishda', () => {
    render(<OrderStatusBadge status="bekor_qilindi_mahsulot_yoq" />)

    expect(screen.getByText(ORDER_STATUS_LABELS.bekor_qilindi_mahsulot_yoq).className)
      .toContain('destructive')
  })
})
