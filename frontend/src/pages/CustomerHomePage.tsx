import { Search } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router'

import { listPublicRestaurants } from '@/api/orders'
import { useAuth } from '@/auth/use-auth'
import { ImageBand } from '@/components/ImageBand'
import { Spinner } from '@/components/Spinner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Input } from '@/components/ui/input'
import { useT } from '@/i18n/use-i18n'
import { apiErrorMessage } from '@/lib/api'
import type { Restaurant } from '@/types/api'

export function CustomerHomePage() {
  const { user } = useAuth()
  const t = useT()
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
      setError(apiErrorMessage(caught, t('Oshxonalarni yuklab boʻlmadi.')))
    } finally {
      setLoading(false)
    }
  }, [query, t])

  useEffect(() => {
    void load()
  }, [load])

  return (
    <div className="grid gap-5">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">
          {t('Assalomu alaykum, :name!', { name: user?.name ?? '' })}
        </h1>
        <p className="text-muted-foreground mt-1 text-sm">
          {t('Oshxonani tanlang, buyurtma bering — taom siz yetib borganingizda tayyor boʻladi.')}
        </p>
      </header>

      {/* Dizayndagidek: qidiruv maydoni butun kenglikda, ichida lupa belgisi. */}
      <form
        role="search"
        onSubmit={(event) => {
          event.preventDefault()
          setQuery(search)
        }}
      >
        <div className="relative">
          <Search
            className="text-muted-foreground pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2"
            aria-hidden
          />
          <Input
            className="bg-card h-11 rounded-xl pl-10 shadow-sm"
            placeholder={t('Oshxona nomi yoki manzili')}
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
        </div>
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
          {t('Hozircha ochiq oshxona topilmadi.')}
        </p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          {restaurants.map((restaurant) => (
            <RestaurantCard key={restaurant.id} restaurant={restaurant} />
          ))}
        </div>
      )}
    </div>
  )
}

function RestaurantCard({ restaurant }: { restaurant: Restaurant }) {
  const t = useT()

  return (
    // Dizaynda alohida "Menyu" tugmasi yoʻq — butun kartochka bosiladi.
    <Link
      to={`/restaurants/${restaurant.id}`}
      className="bg-card ring-ring/60 block overflow-hidden rounded-2xl shadow-sm transition hover:shadow-md focus-visible:ring-2 focus-visible:outline-none"
    >
      <ImageBand src={null} alt={restaurant.name} className="h-32">
        <span
          className={
            restaurant.is_open_now
              ? 'bg-success-muted text-success absolute top-3 left-3 rounded-full px-2.5 py-1 text-xs font-medium'
              : 'bg-card/90 text-muted-foreground absolute top-3 left-3 rounded-full px-2.5 py-1 text-xs font-medium'
          }
        >
          {restaurant.is_open_now ? t('Ochiq') : t('Hozir yopiq')}
        </span>
      </ImageBand>

      <div className="p-4">
        <h2 className="font-semibold">{restaurant.name}</h2>
        <p className="text-muted-foreground mt-0.5 text-sm">
          {restaurant.address} · {restaurant.opens_at}–{restaurant.closes_at}
        </p>
        <p className="text-muted-foreground mt-0.5 text-sm">
          {t(':count ta taom', { count: restaurant.menu_items_count ?? 0 })}
        </p>
      </div>
    </Link>
  )
}
