import { useEffect, useState } from 'react'

import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useT } from '@/i18n/use-i18n'
import { useMoney } from '@/i18n/use-money'
import type { MenuItem } from '@/types/api'

/**
 * Taomni savatchaga qoʻshishdan oldin modifikatorlarni tanlash.
 *
 * Bitta tanlovli guruh radio, koʻp tanlovli guruh checkbox koʻrinishida
 * chiziladi — mijoz qoidani oʻqib emas, koʻrib tushunadi. Majburiy guruhga
 * javob berilmaguncha tugma ochilmaydi; server ham xuddi shuni tekshiradi.
 */
export function OptionPickerDialog({
  item,
  initialOptionIds,
  onOpenChange,
  onConfirm,
}: {
  /** Null oynani yopadi. */
  item: MenuItem | null
  initialOptionIds?: number[]
  onOpenChange: (open: boolean) => void
  onConfirm: (optionIds: number[]) => void
}) {
  const t = useT()
  const money = useMoney()
  const [selected, setSelected] = useState<number[]>([])

  useEffect(() => {
    if (!item) {
      return
    }

    // Majburiy va bitta tanlovli guruhda birinchi variant oldindan
    // belgilanadi: mijoz koʻpincha shuni tanlaydi, ortiqcha bosish shart emas.
    const defaults = (item.option_groups ?? [])
      .filter((group) => group.min_select > 0 && group.max_select === 1)
      .map((group) => group.options[0]?.id)
      .filter((id): id is number => typeof id === 'number')

    setSelected(initialOptionIds?.length ? initialOptionIds : defaults)
  }, [item, initialOptionIds])

  function toggle(groupId: number, optionId: number, single: boolean) {
    const group = item?.option_groups?.find((candidate) => candidate.id === groupId)
    const groupOptionIds = group?.options.map((option) => option.id) ?? []

    setSelected((current) => {
      if (single) {
        return [...current.filter((id) => !groupOptionIds.includes(id)), optionId]
      }

      return current.includes(optionId)
        ? current.filter((id) => id !== optionId)
        : [...current, optionId]
    })
  }

  const groups = item?.option_groups ?? []
  const missing = groups.filter(
    (group) =>
      selected.filter((id) => group.options.some((option) => option.id === id)).length <
      group.min_select,
  )

  const extra = groups
    .flatMap((group) => group.options)
    .filter((option) => selected.includes(option.id))
    .reduce((sum, option) => sum + Number.parseFloat(option.price_delta), 0)

  const unitPrice = item ? Number.parseFloat(item.price) + extra : 0

  return (
    <Dialog open={item !== null} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85dvh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{item?.name}</DialogTitle>
          <DialogDescription>{t('Tanlang')}</DialogDescription>
        </DialogHeader>

        <div className="grid gap-4">
          {groups.map((group) => {
            const single = group.max_select === 1

            return (
              <fieldset className="grid gap-2" key={group.id}>
                <legend className="text-sm font-medium">
                  {group.name}
                  <span className="text-muted-foreground ml-2 text-xs font-normal">
                    {group.min_select > 0
                      ? t('Majburiy')
                      : single
                        ? t('Bittasini tanlang')
                        : t('Xohlagancha tanlang')}
                  </span>
                </legend>

                {group.options.map((option) => (
                  <label
                    className="flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm"
                    key={option.id}
                  >
                    <input
                      type={single ? 'radio' : 'checkbox'}
                      name={`group-${group.id}`}
                      checked={selected.includes(option.id)}
                      onChange={() => toggle(group.id, option.id, single)}
                    />
                    <span className="flex-1">{option.name}</span>
                    {Number.parseFloat(option.price_delta) > 0 && (
                      <span className="text-muted-foreground whitespace-nowrap">
                        +{money(option.price_delta)}
                      </span>
                    )}
                  </label>
                ))}
              </fieldset>
            )
          })}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {t('Bekor qilish')}
          </Button>
          <Button
            type="button"
            disabled={missing.length > 0}
            onClick={() => {
              onConfirm(selected)
              onOpenChange(false)
            }}
          >
            {t('Savatchaga')} · {money(unitPrice)}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
