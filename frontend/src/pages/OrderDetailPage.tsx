import { ArrowLeft, Clock } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { toast } from 'sonner'

import { getOrder, payOrder } from '@/api/orders'
import { OrderStatusBadge } from '@/components/OrderStatusBadge'
import { OutOfStockDecision } from '@/components/OutOfStockDecision'
import { Spinner } from '@/components/Spinner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { apiErrorMessage } from '@/lib/api'
import { formatClock, formatPrice, minutesFromNow } from '@/lib/format'
import type { Order } from '@/types/api'

/**
 * Toʻlangan buyurtma uchun qatʼiy vaqt (ready_at), toʻlanmagani uchun jonli
 * baho (estimated_ready_at) koʻrsatiladi.
 */
function ReadyTimeCard({ order }: { order: Order }) {
  const time = order.ready_at ?? order.estimated_ready_at

  const hidden =
    !time ||
    order.status === 'olib_ketildi' ||
    order.status === 'mijoz_qarori_kutilmoqda' ||
    order.status.startsWith('bekor')

  if (hidden) {
    return null
  }

  return (
    <Card>
      <CardContent className="flex items-center gap-3">
        <Clock className="text-muted-foreground size-5" />
        <div>
          <div className="text-lg font-semibold" data-testid="ready-at">
            {formatClock(time)} da tayyor boʻladi
            <span className="text-muted-foreground ml-2 text-sm font-normal">
              (~{minutesFromNow(time)} daq)
            </span>
          </div>
          <p className="text-muted-foreground text-sm">
            {order.ready_at
              ? 'Oshxona navbatiga qoʻshildi. Shu vaqtga yetib boring — kechiksangiz taom sovib qolishi mumkin.'
              : 'Bu — taxminiy vaqt. Toʻlaganingizdan keyin buyurtma navbatga qoʻshiladi va vaqt qatʼiylashadi.'}
          </p>
        </div>
      </CardContent>
    </Card>
  )
}

export function OrderDetailPage() {
  const { orderId } = useParams()
  const [order, setOrder] = useState<Order | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [paying, setPaying] = useState(false)

  const id = Number(orderId)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)

    try {
      setOrder(await getOrder(id))
    } catch (caught) {
      setError(apiErrorMessage(caught, 'Buyurtmani yuklab boʻlmadi.'))
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    void load()
  }, [load])

  async function pay() {
    setPaying(true)

    try {
      setOrder(await payOrder(id))
      toast.success('Toʻlov qabul qilindi (simulyatsiya).')
    } catch (caught) {
      toast.error(apiErrorMessage(caught, 'Toʻlovni amalga oshirib boʻlmadi.'))
      void load()
    } finally {
      setPaying(false)
    }
  }

  if (loading) {
    return <Spinner />
  }

  if (!order) {
    return (
      <div className="grid gap-4">
        <Alert variant="destructive">
          <AlertDescription>{error ?? 'Buyurtma topilmadi.'}</AlertDescription>
        </Alert>
        <Button asChild variant="outline" className="w-fit">
          <Link to="/orders">
            <ArrowLeft /> Buyurtmalarim
          </Link>
        </Button>
      </div>
    )
  }

  return (
    <div className="grid max-w-2xl gap-6">
      <div>
        <Button asChild variant="ghost" size="sm" className="-ml-2 mb-2">
          <Link to="/orders">
            <ArrowLeft /> Buyurtmalarim
          </Link>
        </Button>
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-semibold">Buyurtma #{order.id}</h1>
          <OrderStatusBadge status={order.status} />
        </div>
        <p className="text-muted-foreground mt-1 text-sm">
          {order.restaurant?.name} · {order.restaurant?.address}
        </p>
      </div>

      {order.status === 'mijoz_qarori_kutilmoqda' && (
        <OutOfStockDecision order={order} onResolved={setOrder} />
      )}

      {order.status === 'muddati_otdi' && (
        <Alert>
          <AlertDescription>
            {order.paid_at
              ? 'Buyurtma belgilangan vaqtda olib ketilmagani uchun yopildi. Taom tayyorlangani sababli toʻlov qaytarilmaydi.'
              : 'Toʻlov qilinmagani uchun buyurtma bekor boʻldi. Xohlasangiz, yangisini bering.'}
          </AlertDescription>
        </Alert>
      )}

      {order.status === 'bekor_qilindi_mahsulot_yoq' && (
        <Alert>
          <AlertDescription>
            Buyurtma bekor qilindi, {formatPrice(order.total_price)} qaytariladi (MVP'da
            simulyatsiya).
          </AlertDescription>
        </Alert>
      )}

      <ReadyTimeCard order={order} />

      <Card>
        <CardHeader>
          <CardTitle>Tarkibi</CardTitle>
          <CardDescription>Tayyorlash vaqti: {order.prep_minutes} daqiqa</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-3">
          <ul className="grid gap-2 text-sm">
            {order.items?.map((item) => (
              <li key={item.id} className="flex justify-between gap-3">
                <span>
                  {item.name} × {item.quantity}
                  {item.is_out_of_stock && (
                    <span className="text-destructive ml-2 text-xs">tugadi</span>
                  )}
                </span>
                <span className="whitespace-nowrap">{formatPrice(item.line_total)}</span>
              </li>
            ))}
          </ul>

          <div className="flex justify-between border-t pt-3 font-medium">
            <span>Jami</span>
            <span data-testid="order-total">{formatPrice(order.total_price)}</span>
          </div>
        </CardContent>
      </Card>

      {order.status === 'kutilmoqda' && (
        <Card>
          <CardHeader>
            <CardTitle>Toʻlov</CardTitle>
            <CardDescription>
              MVP'da toʻlov simulyatsiya qilinadi — haqiqiy Payme/Click integratsiyasi keyingi
              bosqichlarda qoʻshiladi.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button onClick={pay} disabled={paying}>
              {paying ? 'Toʻlanmoqda...' : `${formatPrice(order.total_price)} toʻlash`}
            </Button>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
