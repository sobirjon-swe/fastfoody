import { ListChecks, Pencil, Plus, Trash2 } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { toast } from 'sonner'

import { deleteMenuItem, listMenuItems, updateMenuItem } from '@/api/menu-items'
import { useAuth } from '@/auth/use-auth'
import { Spinner } from '@/components/Spinner'
import { MenuItemFormDialog } from '@/components/staff/MenuItemFormDialog'
import { MenuItemOptionsDialog } from '@/components/staff/MenuItemOptionsDialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Switch } from '@/components/ui/switch'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useT } from '@/i18n/use-i18n'
import { useMoney, usePrepTime } from '@/i18n/use-money'
import { apiErrorMessage } from '@/lib/api'

import type { MenuItem } from '@/types/api'

export function StaffMenuPage() {
  const t = useT()
  const money = useMoney()
  const prepTime = usePrepTime()
  const { user } = useAuth()
  const restaurant = user?.restaurant

  const [items, setItems] = useState<MenuItem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<MenuItem | null>(null)
  const [optionsFor, setOptionsFor] = useState<MenuItem | null>(null)
  const [deleting, setDeleting] = useState<MenuItem | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)

    try {
      setItems(await listMenuItems())
    } catch (caught) {
      setError(apiErrorMessage(caught, t('Menyuni yuklab boʻlmadi.')))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load, t])

  // Mavjud kategoriyalar — yangi taom qoʻshishda taklif qilinadi.
  const categories = [...new Set(items.map((item) => item.category).filter(Boolean))] as string[]

  async function toggleAvailability(item: MenuItem, isAvailable: boolean) {
    setItems((current) =>
      current.map((row) => (row.id === item.id ? { ...row, is_available: isAvailable } : row)),
    )

    try {
      await updateMenuItem(item.id, { is_available: isAvailable })
    } catch (caught) {
      setItems((current) =>
        current.map((row) =>
          row.id === item.id ? { ...row, is_available: item.is_available } : row,
        ),
      )
      toast.error(apiErrorMessage(caught, t('Holatni oʻzgartirib boʻlmadi.')))
    }
  }

  async function confirmDelete() {
    if (!deleting) {
      return
    }

    try {
      await deleteMenuItem(deleting.id)
      toast.success(t('Taom oʻchirildi.'))
      setDeleting(null)
      await load()
    } catch (caught) {
      toast.error(apiErrorMessage(caught, t('Oʻchirib boʻlmadi.')))
    }
  }

  return (
    <div className="grid gap-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            {restaurant?.name ?? t('Oshxona biriktirilmagan')}
            {restaurant && (
              <Badge variant={restaurant.is_active ? 'secondary' : 'destructive'}>
                {restaurant.is_active ? t('Faol') : t('Nofaol')}
              </Badge>
            )}
          </CardTitle>
          <CardDescription>
            {restaurant?.address ?? t('Tizim egasi sizni oshxonaga biriktirishi kerak.')}
          </CardDescription>
        </CardHeader>
        {restaurant && (
          <CardContent className="text-muted-foreground text-sm">
            {t('Ish vaqti: :opens–:closes', {
              opens: restaurant.opens_at,
              closes: restaurant.closes_at,
            })}
            {restaurant.phone && <> · {t('Telefon: :phone', { phone: restaurant.phone })}</>}
          </CardContent>
        )}
      </Card>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">{t('Menyu')}</h1>
          <p className="text-muted-foreground mt-1 text-sm">
            {t('Taom tugagan boʻlsa, uni oʻchirmasdan «mavjud emas» qilib qoʻying.')}
          </p>
        </div>
        <Button
          disabled={!restaurant}
          onClick={() => {
            setEditing(null)
            setFormOpen(true)
          }}
        >
          <Plus /> {t('Yangi taom')}
        </Button>
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {loading ? (
        <Spinner />
      ) : items.length === 0 ? (
        <p className="text-muted-foreground py-10 text-center text-sm">
          {t('Menyu boʻsh — birinchi taomni qoʻshing.')}
        </p>
      ) : (
        <div className="rounded-lg border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="pl-4">{t('Nomi')}</TableHead>
                <TableHead>{t('Narxi')}</TableHead>
                <TableHead>{t('Tayyorlash')}</TableHead>
                <TableHead>{t('Mavjud')}</TableHead>
                <TableHead className="pr-4 text-right">{t('Amallar')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((item) => (
                <TableRow key={item.id}>
                  <TableCell className="pl-4">
                    <div className="flex items-center gap-3">
                      {item.image_url && (
                        <img
                          src={item.image_url}
                          alt={item.name}
                          className="size-10 shrink-0 rounded-md object-cover"
                        />
                      )}
                      <div>
                        <div className="font-medium">{item.name}</div>
                        {item.description && (
                          <div className="text-muted-foreground text-xs">{item.description}</div>
                        )}
                        {item.category && (
                          <div className="text-muted-foreground text-xs">{item.category}</div>
                        )}
                      </div>
                    </div>
                  </TableCell>
                  <TableCell className="whitespace-nowrap">{money(item.price)}</TableCell>
                  <TableCell className="whitespace-nowrap">
                    {prepTime(item.base_prep_minutes, item.extra_prep_minutes)}
                  </TableCell>
                  <TableCell>
                    <Switch
                      checked={item.is_available}
                      onCheckedChange={(checked) => toggleAvailability(item, checked)}
                      aria-label={t(':name mavjudligi', { name: item.name })}
                    />
                  </TableCell>
                  <TableCell className="pr-4">
                    <div className="flex justify-end gap-1">
                      <Button
                        size="icon"
                        variant="ghost"
                        aria-label={t(':name modifikatorlari', { name: item.name })}
                        onClick={() => setOptionsFor(item)}
                      >
                        <ListChecks />
                      </Button>
                      <Button
                        size="icon"
                        variant="ghost"
                        aria-label={t(':name tahrirlash', { name: item.name })}
                        onClick={() => {
                          setEditing(item)
                          setFormOpen(true)
                        }}
                      >
                        <Pencil />
                      </Button>
                      <Button
                        size="icon"
                        variant="ghost"
                        aria-label={t(':name oʻchirish', { name: item.name })}
                        onClick={() => setDeleting(item)}
                      >
                        <Trash2 />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <MenuItemOptionsDialog
        menuItem={optionsFor}
        onOpenChange={(open) => !open && setOptionsFor(null)}
        onSaved={load}
      />

      <MenuItemFormDialog
        open={formOpen}
        menuItem={editing}
        categories={categories}
        onOpenChange={setFormOpen}
        onSaved={load}
      />

      <Dialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>«{deleting?.name}» oʻchirilsinmi?</DialogTitle>
            <DialogDescription>
              Bu amalni qaytarib boʻlmaydi. Taom vaqtincha tugagan boʻlsa, oʻchirish oʻrniga «mavjud
              emas» qilib qoʻying.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDeleting(null)}>
              Bekor qilish
            </Button>
            <Button variant="destructive" onClick={confirmDelete}>
              Oʻchirish
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
