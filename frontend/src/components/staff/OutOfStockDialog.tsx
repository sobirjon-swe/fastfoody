import { useEffect, useState } from 'react'
import { toast } from 'sonner'

import { reportOutOfStock, type StaffOrder } from '@/api/staff-orders'
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
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { apiErrorMessage } from '@/lib/api'

/**
 * Oshxona buyurtmadagi qaysi taom tugaganini belgilaydi. Buyurtma navbatdan
 * chiqadi va mijozdan javob kutiladi.
 */
export function OutOfStockDialog({
  order,
  onOpenChange,
  onReported,
}: {
  /** Null oynani yopadi. */
  order: StaffOrder | null
  onOpenChange: (open: boolean) => void
  onReported: () => void
}) {
  const [selected, setSelected] = useState<number | null>(null)
  const [removeFromMenu, setRemoveFromMenu] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (order) {
      setSelected(order.items?.[0]?.id ?? null)
      setRemoveFromMenu(true)
      setError(null)
    }
  }, [order])

  async function submit() {
    if (!order || selected === null) {
      return
    }

    setSaving(true)
    setError(null)

    try {
      await reportOutOfStock(order.id, selected, removeFromMenu)
      toast.success(`#${order.id}: mijozga xabar berildi.`)
      onOpenChange(false)
      onReported()
    } catch (caught) {
      setError(apiErrorMessage(caught, 'Belgilab boʻlmadi.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={order !== null} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Qaysi taom tugadi?</DialogTitle>
          <DialogDescription>
            Buyurtma navbatdan chiqariladi va mijoz almashtirish yoki pulni qaytarish orasidan
            tanlaydi.
          </DialogDescription>
        </DialogHeader>

        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        <div className="grid gap-2">
          {order?.items?.map((item) => (
            <label
              key={item.id}
              className="flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm"
            >
              <input
                type="radio"
                name="out-of-stock-item"
                value={item.id}
                checked={selected === item.id}
                onChange={() => setSelected(item.id)}
              />
              <span>
                {item.quantity} × {item.name}
              </span>
            </label>
          ))}
        </div>

        <div className="flex items-center justify-between rounded-md border p-3">
          <Label htmlFor="remove-from-menu" className="font-normal">
            Menyudan ham «mavjud emas» qilinsin
          </Label>
          <Switch
            id="remove-from-menu"
            checked={removeFromMenu}
            onCheckedChange={setRemoveFromMenu}
          />
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Bekor qilish
          </Button>
          <Button variant="destructive" onClick={submit} disabled={saving || selected === null}>
            {saving ? 'Yuborilmoqda...' : 'Tugadi deb belgilash'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
