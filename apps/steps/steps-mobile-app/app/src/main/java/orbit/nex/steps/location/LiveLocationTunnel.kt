package orbit.nex.steps.location

import android.content.Context
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Bundle
import android.os.Looper
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.WebhookConfig
import org.json.JSONObject
import java.time.Instant
import java.util.UUID
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicBoolean
import java.util.concurrent.atomic.AtomicReference

/**
 * Pushes the phone's current fix through the Steps websocket tunnel so the web
 * map can show a live marker without waiting for the next batch upload.
 */
class LiveLocationTunnel(
    private val context: Context,
    private val prefs: PreferencesManager,
) {
    private val scope = CoroutineScope(Dispatchers.IO)
    private val client = OkHttpClient.Builder()
        .pingInterval(20, TimeUnit.SECONDS)
        .readTimeout(0, TimeUnit.MILLISECONDS)
        .build()
    private val running = AtomicBoolean(false)
    private val socketOpen = AtomicBoolean(false)
    private val lastFix = AtomicReference<Location?>(null)
    private var socket: WebSocket? = null
    private var renewJob: Job? = null
    private var locationManager: LocationManager? = null
    private var listener: LocationListener? = null

    fun start() {
        if (!prefs.isLiveLocationShareEnabled()) return
        if (!running.compareAndSet(false, true)) return
        renewJob = scope.launch {
            while (isActive && running.get()) {
                if (socket != null && socketOpen.get()) {
                    delay(4_000)
                    continue
                }
                val ok = connectOnce()
                delay(if (ok) 4_000 else 1_200)
            }
        }
        startGps()
    }

    fun stop() {
        running.set(false)
        renewJob?.cancel()
        renewJob = null
        stopGps()
        socketOpen.set(false)
        socket?.close(1000, "stop")
        socket = null
    }

    fun isRunning(): Boolean = running.get()

    private fun startGps() {
        if (!LocationPermissions.hasForeground(context)) return
        val manager = context.getSystemService(LocationManager::class.java) ?: return
        locationManager = manager
        val locListener = object : LocationListener {
            override fun onLocationChanged(location: Location) {
                publish(location)
            }
            @Deprecated("Deprecated in Java")
            override fun onStatusChanged(provider: String?, status: Int, extras: Bundle?) {}
            override fun onProviderEnabled(provider: String) {}
            override fun onProviderDisabled(provider: String) {}
        }
        listener = locListener
        listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER).forEach { provider ->
            runCatching {
                if (manager.isProviderEnabled(provider)) {
                    manager.requestLocationUpdates(provider, 1_000L, 0f, locListener, Looper.getMainLooper())
                }
            }
        }
        runCatching {
            listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
                .firstNotNullOfOrNull { p -> runCatching { manager.getLastKnownLocation(p) }.getOrNull() }
                ?.let(::publish)
        }
    }

    private fun stopGps() {
        val manager = locationManager
        val locListener = listener
        if (manager != null && locListener != null) {
            runCatching { manager.removeUpdates(locListener) }
        }
        locationManager = null
        listener = null
    }

    private fun publish(location: Location) {
        lastFix.set(location)
        val ws = socket ?: return
        if (!socketOpen.get()) return
        val payload = JSONObject()
            .put("type", "fix")
            .put("latitude", location.latitude)
            .put("longitude", location.longitude)
            .put("accuracy_m", location.accuracy.toDouble())
            .put("bearing_degrees", if (location.hasBearing()) location.bearing.toDouble() else JSONObject.NULL)
            .put("source_at", Instant.ofEpochMilli(location.time).toString())
            .put("client_event_id", UUID.randomUUID().toString())
            .put("device_id", prefs.getLocationDeviceId())
        ws.send(payload.toString())
    }

    private suspend fun connectOnce(): Boolean {
        val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled } ?: return false
        val ticket = mintTicket(config) ?: return false
        val base = GeoMapRepository.apiBase(config)
        val uri = android.net.Uri.parse(base)
        val wsScheme = if (uri.scheme.equals("https", true)) "wss" else "ws"
        val host = uri.authority ?: return false
        val url = "$wsScheme://$host/steps-live-ws?ticket=$ticket"
        val opened = CompletableDeferred<Boolean>()
        withContext(Dispatchers.Main) {
            socket?.cancel()
            socketOpen.set(false)
            socket = client.newWebSocket(
                Request.Builder().url(url).build(),
                object : WebSocketListener() {
                    override fun onOpen(webSocket: WebSocket, response: Response) {
                        if (socket === webSocket) {
                            socketOpen.set(true)
                            opened.complete(true)
                            lastFix.get()?.let { publish(it) }
                        }
                    }
                    override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                        if (socket === webSocket) {
                            socket = null
                            socketOpen.set(false)
                        }
                        opened.complete(false)
                    }
                    override fun onClosed(webSocket: WebSocket, code: Int, reason: String) {
                        if (socket === webSocket) {
                            socket = null
                            socketOpen.set(false)
                        }
                    }
                },
            )
        }
        return runCatching {
            withTimeoutOrNullCompat(8_000) { opened.await() } ?: false
        }.getOrDefault(false)
    }

    private suspend fun <T> withTimeoutOrNullCompat(ms: Long, block: suspend () -> T): T? {
        return try {
            kotlinx.coroutines.withTimeout(ms) { block() }
        } catch (_: kotlinx.coroutines.TimeoutCancellationException) {
            null
        }
    }

    private fun mintTicket(config: WebhookConfig): String? {
        val body = JSONObject()
            .put("action", "ticket")
            .put("role", "device")
            .put("device_id", prefs.getLocationDeviceId())
            .toString()
            .toRequestBody("application/json; charset=utf-8".toMediaType())
        val request = Request.Builder()
            .url(GeoMapRepository.apiBase(config).trimEnd('/') + "/geolocation_live")
            .post(body)
            .apply {
                config.headers.forEach { (k, v) ->
                    if (!k.equals("Accept", true)) header(k, v)
                }
                header("X-Device-Id", prefs.getDeviceId())
                header("Accept", "application/json")
            }
            .build()
        return runCatching {
            client.newCall(request).execute().use { response ->
                if (!response.isSuccessful) return@use null
                JSONObject(response.body?.string().orEmpty()).optString("ticket").takeIf { it.isNotBlank() }
            }
        }.getOrNull()
    }
}
