import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router'

import App from '@/App'
import { AuthProvider } from '@/auth/AuthProvider'
import { Toaster } from '@/components/ui/sonner'
import { initTelegram } from '@/lib/telegram'
import '@/index.css'

// Telegram ichida ochilgan boʻlsa qobiq shu yerda sozlanadi; brauzerda bu
// chaqiruv hech narsa qilmaydi.
initTelegram()

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <App />
        <Toaster />
      </AuthProvider>
    </BrowserRouter>
  </StrictMode>,
)
