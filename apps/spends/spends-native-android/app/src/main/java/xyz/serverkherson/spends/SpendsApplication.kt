package xyz.serverkherson.spends

import android.app.Application
import coil.ImageLoader
import coil.ImageLoaderFactory
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import kotlinx.coroutines.runBlocking
import okhttp3.OkHttpClient
import xyz.serverkherson.spends.data.SessionStore
import xyz.serverkherson.spends.data.SpendsApi

class SpendsApplication : Application(), ImageLoaderFactory {
    private val io = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    val store by lazy { SessionStore(this) }
    val api by lazy { SpendsApi { log -> io.launch { store.addLog(log) } } }

    override fun onCreate() {
        super.onCreate()
        // Mirror the stored language so every activity can apply it in attachBaseContext.
        runCatching { runBlocking { AppLocale.store(this@SpendsApplication, store.appearance.first().language) } }
    }

    override fun newImageLoader(): ImageLoader = ImageLoader.Builder(this)
        .okHttpClient {
            OkHttpClient.Builder().addInterceptor { chain ->
                val token = runBlocking { store.session.first().token }
                val req = if (token.isBlank()) chain.request() else chain.request().newBuilder().header("Authorization", "Bearer $token").build()
                chain.proceed(req)
            }.build()
        }
        .crossfade(true)
        .build()
}
