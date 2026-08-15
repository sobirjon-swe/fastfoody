/**
 * "32000.00" -> "32 000 soʻm". The grouping is done by hand because the uz-UZ
 * locale data is not present in every runtime, and a fallback locale would
 * render "32,000".
 */
export function formatPrice(price: string | number): string {
  const value = typeof price === 'string' ? Number.parseFloat(price) : price

  if (Number.isNaN(value)) {
    return '—'
  }

  const grouped = Math.round(value)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, ' ')

  return `${grouped} soʻm`
}

/** Prep time of one portion and of each additional portion. */
export function formatPrepTime(base: number, extra: number): string {
  return extra > 0 ? `${base} daq (+${extra} daq)` : `${base} daq`
}
