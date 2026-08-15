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

  const [whole, fraction] = value.toFixed(2).split('.')
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ')

  // Tiyin are shown only when they exist, so an ordinary price stays "32 000
  // soʻm" while 12 500,75 is not silently rounded away.
  return fraction === '00' ? `${grouped} soʻm` : `${grouped},${fraction} soʻm`
}

/** Prep time of one portion and of each additional portion. */
export function formatPrepTime(base: number, extra: number): string {
  return extra > 0 ? `${base} daq (+${extra} daq)` : `${base} daq`
}
