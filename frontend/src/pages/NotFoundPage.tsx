import { Link } from 'react-router'

import { Button } from '@/components/ui/button'
import { useT } from '@/i18n/use-i18n'

export function NotFoundPage() {
  const t = useT()

  return (
    <div className="grid place-items-center gap-4 py-16 text-center">
      <div>
        <h1 className="text-2xl font-semibold">{t('Sahifa topilmadi')}</h1>
        <p className="text-muted-foreground mt-1 text-sm">{t('Bu manzilda hech narsa yoʻq.')}</p>
      </div>
      <Button asChild variant="outline">
        <Link to="/">{t('Bosh sahifaga qaytish')}</Link>
      </Button>
    </div>
  )
}
