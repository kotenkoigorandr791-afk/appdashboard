package xyz.serverkherson.spends.ui

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.detectTransformGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.onGloballyPositioned
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import xyz.serverkherson.spends.R
import java.io.ByteArrayOutputStream
import kotlin.math.max
import kotlin.math.min

/**
 * Square cropper: the picture is shown cover-scaled in a 1:1 frame; pinch to zoom and
 * drag to choose the region. The visible square is exported as a 512×512 PNG.
 */
@Composable
fun PhotoCropDialog(uri: Uri, onCancel: () -> Unit, onCropped: (ByteArray?) -> Unit) {
    val context = LocalContext.current
    val bitmap = remember(uri) {
        runCatching { context.contentResolver.openInputStream(uri)?.use { BitmapFactory.decodeStream(it) } }.getOrNull()
    }
    var scale by remember { mutableFloatStateOf(1f) }
    var offset by remember { mutableStateOf(Offset.Zero) }
    var boxSize by remember { mutableFloatStateOf(0f) }

    fun clampOffset(candidate: Offset, zoom: Float): Offset {
        val source = bitmap ?: return candidate
        if (boxSize <= 0f) return candidate
        val base = boxSize / min(source.width, source.height).toFloat()
        val scaledW = source.width * base * zoom
        val scaledH = source.height * base * zoom
        val maxX = max(0f, (scaledW - boxSize) / 2f)
        val maxY = max(0f, (scaledH - boxSize) / 2f)
        return Offset(candidate.x.coerceIn(-maxX, maxX), candidate.y.coerceIn(-maxY, maxY))
    }

    fun crop(): ByteArray? {
        val source = bitmap ?: return null
        if (boxSize <= 0f) return null
        val base = boxSize / min(source.width, source.height).toFloat()
        val effective = base * scale
        val left = (boxSize - source.width * effective) / 2f + offset.x
        val top = (boxSize - source.height * effective) / 2f + offset.y
        val side = boxSize / effective
        val x = (-left / effective).toInt().coerceIn(0, max(0, source.width - 1))
        val y = (-top / effective).toInt().coerceIn(0, max(0, source.height - 1))
        val w = side.toInt().coerceIn(1, source.width - x)
        val h = side.toInt().coerceIn(1, source.height - y)
        val square = min(w, h).coerceAtLeast(1)
        val cropped = runCatching { Bitmap.createBitmap(source, x, y, square, square) }.getOrNull() ?: return null
        val scaled = Bitmap.createScaledBitmap(cropped, 512, 512, true)
        val out = ByteArrayOutputStream()
        scaled.compress(Bitmap.CompressFormat.PNG, 90, out)
        return out.toByteArray()
    }

    Dialog(onDismissRequest = onCancel) {
        Surface(shape = RoundedCornerShape(28.dp), color = MaterialTheme.colorScheme.surface, tonalElevation = 2.dp) {
            Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(stringResource(R.string.crop_title), style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
                Text(stringResource(R.string.crop_hint), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Box(
                    Modifier
                        .fillMaxWidth()
                        .aspectRatio(1f)
                        .onGloballyPositioned { boxSize = it.size.width.toFloat() }
                        .background(Color.Black)
                        .pointerInput(bitmap) {
                            detectTransformGestures { _, pan, zoom, _ ->
                                scale = (scale * zoom).coerceIn(1f, 5f)
                                offset = clampOffset(offset + pan, scale)
                            }
                        },
                    contentAlignment = Alignment.Center,
                ) {
                    if (bitmap != null) {
                        Image(
                            bitmap = bitmap.asImageBitmap(),
                            contentDescription = null,
                            modifier = Modifier.fillMaxWidth().aspectRatio(1f).graphicsLayer(
                                scaleX = scale,
                                scaleY = scale,
                                translationX = offset.x,
                                translationY = offset.y,
                            ),
                            contentScale = ContentScale.Crop,
                        )
                    }
                }
                Button({ onCropped(crop()) }, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) {
                    Text(stringResource(R.string.crop_apply))
                }
                TextButton(onCancel, Modifier.fillMaxWidth()) { Text(stringResource(R.string.heatmap_close)) }
            }
        }
    }
}
