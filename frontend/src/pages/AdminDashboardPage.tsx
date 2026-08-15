import { Pencil, Plus, Users } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { toast } from 'sonner'

import { listRestaurants, updateRestaurant } from '@/api/restaurants'
import { RestaurantFormDialog } from '@/components/admin/RestaurantFormDialog'
import { RestaurantStaffDialog } from '@/components/admin/RestaurantStaffDialog'
import { Spinner } from '@/components/Spinner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { apiErrorMessage } from '@/lib/api'
import type { PaginationMeta, Restaurant } from '@/types/api'

type StatusFilter = '' | 'active' | 'inactive'

const STATUS_FILTERS: { value: StatusFilter; label: string }[] = [
  { value: '', label: 'Barchasi' },
  { value: 'active', label: 'Faol' },
  { value: 'inactive', label: 'Nofaol' },
]

export function AdminDashboardPage() {
  const [restaurants, setRestaurants] = useState<Restaurant[]>([])
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const [search, setSearch] = useState('')
  const [query, setQuery] = useState('')
  const [status, setStatus] = useState<StatusFilter>('')
  const [page, setPage] = useState(1)

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Restaurant | null>(null)
  const [staffFor, setStaffFor] = useState<Restaurant | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)

    try {
      const { data, meta: pagination } = await listRestaurants({ q: query, status, page })

      setRestaurants(data)
      setMeta(pagination)
    } catch (caught) {
      setError(apiErrorMessage(caught, 'Oshxonalarni yuklab boʻlmadi.'))
    } finally {
      setLoading(false)
    }
  }, [query, status, page])

  useEffect(() => {
    void load()
  }, [load])

  // Search and filter always restart from the first page, otherwise page 3 of
  // an old result set would be requested for a new, shorter list.
  function applySearch(value: string) {
    setQuery(value)
    setPage(1)
  }

  function applyStatus(value: StatusFilter) {
    setStatus(value)
    setPage(1)
  }

  async function toggleActive(restaurant: Restaurant, isActive: boolean) {
    // Optimistic: flip the row first, roll it back if the API refuses.
    setRestaurants((current) =>
      current.map((item) => (item.id === restaurant.id ? { ...item, is_active: isActive } : item)),
    )

    try {
      await updateRestaurant(restaurant.id, { is_active: isActive })
      toast.success(isActive ? 'Oshxona faollashtirildi.' : 'Oshxona nofaol qilindi.')
    } catch (caught) {
      setRestaurants((current) =>
        current.map((item) =>
          item.id === restaurant.id ? { ...item, is_active: restaurant.is_active } : item,
        ),
      )
      toast.error(apiErrorMessage(caught, 'Holatni oʻzgartirib boʻlmadi.'))
    }
  }

  return (
    <div className="grid gap-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Oshxonalar</h1>
          <p className="text-muted-foreground mt-1 text-sm">
            Oshxona qoʻshing, ish vaqtini tahrirlang va xodim hisoblarini yarating.
          </p>
        </div>
        <Button
          onClick={() => {
            setEditing(null)
            setFormOpen(true)
          }}
        >
          <Plus /> Yangi oshxona
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <form
          className="flex gap-2"
          onSubmit={(event) => {
            event.preventDefault()
            applySearch(search)
          }}
        >
          <Input
            className="w-56"
            placeholder="Nomi yoki manzili"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
          <Button type="submit" variant="secondary">
            Qidirish
          </Button>
        </form>

        <div className="flex gap-1">
          {STATUS_FILTERS.map((filter) => (
            <Button
              key={filter.value || 'all'}
              size="sm"
              variant={status === filter.value ? 'default' : 'outline'}
              onClick={() => applyStatus(filter.value)}
            >
              {filter.label}
            </Button>
          ))}
        </div>
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {loading ? (
        <Spinner />
      ) : restaurants.length === 0 ? (
        <p className="text-muted-foreground py-10 text-center text-sm">
          Oshxona topilmadi.
        </p>
      ) : (
        <div className="rounded-lg border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="pl-4">Nomi</TableHead>
                <TableHead>Manzil</TableHead>
                <TableHead>Ish vaqti</TableHead>
                <TableHead>Menyu</TableHead>
                <TableHead>Xodim</TableHead>
                <TableHead>Holat</TableHead>
                <TableHead className="pr-4 text-right">Amallar</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {restaurants.map((restaurant) => (
                <TableRow key={restaurant.id}>
                  <TableCell className="pl-4 font-medium">{restaurant.name}</TableCell>
                  <TableCell className="text-muted-foreground">{restaurant.address}</TableCell>
                  <TableCell className="whitespace-nowrap">
                    {restaurant.opens_at}–{restaurant.closes_at}
                  </TableCell>
                  <TableCell>{restaurant.menu_items_count ?? 0}</TableCell>
                  <TableCell>{restaurant.staff_count ?? 0}</TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <Switch
                        checked={restaurant.is_active}
                        onCheckedChange={(checked) => toggleActive(restaurant, checked)}
                        aria-label={`${restaurant.name} holati`}
                      />
                      <Badge variant={restaurant.is_active ? 'secondary' : 'outline'}>
                        {restaurant.is_active ? 'Faol' : 'Nofaol'}
                      </Badge>
                    </div>
                  </TableCell>
                  <TableCell className="pr-4">
                    <div className="flex justify-end gap-1">
                      <Button
                        size="icon"
                        variant="ghost"
                        aria-label={`${restaurant.name} xodimlari`}
                        onClick={() => setStaffFor(restaurant)}
                      >
                        <Users />
                      </Button>
                      <Button
                        size="icon"
                        variant="ghost"
                        aria-label={`${restaurant.name} tahrirlash`}
                        onClick={() => {
                          setEditing(restaurant)
                          setFormOpen(true)
                        }}
                      >
                        <Pencil />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-muted-foreground">
            {meta.current_page}/{meta.last_page} — jami {meta.total} ta
          </span>
          <div className="flex gap-2">
            <Button
              size="sm"
              variant="outline"
              disabled={meta.current_page === 1}
              onClick={() => setPage((current) => current - 1)}
            >
              Oldingi
            </Button>
            <Button
              size="sm"
              variant="outline"
              disabled={meta.current_page === meta.last_page}
              onClick={() => setPage((current) => current + 1)}
            >
              Keyingi
            </Button>
          </div>
        </div>
      )}

      <RestaurantFormDialog
        open={formOpen}
        restaurant={editing}
        onOpenChange={setFormOpen}
        onSaved={load}
      />

      <RestaurantStaffDialog
        restaurant={staffFor}
        onOpenChange={(open) => !open && setStaffFor(null)}
        onChanged={load}
      />
    </div>
  )
}
