import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { toast } from 'sonner'

import { resetPassword } from '@/api/account'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { apiErrorMessage } from '@/lib/api'

export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()

  // Token va email havoladan keladi; foydalanuvchi ularni qoʻlda yozmaydi.
  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''

  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSaving(true)

    try {
      await resetPassword({
        token,
        email,
        password,
        password_confirmation: confirmation,
      })

      toast.success('Parol yangilandi.')
      navigate('/login', { replace: true })
    } catch (caught) {
      setError(apiErrorMessage(caught, 'Parolni tiklab boʻlmadi.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="bg-muted/40 flex min-h-screen items-center justify-center px-4 py-12">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle className="text-xl">Yangi parol</CardTitle>
          <CardDescription>{email || 'Havola toʻliq emas.'}</CardDescription>
        </CardHeader>
        <CardContent>
          {!token || !email ? (
            <div className="grid gap-4">
              <Alert variant="destructive">
                <AlertDescription>Havola notoʻgʻri. Tiklashni qaytadan boshlang.</AlertDescription>
              </Alert>
              <Button asChild variant="outline">
                <Link to="/parolni-unutdim">Qaytadan urinish</Link>
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
                <Label htmlFor="password">Yangi parol</Label>
                <Input
                  id="password"
                  type="password"
                  autoComplete="new-password"
                  required
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="password_confirmation">Takrorlang</Label>
                <Input
                  id="password_confirmation"
                  type="password"
                  autoComplete="new-password"
                  required
                  value={confirmation}
                  onChange={(event) => setConfirmation(event.target.value)}
                />
              </div>

              <Button type="submit" disabled={saving}>
                {saving ? 'Saqlanmoqda...' : 'Parolni yangilash'}
              </Button>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
