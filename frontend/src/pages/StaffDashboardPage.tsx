import { useAuth } from '@/auth/use-auth'
import { NextStepsCard } from '@/components/NextStepsCard'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export function StaffDashboardPage() {
  const { user } = useAuth()
  const restaurant = user?.restaurant

  return (
    <div className="grid gap-6">
      <div>
        <h1 className="text-2xl font-semibold">Oshxona paneli</h1>
        <p className="text-muted-foreground mt-1 text-sm">
          Siz faqat oʻzingiz biriktirilgan oshxona maʼlumotlarini koʻrasiz.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            {restaurant?.name ?? 'Oshxona biriktirilmagan'}
            {restaurant && (
              <Badge variant={restaurant.is_active ? 'secondary' : 'destructive'}>
                {restaurant.is_active ? 'Faol' : 'Nofaol'}
              </Badge>
            )}
          </CardTitle>
          <CardDescription>
            {restaurant?.address ?? 'Tizim egasi sizni oshxonaga biriktirishi kerak.'}
          </CardDescription>
        </CardHeader>
        {restaurant && (
          <CardContent className="text-muted-foreground grid gap-1 text-sm">
            <div>Ish vaqti: {restaurant.opens_at.slice(0, 5)}–{restaurant.closes_at.slice(0, 5)}</div>
            {restaurant.phone && <div>Telefon: {restaurant.phone}</div>}
          </CardContent>
        )}
      </Card>

      <NextStepsCard
        title="Oshxona paneli"
        steps={[
          '1-bosqich: menyuni boshqarish (taom, narx, tayyorlash vaqti)',
          '4-bosqich: kelgan buyurtmalar va holatini oʻzgartirish',
          '5-bosqich: mahsulot tugagan holatini belgilash',
        ]}
      />
    </div>
  )
}
