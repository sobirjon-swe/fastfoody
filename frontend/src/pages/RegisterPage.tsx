import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router'

import { ROLE_HOME } from '@/auth/auth-context'
import { useAuth } from '@/auth/use-auth'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { apiErrorMessage } from '@/lib/api'

const EMPTY_FORM = {
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
}

export function RegisterPage() {
  const { user, register } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState(EMPTY_FORM)
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  if (user) {
    return <Navigate to={ROLE_HOME[user.role]} replace />
  }

  function update(field: keyof typeof EMPTY_FORM, value: string) {
    setForm((current) => ({ ...current, [field]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSubmitting(true)

    try {
      // Self-registration always creates a customer account; staff accounts are
      // provisioned by the super admin.
      const created = await register({ ...form, phone: form.phone || undefined })

      navigate(ROLE_HOME[created.role], { replace: true })
    } catch (caught) {
      setError(apiErrorMessage(caught, 'Roʻyxatdan oʻtishda xatolik yuz berdi.'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="bg-muted/40 flex min-h-screen items-center justify-center px-4 py-12">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle className="text-xl">Roʻyxatdan oʻtish</CardTitle>
          <CardDescription>Buyurtma berish uchun mijoz hisobini yarating.</CardDescription>
        </CardHeader>
        <CardContent>
          <form className="grid gap-4" onSubmit={handleSubmit}>
            {error && (
              <Alert variant="destructive">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}

            <div className="grid gap-2">
              <Label htmlFor="name">Ism</Label>
              <Input
                id="name"
                required
                value={form.name}
                onChange={(event) => update('name', event.target.value)}
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                required
                value={form.email}
                onChange={(event) => update('email', event.target.value)}
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="phone">Telefon (ixtiyoriy)</Label>
              <Input
                id="phone"
                type="tel"
                placeholder="+998901234567"
                value={form.phone}
                onChange={(event) => update('phone', event.target.value)}
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password">Parol</Label>
              <Input
                id="password"
                type="password"
                autoComplete="new-password"
                required
                value={form.password}
                onChange={(event) => update('password', event.target.value)}
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password_confirmation">Parolni takrorlang</Label>
              <Input
                id="password_confirmation"
                type="password"
                autoComplete="new-password"
                required
                value={form.password_confirmation}
                onChange={(event) => update('password_confirmation', event.target.value)}
              />
            </div>

            <Button type="submit" disabled={submitting}>
              {submitting ? 'Yaratilmoqda...' : 'Roʻyxatdan oʻtish'}
            </Button>

            <p className="text-muted-foreground text-center text-sm">
              Hisobingiz bormi?{' '}
              <Link to="/login" className="text-foreground underline underline-offset-4">
                Kirish
              </Link>
            </p>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
