package orbit.nex.steps

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

/**
 * App-wide connection state. Mirrors the Spends app: the bottom bar and the
 * offline banner read this flow, so a dropped connection is visible everywhere
 * at once without every screen polling the network itself.
 */
object NetworkState {
    private val _online = MutableStateFlow(true)
    val online: StateFlow<Boolean> = _online

    @Volatile private var started = false

    fun markReachable() {
        _online.value = true
    }

    fun markUnreachable() {
        _online.value = false
    }

    /** Re-checks the current network, used by the banner retry button. */
    fun retry(context: Context) {
        val manager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as? ConnectivityManager
        if (manager?.activeNetwork != null) markReachable() else markUnreachable()
    }

    fun start(context: Context) {
        if (started) return
        started = true
        val manager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as? ConnectivityManager ?: return
        runCatching {
            manager.registerDefaultNetworkCallback(object : ConnectivityManager.NetworkCallback() {
                override fun onAvailable(network: Network) {
                    markReachable()
                }

                override fun onLost(network: Network) {
                    markUnreachable()
                }

                override fun onUnavailable() {
                    markUnreachable()
                }
            })
        }
    }
}
