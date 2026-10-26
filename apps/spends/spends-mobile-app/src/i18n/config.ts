import i18n from "i18next"
import { initReactI18next } from "react-i18next"

import { LangCodeEnum } from "~/i18n/language.constants"

import en from "./translation/en.json" with { type: "json" }

type Primitive = string | number | boolean | bigint | symbol | null | undefined

type IsPlainObject<T> = T extends Primitive
  ? false
  : T extends readonly unknown[]
    ? false
    : T extends object
      ? true
      : false

type NestedKeyOf<T> = {
  [K in keyof T & string]: IsPlainObject<T[K]> extends true
    ? `${K}.${NestedKeyOf<T[K]>}`
    : T[K] extends string
      ? K
      : never
}[keyof T & string]

type TranslationShape = typeof en
export type TranslationKey = NestedKeyOf<TranslationShape>

const En = en satisfies TranslationShape

const LanguageResources = {
  [LangCodeEnum.EN]: { translation: En },
}

const lazyBundles: Record<string, () => object> = {
  [LangCodeEnum.UK]: () => require("./translation/uk.json"),
  [LangCodeEnum.RU]: () => require("./translation/ru.json"),
  [LangCodeEnum.BE]: () => require("./translation/be.json"),
  [LangCodeEnum.PL]: () => require("./translation/pl.json"),
  [LangCodeEnum.CS]: () => require("./translation/cs.json"),
  [LangCodeEnum.AR]: () => require("./translation/ar.json"),
}

export function ensureLanguageLoaded(code: string): void {
  if (i18n.hasResourceBundle(code, "translation")) return
  const load = lazyBundles[code]
  if (!load) return
  i18n.addResourceBundle(code, "translation", load())
}

declare module "i18next" {
  interface CustomTypeOptions {
    defaultNS: "translation"
    resources: {
      translation: TranslationShape
    }
  }
}

const initialLanguage = LangCodeEnum.EN

i18n.use(initReactI18next).init({
  resources: LanguageResources,
  lng: initialLanguage,
  fallbackLng: LangCodeEnum.EN,
  debug: false,
  interpolation: { escapeValue: false },
  showSupportNotice: false,
})

export default i18n
