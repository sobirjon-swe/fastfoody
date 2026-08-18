import { Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { toast } from 'sonner'

import { syncMenuItemOptions, type OptionGroupInput } from '@/api/menu-items'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { DEFAULT_LOCALE, LOCALES, LOCALE_SHORT } from '@/i18n/locales'
import { useT } from '@/i18n/use-i18n'
import { apiErrorMessage } from '@/lib/api'
import type { MenuItem } from '@/types/api'

const EXTRA_LOCALES = LOCALES.filter((locale) => locale !== DEFAULT_LOCALE)

function emptyGroup(): OptionGroupInput {
  return {
    name: '',
    min_select: 0,
    max_select: 1,
    options: [{ name: '', price_delta: 0, prep_delta_minutes: 0, is_available: true }],
  }
}

/**
 * Taomning modifikator guruhlarini tahrirlash.
 *
 * Guruh — bitta savol («Sous»), variantlar — javoblar. Ikki kalit sozlama
 * tugma koʻrinishida: «Majburiy» (min_select) va «Koʻp tanlov» (max_select),
 * chunki xodimga son emas, maʼno kerak.
 */
export function MenuItemOptionsDialog({
  menuItem,
  onOpenChange,
  onSaved,
}: {
  /** Null oynani yopadi. */
  menuItem: MenuItem | null
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}) {
  const t = useT()
  const [groups, setGroups] = useState<OptionGroupInput[]>([])
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!menuItem) {
      return
    }

    setError(null)
    setGroups(
      (menuItem.option_groups ?? []).map((group) => ({
        id: group.id,
        name: group.name,
        min_select: group.min_select,
        max_select: group.max_select,
        translations: group.translations ?? {},
        options: group.options.map((option) => ({
          id: option.id,
          name: option.name,
          price_delta: Number.parseFloat(option.price_delta),
          prep_delta_minutes: option.prep_delta_minutes,
          is_available: option.is_available ?? true,
          translations: option.translations ?? {},
        })),
      })),
    )
  }, [menuItem])

  function updateGroup(index: number, patch: Partial<OptionGroupInput>) {
    setGroups((current) =>
      current.map((group, i) => (i === index ? { ...group, ...patch } : group)),
    )
  }

  function updateOption(groupIndex: number, optionIndex: number, patch: Record<string, unknown>) {
    setGroups((current) =>
      current.map((group, i) =>
        i === groupIndex
          ? {
              ...group,
              options: group.options.map((option, j) =>
                j === optionIndex ? { ...option, ...patch } : option,
              ),
            }
          : group,
      ),
    )
  }

  async function save() {
    if (!menuItem) {
      return
    }

    setSaving(true)
    setError(null)

    try {
      await syncMenuItemOptions(menuItem.id, groups)
      toast.success(t('Modifikatorlar saqlandi.'))
      onSaved()
      onOpenChange(false)
    } catch (caught) {
      setError(apiErrorMessage(caught, t('Modifikatorlarni saqlab boʻlmadi.')))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={menuItem !== null} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t(':name modifikatorlari', { name: menuItem?.name ?? '' })}</DialogTitle>
          <DialogDescription>
            {t(
              'Har bir guruh — bitta savol, variantlar — javoblar. Narx har donaga, vaqt esa qatorga bir marta qoʻshiladi.',
            )}
          </DialogDescription>
        </DialogHeader>

        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {groups.length === 0 && (
          <p className="text-muted-foreground text-sm">
            {t('Hozircha modifikator yoʻq. Masalan «Sous» guruhi va uning variantlari.')}
          </p>
        )}

        <div className="grid gap-4">
          {groups.map((group, groupIndex) => (
            <div className="grid gap-3 rounded-md border p-3" key={group.id ?? `new-${groupIndex}`}>
              <div className="flex items-end gap-2">
                <div className="grid flex-1 gap-2">
                  <Label htmlFor={`group-name-${groupIndex}`}>{t('Guruh nomi')}</Label>
                  <Input
                    id={`group-name-${groupIndex}`}
                    value={group.name}
                    onChange={(event) => updateGroup(groupIndex, { name: event.target.value })}
                  />
                </div>
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  aria-label={t('Guruhni oʻchirish')}
                  onClick={() => setGroups((current) => current.filter((_, i) => i !== groupIndex))}
                >
                  <Trash2 />
                </Button>
              </div>

              <div className="flex flex-wrap gap-4">
                <label className="flex items-center gap-2 text-sm">
                  <Switch
                    checked={group.min_select > 0}
                    onCheckedChange={(checked) =>
                      updateGroup(groupIndex, { min_select: checked ? 1 : 0 })
                    }
                    aria-label={t('Majburiy')}
                  />
                  {t('Majburiy')}
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <Switch
                    checked={group.max_select === null}
                    onCheckedChange={(checked) =>
                      updateGroup(groupIndex, { max_select: checked ? null : 1 })
                    }
                    aria-label={t('Koʻp tanlov')}
                  />
                  {t('Koʻp tanlov')}
                </label>
              </div>

              {EXTRA_LOCALES.map((locale) => (
                <div className="flex items-center gap-2" key={locale}>
                  <span className="text-muted-foreground w-8 text-xs">{LOCALE_SHORT[locale]}</span>
                  <Input
                    aria-label={`${group.name} ${LOCALE_SHORT[locale]}`}
                    value={group.translations?.[locale]?.name ?? ''}
                    onChange={(event) =>
                      updateGroup(groupIndex, {
                        translations: {
                          ...group.translations,
                          [locale]: { name: event.target.value },
                        },
                      })
                    }
                  />
                </div>
              ))}

              <div className="grid gap-2 border-t pt-3">
                <span className="text-sm font-medium">{t('Tanlovlar')}</span>
                {group.options.map((option, optionIndex) => (
                  <div className="grid gap-2" key={option.id ?? `new-${optionIndex}`}>
                    <div className="flex items-end gap-2">
                      <div className="grid flex-1 gap-1">
                        <Label
                          className="text-muted-foreground text-xs"
                          htmlFor={`option-name-${groupIndex}-${optionIndex}`}
                        >
                          {t('Variant nomi')}
                        </Label>
                        <Input
                          id={`option-name-${groupIndex}-${optionIndex}`}
                          value={option.name}
                          onChange={(event) =>
                            updateOption(groupIndex, optionIndex, { name: event.target.value })
                          }
                        />
                      </div>
                      <div className="grid w-28 gap-1">
                        <Label
                          className="text-muted-foreground text-xs"
                          htmlFor={`option-price-${groupIndex}-${optionIndex}`}
                        >
                          {t('Narx (+)')}
                        </Label>
                        <Input
                          id={`option-price-${groupIndex}-${optionIndex}`}
                          type="number"
                          min={0}
                          step="any"
                          value={option.price_delta}
                          onChange={(event) =>
                            updateOption(groupIndex, optionIndex, {
                              price_delta: Number(event.target.value),
                            })
                          }
                        />
                      </div>
                      <div className="grid w-24 gap-1">
                        <Label
                          className="text-muted-foreground text-xs"
                          htmlFor={`option-prep-${groupIndex}-${optionIndex}`}
                        >
                          {t('Vaqt (+daq)')}
                        </Label>
                        <Input
                          id={`option-prep-${groupIndex}-${optionIndex}`}
                          type="number"
                          min={0}
                          value={option.prep_delta_minutes}
                          onChange={(event) =>
                            updateOption(groupIndex, optionIndex, {
                              prep_delta_minutes: Number(event.target.value),
                            })
                          }
                        />
                      </div>
                      <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label={t('Variantni oʻchirish')}
                        onClick={() =>
                          updateGroup(groupIndex, {
                            options: group.options.filter((_, j) => j !== optionIndex),
                          })
                        }
                      >
                        <Trash2 />
                      </Button>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 pl-1">
                      {EXTRA_LOCALES.map((locale) => (
                        <div className="flex items-center gap-1" key={locale}>
                          <span className="text-muted-foreground text-xs">
                            {LOCALE_SHORT[locale]}
                          </span>
                          <Input
                            className="h-8 w-36"
                            aria-label={`${option.name} ${LOCALE_SHORT[locale]}`}
                            value={option.translations?.[locale]?.name ?? ''}
                            onChange={(event) =>
                              updateOption(groupIndex, optionIndex, {
                                translations: {
                                  ...option.translations,
                                  [locale]: { name: event.target.value },
                                },
                              })
                            }
                          />
                        </div>
                      ))}
                      <label className="text-muted-foreground ml-auto flex items-center gap-2 text-xs">
                        <Switch
                          checked={option.is_available ?? true}
                          onCheckedChange={(checked) =>
                            updateOption(groupIndex, optionIndex, { is_available: checked })
                          }
                          aria-label={`${option.name} ${t('Mavjud')}`}
                        />
                        {t('Mavjud')}
                      </label>
                    </div>
                  </div>
                ))}

                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="w-fit"
                  onClick={() =>
                    updateGroup(groupIndex, {
                      options: [
                        ...group.options,
                        { name: '', price_delta: 0, prep_delta_minutes: 0, is_available: true },
                      ],
                    })
                  }
                >
                  <Plus /> {t('Variant qoʻshish')}
                </Button>
              </div>
            </div>
          ))}
        </div>

        <Button
          type="button"
          variant="outline"
          className="w-fit"
          onClick={() => setGroups((current) => [...current, emptyGroup()])}
        >
          <Plus /> {t('Guruh qoʻshish')}
        </Button>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {t('Bekor qilish')}
          </Button>
          <Button type="button" onClick={save} disabled={saving}>
            {saving ? t('Saqlanmoqda...') : t('Saqlash')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
