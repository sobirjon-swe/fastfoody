import { useEffect, useRef } from 'react'

import { getWebApp } from '@/lib/telegram'

/**
 * Telegram'ning pastdagi asosiy tugmasi. Mini App ichida savatchadagi
 * «Buyurtma berish» shu tugmaga chiqadi — Telegram uni klaviatura ustida,
 * barmoq yetadigan joyda chizadi. Brauzerda hech narsa oʻzgarmaydi.
 */
export function useTelegramMainButton({
  text,
  visible,
  disabled = false,
  onClick,
}: {
  text: string
  visible: boolean
  disabled?: boolean
  onClick: () => void
}): void {
  // Bosilganda eng soʻnggi ishlovchi chaqirilsin: aks holda Telegram tugmasi
  // savatchaning eski holatini yodda saqlab qolardi.
  const handler = useRef(onClick)
  handler.current = onClick

  useEffect(() => {
    const button = getWebApp()?.MainButton

    if (!button) {
      return
    }

    const click = () => handler.current()

    button.setText(text)
    button.onClick(click)

    if (visible) {
      button.show()
    } else {
      button.hide()
    }

    if (disabled) {
      button.disable()
    } else {
      button.enable()
    }

    return () => {
      button.offClick(click)
      button.hide()
    }
  }, [text, visible, disabled])
}

/** Telegram sarlavhasidagi «orqaga» tugmasi. */
export function useTelegramBackButton(onBack: (() => void) | null): void {
  const handler = useRef(onBack)
  handler.current = onBack

  // Faqat «bor/yoʻq» oʻzgarganda qayta ulanadi: har renderda yangi funksiya
  // berilsa ham tugma yonib-oʻchmaydi.
  const enabled = onBack !== null

  useEffect(() => {
    const button = getWebApp()?.BackButton

    if (!button) {
      return
    }

    if (!enabled) {
      button.hide()

      return
    }

    const click = () => handler.current?.()

    button.onClick(click)
    button.show()

    return () => {
      button.offClick(click)
      button.hide()
    }
  }, [enabled])
}
