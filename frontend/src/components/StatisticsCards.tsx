import { Card, CardContent } from '@/components/ui/card'
import { useT } from '@/i18n/use-i18n'
import { useMoney } from '@/i18n/use-money'

import type { StatisticsWindow } from '@/api/statistics'

/** Bugungi va haftalik koʻrsatkichlar bir qatorda. */
export function StatisticsCards({
  today,
  week,
}: {
  today: StatisticsWindow
  week: StatisticsWindow
}) {
  const t = useT()
  const money = useMoney()

  const tiles = [
    { label: t('Bugungi buyurtmalar'), value: String(today.orders) },
    { label: t('Bugungi tushum'), value: money(today.revenue) },
    {
      label: t('Oʻrtacha tayyorlash'),
      value: t(':minutes daq', { minutes: today.average_prep_minutes }),
    },
    { label: t('7 kunlik tushum'), value: money(week.revenue) },
  ]

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      {tiles.map((tile) => (
        <Card key={tile.label}>
          <CardContent className="grid gap-1">
            <span className="text-muted-foreground text-xs">{tile.label}</span>
            <span className="text-xl font-semibold">{tile.value}</span>
          </CardContent>
        </Card>
      ))}

      {(today.cancelled > 0 || today.expired > 0) && (
        <Card className="sm:col-span-2 lg:col-span-4">
          <CardContent className="text-muted-foreground text-sm">
            {t('Bugun bekor qilingan: :cancelled · muddati oʻtgan: :expired', {
              cancelled: today.cancelled,
              expired: today.expired,
            })}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
