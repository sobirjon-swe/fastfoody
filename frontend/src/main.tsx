import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router'

import App from '@/App'
import { AuthProvider } from '@/auth/AuthProvider'
import { Toaster } from '@/components/ui/sonner'
import { I18nProvider } from '@/i18n/I18nProvider'
import { initTelegram } from '@/lib/telegram'
import '@/index.css'

// Telegram ichida ochilgan boʻlsa qobiq shu yerda sozlanadi; brauzerda bu
// chaqiruv hech narsa qilmaydi.
initTelegram()

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter>
      <I18nProvider>
        <AuthProvider>
          <App />
          <Toaster />
        </AuthProvider>
      </I18nProvider>
    </BrowserRouter>
  </StrictMode>,
)
