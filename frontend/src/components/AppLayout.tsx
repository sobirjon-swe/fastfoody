import { LogOut, UtensilsCrossed } from 'lucide-react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router'

import { ROLE_HOME } from '@/auth/auth-context'
import { useAuth } from '@/auth/use-auth'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { ROLE_LABELS } from '@/types/api'

export function AppLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  async function handleLogout() {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <div className="bg-background min-h-screen">
      <header className="border-b">
        <div className="mx-auto flex h-14 max-w-5xl items-center justify-between gap-4 px-4">
          <div className="flex items-center gap-6">
            <Link
              to={user ? ROLE_HOME[user.role] : '/'}
              className="flex items-center gap-2 font-semibold"
            >
              <UtensilsCrossed className="size-5" />
              FastFoody
            </Link>

            {user?.role === 'customer' && (
              <nav className="flex items-center gap-4 text-sm">
                <NavLink
                  to="/"
                  end
                  className={({ isActive }) =>
                    cn('hover:text-foreground', isActive ? 'font-medium' : 'text-muted-foreground')
                  }
                >
                  Oshxonalar
                </NavLink>
                <NavLink
                  to="/orders"
                  className={({ isActive }) =>
                    cn('hover:text-foreground', isActive ? 'font-medium' : 'text-muted-foreground')
                  }
                >
                  Buyurtmalarim
                </NavLink>
              </nav>
            )}
          </div>

          {user && (
            <div className="flex items-center gap-3">
              <div className="hidden text-right sm:block">
                <div className="text-sm leading-tight font-medium">{user.name}</div>
                <div className="text-muted-foreground text-xs">{user.email}</div>
              </div>
              <Badge variant="secondary">{ROLE_LABELS[user.role]}</Badge>
              <Button variant="ghost" size="icon" onClick={handleLogout} aria-label="Chiqish">
                <LogOut />
              </Button>
            </div>
          )}
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-4 py-8">
        <Outlet />
      </main>
    </div>
  )
}
