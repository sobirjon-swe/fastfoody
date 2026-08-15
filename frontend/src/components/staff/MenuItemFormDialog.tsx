import { useEffect, useState, type FormEvent } from 'react'
import { toast } from 'sonner'

import { createMenuItem, updateMenuItem, type MenuItemPayload } from '@/api/menu-items'
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
import { Textarea } from '@/components/ui/textarea'
import { apiErrorMessage } from '@/lib/api'
import type { MenuItem } from '@/types/api'

interface FormState {
  name: string
  description: string
  price: string
  base_prep_minutes: string
  extra_prep_minutes: string
  is_available: boolean
}

const EMPTY: FormState = {
  name: '',
  description: '',
  price: '',
  base_prep_minutes: '3',
  extra_prep_minutes: '1',
  is_available: true,
}

export function MenuItemFormDialog({
  open,
  menuItem,
  onOpenChange,
  onSaved,
}: {
  open: boolean
  /** Null means "add a new item". */
  menuItem: MenuItem | null
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}) {
  const [form, setForm] = useState<FormState>(EMPTY)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) {
      return
    }

    setError(null)
    setForm(
      menuItem
        ? {
            name: menuItem.name,
            description: menuItem.description ?? '',
            price: String(Number.parseFloat(menuItem.price)),
            base_prep_minutes: String(menuItem.base_prep_minutes),
            extra_prep_minutes: String(menuItem.extra_prep_minutes),
            is_available: menuItem.is_available,
          }
        : EMPTY,
    )
  }, [open, menuItem])

  function update<K extends keyof FormState>(field: K, value: FormState[K]) {
    setForm((current) => ({ ...current, [field]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSaving(true)

    const payload: MenuItemPayload = {
      name: form.name,
      description: form.description || null,
      price: Number(form.price),
      base_prep_minutes: Number(form.base_prep_minutes),
      extra_prep_minutes: Number(form.extra_prep_minutes),
      is_available: form.is_available,
    }

    try {
      if (menuItem) {
        await updateMenuItem(menuItem.id, payload)
        toast.success('Taom yangilandi.')
      } else {
        await createMenuItem(payload)
        toast.success('Taom qoʻshildi.')
      }

      onOpenChange(false)
      onSaved()
    } catch (caught) {
      setError(apiErrorMessage(caught, 'Saqlashda xatolik yuz berdi.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{menuItem ? 'Taomni tahrirlash' : 'Yangi taom'}</DialogTitle>
          <DialogDescription>
            Tayyorlash vaqti buyurtma qachon tayyor boʻlishini hisoblashda ishlatiladi.
          </DialogDescription>
        </DialogHeader>

        <form className="grid gap-4" onSubmit={handleSubmit}>
          {error && (
            <Alert variant="destructive">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}

          <div className="grid gap-2">
            <Label htmlFor="item-name">Nomi</Label>
            <Input
              id="item-name"
              required
              value={form.name}
              onChange={(event) => update('name', event.target.value)}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="item-description">Tavsif (ixtiyoriy)</Label>
            <Textarea
              id="item-description"
              maxLength={500}
              value={form.description}
              onChange={(event) => update('description', event.target.value)}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="item-price">Narxi (soʻm)</Label>
            <Input
              id="item-price"
              type="number"
              min={0}
              step={500}
              required
              value={form.price}
              onChange={(event) => update('price', event.target.value)}
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="grid gap-2">
              <Label htmlFor="item-base">1-dona (daqiqa)</Label>
              <Input
                id="item-base"
                type="number"
                min={1}
                max={600}
                required
                value={form.base_prep_minutes}
                onChange={(event) => update('base_prep_minutes', event.target.value)}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="item-extra">Har keyingi dona</Label>
              <Input
                id="item-extra"
                type="number"
                min={0}
                max={600}
                required
                value={form.extra_prep_minutes}
                onChange={(event) => update('extra_prep_minutes', event.target.value)}
              />
            </div>
          </div>

          <div className="flex items-center justify-between rounded-md border p-3">
            <Label htmlFor="item-available" className="font-normal">
              Mavjud (mijozga koʻrinadi)
            </Label>
            <Switch
              id="item-available"
              checked={form.is_available}
              onCheckedChange={(checked) => update('is_available', checked)}
            />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Bekor qilish
            </Button>
            <Button type="submit" disabled={saving}>
              {saving ? 'Saqlanmoqda...' : 'Saqlash'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
