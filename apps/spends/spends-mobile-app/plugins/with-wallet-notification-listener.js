const {
  withAndroidManifest,
  withMainApplication,
  withDangerousMod,
  AndroidConfig,
} = require("expo/config-plugins")
const fs = require("node:fs")
const path = require("node:path")

const SERVICE_CLASS = ".wallet.SpendsWalletNotificationListener"
const PACKAGE_PATH = "xyz/serverkherson/spends/wallet"

function ensureWalletSources(projectRoot) {
  const javaRoot = path.join(
    projectRoot,
    "android/app/src/main/java",
    PACKAGE_PATH,
  )
  fs.mkdirSync(javaRoot, { recursive: true })

  const servicePath = path.join(javaRoot, "SpendsWalletNotificationListener.kt")
  const modulePath = path.join(javaRoot, "SpendsWalletNotificationModule.kt")
  const packagePath = path.join(javaRoot, "SpendsWalletNotificationPackage.kt")

  fs.writeFileSync(
    servicePath,
    `package xyz.serverkherson.spends.wallet

import android.app.Notification
import android.content.Intent
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import com.facebook.react.ReactApplication
import com.facebook.react.bridge.Arguments
import com.facebook.react.bridge.ReactContext
import com.facebook.react.modules.core.DeviceEventManagerModule
import java.util.Locale
import java.util.regex.Pattern

class SpendsWalletNotificationListener : NotificationListenerService() {
  companion object {
    private val WALLET_PACKAGES = setOf(
      "com.google.android.apps.walletnfcrel",
      "com.google.android.gms",
      "com.google.android.apps.nbu.paisa.user",
      "com.google.android.apps.pay"
    )
    private val AMOUNT = Pattern.compile(
      "(?:^|\\\\s)(?:USD|EUR|PLN|UAH|BYN|\\\\$|€|zł|₴)?\\\\s*([0-9]+(?:[.,][0-9]{1,2})?)"
    )
  }

  override fun onNotificationPosted(sbn: StatusBarNotification?) {
    if (sbn == null) return
    val pkg = sbn.packageName ?: return
    if (!WALLET_PACKAGES.contains(pkg) && !pkg.contains("wallet", true) && !pkg.contains("pay", true)) return
    val extras = sbn.notification?.extras ?: return
    val title = extras.getCharSequence(Notification.EXTRA_TITLE)?.toString()?.trim().orEmpty()
    val text = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString()?.trim().orEmpty()
    val big = extras.getCharSequence(Notification.EXTRA_BIG_TEXT)?.toString()?.trim().orEmpty()
    val raw = listOf(title, text, big).filter { it.isNotEmpty() }.joinToString(" | ")
    if (raw.isEmpty()) return
    val amount = parseAmount(raw) ?: return
    val store = parseStore(title, text)
    if (store.isEmpty()) return
    emit(store, amount, raw, pkg, sbn.key)
    try { cancelNotification(sbn.key) } catch (_: Throwable) {}
  }

  private fun parseAmount(raw: String): Double? {
    val m = AMOUNT.matcher(raw.replace(',', '.'))
    var best: Double? = null
    while (m.find()) {
      val v = m.group(1)?.toDoubleOrNull() ?: continue
      if (v > 0) best = v
    }
    return best
  }

  private fun parseStore(title: String, text: String): String {
    val candidates = listOf(title, text)
    for (c in candidates) {
      val cleaned = c
        .replace(Regex("(?i)(paid|payment|purchase|google wallet|wallet|gpay|google pay)[:\\\\s-]*"), "")
        .trim()
      if (cleaned.isNotEmpty() && !cleaned.matches(Regex("^[0-9.,\\\\s$€zł₴]+$"))) return cleaned.take(80)
    }
    return title.ifBlank { "Wallet" }.take(80)
  }

  private fun emit(store: String, amount: Double, raw: String, pkg: String, key: String) {
    try {
      val app = application as? ReactApplication ?: return
      val ctx = app.reactNativeHost.reactInstanceManager.currentReactContext ?: return
      val map = Arguments.createMap()
      map.putString("store", store)
      map.putDouble("amount", amount)
      map.putString("raw", raw)
      map.putString("packageName", pkg)
      map.putString("key", key)
      ctx
        .getJSModule(DeviceEventManagerModule.RCTDeviceEventEmitter::class.java)
        .emit("spends.wallet.notification", map)
    } catch (_: Throwable) {}
  }
}
`,
  )

  fs.writeFileSync(
    modulePath,
    `package xyz.serverkherson.spends.wallet

import android.content.ComponentName
import android.content.Intent
import android.provider.Settings
import android.text.TextUtils
import com.facebook.react.bridge.Promise
import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.bridge.ReactContextBaseJavaModule
import com.facebook.react.bridge.ReactMethod

class SpendsWalletNotificationModule(private val ctx: ReactApplicationContext) :
  ReactContextBaseJavaModule(ctx) {
  override fun getName(): String = "SpendsWalletNotification"

  @ReactMethod
  fun openListenerSettings(promise: Promise) {
    try {
      val intent = Intent(Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS)
      intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
      ctx.startActivity(intent)
      promise.resolve(null)
    } catch (e: Exception) {
      promise.reject("open_failed", e)
    }
  }

  @ReactMethod
  fun isListenerEnabled(promise: Promise) {
    try {
      val flat = Settings.Secure.getString(ctx.contentResolver, "enabled_notification_listeners")
      if (flat.isNullOrEmpty()) {
        promise.resolve(false)
        return
      }
      val cn = ComponentName(ctx, SpendsWalletNotificationListener::class.java)
      val enabled = TextUtils.split(flat, ":").any {
        ComponentName.unflattenFromString(it)?.flattenToString() == cn.flattenToString()
          || it.contains(ctx.packageName)
      }
      promise.resolve(enabled)
    } catch (e: Exception) {
      promise.reject("check_failed", e)
    }
  }

  @ReactMethod
  fun addListener(eventName: String) {}

  @ReactMethod
  fun removeListeners(count: Int) {}
}
`,
  )

  fs.writeFileSync(
    packagePath,
    `package xyz.serverkherson.spends.wallet

import com.facebook.react.ReactPackage
import com.facebook.react.bridge.NativeModule
import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.uimanager.ViewManager

class SpendsWalletNotificationPackage : ReactPackage {
  override fun createNativeModules(reactContext: ReactApplicationContext): List<NativeModule> =
    listOf(SpendsWalletNotificationModule(reactContext))

  override fun createViewManagers(reactContext: ReactApplicationContext): List<ViewManager<*, *>> =
    emptyList()
}
`,
  )
}

