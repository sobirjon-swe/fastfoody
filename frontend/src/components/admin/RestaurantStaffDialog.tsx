import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react'
import { toast } from 'sonner'

import {
  createRestaurantStaff,
  deactivateStaff,
  listRestaurantStaff,
  restoreStaff,
} from '@/api/restaurants'
import { Spinner } from '@/components/Spinner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { apiErrorMessage } from '@/lib/api'
import type { Restaurant, User } from '@/types/api'

const EMPTY = { name: '', email: '', phone: '', password: '', password_confirmation: '' }

export function RestaurantStaffDialog({
  restaurant,
  onOpenChange,
  onChanged,
}: {
  /** Null closes the dialog. */
  restaurant: Restaurant | null
  onOpenChange: (open: boolean) => void
  onChanged: () => void
}) {
  // The staff list is stored together with the restaurant it belongs to, so a
  // previously opened restaurant's people can never be rendered under another
  // restaurant's name — not on the first frame, and not when a slow response
  // arrives after the dialog has moved on.
  const [loaded, setLoaded] = useState<{ restaurantId: number; staff: User[] } | null>(null)
  const [loading, setLoading] = useState(false)
  const [form, setForm] = useState(EMPTY)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [busyId, setBusyId] = useState<number | null>(null)

  const restaurantId = restaurant?.id
  const staff = loaded && loaded.restaurantId === restaurantId ? loaded.staff : []

  // The dialog keeps rendering while it animates out; remembering the last
  // restaurant during render stops the title from flashing without a name.
  const shownRef = useRef<Restaurant | null>(restaurant)

  if (restaurant) {
    shownRef.current = restaurant
  }

  const shown = shownRef.current

  const load = useCallback(async () => {
    if (!restaurantId) {
      return
    }

    setLoading(true)

    try {
      const list = await listRestaurantStaff(restaurantId)

      setLoaded({ restaurantId, staff: list })
    } catch (caught) {
      setError(apiErrorMessage(caught, 'Xodimlarni yuklab boʻlmadi.'))
    } finally {
      setLoading(false)
    }
  }, [restaurantId])

  useEffect(() => {
    setForm(EMPTY)
    setError(null)
    void load()
  }, [load])

  /** Faolsizlantirilgan xodim tizimga kira olmaydi; hisobi esa saqlanadi. */
  async function toggleAccess(member: User) {
    if (!restaurantId) {
      return
    }

    setBusyId(member.id)

    try {
      if (member.is_deactivated) {
        await restoreStaff(restaurantId, member.id)
        toast.success(`${member.name} yana kira oladi.`)
      } else {
        await deactivateStaff(restaurantId, member.id)
        toast.success(`${member.name} tizimdan chiqarildi.`)
      }

      await load()
      onChanged()
    } catch (caught) {
      setError(apiErrorMessage(caught, 'Amalni bajarib boʻlmadi.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    if (!restaurantId) {
      return
    }

    setError(null)
    setSaving(true)

    try {
      await createRestaurantStaff(restaurantId, { ...form, phone: form.phone || null })
      toast.success('Xodim hisobi yaratildi.')
      setForm(EMPTY)
      await load()
      onChanged()
    } catch (caught) {
      setError(apiErrorMessage(caught, 'Xodim qoʻshishda xatolik yuz berdi.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={restaurant !== null} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{shown?.name} — xodimlar</DialogTitle>
          <DialogDescription>
            Xodim oʻzi roʻyxatdan oʻta olmaydi; hisobni siz yaratasiz va u faqat shu oshxona
            menyusini boshqaradi.
          </DialogDescription>
        </DialogHeader>

        {loading ? (
          <Spinner className="py-6" />
        ) : staff.length > 0 ? (
          <ul className="grid gap-1 text-sm">
            {staff.map((member) => (
              <li
                key={member.id}
                className="flex items-center justify-between gap-2 rounded-md border px-3 py-2"
              >
                <span className={member.is_deactivated ? 'text-muted-foreground line-through' : ''}>
                  {member.name}
                  <span className="text-muted-foreground ml-2 text-xs">{member.email}</span>
                </span>
                <Button
                  size="sm"
                  variant={member.is_deactivated ? 'outline' : 'ghost'}
                  disabled={busyId === member.id}
                  onClick={() => toggleAccess(member)}
                >
                  {member.is_deactivated ? 'Tiklash' : 'Oʻchirish'}
                </Button>
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-muted-foreground text-sm">Hozircha xodim yoʻq.</p>
        )}

        <form className="grid gap-3 border-t pt-4" onSubmit={handleSubmit}>
          {error && (
            <Alert variant="destructive">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}

          <div className="grid gap-2">
            <Label htmlFor="staff-name">Ism</Label>
            <Input
              id="staff-name"
              required
              value={form.name}
              onChange={(event) => setForm({ ...form, name: event.target.value })}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="staff-email">Email</Label>
            <Input
              id="staff-email"
              type="email"
              required
              value={form.email}
              onChange={(event) => setForm({ ...form, email: event.target.value })}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="staff-phone">Telefon (ixtiyoriy)</Label>
            <Input
              id="staff-phone"
              type="tel"
              value={form.phone}
              onChange={(event) => setForm({ ...form, phone: event.target.value })}
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="grid gap-2">
              <Label htmlFor="staff-password">Parol</Label>
              <Input
                id="staff-password"
                type="password"
                required
                value={form.password}
                onChange={(event) => setForm({ ...form, password: event.target.value })}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="staff-password-confirm">Takrorlang</Label>
              <Input
                id="staff-password-confirm"
                type="password"
                required
                value={form.password_confirmation}
                onChange={(event) =>
                  setForm({ ...form, password_confirmation: event.target.value })
                }
              />
            </div>
          </div>

          <Button type="submit" disabled={saving}>
            {saving ? 'Yaratilmoqda...' : 'Xodim qoʻshish'}
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  )
}
