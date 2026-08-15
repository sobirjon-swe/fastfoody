import { Loader2 } from 'lucide-react'

import { cn } from '@/lib/utils'

export function Spinner({ className }: { className?: string }) {
  return (
    <div className={cn('flex items-center justify-center py-16', className)}>
      <Loader2 className="text-muted-foreground size-6 animate-spin" aria-label="Yuklanmoqda" />
    </div>
  )
}
