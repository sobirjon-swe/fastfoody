import { Languages } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { LOCALES, LOCALE_LABELS, LOCALE_SHORT, type Locale } from '@/i18n/locales'
import { useI18n } from '@/i18n/use-i18n'
import { cn } from '@/lib/utils'

/**
 * Til almashtirgich. Kirmagan foydalanuvchi uchun ham ishlaydi (tanlov
 * brauzerda saqlanadi); kirgan boʻlsa, tanlov profilga ham yoziladi va bot
 * xabarlari shu tilda keladi.
 */
export function LanguageSwitcher({
  onChange,
  className,
}: {
  onChange?: (locale: Locale) => void
  className?: string
}) {
  const { locale, setLocale } = useI18n()

  return (
    <div className={cn('flex items-center gap-1', className)}>
      <Languages className="text-muted-foreground size-4 shrink-0" aria-hidden />
      {LOCALES.map((option) => (
        <Button
          key={option}
          type="button"
          size="sm"
          variant={locale === option ? 'secondary' : 'ghost'}
          aria-label={LOCALE_LABELS[option]}
          aria-pressed={locale === option}
          className="px-2"
          onClick={() => {
            setLocale(option)
            onChange?.(option)
          }}
        >
          {LOCALE_SHORT[option]}
        </Button>
      ))}
    </div>
  )
}
