/**
 * Ilova tillari. Kalitlar backend'dagi `config/fastfoody.php` bilan bir xil:
 * shu sababli profilga saqlangan til ikkala tomonda ham bir maʼnoni bildiradi.
 */
export const LOCALES = ['uz', 'uz_Cyrl', 'ru', 'en'] as const

export type Locale = (typeof LOCALES)[number]

export const DEFAULT_LOCALE: Locale = 'uz'

/** Til almashtirgichda koʻrinadigan nomlar — har biri oʻz tilida. */
export const LOCALE_LABELS: Record<Locale, string> = {
  uz: "O'zbekcha",
  uz_Cyrl: 'Ўзбекча',
  ru: 'Русский',
  en: 'English',
}

/** Qisqa belgi: joy tor boʻlgan sarlavhalarda. */
export const LOCALE_SHORT: Record<Locale, string> = {
  uz: 'UZ',
  uz_Cyrl: 'ЎЗ',
  ru: 'RU',
  en: 'EN',
}

export function isLocale(value: unknown): value is Locale {
  return typeof value === 'string' && (LOCALES as readonly string[]).includes(value)
}

/**
 * Brauzer yoki Telegram bergan til kodini ilova tiliga solishtiradi:
 * "ru-RU" → "ru", "uz-Cyrl-UZ" → "uz_Cyrl", "de" → null.
 */
export function matchLocale(code: string | null | undefined): Locale | null {
  if (!code) {
    return null
  }

  const normalised = code.replace(/-/g, '_').toLowerCase()

  // Uzunroq kalit birinchi tekshiriladi, aks holda "uz_cyrl" ni "uz" yutib
  // yuborardi.
  const byLength = [...LOCALES].sort((a, b) => b.length - a.length)

  return (
    byLength.find(
      (locale) =>
        normalised === locale.toLowerCase() || normalised.startsWith(`${locale.toLowerCase()}_`),
    ) ?? null
  )
}
