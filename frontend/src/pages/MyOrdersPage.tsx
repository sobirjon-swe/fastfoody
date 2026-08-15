import { RefreshCw } from 'lucide-react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { Link } from 'react-router'

import { listMyOrders } from '@/api/orders'
import { OrderStatusBadge } from '@/components/OrderStatusBadge'
import { Spinner } from '@/components/Spinner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { apiErrorMessage } from '@/lib/api'
import { formatClock, formatPrice } from '@/lib/format'
import { POLL_MS, usePolling } from '@/lib/use-polling'
import type { Order, PaginationMeta } from '@/types/api'

export function MyOrdersPage() {
  const [orders, setOrders] = useState<Order[]>([])
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  // Faqat eng oxirgi soʻrov roʻyxatga yozadi.
  const requestRef = useRef(0)

  const load = useCallback(
    async (quiet = false) => {
      const requestId = ++requestRef.current

      if (!quiet) {
        setLoading(true)
      }

      try {
        const { orders: data, meta: pagination } = await listMyOrders(page)

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
    [page],
  )

  useEffect(() => {
    void load()
  }, [load])

  usePolling(() => void load(true), POLL_MS)

  return (
    <div className="grid gap-6">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Buyurtmalarim</h1>
          <p className="text-muted-foreground mt-1 text-sm">
            Holat oʻzi yangilanib turadi. Batafsil koʻrish uchun buyurtmani oching.
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={() => load()}>
          <RefreshCw /> Yangilash
        </Button>
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
          Hozircha buyurtma yoʻq.{' '}
          <Link to="/" className="underline">
            Oshxonalarni koʻring.
          </Link>
        </p>
      ) : (
        <div className="grid gap-3">
          {orders.map((order) => (
            <Card key={order.id}>
              <CardContent className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <div className="font-medium">
                    #{order.id} · {order.restaurant?.name ?? 'Oshxona'}
                  </div>
                  <div className="text-muted-foreground text-sm">
                    {new Date(order.created_at).toLocaleString('uz-UZ')} ·{' '}
                    {formatPrice(order.total_price)}
                  </div>
                  {(order.ready_at ?? order.estimated_ready_at) && (
                    <div className="mt-1 text-sm">
                      {order.ready_at ? 'Tayyor boʻladi' : 'Taxminan'}:{' '}
                      <span className="font-medium">
                        {formatClock(order.ready_at ?? order.estimated_ready_at!)}
                      </span>
                    </div>
                  )}
                </div>
                <div className="flex items-center gap-3">
                  <OrderStatusBadge status={order.status} />
                  <Button asChild size="sm" variant="outline">
                    <Link to={`/orders/${order.id}`}>Batafsil</Link>
                  </Button>
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
    </div>
  )
}
