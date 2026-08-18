import { useEffect, useState, type FormEvent } from 'react'
import { toast } from 'sonner'

import {
  createMenuItem,
  deleteMenuItemImage,
  updateMenuItem,
  uploadMenuItemImage,
  type MenuItemPayload,
  type MenuItemTranslations,
} from '@/api/menu-items'
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
import { Textarea } from '@/components/ui/textarea'
import { DEFAULT_LOCALE, LOCALES, LOCALE_LABELS } from '@/i18n/locales'
import { useT } from '@/i18n/use-i18n'
import { apiErrorMessage } from '@/lib/api'
import type { MenuItem } from '@/types/api'

interface FormState {
  name: string
  description: string
  category: string
  /** Til kodi → shu tildagi matnlar; asosiy til bu yerda yoʻq. */
  translations: MenuItemTranslations
  price: string
  base_prep_minutes: string
  extra_prep_minutes: string
  is_available: boolean
}

const EMPTY: FormState = {
  name: '',
  description: '',
  category: '',
  translations: {},
  price: '',
  base_prep_minutes: '3',
  extra_prep_minutes: '1',
  is_available: true,
}

export function MenuItemFormDialog({
  open,
  menuItem,
  categories,
  onOpenChange,
  onSaved,
}: {
  open: boolean
  /** Null means "add a new item". */
  menuItem: MenuItem | null
  /** Menyuda allaqachon ishlatilgan kategoriyalar — takliflar uchun. */
  categories: string[]
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}) {
  const t = useT()
  const [form, setForm] = useState<FormState>(EMPTY)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [image, setImage] = useState<File | null>(null)

  useEffect(() => {
    if (!open) {
      return
    }

    setError(null)
    setImage(null)
    setForm(
      menuItem
        ? {
            name: menuItem.name,
            description: menuItem.description ?? '',
            category: menuItem.category ?? '',
            translations: menuItem.translations ?? {},
            price: String(Number.parseFloat(menuItem.price)),
            base_prep_minutes: String(menuItem.base_prep_minutes),
            extra_prep_minutes: String(menuItem.extra_prep_minutes),
            is_available: menuItem.is_available,
          }
        : EMPTY,
    )
  }, [open, menuItem])

  // Tarjimasi bor taomni tahrirlashda boʻlim ochiq turadi, aks holda xodim
  // kiritgan matnini koʻrmay qolardi.
  const hasTranslations = Object.keys(form.translations).length > 0

  function update<K extends keyof FormState>(field: K, value: FormState[K]) {
    setForm((current) => ({ ...current, [field]: value }))
  }

  function updateTranslation(
    locale: string,
    field: 'name' | 'description' | 'category',
    value: string,
  ) {
    setForm((current) => ({
      ...current,
      translations: {
        ...current.translations,
        [locale]: { ...current.translations[locale], [field]: value },
      },
    }))
  }

  async function removeImage() {
    if (!menuItem) {
      return
    }

    try {
      await deleteMenuItemImage(menuItem.id)
      toast.success(t('Rasm oʻchirildi.'))
      onOpenChange(false)
      onSaved()
    } catch (caught) {
      setError(apiErrorMessage(caught, t('Rasmni oʻchirib boʻlmadi.')))
    }
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSaving(true)

    const payload: MenuItemPayload = {
      name: form.name,
      description: form.description || null,
      category: form.category || null,
      price: Number(form.price),
      base_prep_minutes: Number(form.base_prep_minutes),
      extra_prep_minutes: Number(form.extra_prep_minutes),
      is_available: form.is_available,
      translations: form.translations,
    }

    try {
      const saved = menuItem
        ? await updateMenuItem(menuItem.id, payload)
        : await createMenuItem(payload)

      if (image) {
        await uploadMenuItemImage(saved.id, image)
      }

      toast.success(menuItem ? t('Taom yangilandi.') : t('Taom qoʻshildi.'))

      onOpenChange(false)
      onSaved()
    } catch (caught) {
      setError(apiErrorMessage(caught, t('Saqlashda xatolik yuz berdi.')))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85dvh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{menuItem ? t('Taomni tahrirlash') : t('Yangi taom')}</DialogTitle>
          <DialogDescription>
            Tayyorlash vaqti buyurtma qachon tayyor boʻlishini hisoblashda ishlatiladi.
          </DialogDescription>
        </DialogHeader>

        <form className="grid gap-4" onSubmit={handleSubmit}>
          {error && (
            <Alert variant="destructive">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}

          <div className="grid gap-2">
            <Label htmlFor="item-name">{t('Nomi')}</Label>
            <Input
              id="item-name"
              required
              value={form.name}
              onChange={(event) => update('name', event.target.value)}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="item-description">{t('Tavsif (ixtiyoriy)')}</Label>
            <Textarea
              id="item-description"
              maxLength={500}
              value={form.description}
              onChange={(event) => update('description', event.target.value)}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="item-category">{t('Kategoriya (ixtiyoriy)')}</Label>
            <Input
              id="item-category"
              list="menu-categories"
              placeholder={t('Lavashlar, Ichimliklar...')}
              maxLength={60}
              value={form.category}
              onChange={(event) => update('category', event.target.value)}
            />
            <datalist id="menu-categories">
              {categories.map((name) => (
                <option key={name} value={name} />
              ))}
            </datalist>
          </div>

          <div className="grid gap-2">
            <Label htmlFor="item-image">{t('Rasm')}</Label>
            {menuItem?.image_url && (
              <div className="flex items-center gap-3">
                <img
                  src={menuItem.image_url}
                  alt={menuItem.name}
                  className="size-16 rounded-md object-cover"
                />
                <Button type="button" variant="ghost" size="sm" onClick={removeImage}>
                  {t('Rasmni oʻchirish')}
                </Button>
              </div>
            )}
            <Input
              id="item-image"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              onChange={(event) => setImage(event.target.files?.[0] ?? null)}
            />
            <p className="text-muted-foreground text-xs">
              {t('JPG, PNG yoki WebP, 4 MB gacha. Rasm taom saqlangandan keyin yuklanadi.')}
            </p>
          </div>

          <div className="grid gap-2">
            <Label htmlFor="item-price">{t('Narxi (soʻm)')}</Label>
            {/*
              step must stay "any": a numeric step is a validity constraint, so
              step={500} would make the browser silently refuse to submit an
              ordinary price like 32 900 that the API accepts.
            */}
            <Input
              id="item-price"
              type="number"
              min={0}
              max={99999999.99}
              step="any"
              required
              value={form.price}
              onChange={(event) => update('price', event.target.value)}
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="grid gap-2">
              <Label htmlFor="item-base">{t('1-dona (daqiqa)')}</Label>
              <Input
                id="item-base"
                type="number"
                min={1}
                max={600}
                required
                value={form.base_prep_minutes}
                onChange={(event) => update('base_prep_minutes', event.target.value)}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="item-extra">{t('Har keyingi dona')}</Label>
              <Input
                id="item-extra"
                type="number"
                min={0}
                max={600}
                required
                value={form.extra_prep_minutes}
                onChange={(event) => update('extra_prep_minutes', event.target.value)}
              />
            </div>
          </div>

          <details className="grid gap-3 rounded-md border p-3" open={hasTranslations}>
            <summary className="cursor-pointer text-sm font-medium">
              {t('Boshqa tillarda')}
              <p className="text-muted-foreground mt-1 text-xs font-normal">
                {t('Ixtiyoriy. Toʻldirilmagan til uchun mijozga asl nom koʻrsatiladi.')}
              </p>
            </summary>

            {LOCALES.filter((locale) => locale !== DEFAULT_LOCALE).map((locale) => (
              <div className="grid gap-2" key={locale}>
                <Label htmlFor={`item-name-${locale}`}>
                  {t('Nomi (:locale)', { locale: LOCALE_LABELS[locale] })}
                </Label>
                <Input
                  id={`item-name-${locale}`}
                  value={form.translations[locale]?.name ?? ''}
                  onChange={(event) => updateTranslation(locale, 'name', event.target.value)}
                />
                <Label htmlFor={`item-description-${locale}`} className="text-muted-foreground">
                  {t('Tavsif (:locale)', { locale: LOCALE_LABELS[locale] })}
                </Label>
                <Textarea
                  id={`item-description-${locale}`}
                  rows={2}
                  value={form.translations[locale]?.description ?? ''}
                  onChange={(event) => updateTranslation(locale, 'description', event.target.value)}
                />
                <Label htmlFor={`item-category-${locale}`} className="text-muted-foreground">
                  {t('Kategoriya (:locale)', { locale: LOCALE_LABELS[locale] })}
                </Label>
                <Input
                  id={`item-category-${locale}`}
                  value={form.translations[locale]?.category ?? ''}
                  onChange={(event) => updateTranslation(locale, 'category', event.target.value)}
                />
              </div>
            ))}
          </details>

          <div className="flex items-center justify-between rounded-md border p-3">
            <Label htmlFor="item-available" className="font-normal">
              Mavjud (mijozga koʻrinadi)
            </Label>
            <Switch
              id="item-available"
              checked={form.is_available}
              onCheckedChange={(checked) => update('is_available', checked)}
            />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Bekor qilish
            </Button>
            <Button type="submit" disabled={saving}>
              {saving ? t('Saqlanmoqda...') : t('Saqlash')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
