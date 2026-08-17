import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'

import { updatePassword, updateProfile } from '@/api/account'
import { useAuth } from '@/auth/use-auth'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { LanguageSwitcher } from '@/components/LanguageSwitcher'
import type { Locale } from '@/i18n/locales'
import { useT } from '@/i18n/use-i18n'
import { apiErrorMessage } from '@/lib/api'
import { ROLE_LABELS } from '@/types/api'

const EMPTY_PASSWORD = { current_password: '', password: '', password_confirmation: '' }

export function ProfilePage() {
  const { user, refresh } = useAuth()
  const t = useT()

  const [details, setDetails] = useState({
    name: user?.name ?? '',
    email: user?.email ?? '',
    phone: user?.phone ?? '',
  })
  const [detailsError, setDetailsError] = useState<string | null>(null)
  const [savingDetails, setSavingDetails] = useState(false)

  const [passwords, setPasswords] = useState(EMPTY_PASSWORD)
  const [passwordError, setPasswordError] = useState<string | null>(null)
  const [savingPassword, setSavingPassword] = useState(false)

  async function saveDetails(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setDetailsError(null)
    setSavingDetails(true)

    try {
      await updateProfile({ ...details, phone: details.phone || null })
      await refresh()
      toast.success(t('Maʼlumotlar saqlandi.'))
    } catch (caught) {
      setDetailsError(apiErrorMessage(caught, t('Saqlab boʻlmadi.')))
    } finally {
      setSavingDetails(false)
    }
  }

  async function savePassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setPasswordError(null)
    setSavingPassword(true)

    try {
      await updatePassword(passwords)
      setPasswords(EMPTY_PASSWORD)
      toast.success(t('Parol yangilandi. Boshqa qurilmalardagi seanslar yopildi.'))
    } catch (caught) {
      setPasswordError(apiErrorMessage(caught, t('Parolni oʻzgartirib boʻlmadi.')))
    } finally {
      setSavingPassword(false)
    }
  }

  async function changeLocale(locale: Locale) {
    try {
      await updateProfile({ locale })
      await refresh()
      toast.success(t('Til oʻzgartirildi.'))
    } catch (caught) {
      toast.error(apiErrorMessage(caught, t('Tilni oʻzgartirib boʻlmadi.')))
    }
  }

  return (
    <div className="grid max-w-xl gap-6">
      <div>
        <h1 className="text-2xl font-semibold">{t('Profil')}</h1>
        <p className="text-muted-foreground mt-1 text-sm">
          {user && t(ROLE_LABELS[user.role])}
          {user?.restaurant && ` · ${user.restaurant.name}`}
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t('Interfeys tili')}</CardTitle>
          <CardDescription>
            {t('Tanlangan til hisobingizga saqlanadi va bot xabarlari ham shu tilda keladi.')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <LanguageSwitcher onChange={changeLocale} />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t('Maʼlumotlarim')}</CardTitle>
          <CardDescription>{t('Rol va oshxona bu yerdan oʻzgarmaydi.')}</CardDescription>
        </CardHeader>
        <CardContent>
          <form className="grid gap-4" onSubmit={saveDetails}>
            {detailsError && (
              <Alert variant="destructive">
                <AlertDescription>{detailsError}</AlertDescription>
              </Alert>
            )}

            <div className="grid gap-2">
              <Label htmlFor="profile-name">{t('Ism')}</Label>
              <Input
                id="profile-name"
                required
                value={details.name}
                onChange={(event) => setDetails({ ...details, name: event.target.value })}
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="profile-email">{t('Email')}</Label>
              <Input
                id="profile-email"
                type="email"
                required
                value={details.email}
                onChange={(event) => setDetails({ ...details, email: event.target.value })}
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="profile-phone">{t('Telefon')}</Label>
              <Input
                id="profile-phone"
                type="tel"
                placeholder="+998901234567"
                value={details.phone ?? ''}
                onChange={(event) => setDetails({ ...details, phone: event.target.value })}
              />
            </div>

            <Button type="submit" disabled={savingDetails} className="w-fit">
              {savingDetails ? t('Saqlanmoqda...') : t('Saqlash')}
            </Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t('Parolni oʻzgartirish')}</CardTitle>
          <CardDescription>
            {t('Yangi parol qoʻyilgach, boshqa qurilmalardagi seanslar yopiladi.')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form className="grid gap-4" onSubmit={savePassword}>
            {passwordError && (
              <Alert variant="destructive">
                <AlertDescription>{passwordError}</AlertDescription>
              </Alert>
            )}

            <div className="grid gap-2">
              <Label htmlFor="current-password">{t('Joriy parol')}</Label>
              <Input
                id="current-password"
                type="password"
                autoComplete="current-password"
                required
                value={passwords.current_password}
                onChange={(event) =>
                  setPasswords({ ...passwords, current_password: event.target.value })
                }
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="new-password">{t('Yangi parol')}</Label>
              <Input
                id="new-password"
                type="password"
                autoComplete="new-password"
                required
                value={passwords.password}
                onChange={(event) => setPasswords({ ...passwords, password: event.target.value })}
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="new-password-confirm">{t('Takrorlang')}</Label>
              <Input
                id="new-password-confirm"
                type="password"
                autoComplete="new-password"
                required
                value={passwords.password_confirmation}
                onChange={(event) =>
                  setPasswords({ ...passwords, password_confirmation: event.target.value })
                }
              />
            </div>

            <Button type="submit" disabled={savingPassword} className="w-fit">
              {savingPassword ? t('Oʻzgartirilmoqda...') : t('Parolni oʻzgartirish')}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
