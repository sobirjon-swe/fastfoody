import { useAuth } from '@/auth/use-auth'
import { NextStepsCard } from '@/components/NextStepsCard'

export function CustomerHomePage() {
  const { user } = useAuth()

  return (
    <div className="grid gap-6">
      <div>
        <h1 className="text-2xl font-semibold">Assalomu alaykum, {user?.name}!</h1>
        <p className="text-muted-foreground mt-1 text-sm">
          Bu yerda oshxonalarni tanlab, buyurtma berasiz va taom qachon tayyor boʻlishini
          koʻrasiz.
        </p>
      </div>

      <NextStepsCard
        title="Mijoz sahifasi"
        steps={[
          '2-bosqich: oshxonalar roʻyxati va menyu',
          '2-bosqich: savatcha va buyurtma berish',
          '3-bosqich: tayyor boʻlish vaqtini toʻlovdan oldin koʻrsatish',
          '4-bosqich: buyurtma holatini kuzatish',
        ]}
      />
    </div>
  )
}
