import { ArrowLeft, Clock } from 'lucide-react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useParams } from 'react-router'
import { toast } from 'sonner'

import { getOrder, payOrder } from '@/api/orders'
import { OrderStatusBadge } from '@/components/OrderStatusBadge'
import { OutOfStockDecision } from '@/components/OutOfStockDecision'
import { Spinner } from '@/components/Spinner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useT } from '@/i18n/use-i18n'
import { useMoney } from '@/i18n/use-money'
import { apiErrorMessage } from '@/lib/api'
import { POLL_MS, usePolling } from '@/lib/use-polling'
import { formatClock, minutesFromNow } from '@/lib/format'
import { ORDER_STATUS_LABELS, type Order } from '@/types/api'

/**
 * Toʻlangan buyurtma uchun qatʼiy vaqt (ready_at), toʻlanmagani uchun jonli
 * baho (estimated_ready_at) koʻrsatiladi.
 */
function ReadyTimeCard({ order }: { order: Order }) {
  const t = useT()
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
            {t(':time da tayyor boʻladi', { time: formatClock(time) })}
            <span className="text-muted-foreground ml-2 text-sm font-normal">
              {t('(~:minutes daq)', { minutes: minutesFromNow(time) })}
            </span>
          </div>
          <p className="text-muted-foreground text-sm">
            {order.ready_at
              ? t(
                  'Oshxona navbatiga qoʻshildi. Shu vaqtga yetib boring — kechiksangiz taom sovib qolishi mumkin.',
                )
              : t(
                  'Bu — taxminiy vaqt. Toʻlaganingizdan keyin buyurtma navbatga qoʻshiladi va vaqt qatʼiylashadi.',
                )}
          </p>
        </div>
      </CardContent>
    </Card>
  )
}

export function OrderDetailPage() {
  const t = useT()
  const money = useMoney()
  const { orderId } = useParams()
  const [order, setOrder] = useState<Order | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [paying, setPaying] = useState(false)

  const id = Number(orderId)

  // Oxirgi koʻrilgan holat: fon yangilanishida oʻzgargani sezilsa, mijozga
  // xabar beriladi — mahsulot tugagani yoki taom tayyor boʻlgani shu tariqa
  // sahifani ochib turgan mijozga yetib boradi.
  const seenStatus = useRef<string | null>(null)

  const load = useCallback(
    async (quiet = false) => {
      if (!quiet) {
        setLoading(true)
      }

      try {
        const fresh = await getOrder(id)

        if (quiet && seenStatus.current && seenStatus.current !== fresh.status) {
          toast.info(
            t('Buyurtma holati: :status', { status: t(ORDER_STATUS_LABELS[fresh.status]) }),
          )
        }

        seenStatus.current = fresh.status
        setOrder(fresh)
        setError(null)
      } catch (caught) {
        setError(apiErrorMessage(caught, t('Buyurtmani yuklab boʻlmadi.')))
      } finally {
        setLoading(false)
      }
    },
    [id, t],
  )

  useEffect(() => {
    void load()
  }, [load, t])

  // Tugagan buyurtmani soʻrab turishning maʼnosi yoʻq.
  const live =
    order !== null &&
    !['olib_ketildi', 'bekor_qilindi_mahsulot_yoq', 'muddati_otdi'].includes(order.status)

  usePolling(() => void load(true), POLL_MS, live)

  async function pay() {
    setPaying(true)

    try {
      setOrder(await payOrder(id))
      toast.success(t('Toʻlov qabul qilindi (simulyatsiya).'))
    } catch (caught) {
      toast.error(apiErrorMessage(caught, t('Toʻlovni amalga oshirib boʻlmadi.')))
      void load(true)
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
          <AlertDescription>{error ?? t('Buyurtma topilmadi.')}</AlertDescription>
        </Alert>
        <Button asChild variant="outline" className="w-fit">
          <Link to="/orders">
            <ArrowLeft /> {t('Buyurtmalarim')}
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
            <ArrowLeft /> {t('Buyurtmalarim')}
          </Link>
        </Button>
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-semibold">{t('Buyurtma #:id', { id: order.id })}</h1>
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
              ? t(
                  'Buyurtma belgilangan vaqtda olib ketilmagani uchun yopildi. Taom tayyorlangani sababli toʻlov qaytarilmaydi.',
                )
              : t('Toʻlov qilinmagani uchun buyurtma bekor boʻldi. Xohlasangiz, yangisini bering.')}
          </AlertDescription>
        </Alert>
      )}

      {order.status === 'bekor_qilindi_mahsulot_yoq' && (
        <Alert>
          <AlertDescription>
            {t('Buyurtma bekor qilindi, :amount qaytariladi (simulyatsiya).', {
              amount: money(order.total_price),
            })}
          </AlertDescription>
        </Alert>
      )}

      {order.pickup_code && (
        <Card>
          <CardContent className="flex items-center justify-between gap-4">
            <div>
              <div className="text-muted-foreground text-sm">{t('Olib ketish kodi')}</div>
              <div className="text-3xl font-semibold tracking-widest" data-testid="pickup-code">
                {order.pickup_code}
              </div>
            </div>
            <p className="text-muted-foreground max-w-56 text-sm">
              {t('Oshxonaga kelganingizda shu kodni ayting.')}
            </p>
          </CardContent>
        </Card>
      )}

      <ReadyTimeCard order={order} />

      <Card>
        <CardHeader>
          <CardTitle>{t('Tarkibi')}</CardTitle>
          <CardDescription>
            {t('Tayyorlash vaqti: :minutes daqiqa', { minutes: order.prep_minutes })}
          </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-3">
          <ul className="grid gap-2 text-sm">
            {order.items?.map((item) => (
              <li key={item.id} className="flex justify-between gap-3">
                <span>
                  {item.name} × {item.quantity}
                  {item.is_out_of_stock && (
                    <span className="text-destructive ml-2 text-xs">{t('tugadi')}</span>
                  )}
                </span>
                <span className="whitespace-nowrap">{money(item.line_total)}</span>
              </li>
            ))}
          </ul>

          <div className="flex justify-between border-t pt-3 font-medium">
            <span>{t('Jami')}</span>
            <span data-testid="order-total">{money(order.total_price)}</span>
          </div>
        </CardContent>
      </Card>

      {order.status === 'kutilmoqda' && (
        <Card>
          <CardHeader>
            <CardTitle>{t('Toʻlov')}</CardTitle>
            <CardDescription>
              {t(
                "MVP'da toʻlov simulyatsiya qilinadi — haqiqiy Payme/Click integratsiyasi keyingi bosqichlarda qoʻshiladi.",
              )}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button onClick={pay} disabled={paying}>
              {paying
                ? t('Toʻlanmoqda...')
                : t(':amount toʻlash', { amount: money(order.total_price) })}
            </Button>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
