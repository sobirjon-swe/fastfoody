import { Check } from 'lucide-react'

import { useT } from '@/i18n/use-i18n'
import { ORDER_STATUS_LABELS, type OrderStatus } from '@/types/api'

/**
 * Buyurtmaning odatiy yoʻli. Toʻlov kutilayotgan holat ham shu yoʻlning
 * boshi, shuning uchun u birinchi qadam sifatida koʻrsatiladi.
 */
const STEPS = ['kutilmoqda', 'tolov_qilindi', 'tayyorlanmoqda', 'tayyor', 'olib_ketildi'] as const

/**
 * Bekor boʻlgan yoki javob kutilayotgan buyurtmada chiziqli yoʻl maʼnosini
 * yoʻqotadi — bunday holatlarda indikator umuman chizilmaydi.
 */
function stepIndex(status: OrderStatus): number {
  return STEPS.indexOf(status as (typeof STEPS)[number])
}

export function OrderProgress({ status }: { status: OrderStatus }) {
  const t = useT()
  const current = stepIndex(status)

  if (current === -1) {
    return null
  }

  return (
    <ol className="flex items-start gap-1" aria-label={t('Buyurtma bosqichlari')}>
      {STEPS.map((step, index) => {
        const done = index < current
        const active = index === current

        return (
          <li key={step} className="flex flex-1 flex-col items-center gap-1.5 text-center">
            <div className="flex w-full items-center gap-1">
              {/* Chapdagi ulovchi chiziq: birinchi qadamda boʻlmaydi. */}
              <span
                className={
                  index === 0
                    ? 'h-0.5 flex-1 bg-transparent'
                    : done || active
                      ? 'bg-success h-0.5 flex-1'
                      : 'bg-border h-0.5 flex-1'
                }
                aria-hidden
              />
              <span
                className={
                  done
                    ? 'bg-success text-success-foreground flex size-6 shrink-0 items-center justify-center rounded-full'
                    : active
                      ? 'bg-primary ring-primary/25 flex size-6 shrink-0 items-center justify-center rounded-full ring-4'
                      : 'bg-muted border-border flex size-6 shrink-0 items-center justify-center rounded-full border'
                }
                aria-hidden
              >
                {done ? (
                  <Check className="size-3.5" />
                ) : active ? (
                  <span className="bg-primary-foreground size-2 rounded-full" />
                ) : (
                  <span className="bg-muted-foreground/40 size-1.5 rounded-full" />
                )}
              </span>
              <span
                className={
                  index === STEPS.length - 1
                    ? 'h-0.5 flex-1 bg-transparent'
                    : done
                      ? 'bg-success h-0.5 flex-1'
                      : 'bg-border h-0.5 flex-1'
                }
                aria-hidden
              />
            </div>

            <span
              className={
                active
                  ? 'text-foreground text-[11px] leading-tight font-medium'
                  : 'text-muted-foreground text-[11px] leading-tight'
              }
              aria-current={active ? 'step' : undefined}
            >
              {t(ORDER_STATUS_LABELS[step])}
            </span>
          </li>
        )
      })}
    </ol>
  )
}
