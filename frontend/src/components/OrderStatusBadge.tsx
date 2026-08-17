import { Badge } from '@/components/ui/badge'
import { useT } from '@/i18n/use-i18n'
import { ORDER_STATUS_LABELS, type OrderStatus } from '@/types/api'

const VARIANTS: Record<OrderStatus, 'default' | 'secondary' | 'outline' | 'destructive'> = {
  kutilmoqda: 'outline',
  tolov_qilindi: 'secondary',
  tayyorlanmoqda: 'secondary',
  tayyor: 'default',
  mijoz_qarori_kutilmoqda: 'destructive',
  olib_ketildi: 'outline',
  bekor_qilindi_mahsulot_yoq: 'destructive',
  muddati_otdi: 'destructive',
}

export function OrderStatusBadge({ status }: { status: OrderStatus }) {
  const t = useT()

  return <Badge variant={VARIANTS[status]}>{t(ORDER_STATUS_LABELS[status])}</Badge>
}
