import { useState, type FormEvent } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router'

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

export function LoginPage() {
  const { user, login } = useAuth()
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
      setError(apiErrorMessage(caught, 'Kirishda xatolik yuz berdi.'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="bg-muted/40 flex min-h-screen items-center justify-center px-4 py-12">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle className="text-xl">Kirish</CardTitle>
          <CardDescription>FastFoody hisobingizga kiring.</CardDescription>
        </CardHeader>
        <CardContent>
          <form className="grid gap-4" onSubmit={handleSubmit}>
            {error && (
              <Alert variant="destructive">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}

            <div className="grid gap-2">
              <Label htmlFor="email">Email</Label>
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
              <Label htmlFor="password">Parol</Label>
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
              {submitting ? 'Kirilmoqda...' : 'Kirish'}
            </Button>

            <p className="text-muted-foreground text-center text-sm">
              Hisobingiz yoʻqmi?{' '}
              <Link to="/register" className="text-foreground underline underline-offset-4">
                Roʻyxatdan oʻtish
              </Link>
            </p>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
