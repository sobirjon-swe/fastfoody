import { ArrowLeft, Clock, Minus, Plus, ShoppingCart } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { toast } from 'sonner'

import { estimateOrder, getRestaurantMenu, placeOrder } from '@/api/orders'
import { ImageBand } from '@/components/ImageBand'
import { OptionPickerDialog } from '@/components/OptionPickerDialog'
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

/**
 * Savatcha qatori. Bitta taom har xil modifikatorlar bilan bir necha marta
 * qoʻshilishi mumkin, shuning uchun kalit — taom id'si va tanlangan
 * variantlar birgalikda.
 */
interface CartLineState {
  key: string
  item: MenuItem
  quantity: number
  optionIds: number[]
}

function lineKey(itemId: number, optionIds: number[]): string {
  return [itemId, ...[...optionIds].sort((a, b) => a - b)].join(':')
}

export function RestaurantMenuPage() {
  const t = useT()
  const money = useMoney()
  const prepTime = usePrepTime()
  const { restaurantId } = useParams()
  const navigate = useNavigate()

  const [restaurant, setRestaurant] = useState<Restaurant | null>(null)
  const [menu, setMenu] = useState<MenuItem[]>([])
  const [cart, setCart] = useState<CartLineState[]>([])
  const [picking, setPicking] = useState<MenuItem | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [placing, setPlacing] = useState(false)
  const [estimate, setEstimate] = useState<OrderEstimate | null>(null)
  const [estimating, setEstimating] = useState(false)
  /** null — hali tanlanmagan, birinchi kategoriya koʻrsatiladi. */
  const [activeCategory, setActiveCategory] = useState<string | null>(null)

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

  /** Savatchadagi qator miqdorini oʻzgartiradi; nolga tushsa qator chiqadi. */
  function changeQuantity(key: string, delta: number) {
    setCart((current) =>
      current
        .map((line) =>
          line.key === key
            ? { ...line, quantity: Math.min(50, Math.max(0, line.quantity + delta)) }
            : line,
        )
        .filter((line) => line.quantity > 0),
    )
  }

  /**
   * Modifikatorli taom uchun avval tanlov oynasi ochiladi; oddiy taom
   * toʻgʻridan-toʻgʻri savatchaga tushadi.
   */
  function addToCart(item: MenuItem, optionIds: number[] = []) {
    const key = lineKey(item.id, optionIds)

    setCart((current) =>
      current.some((line) => line.key === key)
        ? current.map((line) =>
            line.key === key ? { ...line, quantity: Math.min(50, line.quantity + 1) } : line,
          )
        : [...current, { key, item, quantity: 1, optionIds }],
    )
  }

  function add(item: MenuItem) {
    if ((item.option_groups ?? []).length > 0) {
      setPicking(item)

      return
    }

    addToCart(item)
  }

  /** Shu taomning savatchadagi umumiy soni — kartadagi raqam uchun. */
  function quantityOf(item: MenuItem): number {
    return cart
      .filter((line) => line.item.id === item.id)
      .reduce((sum, line) => sum + line.quantity, 0)
  }

  /** Taomning oxirgi qatoridan bittasini olib tashlaydi. */
  function removeOne(item: MenuItem) {
    const last = [...cart].reverse().find((line) => line.item.id === item.id)

    if (last) {
      changeQuantity(last.key, -1)
    }
  }

  // Kategoriya boʻyicha guruhlash: nomsizlar oxirida, tartib menyudagidek.
  const groups = [
    ...menu.reduce((map, item) => {
      const key = item.category ?? ''

      return map.set(key, [...(map.get(key) ?? []), item])
    }, new Map<string, MenuItem[]>()),
  ].sort(([a], [b]) => (a === '' ? 1 : b === '' ? -1 : a.localeCompare(b)))

  /*
    Dizaynda kategoriyalar ustma-ust emas, tepadagi yorliqlar orqali
    almashtiriladi. Yorliqlar faqat rostdan bir nechta kategoriya boʻlsa
    chiqadi — bitta (yoki nomsiz) guruhda ular ortiqcha shovqin.
  */
  const hasTabs = groups.length > 1
  const activeGroup = hasTabs
    ? (groups.find(([category]) => category === activeCategory) ?? groups[0])
    : null
  const visibleGroups = activeGroup ? [activeGroup] : groups

  const lines = cart

  // Stable identity of the cart contents, so the estimate effect reruns on a
  // real change and not on every render.
  const cartKey = lines.map((line) => `${line.key}x${line.quantity}`).join(',')

  /** Bitta dona narxi: taom narxi + tanlangan variantlar. */
  function unitPrice(line: CartLineState): number {
    const extras = (line.item.option_groups ?? [])
      .flatMap((group) => group.options)
      .filter((option) => line.optionIds.includes(option.id))
      .reduce((sum, option) => sum + Number.parseFloat(option.price_delta), 0)

    return Number.parseFloat(line.item.price) + extras
  }

  /** Qatordagi tanlangan variantlar nomi: «Smetana · Pishloq». */
  function optionNames(line: CartLineState): string {
    return (line.item.option_groups ?? [])
      .flatMap((group) => group.options)
      .filter((option) => line.optionIds.includes(option.id))
      .map((option) => option.name)
      .join(' · ')
  }

  const total = lines.reduce((sum, line) => sum + unitPrice(line) * line.quantity, 0)

  // The ready time depends on the kitchen queue, so it is asked of the server
  // rather than guessed here. Debounced: tapping "+" five times must not fire
  // five requests, and a stale answer must never overwrite a newer one.
  useEffect(() => {
    if (lines.length === 0 || restaurant?.is_open_now === false) {
      setEstimate(null)

      return
    }

    const payload = lines.map((line) => ({
      menu_item_id: line.item.id,
      quantity: line.quantity,
      option_ids: line.optionIds,
    }))
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
        lines.map((line) => ({
          menu_item_id: line.item.id,
          quantity: line.quantity,
          option_ids: line.optionIds,
        })),
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
        {/* Dizayndagi muqova tasmasi: orqaga tugmasi rasm ustida turadi. */}
        <div className="bg-card overflow-hidden rounded-2xl shadow-sm">
          <ImageBand src={null} alt={restaurant.name} className="h-28">
            <Button
              asChild
              size="icon"
              variant="secondary"
              className="bg-card/90 absolute top-3 left-3 rounded-full shadow-sm"
            >
              <Link to="/" aria-label={t('Oshxonalar')}>
                <ArrowLeft />
              </Link>
            </Button>
          </ImageBand>

          <div className="p-4">
            <h1 className="text-xl font-semibold tracking-tight">{restaurant.name}</h1>
            <p className="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-1.5 text-sm">
              <span
                className={
                  restaurant.is_open_now
                    ? 'bg-success size-2 rounded-full'
                    : 'bg-muted-foreground/50 size-2 rounded-full'
                }
                aria-hidden
              />
              <span className={restaurant.is_open_now ? 'text-success font-medium' : undefined}>
                {restaurant.is_open_now ? t('Hozir ochiq') : t('Hozir yopiq')}
              </span>
              <span>
                · {restaurant.opens_at}–{restaurant.closes_at} · {restaurant.address}
              </span>
            </p>
          </div>
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

        {hasTabs && (
          <div
            role="tablist"
            aria-label={t('Menyu boʻlimlari')}
            className="border-border -mb-1 flex gap-5 overflow-x-auto border-b"
          >
            {groups.map(([category]) => {
              const active = activeGroup?.[0] === category

              return (
                <button
                  key={category}
                  type="button"
                  role="tab"
                  aria-selected={active}
                  onClick={() => setActiveCategory(category)}
                  className={
                    active
                      ? 'border-primary text-primary -mb-px shrink-0 border-b-2 pb-2.5 text-sm font-medium'
                      : 'text-muted-foreground hover:text-foreground -mb-px shrink-0 border-b-2 border-transparent pb-2.5 text-sm transition'
                  }
                >
                  {category || t('Boshqa')}
                </button>
              )
            })}
          </div>
        )}

        {menu.length === 0 ? (
          <p className="text-muted-foreground py-10 text-center text-sm">
            {t('Bu oshxonada hozircha mavjud taom yoʻq.')}
          </p>
        ) : (
          visibleGroups.map(([category, items]) => (
            <div className="grid gap-2" key={category}>
              {items.map((item) => {
                const quantity = quantityOf(item)
                const soldOut = item.is_available === false

                return (
                  <div
                    key={item.id}
                    className={
                      soldOut
                        ? 'bg-card flex items-center gap-3 rounded-2xl p-3 opacity-55 shadow-sm'
                        : 'bg-card flex items-center gap-3 rounded-2xl p-3 shadow-sm'
                    }
                  >
                    <ImageBand
                      src={item.image_url}
                      alt={item.name}
                      className="size-16 shrink-0 rounded-xl"
                    />

                    <div className="min-w-0 flex-1">
                      <div className="font-medium">{item.name}</div>
                      {item.description && (
                        <div className="text-muted-foreground truncate text-sm">
                          {item.description}
                        </div>
                      )}
                      <div className="mt-1 text-sm">
                        <span className="font-semibold">{money(item.price)}</span>
                        <span className="text-muted-foreground">
                          {' '}
                          · {prepTime(item.base_prep_minutes, item.extra_prep_minutes)}
                        </span>
                      </div>
                    </div>

                    {/*
                      Soni koʻrinadigan joyda faqat 0 dan katta boʻlganda
                      chiqadi, lekin testlar uchun u har doim oʻqilishi kerak.
                    */}
                    <span className="sr-only" data-testid={`qty-${item.id}`}>
                      {quantity}
                    </span>

                    {/*
                      Dizaynda savatda yoʻq taomda bitta "+ Qoʻshish" tugmasi
                      turadi; soni paydo boʻlgach −/son/+ boshqaruviga oʻtadi.
                    */}
                    {soldOut ? (
                      <span className="bg-muted text-muted-foreground shrink-0 rounded-full px-3 py-1.5 text-xs font-medium">
                        {t('Tugagan')}
                      </span>
                    ) : quantity === 0 ? (
                      <Button
                        size="sm"
                        className="shrink-0 rounded-full"
                        aria-label={t(':name qoʻshish', { name: item.name })}
                        onClick={() => add(item)}
                      >
                        <Plus /> {t('Qoʻshish')}
                      </Button>
                    ) : (
                      <div className="flex shrink-0 items-center gap-1.5">
                        <Button
                          size="icon"
                          variant="outline"
                          className="size-8 rounded-full"
                          aria-label={t(':name kamaytirish', { name: item.name })}
                          onClick={() => removeOne(item)}
                        >
                          <Minus />
                        </Button>
                        <span className="w-5 text-center font-medium tabular-nums" aria-hidden>
                          {quantity}
                        </span>
                        <Button
                          size="icon"
                          className="size-8 rounded-full"
                          aria-label={t(':name qoʻshish', { name: item.name })}
                          onClick={() => add(item)}
                        >
                          <Plus />
                        </Button>
                      </div>
                    )}
                  </div>
                )
              })}
            </div>
          ))
        )}
      </div>

      <OptionPickerDialog
        item={picking}
        onOpenChange={(open) => !open && setPicking(null)}
        onConfirm={(optionIds) => picking && addToCart(picking, optionIds)}
      />

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
                {lines.map((line) => (
                  <li key={line.key} className="flex justify-between gap-2">
                    <span>
                      {line.item.name} × {line.quantity}
                      {line.optionIds.length > 0 && (
                        <span className="text-muted-foreground block text-xs">
                          {optionNames(line)}
                        </span>
                      )}
                    </span>
                    <span className="whitespace-nowrap">
                      {money(unitPrice(line) * line.quantity)}
                    </span>
                  </li>
                ))}
              </ul>

              <div className="flex justify-between border-t pt-3 font-medium">
                <span>{t('Jami')}</span>
                <span data-testid="cart-total">{money(total)}</span>
              </div>

              <div
                className="bg-warning-muted/60 grid gap-1 rounded-xl p-3 text-sm"
                data-testid="estimate"
              >
                {estimate ? (
                  <>
                    <div className="text-warning-foreground flex items-center gap-2 font-medium">
                      <Clock className="size-4 shrink-0" />
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
