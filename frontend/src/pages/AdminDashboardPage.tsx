import { useAuth } from '@/auth/use-auth'
import { NextStepsCard } from '@/components/NextStepsCard'

export function AdminDashboardPage() {
  const { user } = useAuth()

  return (
    <div className="grid gap-6">
      <div>
        <h1 className="text-2xl font-semibold">Tizim boshqaruvi</h1>
        <p className="text-muted-foreground mt-1 text-sm">
          {user?.name}, siz barcha oshxonalarni boshqarish huquqiga egasiz.
        </p>
      </div>

      <NextStepsCard
        title="Super admin paneli"
        steps={[
          '1-bosqich: oshxona qoʻshish, tahrirlash, faollashtirish/oʻchirish',
          '1-bosqich: oshxonaga xodim hisobini yaratish',
          'Keyingi bosqichlar: umumiy statistika',
        ]}
      />
    </div>
  )
}
