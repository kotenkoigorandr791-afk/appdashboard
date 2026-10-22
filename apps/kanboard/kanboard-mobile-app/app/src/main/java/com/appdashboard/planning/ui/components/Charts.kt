package com.appdashboard.planning.ui.components

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.foundation.background
import androidx.compose.material3.Text
import com.appdashboard.planning.ui.theme.ChartPalette
import com.flyfishxu.vetraui.core.theme.VetraTheme
import kotlin.math.max

data class ChartSlice(val label: String, val value: Float)

@Composable
fun DonutChart(
    slices: List<ChartSlice>,
    modifier: Modifier = Modifier,
    title: String,
) {
    val total = slices.sumOf { it.value.toDouble() }.toFloat().coerceAtLeast(0.0001f)
    val colors = VetraTheme.colors
    Column(modifier = modifier.fillMaxWidth()) {
        Text(title, color = colors.textPrimary, fontWeight = FontWeight.SemiBold)
        Spacer(Modifier.height(12.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Canvas(Modifier.size(120.dp)) {
                var start = -90f
                slices.forEachIndexed { i, slice ->
                    val sweep = (slice.value / total) * 360f
                    drawArc(
                        color = ChartPalette[i % ChartPalette.size],
                        startAngle = start,
                        sweepAngle = sweep,
                        useCenter = false,
                        style = Stroke(width = size.minDimension * 0.18f, cap = StrokeCap.Round),
                        size = Size(size.minDimension, size.minDimension),
                    )
                    start += sweep
                }
            }
            Spacer(Modifier.width(16.dp))
            Column(verticalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.weight(1f)) {
                slices.forEachIndexed { i, slice ->
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Spacer(
                            Modifier
                                .size(10.dp)
                                .background(ChartPalette[i % ChartPalette.size], CircleShape),
                        )
                        Spacer(Modifier.width(8.dp))
                        Text(
                            "${slice.label} · ${slice.value.toInt()}",
                            color = colors.textSecondary,
                            modifier = Modifier.weight(1f),
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun BarChart(
    slices: List<ChartSlice>,
    modifier: Modifier = Modifier,
    title: String,
) {
    val maxV = slices.maxOfOrNull { it.value }?.coerceAtLeast(1f) ?: 1f
    val colors = VetraTheme.colors
    Column(modifier = modifier.fillMaxWidth()) {
        Text(title, color = colors.textPrimary, fontWeight = FontWeight.SemiBold)
        Spacer(Modifier.height(12.dp))
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            slices.forEachIndexed { i, slice ->
                Column {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text(slice.label, color = colors.textSecondary)
                        Text(slice.value.toInt().toString(), color = colors.brand, fontWeight = FontWeight.Bold)
                    }
                    Spacer(Modifier.height(4.dp))
                    Canvas(
                        Modifier
                            .fillMaxWidth()
                            .height(10.dp),
                    ) {
                        val w = size.width * (slice.value / maxV)
                        drawRoundRect(
                            color = colors.borderSubtle.copy(alpha = 0.35f),
                            size = size,
                            cornerRadius = androidx.compose.ui.geometry.CornerRadius(20f, 20f),
                        )
                        drawRoundRect(
                            color = ChartPalette[i % ChartPalette.size],
                            size = Size(max(w, 8f), size.height),
                            cornerRadius = androidx.compose.ui.geometry.CornerRadius(20f, 20f),
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun DualMetricChart(
    open: Float,
    closed: Float,
    modifier: Modifier = Modifier,
    title: String = "Open vs closed",
) {
    DonutChart(
        title = title,
        slices = listOf(
            ChartSlice("Open", open),
            ChartSlice("Closed", closed),
        ),
        modifier = modifier,
    )
}

@Composable
fun FlowAreaChart(
    points: List<Pair<String, Float>>,
    modifier: Modifier = Modifier,
    title: String,
) {
    val colors = VetraTheme.colors
    val maxV = points.maxOfOrNull { it.second }?.coerceAtLeast(1f) ?: 1f
    Column(modifier = modifier.fillMaxWidth()) {
        Text(title, color = colors.textPrimary, fontWeight = FontWeight.SemiBold)
        Spacer(Modifier.height(12.dp))
        Canvas(
            Modifier
                .fillMaxWidth()
                .height(140.dp)
                .background(colors.canvasSubtle.copy(alpha = 0.4f), RoundedCornerShape(16.dp))
                .padding(8.dp),
        ) {
            if (points.isEmpty()) return@Canvas
            val step = if (points.size == 1) size.width else size.width / (points.size - 1)
            val pathPoints = points.mapIndexed { i, p ->
                Offset(i * step, size.height - (p.second / maxV) * size.height * 0.85f - 8f)
            }
            for (i in 0 until pathPoints.lastIndex) {
                drawLine(
                    color = ChartPalette[0].copy(alpha = 0.9f),
                    start = pathPoints[i],
                    end = pathPoints[i + 1],
                    strokeWidth = 5f,
                    cap = StrokeCap.Round,
                )
            }
            pathPoints.forEach {
                drawCircle(color = ChartPalette[1], radius = 6f, center = it)
            }
        }
        Spacer(Modifier.height(8.dp))
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            points.take(4).forEach {
                Text(it.first.take(6), color = colors.textTertiary)
            }
        }
    }
}
