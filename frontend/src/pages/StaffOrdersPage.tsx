import { Clock, Phone, RefreshCw } from 'lucide-react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { toast } from 'sonner'

import { listStaffOrders, updateStaffOrderStatus, type StaffOrder } from '@/api/staff-orders'
import { useAuth } from '@/auth/use-auth'
import { OrderStatusBadge } from '@/components/OrderStatusBadge'
import { Spinner } from '@/components/Spinner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { apiErrorMessage } from '@/lib/api'
import { formatClock, formatPrice, minutesFromNow } from '@/lib/format'
import { ORDER_STATUS_LABELS, type OrderStatus } from '@/types/api'

/** MVP'da jonli bildirishnoma yoʻq, shuning uchun taxta shu oraliqda yangilanadi. */
const POLL_MS = 15_000

const ACTIONS: Partial<Record<OrderStatus, string>> = {
  tayyorlanmoqda: 'Tayyorlashni boshlash',
  tayyor: 'Tayyor',
  olib_ketildi: 'Berildi',
}

const FILTERS: { value: OrderStatus | ''; label: string }[] = [
  { value: '', label: 'Ish taxtasi' },
  { value: 'tolov_qilindi', label: 'Toʻlangan' },
  { value: 'tayyorlanmoqda', label: 'Tayyorlanmoqda' },
  { value: 'tayyor', label: 'Tayyor' },
  { value: 'olib_ketildi', label: 'Berilgan' },
]

export function StaffOrdersPage() {
  const { user } = useAuth()
  const [orders, setOrders] = useState<StaffOrder[]>([])
  const [filter, setFilter] = useState<OrderStatus | ''>('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)

  const requestRef = useRef(0)

  const load = useCallback(
    async (quiet = false) => {
      const requestId = ++requestRef.current

      if (!quiet) {
        setLoading(true)
      }

      try {
        const data = await listStaffOrders(filter)

        if (requestId === requestRef.current) {
          setOrders(data)
          setError(null)
        }
      } catch (caught) {
        if (requestId === requestRef.current) {
          setError(apiErrorMessage(caught, 'Buyurtmalarni yuklab boʻlmadi.'))
        }
      } finally {
        if (requestId === requestRef.current) {
          setLoading(false)
        }
      }
    },
    [filter],
  )

  useEffect(() => {
    void load()
  }, [load])

  // Polling: quiet refreshes, so the board does not flash a spinner every 15s.
  useEffect(() => {
    const timer = setInterval(() => void load(true), POLL_MS)

    return () => clearInterval(timer)
  }, [load])

  async function move(order: StaffOrder, status: OrderStatus) {
    setBusyId(order.id)

    try {
      await updateStaffOrderStatus(order.id, status)
      toast.success(`#${order.id}: ${ORDER_STATUS_LABELS[status]}`)
      await load(true)
    } catch (caught) {
      toast.error(apiErrorMessage(caught, 'Holatni oʻzgartirib boʻlmadi.'))
      await load(true)
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div className="grid gap-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Buyurtmalar</h1>
          <p className="text-muted-foreground mt-1 text-sm">
            {user?.restaurant?.name} · roʻyxat har 15 soniyada oʻzi yangilanadi.
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={() => load()}>
          <RefreshCw /> Yangilash
        </Button>
      </div>

      <div className="flex flex-wrap gap-1">
        {FILTERS.map((option) => (
          <Button
            key={option.value || 'board'}
            size="sm"
            variant={filter === option.value ? 'default' : 'outline'}
            onClick={() => setFilter(option.value)}
          >
            {option.label}
          </Button>
        ))}
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {loading ? (
        <Spinner />
      ) : orders.length === 0 ? (
        <p className="text-muted-foreground py-10 text-center text-sm">
          {filter === '' ? 'Hozircha yangi buyurtma yoʻq.' : 'Bu holatda buyurtma yoʻq.'}
        </p>
      ) : (
        <div className="grid gap-3 md:grid-cols-2">
          {orders.map((order) => (
            <Card key={order.id} data-testid={`order-${order.id}`}>
              <CardHeader>
                <CardTitle className="flex flex-wrap items-center justify-between gap-2">
                  <span>Buyurtma #{order.id}</span>
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
                      <Clock className="size-3" />
                      {formatClock(order.ready_at)} ga tayyor boʻlishi kerak (~
                      {minutesFromNow(order.ready_at)} daq) · {order.prep_minutes} daq ish
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
                      </span>
                      <span className="text-muted-foreground whitespace-nowrap">
                        {formatPrice(item.line_total)}
                      </span>
                    </li>
                  ))}
                </ul>

                <div className="flex items-center justify-between gap-3 border-t pt-3">
                  <span className="font-medium">{formatPrice(order.total_price)}</span>
                  <div className="flex gap-2">
                    {order.next_statuses.map((next) => (
                      <Button
                        key={next}
                        size="sm"
                        disabled={busyId === order.id}
                        onClick={() => move(order, next)}
                      >
                        {ACTIONS[next] ?? ORDER_STATUS_LABELS[next]}
                      </Button>
                    ))}
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
