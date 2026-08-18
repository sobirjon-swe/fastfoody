import { ArrowLeft, Clock, Minus, Plus, ShoppingCart } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { toast } from 'sonner'

import { estimateOrder, getRestaurantMenu, placeOrder } from '@/api/orders'
import { Spinner } from '@/components/Spinner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { apiErrorMessage } from '@/lib/api'
import { formatClock, minutesFromNow } from '@/lib/format'
import { useT } from '@/i18n/use-i18n'
import { useMoney, usePrepTime } from '@/i18n/use-money'
import { useTelegramBackButton, useTelegramMainButton } from '@/lib/use-telegram'
import type { MenuItem, OrderEstimate, Restaurant } from '@/types/api'

/** menu item id -> quantity */
type Cart = Record<number, number>

export function RestaurantMenuPage() {
  const t = useT()
  const money = useMoney()
  const prepTime = usePrepTime()
  const { restaurantId } = useParams()
  const navigate = useNavigate()

  const [restaurant, setRestaurant] = useState<Restaurant | null>(null)
  const [menu, setMenu] = useState<MenuItem[]>([])
  const [cart, setCart] = useState<Cart>({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [placing, setPlacing] = useState(false)
  const [estimate, setEstimate] = useState<OrderEstimate | null>(null)
  const [estimating, setEstimating] = useState(false)

  const id = Number(restaurantId)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)

    try {
      const data = await getRestaurantMenu(id)

      setRestaurant(data.restaurant)
      setMenu(data.menu_items)
    } catch (caught) {
      setError(apiErrorMessage(caught, t('Menyuni yuklab boʻlmadi.')))
    } finally {
      setLoading(false)
    }
  }, [id, t])

  useEffect(() => {
    void load()
  }, [load, t])

  function changeQuantity(item: MenuItem, delta: number) {
    setCart((current) => {
      const quantity = Math.min(50, Math.max(0, (current[item.id] ?? 0) + delta))
      const next = { ...current }

      if (quantity === 0) {
        delete next[item.id]
      } else {
        next[item.id] = quantity
      }

      return next
    })
  }

  // Kategoriya boʻyicha guruhlash: nomsizlar oxirida, tartib menyudagidek.
  const groups = [
    ...menu.reduce((map, item) => {
      const key = item.category ?? ''

      return map.set(key, [...(map.get(key) ?? []), item])
    }, new Map<string, MenuItem[]>()),
  ].sort(([a], [b]) => (a === '' ? 1 : b === '' ? -1 : a.localeCompare(b)))

  const lines = menu
    .filter((item) => cart[item.id])
    .map((item) => ({ item, quantity: cart[item.id] }))

  // Stable identity of the cart contents, so the estimate effect reruns on a
  // real change and not on every render.
  const cartKey = lines.map((line) => `${line.item.id}:${line.quantity}`).join(',')

  const total = lines.reduce(
    (sum, line) => sum + Number.parseFloat(line.item.price) * line.quantity,
    0,
  )

  // The ready time depends on the kitchen queue, so it is asked of the server
  // rather than guessed here. Debounced: tapping "+" five times must not fire
  // five requests, and a stale answer must never overwrite a newer one.
  useEffect(() => {
    if (lines.length === 0 || restaurant?.is_open_now === false) {
      setEstimate(null)

      return
    }

    const payload = lines.map((line) => ({ menu_item_id: line.item.id, quantity: line.quantity }))
    let current = true

    setEstimating(true)

    const timer = setTimeout(() => {
      estimateOrder(id, payload)
        .then((result) => current && setEstimate(result))
        .catch(() => current && setEstimate(null))
        .finally(() => current && setEstimating(false))
    }, 350)

    return () => {
      current = false
      clearTimeout(timer)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id, cartKey, restaurant?.is_open_now, t])

  async function submit() {
    setPlacing(true)

    try {
      const order = await placeOrder(
        id,
        lines.map((line) => ({ menu_item_id: line.item.id, quantity: line.quantity })),
      )

      toast.success(t('Buyurtma qabul qilindi.'))
      navigate(`/orders/${order.id}`)
    } catch (caught) {
      const message = apiErrorMessage(caught, t('Buyurtma berishda xatolik yuz berdi.'))

      setError(message)
      toast.error(message)
      // The menu may have changed under us (an item ran out), so reload it.
      void load()
    } finally {
      setPlacing(false)
    }
  }

  // Telegram ichida buyurtma tugmasi pastdagi asosiy tugmaga chiqadi —
  // barmoq yetadigan joyda. Brauzerda kartadagi tugma oʻz oʻrnida qoladi.
  useTelegramMainButton({
    text: restaurant?.is_open_now === false ? t('Oshxona yopiq') : t('Buyurtma berish'),
    visible: lines.length > 0,
    disabled: placing || restaurant?.is_open_now === false,
    onClick: submit,
  })

  useTelegramBackButton(() => navigate('/'))

  if (loading) {
    return <Spinner />
  }

  if (!restaurant) {
    return (
      <div className="grid gap-4">
        <Alert variant="destructive">
          <AlertDescription>{error ?? t('Oshxona topilmadi.')}</AlertDescription>
        </Alert>
        <Button asChild variant="outline" className="w-fit">
          <Link to="/">
            <ArrowLeft /> {t('Oshxonalar')}
          </Link>
        </Button>
      </div>
    )
  }

  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
      <div className="grid gap-4">
        <div>
          <Button asChild variant="ghost" size="sm" className="-ml-2 mb-2">
            <Link to="/">
              <ArrowLeft /> {t('Oshxonalar')}
            </Link>
          </Button>
          <h1 className="text-2xl font-semibold">{restaurant.name}</h1>
          <p className="text-muted-foreground mt-1 text-sm">
            {restaurant.address} · {restaurant.opens_at}–{restaurant.closes_at}
          </p>
        </div>

        {!restaurant.is_open_now && (
          <Alert>
            <AlertDescription>
              {t('Oshxona hozir yopiq. Buyurtma faqat :opens–:closes oraligʻida qabul qilinadi.', {
                opens: restaurant.opens_at,
                closes: restaurant.closes_at,
              })}
            </AlertDescription>
          </Alert>
        )}

        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {menu.length === 0 ? (
          <p className="text-muted-foreground py-10 text-center text-sm">
            {t('Bu oshxonada hozircha mavjud taom yoʻq.')}
          </p>
        ) : (
          groups.map(([category, items]) => (
            <div className="grid gap-3" key={category}>
              {category && (
                <h2 className="text-muted-foreground mt-2 text-sm font-medium uppercase">
                  {category}
                </h2>
              )}
              {items.map((item) => (
                <Card key={item.id}>
                  <CardContent className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                      {item.image_url && (
                        <img
                          src={item.image_url}
                          alt={item.name}
                          className="size-16 shrink-0 rounded-md object-cover"
                          loading="lazy"
                        />
                      )}
                      <div>
                        <div className="font-medium">{item.name}</div>
                        {item.description && (
                          <div className="text-muted-foreground text-sm">{item.description}</div>
                        )}
                        <div className="text-muted-foreground mt-1 text-sm">
                          {money(item.price)} ·{' '}
                          {prepTime(item.base_prep_minutes, item.extra_prep_minutes)}
                        </div>
                      </div>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                      <Button
                        size="icon"
                        variant="outline"
                        aria-label={t(':name kamaytirish', { name: item.name })}
                        disabled={!cart[item.id]}
                        onClick={() => changeQuantity(item, -1)}
                      >
                        <Minus />
                      </Button>
                      <span className="w-6 text-center tabular-nums" data-testid={`qty-${item.id}`}>
                        {cart[item.id] ?? 0}
                      </span>
                      <Button
                        size="icon"
                        variant="outline"
                        aria-label={t(':name qoʻshish', { name: item.name })}
                        onClick={() => changeQuantity(item, 1)}
                      >
                        <Plus />
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          ))
        )}
      </div>

      <Card className="h-fit lg:sticky lg:top-6">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <ShoppingCart className="size-4" /> {t('Savatcha')}
          </CardTitle>
        </CardHeader>
        <CardContent className="grid gap-3">
          {lines.length === 0 ? (
            <p className="text-muted-foreground text-sm">{t('Savatcha boʻsh.')}</p>
          ) : (
            <>
              <ul className="grid gap-2 text-sm">
                {lines.map(({ item, quantity }) => (
                  <li key={item.id} className="flex justify-between gap-2">
                    <span>
                      {item.name} × {quantity}
                    </span>
                    <span className="whitespace-nowrap">
                      {money(Number.parseFloat(item.price) * quantity)}
                    </span>
                  </li>
                ))}
              </ul>

              <div className="flex justify-between border-t pt-3 font-medium">
                <span>{t('Jami')}</span>
                <span data-testid="cart-total">{money(total)}</span>
              </div>

              <div className="bg-muted/50 grid gap-1 rounded-md p-3 text-sm" data-testid="estimate">
                {estimate ? (
                  <>
                    <div className="flex items-center gap-2 font-medium">
                      <Clock className="size-4" />
                      {t('Taxminan :time da tayyor', { time: formatClock(estimate.ready_at) })}
                      <span className="text-muted-foreground font-normal">
                        {t('(~:minutes daq)', { minutes: minutesFromNow(estimate.ready_at) })}
                      </span>
                    </div>
                    <p className="text-muted-foreground text-xs">
                      {t('Tayyorlash :minutes daq', { minutes: estimate.prep_minutes })}
                      {estimate.queue_minutes > 0
                        ? ` · ${t('oldingizda :minutes daqiqalik navbat bor', {
                            minutes: estimate.queue_minutes,
                          })}`
                        : ` · ${t('navbat boʻsh')}`}
                    </p>
                  </>
                ) : (
                  <span className="text-muted-foreground text-xs">
                    {estimating ? t('Vaqt hisoblanmoqda...') : t('Vaqt hisoblab boʻlinmadi.')}
                  </span>
                )}
              </div>
            </>
          )}

          <Button
            disabled={lines.length === 0 || placing || !restaurant.is_open_now}
            onClick={submit}
          >
            {restaurant.is_open_now
              ? placing
                ? t('Yuborilmoqda...')
                : t('Buyurtma berish')
              : t('Oshxona yopiq')}
          </Button>
        </CardContent>
      </Card>
    </div>
  )
}