function withWalletPackage(config) {
  return withMainApplication(config, (cfg) => {
    let contents = cfg.modResults.contents
    if (!contents.includes("SpendsWalletNotificationPackage")) {
      contents = contents.replace(
        /packages\.apply\s*\{/,
        `packages.apply {
              add(xyz.serverkherson.spends.wallet.SpendsWalletNotificationPackage())`,
      )
      if (!contents.includes("SpendsWalletNotificationPackage")) {
        contents = contents.replace(
          /return\s+PackageList\(this\)\.packages/,
          `val packages = PackageList(this).packages
            packages.add(xyz.serverkherson.spends.wallet.SpendsWalletNotificationPackage())
            return packages`,
        )
      }
      cfg.modResults.contents = contents
    }
    return cfg
  })
}

function withWalletManifest(config) {
  return withAndroidManifest(config, (cfg) => {
    const manifest = cfg.modResults
    const app = AndroidConfig.Manifest.getMainApplicationOrThrow(manifest)
    app.service = app.service || []
    const exists = app.service.some(
      (s) => s.$?.["android:name"] === SERVICE_CLASS,
    )
    if (!exists) {
      app.service.push({
        $: {
          "android:name": SERVICE_CLASS,
          "android:exported": "true",
          "android:permission":
            "android.permission.BIND_NOTIFICATION_LISTENER_SERVICE",
        },
        "intent-filter": [
          {
            action: [
              {
                $: {
                  "android:name":
                    "android.service.notification.NotificationListenerService",
                },
              },
            ],
          },
        ],
      })
    }
    return cfg
  })
}

const withWalletNotificationListener = (config) => {
  config = withWalletManifest(config)
  config = withWalletPackage(config)
  config = withDangerousMod(config, [
    "android",
    async (cfg) => {
      ensureWalletSources(cfg.modRequest.projectRoot)
      return cfg
    },
  ])
  return config
}

module.exports = withWalletNotificationListener
