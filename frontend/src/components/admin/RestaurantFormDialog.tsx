import { useEffect, useState, type FormEvent } from 'react'
import { toast } from 'sonner'

import { createRestaurant, updateRestaurant, type RestaurantPayload } from '@/api/restaurants'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { useT } from '@/i18n/use-i18n'
import { apiErrorMessage } from '@/lib/api'
import type { Restaurant } from '@/types/api'

const EMPTY: RestaurantPayload = {
  name: '',
  address: '',
  phone: '',
  opens_at: '09:00',
  closes_at: '22:00',
  is_active: true,
}

export function RestaurantFormDialog({
  open,
  restaurant,
  onOpenChange,
  onSaved,
}: {
  open: boolean
  /** Null means "create a new restaurant". */
  restaurant: Restaurant | null
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}) {
  const t = useT()
  const [form, setForm] = useState<RestaurantPayload>(EMPTY)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) {
      return
    }

    setError(null)
    setForm(
      restaurant
        ? {
            name: restaurant.name,
            address: restaurant.address,
            phone: restaurant.phone ?? '',
            opens_at: restaurant.opens_at,
            closes_at: restaurant.closes_at,
            is_active: restaurant.is_active,
          }
        : EMPTY,
    )
  }, [open, restaurant])

  function update<K extends keyof RestaurantPayload>(field: K, value: RestaurantPayload[K]) {
    setForm((current) => ({ ...current, [field]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSaving(true)

    const payload = { ...form, phone: form.phone || null }

    try {
      if (restaurant) {
        await updateRestaurant(restaurant.id, payload)
        toast.success(t('Oshxona yangilandi.'))
      } else {
        await createRestaurant(payload)
        toast.success(t('Yangi oshxona qoʻshildi.'))
      }

      onOpenChange(false)
      onSaved()
    } catch (caught) {
      setError(apiErrorMessage(caught, t('Saqlashda xatolik yuz berdi.')))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{restaurant ? t('Oshxonani tahrirlash') : t('Yangi oshxona')}</DialogTitle>
          <DialogDescription>
            Nofaol oshxona mijozlarga koʻrinmaydi, lekin maʼlumotlari saqlanib qoladi.
          </DialogDescription>
        </DialogHeader>

        <form className="grid gap-4" onSubmit={handleSubmit}>
          {error && (
            <Alert variant="destructive">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}

          <div className="grid gap-2">
            <Label htmlFor="restaurant-name">{t('Nomi')}</Label>
            <Input
              id="restaurant-name"
              required
              value={form.name}
              onChange={(event) => update('name', event.target.value)}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="restaurant-address">{t('Manzil')}</Label>
            <Input
              id="restaurant-address"
              required
              value={form.address}
              onChange={(event) => update('address', event.target.value)}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="restaurant-phone">{t('Telefon')}</Label>
            <Input
              id="restaurant-phone"
              type="tel"
              placeholder="+998901234567"
              value={form.phone ?? ''}
              onChange={(event) => update('phone', event.target.value)}
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="grid gap-2">
              <Label htmlFor="restaurant-opens">{t('Ochilish')}</Label>
              <Input
                id="restaurant-opens"
                type="time"
                required
                value={form.opens_at}
                onChange={(event) => update('opens_at', event.target.value)}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="restaurant-closes">{t('Yopilish')}</Label>
              <Input
                id="restaurant-closes"
                type="time"
                required
                value={form.closes_at}
                onChange={(event) => update('closes_at', event.target.value)}
              />
            </div>
          </div>

          <div className="flex items-center justify-between rounded-md border p-3">
            <Label htmlFor="restaurant-active" className="font-normal">
              {t('Faol')}
            </Label>
            <Switch
              id="restaurant-active"
              checked={form.is_active ?? true}
              onCheckedChange={(checked) => update('is_active', checked)}
            />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              {t('Bekor qilish')}
            </Button>
            <Button type="submit" disabled={saving}>
              {saving ? t('Saqlanmoqda...') : t('Saqlash')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
