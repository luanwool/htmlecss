package br.com.radardinamico.service

import android.accessibilityservice.AccessibilityService
import android.graphics.Rect
import android.view.accessibility.AccessibilityEvent
import android.view.accessibility.AccessibilityNodeInfo
import br.com.radardinamico.data.Geo
import br.com.radardinamico.data.ScreenProjector
import br.com.radardinamico.data.SettingsStore
import br.com.radardinamico.data.SurgeEvent
import br.com.radardinamico.data.SurgeParser
import br.com.radardinamico.data.SurgeRepository
import br.com.radardinamico.data.SurgeSource

/**
 * Leitor de tela (Serviço de Acessibilidade) — somente leitura.
 * Lê os textos visíveis do app Uber Driver, encontra cada rótulo de dinâmico ("+R$ 6,00", "1,5x")
 * e usa a POSIÇÃO do rótulo na tela para estimar onde fica aquela zona no mapa real.
 */
class UberReaderService : AccessibilityService() {

    private var lastScanAt = 0L

    private data class Label(val text: String, val bounds: Rect)

    override fun onAccessibilityEvent(event: AccessibilityEvent?) {
        val s = SettingsStore.state.value
        if (!s.sourceUberScreen) return
        val now = System.currentTimeMillis()
        if (now - lastScanAt < SCAN_INTERVAL_MS) return
        lastScanAt = now

        val root = rootInActiveWindow ?: return
        if (root.packageName?.toString() !in UBER_PACKAGES) return
        val labels = mutableListOf<Label>()
        collect(root, labels, 0)
        RadarState.lastScreenReadAt.value = now

        val here = RadarState.location.value ?: return
        val dm = resources.displayMetrics
        val screenArea = dm.widthPixels.toLong() * dm.heightPixels
        val repo = SurgeRepository.get(this)
        val debug = mutableListOf<String>()

        labels.forEach { label ->
            val reading = SurgeParser.parse(label.text).maxByOrNull { it.value } ?: return@forEach
            val b = label.bounds
            // Rótulo pequeno = marcador no mapa -> projeta a posição. Elemento grande (ex.: o mapa
            // inteiro com descrição) não diz onde fica a zona -> usa a posição do motorista.
            val isMapLabel = b.width().toLong() * b.height() < screenArea / 6 && b.width() > 0
            val point = if (isMapLabel) ScreenProjector.project(
                here, b.exactCenterX(), b.exactCenterY(), dm.widthPixels, dm.heightPixels,
                dm.density, s.mapZoom, s.carYPercent,
            ) else here
            val e = SurgeEvent(timestamp = now, lat = point.lat, lng = point.lng,
                kind = reading.kind, value = reading.value, source = SurgeSource.UBER_SCREEN,
                note = if (isMapLabel) null else "posição aproximada (seu local)")
            debug += "${e.label()} em (${b.centerX()},${b.centerY()}) → " +
                (if (isMapLabel) Geo.formatKm(Geo.distanceKm(here, point)) + " " + Geo.cardinal(here, point)
                else "sem posição no mapa") + " • \"${label.text.take(40)}\""
            if (!repo.isDuplicate(e)) RadarService.shareIfEnabled(repo.insert(e))
        }
        if (debug.isNotEmpty()) RadarState.readerDebug.value = debug.take(15)
    }

    private fun collect(node: AccessibilityNodeInfo?, out: MutableList<Label>, depth: Int) {
        if (node == null || depth > 40 || out.size > 400) return
        val txt = listOfNotNull(node.text, node.contentDescription)
            .map { it.toString() }.filter { it.isNotBlank() }.joinToString(" ")
        if (txt.isNotEmpty() && SurgeParser.parse(txt).isNotEmpty()) {
            val r = Rect(); node.getBoundsInScreen(r)
            out += Label(txt, r)
        }
        for (i in 0 until node.childCount) collect(node.getChild(i), out, depth + 1)
    }

    override fun onInterrupt() = Unit

    companion object {
        const val SCAN_INTERVAL_MS = 3_000L
        val UBER_PACKAGES = setOf("com.ubercab.driver")
    }
}
