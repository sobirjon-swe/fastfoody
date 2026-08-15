import { Card, CardContent } from '@/components/ui/card'
import { formatPrice } from '@/lib/format'
import type { StatisticsWindow } from '@/api/statistics'

/** Bugungi va haftalik koʻrsatkichlar bir qatorda. */
export function StatisticsCards({
  today,
  week,
}: {
  today: StatisticsWindow
  week: StatisticsWindow
}) {
  const tiles = [
    { label: 'Bugungi buyurtmalar', value: String(today.orders) },
    { label: 'Bugungi tushum', value: formatPrice(today.revenue) },
    { label: 'Oʻrtacha tayyorlash', value: `${today.average_prep_minutes} daq` },
    { label: '7 kunlik tushum', value: formatPrice(week.revenue) },
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
            Bugun bekor qilingan: {today.cancelled} · muddati oʻtgan: {today.expired}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
