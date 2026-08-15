import { Clock, Search, UtensilsCrossed } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router'

import { listPublicRestaurants } from '@/api/orders'
import { useAuth } from '@/auth/use-auth'
import { Spinner } from '@/components/Spinner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { apiErrorMessage } from '@/lib/api'
import type { Restaurant } from '@/types/api'

export function CustomerHomePage() {
  const { user } = useAuth()
  const [restaurants, setRestaurants] = useState<Restaurant[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [query, setQuery] = useState('')

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)

    try {
      setRestaurants(await listPublicRestaurants(query))
    } catch (caught) {
      setError(apiErrorMessage(caught, 'Oshxonalarni yuklab boʻlmadi.'))
    } finally {
      setLoading(false)
    }
  }, [query])

  useEffect(() => {
    void load()
  }, [load])

  return (
    <div className="grid gap-6">
      <div>
        <h1 className="text-2xl font-semibold">Assalomu alaykum, {user?.name}!</h1>
        <p className="text-muted-foreground mt-1 text-sm">
          Oshxonani tanlang, buyurtma bering — taom siz yetib borganingizda tayyor boʻladi.
        </p>
      </div>

      <form
        className="flex gap-2"
        onSubmit={(event) => {
          event.preventDefault()
          setQuery(search)
        }}
      >
        <Input
          className="max-w-sm"
          placeholder="Oshxona nomi yoki manzili"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
        />
        <Button type="submit" variant="secondary">
          <Search /> Qidirish
        </Button>
      </form>

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {loading ? (
        <Spinner />
      ) : restaurants.length === 0 ? (
        <p className="text-muted-foreground py-10 text-center text-sm">
          Hozircha ochiq oshxona topilmadi.
        </p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          {restaurants.map((restaurant) => (
            <Card key={restaurant.id}>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <UtensilsCrossed className="size-4" />
                  {restaurant.name}
                </CardTitle>
                <CardDescription>{restaurant.address}</CardDescription>
              </CardHeader>
              <CardContent className="flex items-end justify-between gap-3">
                <div className="text-muted-foreground grid gap-1 text-sm">
                  <span className="flex items-center gap-1">
                    <Clock className="size-3.5" />
                    {restaurant.opens_at}–{restaurant.closes_at}
                  </span>
                  <span>{restaurant.menu_items_count ?? 0} ta taom</span>
                </div>
                <Button asChild size="sm">
                  <Link to={`/restaurants/${restaurant.id}`}>Menyu</Link>
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
