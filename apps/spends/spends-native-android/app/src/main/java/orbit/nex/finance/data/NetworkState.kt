package orbit.nex.finance.data

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

/**
 * App-wide connection state used by the bottom bar indicator and to replay the
 * durable outbox. It reflects whether the paired server is actually reachable:
 * a plain Wi-Fi/LTE network is not enough (captive portals and local servers
 * without internet validation both used to keep the bar red forever).
 * Every API call reports its result here.
 */
object NetworkState {
    private val _online = MutableStateFlow(true)
    val online: StateFlow<Boolean> = _online

    @Volatile private var started = false

    fun markReachable() { _online.value = true }

    fun markUnreachable() { _online.value = false }

    fun start(context: Context) {
        if (started) return
        started = true
        val manager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as? ConnectivityManager ?: return
        runCatching {
            manager.registerDefaultNetworkCallback(object : ConnectivityManager.NetworkCallback() {
                // Losing the network is immediately visible; regaining it only
                // clears the indicator once the server answers a real request.
                override fun onLost(network: Network) {}
            })
        }
    }

    /** True on cellular or metered Wi‑Fi; used to skip discretionary sync while RESUMED. */
    fun isMetered(context: Context): Boolean {
        val manager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as? ConnectivityManager ?: return false
        val network = manager.activeNetwork ?: return false
        val caps = manager.getNetworkCapabilities(network) ?: return false
        return !caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_NOT_METERED)
    }
}
