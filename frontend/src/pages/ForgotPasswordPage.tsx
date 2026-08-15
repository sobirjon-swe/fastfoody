import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'

import { requestPasswordReset } from '@/api/account'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { apiErrorMessage } from '@/lib/api'

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [sending, setSending] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSending(true)

    try {
      setSent(await requestPasswordReset(email))
    } catch (caught) {
      setError(apiErrorMessage(caught, 'Havolani yuborib boʻlmadi.'))
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="bg-muted/40 flex min-h-screen items-center justify-center px-4 py-12">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle className="text-xl">Parolni tiklash</CardTitle>
          <CardDescription>Emailingizni yozing — tiklash havolasini yuboramiz.</CardDescription>
        </CardHeader>
        <CardContent>
          {sent ? (
            <div className="grid gap-4">
              <Alert>
                <AlertDescription>{sent}</AlertDescription>
              </Alert>
              <Button asChild variant="outline">
                <Link to="/login">Kirish sahifasiga</Link>
              </Button>
            </div>
          ) : (
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

              <Button type="submit" disabled={sending}>
                {sending ? 'Yuborilmoqda...' : 'Havola yuborish'}
              </Button>

              <p className="text-muted-foreground text-center text-sm">
                <Link to="/login" className="text-foreground underline underline-offset-4">
                  Kirishga qaytish
                </Link>
              </p>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
