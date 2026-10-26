export const DirectionEnum = {
  LTR: "ltr",
  RTL: "rtl",
} as const

export type DirectionType = (typeof DirectionEnum)[keyof typeof DirectionEnum]

/** Languages supported by AppDashboard Spends (+ Arabic RTL from the mobile UI). */
export const LangCodeEnum = {
  EN: "en",
  UK: "uk",
  RU: "ru",
  BE: "be",
  PL: "pl",
  CS: "cs",
  AR: "ar",
} as const

export type LangCodeType = (typeof LangCodeEnum)[keyof typeof LangCodeEnum]

export const LANG_LABELS: Record<LangCodeType, string> = {
  en: "English",
  uk: "Українська",
  ru: "Русский",
  be: "Беларуская",
  pl: "Polski",
  cs: "Čeština",
  ar: "العربية",
}
