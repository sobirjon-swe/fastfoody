import { Link } from 'react-router'

import { Button } from '@/components/ui/button'

export function NotFoundPage() {
  return (
    <div className="grid place-items-center gap-4 py-16 text-center">
      <div>
        <h1 className="text-2xl font-semibold">Sahifa topilmadi</h1>
        <p className="text-muted-foreground mt-1 text-sm">
          Siz qidirgan sahifa mavjud emas yoki koʻchirilgan.
        </p>
      </div>
      <Button asChild variant="outline">
        <Link to="/">Bosh sahifa</Link>
      </Button>
    </div>
  )
}
