import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router'

import { listMyOrders } from '@/api/orders'
import { OrderStatusBadge } from '@/components/OrderStatusBadge'
import { Spinner } from '@/components/Spinner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { apiErrorMessage } from '@/lib/api'
import { formatClock, formatPrice } from '@/lib/format'
import type { Order } from '@/types/api'

export function MyOrdersPage() {
  const [orders, setOrders] = useState<Order[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)

    try {
      setOrders(await listMyOrders())
    } catch (caught) {
      setError(apiErrorMessage(caught, 'Buyurtmalarni yuklab boʻlmadi.'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  return (
    <div className="grid gap-6">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Buyurtmalarim</h1>
          <p className="text-muted-foreground mt-1 text-sm">
            Holatni koʻrish uchun buyurtmani oching. Sahifani yangilab turing — jonli
            bildirishnoma keyingi bosqichlarda qoʻshiladi.
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={load}>
          Yangilash
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
          Hozircha buyurtma yoʻq. <Link to="/" className="underline">Oshxonalarni koʻring.</Link>
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
    </div>
  )
}
