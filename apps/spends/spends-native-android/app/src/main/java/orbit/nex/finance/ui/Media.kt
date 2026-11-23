package orbit.nex.finance.ui
import orbit.nex.finance.ui.AppIcons

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import coil.compose.AsyncImage
import coil.request.ImageRequest
import coil.size.Scale
import java.net.URI

fun originOf(endpoint: String): String = runCatching {
    val u = URI(endpoint)
    val port = if (u.port != -1) ":${u.port}" else ""
    "${u.scheme}://${u.host}$port"
}.getOrDefault(endpoint)

fun rebaseUrl(url: String, endpoint: String): String {
    if (url.isBlank()) return url
    return url.replace(Regex("^https?://[^/]+"), originOf(endpoint))
}

@Composable
fun RemotePhoto(url: String, fallback: String, round: Boolean, size: Dp, person: Boolean = false) {
    val shape = if (round) CircleShape else RoundedCornerShape(14.dp)
    val context = LocalContext.current
    val px = with(LocalDensity.current) { size.roundToPx() }
    Box(Modifier.size(size).clip(shape).background(MaterialTheme.colorScheme.primaryContainer), contentAlignment = Alignment.Center) {
        if (url.isNotBlank()) {
            val model = remember(url, px) {
                ImageRequest.Builder(context)
                    .data(url)
                    .size(px)
                    .scale(Scale.FILL)
                    .crossfade(false)
                    .build()
            }
            AsyncImage(model, fallback, Modifier.matchParentSize(), contentScale = ContentScale.Crop)
        } else if (person) {
            Icon(AppIcons.Person, null, tint = MaterialTheme.colorScheme.onPrimaryContainer)
        } else if (fallback.length == 1) {
            Text(fallback, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onPrimaryContainer)
        } else {
            Icon(AppIcons.Storefront, null, tint = MaterialTheme.colorScheme.onPrimaryContainer)
        }
    }
}
