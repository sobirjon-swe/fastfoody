import { Clock, PackageX, Phone, RefreshCw } from 'lucide-react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { toast } from 'sonner'

import { listStaffOrders, updateStaffOrderStatus, type StaffOrder } from '@/api/staff-orders'
import { OutOfStockDialog } from '@/components/staff/OutOfStockDialog'
import { useAuth } from '@/auth/use-auth'
import { OrderStatusBadge } from '@/components/OrderStatusBadge'
import { Spinner } from '@/components/Spinner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { apiErrorMessage } from '@/lib/api'
import { POLL_MS, usePolling } from '@/lib/use-polling'
import { formatClock, formatPrice, minutesFromNow } from '@/lib/format'
import { ORDER_STATUS_LABELS, type OrderStatus, type PaginationMeta } from '@/types/api'

const ACTIONS: Partial<Record<OrderStatus, string>> = {
  tayyorlanmoqda: 'Tayyorlashni boshlash',
  tayyor: 'Tayyor',
  olib_ketildi: 'Berildi',
  muddati_otdi: 'Kelmadi',
}

const FILTERS: { value: OrderStatus | ''; label: string }[] = [
  { value: '', label: 'Ish taxtasi' },
  { value: 'tolov_qilindi', label: 'Toʻlangan' },
  { value: 'tayyorlanmoqda', label: 'Tayyorlanmoqda' },
  { value: 'mijoz_qarori_kutilmoqda', label: 'Mijoz javobi kutilmoqda' },
  { value: 'tayyor', label: 'Tayyor' },
  { value: 'olib_ketildi', label: 'Berilgan' },
  { value: 'muddati_otdi', label: 'Muddati oʻtgan' },
]

export function StaffOrdersPage() {
  const { user } = useAuth()
  const [orders, setOrders] = useState<StaffOrder[]>([])
  const [filter, setFilter] = useState<OrderStatus | ''>('')
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [outOfStockFor, setOutOfStockFor] = useState<StaffOrder | null>(null)

  const requestRef = useRef(0)

  const load = useCallback(
    async (quiet = false) => {
      const requestId = ++requestRef.current

      if (!quiet) {
        setLoading(true)
      }

      try {
        const { orders: data, meta: pagination } = await listStaffOrders(filter, page)

        if (requestId === requestRef.current) {
          setOrders(data)
          setMeta(pagination)
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
    [filter, page],
  )

  useEffect(() => {
    void load()
  }, [load])

  // Jimgina yangilanish: taxta har 15 soniyada spinner koʻrsatib yonib-oʻchmaydi.
  usePolling(() => void load(true), POLL_MS)

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
            onClick={() => {
              setFilter(option.value)
              setPage(1)
            }}
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
                        {item.is_out_of_stock && (
                          <span className="text-destructive ml-2 text-xs font-normal">tugadi</span>
                        )}
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
                    {order.can_report_out_of_stock && (
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={busyId === order.id}
                        onClick={() => setOutOfStockFor(order)}
                      >
                        <PackageX /> Mahsulot tugadi
                      </Button>
                    )}
                    {order.next_statuses.map((next) => (
                      <Button
                        key={next}
                        size="sm"
                        variant={next === 'muddati_otdi' ? 'outline' : 'default'}
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

      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-muted-foreground">
            {meta.current_page}/{meta.last_page} — jami {meta.total} ta
          </span>
          <div className="flex gap-2">
            <Button
              size="sm"
              variant="outline"
              disabled={loading || page <= 1}
              onClick={() => setPage((current) => current - 1)}
            >
              Oldingi
            </Button>
            <Button
              size="sm"
              variant="outline"
              disabled={loading || page >= meta.last_page}
              onClick={() => setPage((current) => current + 1)}
            >
              Keyingi
            </Button>
          </div>
        </div>
      )}

      <OutOfStockDialog
        order={outOfStockFor}
        onOpenChange={(open) => !open && setOutOfStockFor(null)}
        onReported={() => load(true)}
      />
    </div>
  )
}
