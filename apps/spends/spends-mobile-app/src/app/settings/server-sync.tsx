import { ScrollView } from "react-native"

import { ServerPairingPanel } from "~/components/settings/server-pairing-panel"
import { settingsStyles } from "~/components/settings/settings-list"

export default function ServerSyncScreen() {
  return (
    <ScrollView
      style={settingsStyles.screen}
      contentContainerStyle={settingsStyles.content}
      contentInsetAdjustmentBehavior="automatic"
      showsVerticalScrollIndicator={false}
    >
      <ServerPairingPanel />
    </ScrollView>
  )
}
