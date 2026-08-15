import { useEffect, useRef } from 'react'

/**
 * MVP'da jonli bildirishnoma (WebSocket) yoʻq, shuning uchun holat oʻzgarishi
 * shu oraliqda soʻrab olinadi. Callback har safar yangilanadi, lekin taymer
 * qayta yaratilmaydi — aks holda har render'da soʻrov ketardi.
 */
export function usePolling(callback: () => void, intervalMs: number, enabled = true): void {
  const saved = useRef(callback)

  useEffect(() => {
    saved.current = callback
  })

  useEffect(() => {
    if (!enabled) {
      return
    }

    const timer = setInterval(() => saved.current(), intervalMs)

    return () => clearInterval(timer)
  }, [intervalMs, enabled])
}

/** Buyurtma va taxta sahifalari uchun umumiy oraliq. */
export const POLL_MS = 15_000
