import * as Sentry from "@sentry/react-native"

Sentry.init({
  dsn: "https://af8bc3bbf11840429097f675fc5c9be4@sentry.serverkherson.xyz/4",
  tracesSampleRate: 0.01,
  enableAutoSessionTracking: false,
  sendDefaultPii: false,
})

import "./src/styles/unistyles"
import "./src/i18n/config"
import "expo-router/entry"
