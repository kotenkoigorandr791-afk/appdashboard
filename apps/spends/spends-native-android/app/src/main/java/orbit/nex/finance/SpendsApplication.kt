package orbit.nex.finance

import android.app.Application
import coil.ImageLoader
import coil.ImageLoaderFactory
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import okhttp3.OkHttpClient
import orbit.nex.finance.data.NetworkState
import orbit.nex.finance.data.SessionStore
import orbit.nex.finance.data.SpendsApi
import orbit.nex.finance.data.SyncWorker
import orbit.nex.finance.data.local.SpendsDatabase

class SpendsApplication : Application(), ImageLoaderFactory {
    private val io = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    val db by lazy { SpendsDatabase.create(this) }
    val store by lazy { SessionStore(this, db.ledger()) }
    val api by lazy { SpendsApi(store.deviceId()) { log -> io.launch { store.addLog(log) } } }

    override fun onCreate() {
        super.onCreate()
        NetworkState.start(this)
        WalletListener.install(this)
        SyncWorker.schedule(this)
        io.launch { store.migrateLegacySnapshotIfNeeded() }
        // Mirror the stored language so attachBaseContext can read it synchronously.
        // Only needed when the preference file is empty (restored install), and it
        // must stay off the main thread: a DataStore read here cost ~100 ms of
        // cold start and ran before the first frame.
        if (AppLocale.current(this).isBlank()) {
            io.launch { runCatching { AppLocale.store(this@SpendsApplication, store.appearance.first().language) } }
        }
    }

    override fun newImageLoader(): ImageLoader = ImageLoader.Builder(this)
        .okHttpClient {
            OkHttpClient.Builder().addInterceptor { chain ->
                val token = store.tokenNow()
                val req = if (token.isBlank()) chain.request() else chain.request().newBuilder().header("Authorization", "Bearer $token").build()
                chain.proceed(req)
            }.build()
        }
        .crossfade(false)
        .memoryCache {
            coil.memory.MemoryCache.Builder(this)
                .maxSizePercent(0.15)
                .build()
        }
        .diskCache {
            coil.disk.DiskCache.Builder()
                .directory(cacheDir.resolve("image_cache"))
                .maxSizePercent(0.02)
                .build()
        }
        .respectCacheHeaders(true)
        .build()
}
