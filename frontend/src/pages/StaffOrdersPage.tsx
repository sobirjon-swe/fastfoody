import { Clock, PackageX, Phone, RefreshCw, Search } from 'lucide-react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { toast } from 'sonner'

import { listStaffOrders, updateStaffOrderStatus, type StaffOrder } from '@/api/staff-orders'
import { getStaffStatistics, type StatisticsWindow } from '@/api/statistics'
import { OutOfStockDialog } from '@/components/staff/OutOfStockDialog'
import { useAuth } from '@/auth/use-auth'
import { OrderStatusBadge } from '@/components/OrderStatusBadge'
import { Spinner } from '@/components/Spinner'
import { StatisticsCards } from '@/components/StatisticsCards'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { useT } from '@/i18n/use-i18n'
import { useMoney } from '@/i18n/use-money'
import { apiErrorMessage } from '@/lib/api'
import { POLL_MS, usePolling } from '@/lib/use-polling'
import { formatClock, minutesFromNow } from '@/lib/format'
import type { TranslationKey } from '@/i18n/uz'
import { ORDER_STATUS_LABELS, type OrderStatus, type PaginationMeta } from '@/types/api'

/** Qiymatlar — tarjima kalitlari. */
const ACTIONS: Partial<Record<OrderStatus, TranslationKey>> = {
  tayyorlanmoqda: 'Tayyorlashni boshlash',
  tayyor: 'Tayyor',
  olib_ketildi: 'Berildi',
  muddati_otdi: 'Kelmadi',
}

const FILTERS: { value: OrderStatus | ''; label: TranslationKey }[] = [
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
  const t = useT()
  const money = useMoney()
  const [orders, setOrders] = useState<StaffOrder[]>([])
  const [filter, setFilter] = useState<OrderStatus | ''>('')
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [search, setSearch] = useState('')
  const [code, setCode] = useState('')
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [outOfStockFor, setOutOfStockFor] = useState<StaffOrder | null>(null)
  const [stats, setStats] = useState<{ today: StatisticsWindow; week: StatisticsWindow } | null>(
    null,
  )

  const requestRef = useRef(0)

  // Koʻrsatkichlar roʻyxatdan mustaqil yuklanadi: xatosi taxtani toʻsmasligi
  // uchun jimgina eʼtiborsiz qoldiriladi.
  const loadStats = useCallback(async () => {
    try {
      setStats(await getStaffStatistics())
    } catch {
      // Koʻrsatkichlar ikkinchi darajali — buyurtmalar baribir koʻrinadi.
    }
  }, [])

  const load = useCallback(
    async (quiet = false) => {
      const requestId = ++requestRef.current

      if (!quiet) {
        setLoading(true)
      }

      try {
        const { orders: data, meta: pagination } = await listStaffOrders(filter, page, code)

        if (requestId === requestRef.current) {
          setOrders(data)
          setMeta(pagination)
          setError(null)
        }
      } catch (caught) {
        if (requestId === requestRef.current) {
          setError(apiErrorMessage(caught, t('Buyurtmalarni yuklab boʻlmadi.')))
        }
      } finally {
        if (requestId === requestRef.current) {
          setLoading(false)
        }
      }
    },
    [filter, page, code, t],
  )

  useEffect(() => {
    void load()
  }, [load, t])

  useEffect(() => {
    void loadStats()
  }, [loadStats, t])

  // Jimgina yangilanish: taxta har 15 soniyada spinner koʻrsatib yonib-oʻchmaydi.
  usePolling(() => {
    void load(true)
    void loadStats()
  }, POLL_MS)

  async function move(order: StaffOrder, status: OrderStatus) {
    setBusyId(order.id)

    try {
      await updateStaffOrderStatus(order.id, status)
      toast.success(`#${order.id}: ${t(ORDER_STATUS_LABELS[status])}`)
      await load(true)
      void loadStats()
    } catch (caught) {
      toast.error(apiErrorMessage(caught, t('Holatni oʻzgartirib boʻlmadi.')))
      await load(true)
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div className="grid gap-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">{t('Buyurtmalar')}</h1>
          <p className="text-muted-foreground mt-1 text-sm">
            {t(':name · roʻyxat har 15 soniyada oʻzi yangilanadi.', {
              name: user?.restaurant?.name ?? '',
            })}
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={() => load()}>
          <RefreshCw /> {t('Yangilash')}
        </Button>
      </div>

      {stats && <StatisticsCards today={stats.today} week={stats.week} />}

      <form
        className="flex gap-2"
        onSubmit={(event) => {
          event.preventDefault()
          setCode(search.trim())
          setPage(1)
        }}
      >
        <Input
          className="w-44"
          placeholder={t('Olib ketish kodi')}
          inputMode="numeric"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
        />
        <Button type="submit" variant="secondary">
          <Search /> {t('Topish')}
        </Button>
        {code && (
          <Button
            type="button"
            variant="ghost"
            onClick={() => {
              setSearch('')
              setCode('')
            }}
          >
            {t('Tozalash')}
          </Button>
        )}
      </form>

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
            {t(option.label)}
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
          {code
            ? t('«:code» kodli buyurtma topilmadi.', { code })
            : filter === ''
              ? t('Hozircha yangi buyurtma yoʻq.')
              : t('Bu holatda buyurtma yoʻq.')}
        </p>
      ) : (
        <div className="grid gap-3 md:grid-cols-2">
          {orders.map((order) => (
            <Card key={order.id} data-testid={`order-${order.id}`}>
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
                      <Clock className="size-3" />
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
                          <span className="text-destructive ml-2 text-xs font-normal">
                            {t('tugadi')}
                          </span>
                        )}
                      </span>
                      <span className="text-muted-foreground whitespace-nowrap">
                        {money(item.line_total)}
                      </span>
                    </li>
                  ))}
                </ul>

                <div className="flex items-center justify-between gap-3 border-t pt-3">
                  <span className="font-medium">{money(order.total_price)}</span>
                  <div className="flex gap-2">
                    {order.can_report_out_of_stock && (
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={busyId === order.id}
                        onClick={() => setOutOfStockFor(order)}
                      >
                        <PackageX /> {t('Mahsulot tugadi')}
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
                        {t(ACTIONS[next] ?? ORDER_STATUS_LABELS[next])}
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
            {t(':current/:last — jami :total ta', {
              current: meta.current_page,
              last: meta.last_page,
              total: meta.total,
            })}
          </span>
          <div className="flex gap-2">
            <Button
              size="sm"
              variant="outline"
              disabled={loading || page <= 1}
              onClick={() => setPage((current) => current - 1)}
            >
              {t('Oldingi')}
            </Button>
            <Button
              size="sm"
              variant="outline"
              disabled={loading || page >= meta.last_page}
              onClick={() => setPage((current) => current + 1)}
            >
              {t('Keyingi')}
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
