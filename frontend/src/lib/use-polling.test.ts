import { renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { usePolling } from '@/lib/use-polling'

describe('usePolling', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('belgilangan oraliqda chaqiradi', () => {
    const spy = vi.fn()

    renderHook(() => usePolling(spy, 1000))

    expect(spy).not.toHaveBeenCalled()
    vi.advanceTimersByTime(3000)
    expect(spy).toHaveBeenCalledTimes(3)
  })

  it('oʻchirilgan boʻlsa umuman chaqirmaydi', () => {
    const spy = vi.fn()

    renderHook(() => usePolling(spy, 1000, false))
    vi.advanceTimersByTime(5000)

    expect(spy).not.toHaveBeenCalled()
  })

  it('callback yangilansa ham taymer qayta yaratilmaydi', () => {
    const first = vi.fn()
    const second = vi.fn()

    const { rerender } = renderHook(({ fn }) => usePolling(fn, 1000), {
      initialProps: { fn: first },
    })

    vi.advanceTimersByTime(1000)
    rerender({ fn: second })
    vi.advanceTimersByTime(1000)

    // Har bir tik bir marta, yangi callback bilan.
    expect(first).toHaveBeenCalledTimes(1)
    expect(second).toHaveBeenCalledTimes(1)
  })

  it('komponent yopilganda taymer toʻxtaydi', () => {
    const spy = vi.fn()
    const { unmount } = renderHook(() => usePolling(spy, 1000))

    unmount()
    vi.advanceTimersByTime(5000)

    expect(spy).not.toHaveBeenCalled()
  })
})
