import { UtensilsCrossed } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router'

import { ROLE_HOME } from '@/auth/auth-context'
import { useAuth } from '@/auth/use-auth'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { LanguageSwitcher } from '@/components/LanguageSwitcher'
import { useT } from '@/i18n/use-i18n'
import { apiErrorMessage } from '@/lib/api'

export function LoginPage() {
  const { user, login } = useAuth()
  const t = useT()
  const navigate = useNavigate()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  if (user) {
    return <Navigate to={ROLE_HOME[user.role]} replace />
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSubmitting(true)

    try {
      const signedIn = await login({ email, password })
      const from = (location.state as { from?: string } | null)?.from

      navigate(from ?? ROLE_HOME[signedIn.role], { replace: true })
    } catch (caught) {
      setError(apiErrorMessage(caught, t('Kirishda xatolik yuz berdi.')))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="bg-muted/40 flex min-h-screen items-center justify-center px-4 py-12">
      <Card className="w-full max-w-sm">
        <CardHeader className="items-center text-center">
          {/* Dizayndagi belgi: to'q sariq kvadrat ichida oshxona ramzi. */}
          <span className="bg-primary text-primary-foreground mx-auto flex size-12 items-center justify-center rounded-2xl">
            <UtensilsCrossed className="size-6" aria-hidden />
          </span>
          <CardTitle className="mt-3 text-xl">FastFoody</CardTitle>
          <CardDescription>
            {t('Navbatda kutmang — kelguningizcha tayyor boʻladi.')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {/* Kirish / Roʻyxatdan oʻtish — dizayndagi ikki boʻlakli almashtirgich. */}
          <div className="bg-muted mb-4 grid grid-cols-2 gap-1 rounded-xl p-1">
            <span className="bg-card rounded-lg py-1.5 text-center text-sm font-medium shadow-sm">
              {t('Kirish')}
            </span>
            <Link
              to="/register"
              className="text-muted-foreground hover:text-foreground rounded-lg py-1.5 text-center text-sm transition"
            >
              {t('Roʻyxatdan oʻtish')}
            </Link>
          </div>

          <LanguageSwitcher className="mb-4 justify-center" />
          <form className="grid gap-4" onSubmit={handleSubmit}>
            {error && (
              <Alert variant="destructive">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}

            <div className="grid gap-2">
              <Label htmlFor="email">{t('Email')}</Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                required
                value={email}
                onChange={(event) => setEmail(event.target.value)}
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password">{t('Parol')}</Label>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                required
                value={password}
                onChange={(event) => setPassword(event.target.value)}
              />
            </div>

            <Button type="submit" disabled={submitting}>
              {submitting ? t('Kirilmoqda...') : t('Kirish')}
            </Button>

            {/* Roʻyxatdan oʻtish havolasi endi yuqoridagi almashtirgichda. */}
            <p className="text-muted-foreground text-center text-sm">
              <Link to="/parolni-unutdim" className="underline underline-offset-4">
                {t('Parolni unutdingizmi?')}
              </Link>
            </p>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
