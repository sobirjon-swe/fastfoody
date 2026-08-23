import { ImageIcon } from 'lucide-react'

import { cn } from '@/lib/utils'

interface ImageBandProps {
  /** Rasm manzili; boʻsh boʻlsa iliq toʻldiruvchi koʻrsatiladi. */
  src?: string | null
  alt: string
  className?: string
  /** Rasm ustida turadigan nishonlar (masalan "Ochiq"). */
  children?: React.ReactNode
}

/**
 * Oshxona va taom kartochkalaridagi rasm maydoni. Backend hozircha faqat
 * taomga rasm saqlaydi, oshxonaga saqlamaydi — shuning uchun rasm yoʻqligi
 * odatiy hol va boʻsh joy emas, dizayndagi iliq toʻldiruvchi koʻrinadi.
 */
export function ImageBand({ src, alt, className, children }: ImageBandProps) {
  return (
    <div className={cn('bg-image-placeholder relative overflow-hidden', className)}>
      {src ? (
        <img src={src} alt={alt} loading="lazy" className="size-full object-cover" />
      ) : (
        <div className="text-foreground/25 flex size-full items-center justify-center">
          <ImageIcon className="size-7" aria-hidden />
        </div>
      )}
      {children}
    </div>
  )
}
