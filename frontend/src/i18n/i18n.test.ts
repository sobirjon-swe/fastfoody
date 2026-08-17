import { describe, expect, it } from 'vitest'

import { en } from '@/i18n/en'
import { LOCALES, matchLocale } from '@/i18n/locales'
import { ru } from '@/i18n/ru'
import { uz } from '@/i18n/uz'
import { uzCyrl } from '@/i18n/uz-cyrl'

describe('lugʻatlar', () => {
  const dictionaries = { uz_Cyrl: uzCyrl, ru, en }

  it.each(Object.entries(dictionaries))(
    '%s barcha kalitlarni qamrab oladi va boʻsh qiymat qoldirmaydi',
    (_name, dictionary) => {
      const keys = Object.keys(uz)

      expect(Object.keys(dictionary).sort()).toEqual(keys.sort())
      expect(Object.values(dictionary).filter((value) => value.trim() === '')).toEqual([])
    },
  )

  it('tarjima kalitidan farq qiladi — nusxa koʻchirilib qolmagan', () => {
    // Bir nechta soʻz (masalan «Menyu», «Telefon») tillarda bir xil boʻlishi
    // tabiiy, shuning uchun koʻpchilik tarjima qilinganini tekshiramiz.
    for (const [name, dictionary] of Object.entries(dictionaries)) {
      const total = Object.keys(uz).length
      const translated = Object.entries(dictionary).filter(([key, value]) => key !== value).length

      expect(translated / total, `${name} lugʻati`).toBeGreaterThan(0.8)
    }
  })
})

describe('matchLocale', () => {
  it('til kodini ilova tiliga solishtiradi', () => {
    expect(matchLocale('ru-RU')).toBe('ru')
    expect(matchLocale('en')).toBe('en')
    expect(matchLocale('uz')).toBe('uz')
  })

  it('kirill kodini lotin bilan chalkashtirmaydi', () => {
    expect(matchLocale('uz-Cyrl')).toBe('uz_Cyrl')
    expect(matchLocale('uz-Cyrl-UZ')).toBe('uz_Cyrl')
  })

  it('notanish tilga NULL qaytaradi', () => {
    expect(matchLocale('de-DE')).toBeNull()
    expect(matchLocale('')).toBeNull()
    expect(matchLocale(null)).toBeNull()
  })

  it('barcha tillar oʻzini topadi', () => {
    for (const locale of LOCALES) {
      expect(matchLocale(locale)).toBe(locale)
    }
  })
})
