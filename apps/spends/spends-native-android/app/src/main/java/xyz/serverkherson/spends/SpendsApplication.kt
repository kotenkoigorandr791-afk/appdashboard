package xyz.serverkherson.spends

import android.app.Application
import xyz.serverkherson.spends.data.SessionStore
import xyz.serverkherson.spends.data.SpendsApi

class SpendsApplication : Application() {
    val store by lazy { SessionStore(this) }
    val api by lazy { SpendsApi() }
}
