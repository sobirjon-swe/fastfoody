import { Clock, PackageX, Phone } from 'lucide-react'

import type { StaffOrder } from '@/api/staff-orders'
import { OrderStatusBadge } from '@/components/OrderStatusBadge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { TranslationKey } from '@/i18n/uz'
import { useT } from '@/i18n/use-i18n'
import { useMoney } from '@/i18n/use-money'
import { formatClock, minutesFromNow } from '@/lib/format'
import { ORDER_STATUS_LABELS, type OrderStatus } from '@/types/api'

/** Qiymatlar — tarjima kalitlari. */
const ACTIONS: Partial<Record<OrderStatus, TranslationKey>> = {
  tayyorlanmoqda: 'Tayyorlashni boshlash',
  tayyor: 'Tayyor',
  olib_ketildi: 'Berildi',
  muddati_otdi: 'Kelmadi',
}

interface StaffOrderCardProps {
  order: StaffOrder
  busy: boolean
  onOutOfStock: (order: StaffOrder) => void
  onMove: (order: StaffOrder, status: OrderStatus) => void
}

export function StaffOrderCard({ order, busy, onOutOfStock, onMove }: StaffOrderCardProps) {
  const t = useT()
  const money = useMoney()

  // Mijozning javobi kutilayotgan buyurtma dizaynda sariq ramka bilan
  // ajratiladi — oshxona uni oʻzi hal qila olmaydi, koʻzga tashlanishi kerak.
  const waiting = order.status === 'mijoz_qarori_kutilmoqda'

  return (
    <Card
      data-testid={`order-${order.id}`}
      className={waiting ? 'border-warning bg-warning-muted/30' : undefined}
    >
      <CardHeader>
        <CardTitle className="flex flex-wrap items-center justify-between gap-2">
          <span className="flex items-center gap-2">
            {t('Buyurtma #:id', { id: order.id })}
            {order.pickup_code && (
              <span className="bg-muted rounded px-2 py-0.5 font-mono text-base tracking-widest">
                {order.pickup_code}
              </span>
            )}
          </span>
          <OrderStatusBadge status={order.status} />
        </CardTitle>
        <div className="text-muted-foreground grid gap-1 text-sm">
          <span>
            {order.customer.name}
            {order.customer.phone && (
              <span className="ml-2 inline-flex items-center gap-1">
                <Phone className="size-3" />
                {order.customer.phone}
              </span>
            )}
          </span>
          {order.ready_at && (
            <span className="flex items-center gap-1">
              <Clock className="size-3 shrink-0" />
              {t(':time ga tayyor boʻlishi kerak (~:minutes daq) · :prep daq ish', {
                time: formatClock(order.ready_at),
                minutes: minutesFromNow(order.ready_at),
                prep: order.prep_minutes,
              })}
            </span>
          )}
        </div>
      </CardHeader>
      <CardContent className="grid gap-3">
        <ul className="grid gap-1 text-sm">
          {order.items?.map((item) => (
            <li key={item.id} className="flex justify-between gap-3">
              <span className="font-medium">
                {item.quantity} × {item.name}
                {item.options && item.options.length > 0 && (
                  <span className="text-muted-foreground block text-xs font-normal">
                    {item.options.map((option) => option.name).join(' · ')}
                  </span>
                )}
                {item.is_out_of_stock && (
                  <span className="text-destructive ml-2 text-xs font-normal">{t('tugadi')}</span>
                )}
              </span>
              <span className="text-muted-foreground whitespace-nowrap">
                {money(item.line_total)}
              </span>
            </li>
          ))}
        </ul>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-3">
          <span className="font-medium">{money(order.total_price)}</span>
          <div className="flex flex-wrap gap-2">
            {order.can_report_out_of_stock && (
              <Button size="sm" variant="outline" disabled={busy} onClick={() => onOutOfStock(order)}>
                <PackageX /> {t('Mahsulot tugadi')}
              </Button>
            )}
            {order.next_statuses.map((next) => (
              <Button
                key={next}
                size="sm"
                variant={next === 'muddati_otdi' ? 'outline' : 'default'}
                disabled={busy}
                onClick={() => onMove(order, next)}
              >
                {t(ACTIONS[next] ?? ORDER_STATUS_LABELS[next])}
              </Button>
            ))}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
