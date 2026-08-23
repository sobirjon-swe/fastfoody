import { RefreshCw, Search } from 'lucide-react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { toast } from 'sonner'

import { listStaffOrders, updateStaffOrderStatus, type StaffOrder } from '@/api/staff-orders'
import { getStaffStatistics, type StatisticsWindow } from '@/api/statistics'
import { OutOfStockDialog } from '@/components/staff/OutOfStockDialog'
import { StaffOrderCard } from '@/components/staff/StaffOrderCard'
import { useAuth } from '@/auth/use-auth'
import { Spinner } from '@/components/Spinner'
import { StatisticsCards } from '@/components/StatisticsCards'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useT } from '@/i18n/use-i18n'
import { apiErrorMessage } from '@/lib/api'
import { POLL_MS, usePolling } from '@/lib/use-polling'
import type { TranslationKey } from '@/i18n/uz'
import { ORDER_STATUS_LABELS, type OrderStatus, type PaginationMeta } from '@/types/api'

/**
 * Ish taxtasining ustunlari. Mijoz javobi kutilayotgan buyurtma ham
 * "Tayyorlanmoqda" ustunida turadi — u oshxona uchun oʻsha bosqichning bir
 * qismi, alohida ustun ochish kerak emas.
 */
const BOARD_COLUMNS: { label: TranslationKey; statuses: OrderStatus[] }[] = [
  { label: 'Toʻlangan', statuses: ['tolov_qilindi'] },
  { label: 'Tayyorlanmoqda', statuses: ['tayyorlanmoqda', 'mijoz_qarori_kutilmoqda'] },
  { label: 'Tayyor', statuses: ['tayyor'] },
]

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

  // Ustunli koʻrinish faqat umumiy taxtada maʼnoli: bitta holat tanlangan
  // yoki kod boʻyicha qidirilgan boʻlsa, oddiy roʻyxat qulayroq.
  const board = filter === '' && code === ''

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
      ) : board ? (
        // Dizayndagi ish taxtasi: uchta ustun, har birida sanogʻi bilan.
        <div className="grid gap-4 lg:grid-cols-3">
          {BOARD_COLUMNS.map((column) => {
            const columnOrders = orders.filter((order) => column.statuses.includes(order.status))

            return (
              <section key={column.label} className="bg-muted/40 grid gap-3 rounded-2xl p-3">
                <h2 className="flex items-center justify-between px-1 text-sm font-medium">
                  {t(column.label)}
                  <span className="text-muted-foreground tabular-nums">{columnOrders.length}</span>
                </h2>

                {columnOrders.length === 0 ? (
                  <p className="text-muted-foreground px-1 pb-2 text-xs">{t('Boʻsh')}</p>
                ) : (
                  columnOrders.map((order) => (
                    <StaffOrderCard
                      key={order.id}
                      order={order}
                      busy={busyId === order.id}
                      onOutOfStock={setOutOfStockFor}
                      onMove={move}
                    />
                  ))
                )}
              </section>
            )
          })}
        </div>
      ) : (
        <div className="grid gap-3 md:grid-cols-2">
          {orders.map((order) => (
            <StaffOrderCard
              key={order.id}
              order={order}
              busy={busyId === order.id}
              onOutOfStock={setOutOfStockFor}
              onMove={move}
            />
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
