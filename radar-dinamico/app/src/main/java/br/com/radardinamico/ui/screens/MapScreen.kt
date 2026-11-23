package br.com.radardinamico.ui.screens

import android.content.Context
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Paint
import android.graphics.RectF
import android.graphics.drawable.BitmapDrawable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import br.com.radardinamico.data.Insights
import br.com.radardinamico.data.SettingsStore
import br.com.radardinamico.data.SurgeRepository
import br.com.radardinamico.service.RadarState
import org.osmdroid.tileprovider.tilesource.TileSourceFactory
import org.osmdroid.util.GeoPoint as OsmPoint
import org.osmdroid.views.MapView
import org.osmdroid.views.overlay.Marker
import org.osmdroid.views.overlay.Polygon
import java.util.Calendar

@Composable
fun MapScreen(modifier: Modifier) {
    val ctx = LocalContext.current
    val settings by SettingsStore.state.collectAsState()
    val here by RadarState.location.collectAsState()
    val recent by SurgeRepository.get(ctx).recent.collectAsState()
    var showHistory by remember { mutableStateOf(false) }
    var centered by remember { mutableStateOf(false) }

    val map = remember {
        MapView(ctx).apply {
            setTileSource(TileSourceFactory.MAPNIK)
            setMultiTouchControls(true)
            controller.setZoom(13.0)
            controller.setCenter(OsmPoint(-23.5505, -46.6333)) // São Paulo como padrão até o GPS responder
        }
    }
    DisposableEffect(Unit) { map.onResume(); onDispose { map.onPause() } }

    val history = remember(showHistory) {
        if (showHistory) SurgeRepository.get(ctx).since(System.currentTimeMillis() - 60L * 24 * 3600_000L) else emptyList()
    }

    Box(modifier.fillMaxSize()) {
        AndroidView(factory = { map }, modifier = Modifier.fillMaxSize(), update = { mv ->
            mv.overlays.clear()
            val now = System.currentTimeMillis()
            here?.let { h ->
                val center = OsmPoint(h.lat, h.lng)
                if (!centered) { mv.controller.setCenter(center); centered = true }
                mv.overlays += Polygon(mv).apply {
                    points = Polygon.pointsAsCircle(center, settings.radiusKm * 1000)
                    fillPaint.color = 0x223B82F6
                    outlinePaint.color = 0xFF3B82F6.toInt()
                    outlinePaint.strokeWidth = 3f
                    setOnClickListener { _, _, _ -> false }
                }
                mv.overlays += Marker(mv).apply {
                    position = center
                    title = "Você"
                    icon = BitmapDrawable(ctx.resources, pill(ctx, "Você", 0xFF3B82F6.toInt()))
                    setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
                }
            }
            if (showHistory) {
                Insights.bestSpots(history, Calendar.getInstance(), here, limit = 30).forEach { s ->
                    val label = s.avgMoney?.let { "~R$%.0f".format(it) }
                        ?: s.avgMultiplier?.let { "~%.1fx".format(it) } ?: return@forEach
                    mv.overlays += Marker(mv).apply {
                        position = OsmPoint(s.center.lat, s.center.lng)
                        title = "Média histórica neste horário: $label (${s.samples} leituras)"
                        icon = BitmapDrawable(ctx.resources, pill(ctx, label, 0xFF64748B.toInt()))
                        setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
                    }
                }
            }
            recent.filter { now - it.timestamp <= settings.freshnessMin * 60_000L }.forEach { e ->
                mv.overlays += Marker(mv).apply {
                    position = OsmPoint(e.lat, e.lng)
                    title = "${e.label()} • ${e.source.label} • ${timeAgo(e.timestamp, now)}"
                    icon = BitmapDrawable(ctx.resources, pill(ctx, e.label(), 0xFFF59E0B.toInt()))
                    setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
                }
            }
            mv.invalidate()
        })
        Row(
            Modifier.align(Alignment.TopCenter).padding(12.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            FilterChip(selected = !showHistory, onClick = { showHistory = false }, label = { Text("Agora") })
            FilterChip(selected = showHistory, onClick = { showHistory = true }, label = { Text("Agora + histórico") })
            FilterChip(selected = false, onClick = {
                here?.let { map.controller.animateTo(OsmPoint(it.lat, it.lng)) }
            }, label = { Text("Centralizar") })
        }
    }
}

/** Etiqueta arredondada com texto, usada como marcador no mapa. */
private fun pill(ctx: Context, text: String, color: Int): Bitmap {
    val d = ctx.resources.displayMetrics.density
    val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        textSize = 14 * d; this.color = if (color == 0xFFF59E0B.toInt()) 0xFF111827.toInt() else 0xFFFFFFFF.toInt()
        isFakeBoldText = true
    }
    val w = (paint.measureText(text) + 20 * d).toInt()
    val h = (28 * d).toInt()
    val bmp = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
    val c = Canvas(bmp)
    c.drawRoundRect(RectF(0f, 0f, w.toFloat(), h.toFloat()), h / 2f, h / 2f, Paint(Paint.ANTI_ALIAS_FLAG).apply { this.color = color })
    c.drawText(text, 10 * d, h / 2f - (paint.descent() + paint.ascent()) / 2, paint)
    return bmp
}
