import { useTranslation } from "react-i18next"
import { Platform, ScrollView } from "react-native"

import {
  SettingsOptionRow,
  SettingsSection,
  settingsStyles,
} from "~/components/settings/settings-list"
import {
  DirectionEnum,
  LANG_LABELS,
  LangCodeEnum,
  type LangCodeType,
} from "~/i18n/language.constants"
import { useLanguageStore } from "~/stores/language.store"
import { reloadApp } from "~/utils/reload-app"

const languageOptions: { value: LangCodeType; label: string }[] = [
  LangCodeEnum.EN,
  LangCodeEnum.UK,
  LangCodeEnum.RU,
  LangCodeEnum.BE,
  LangCodeEnum.PL,
  LangCodeEnum.CS,
  LangCodeEnum.AR,
].map((value) => ({ value, label: LANG_LABELS[value] }))

export default function LanguageOptionsScreen() {
  const { t } = useTranslation()
  const languageCode = useLanguageStore((s) => s.languageCode)
  const direction = useLanguageStore((s) => s.direction)
  const setLanguageCode = useLanguageStore((s) => s.setLanguageCode)

  const handleSelectLanguage = async (code: LangCodeType) => {
    const newDirection =
      code === LangCodeEnum.AR ? DirectionEnum.RTL : DirectionEnum.LTR
    setLanguageCode(code)
    if (Platform.OS === "web" || newDirection === direction) return
    await reloadApp()
  }

  return (
    <ScrollView
      style={settingsStyles.screen}
      contentContainerStyle={settingsStyles.content}
      contentInsetAdjustmentBehavior="automatic"
      showsVerticalScrollIndicator={false}
    >
      <SettingsSection
        title={t("screens.settings.preferences.language.sectionLabel")}
      >
        {languageOptions.map((option) => (
          <SettingsOptionRow
            key={option.value}
            label={option.label}
            selected={languageCode === option.value}
            onPress={() => handleSelectLanguage(option.value)}
          />
        ))}
      </SettingsSection>
    </ScrollView>
  )
}
