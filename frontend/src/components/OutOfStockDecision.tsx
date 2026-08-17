import { PackageX } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { toast } from 'sonner'

import { cancelOrder, getRestaurantMenu, replaceOrderItem } from '@/api/orders'
import { Spinner } from '@/components/Spinner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useT } from '@/i18n/use-i18n'
import { useMoney } from '@/i18n/use-money'
import { apiErrorMessage } from '@/lib/api'
import { formatPrepTime } from '@/lib/format'
import type { MenuItem, Order } from '@/types/api'

/**
 * Mahsulot tugaganda mijozning tanlovi: tugagan taomni boshqasiga almashtirish
 * yoki buyurtmani bekor qilib pulni qaytarib olish.
 */
export function OutOfStockDecision({
  order,
  onResolved,
}: {
  order: Order
  onResolved: (order: Order) => void
}) {
  const t = useT()
  const money = useMoney()
  const missing = order.items?.find((item) => item.is_out_of_stock)

  const [menu, setMenu] = useState<MenuItem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const restaurantId = order.restaurant_id

  const load = useCallback(async () => {
    setLoading(true)

    try {
      const data = await getRestaurantMenu(restaurantId)

      setMenu(data.menu_items)
    } catch (caught) {
      setError(apiErrorMessage(caught, t('Menyuni yuklab boʻlmadi.')))
    } finally {
      setLoading(false)
    }
  }, [restaurantId, t])

  useEffect(() => {
    void load()
  }, [load, t])

  async function replace(menuItem: MenuItem) {
    if (!missing) {
      return
    }

    setBusy(true)
    setError(null)

    try {
      const updated = await replaceOrderItem(order.id, missing.id, menuItem.id)

      toast.success(`«${missing.name}» oʻrniga «${menuItem.name}» tanlandi.`)
      onResolved(updated)
    } catch (caught) {
      setError(apiErrorMessage(caught, t('Almashtirib boʻlmadi.')))
      void load()
    } finally {
      setBusy(false)
    }
  }

  async function cancel() {
    setBusy(true)
    setError(null)

    try {
      const updated = await cancelOrder(order.id)

      toast.success(t('Buyurtma bekor qilindi, pul qaytariladi.'))
      onResolved(updated)
    } catch (caught) {
      setError(apiErrorMessage(caught, t('Bekor qilib boʻlmadi.')))
    } finally {
      setBusy(false)
    }
  }

  // Tugagan taomning oʻzi almashtirish roʻyxatida koʻrinmasligi kerak.
  const alternatives = menu.filter((item) => item.id !== missing?.menu_item_id)

  return (
    <Card className="border-destructive/50">
      <CardHeader>
        <CardTitle className="text-destructive flex items-center gap-2">
          <PackageX className="size-5" />
          {t('«:name» tugab qolibdi', { name: missing?.name ?? '' })}
        </CardTitle>
        <CardDescription>
          {t(
            'Kechirasiz, oshxonada bu taom qolmagan. Boshqa taomga almashtirasizmi yoki buyurtmani bekor qilib pulni qaytarib olasizmi?',
          )}
        </CardDescription>
      </CardHeader>
      <CardContent className="grid gap-4">
        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {loading ? (
          <Spinner className="py-4" />
        ) : alternatives.length === 0 ? (
          <p className="text-muted-foreground text-sm">
            {t('Hozircha almashtirish uchun boshqa taom yoʻq.')}
          </p>
        ) : (
          <div className="grid gap-2" data-testid="alternatives">
            {alternatives.map((item) => (
              <div
                key={item.id}
                className="flex items-center justify-between gap-3 rounded-md border p-3"
              >
                <div>
                  <div className="text-sm font-medium">{item.name}</div>
                  <div className="text-muted-foreground text-xs">
                    {money(item.price)} ·{' '}
                    {formatPrepTime(item.base_prep_minutes, item.extra_prep_minutes)}
                  </div>
                </div>
                <Button size="sm" disabled={busy} onClick={() => replace(item)}>
                  {t(':count dona olish', { count: missing?.quantity ?? 0 })}
                </Button>
              </div>
            ))}
          </div>
        )}

        <Button variant="destructive" disabled={busy} onClick={cancel}>
          {t('Bekor qilish va pulni qaytarish')}
        </Button>
      </CardContent>
    </Card>
  )
}
