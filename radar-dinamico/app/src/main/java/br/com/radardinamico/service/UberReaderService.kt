package br.com.radardinamico.service

import android.accessibilityservice.AccessibilityService
import android.view.accessibility.AccessibilityEvent
import android.view.accessibility.AccessibilityNodeInfo
import br.com.radardinamico.data.SettingsStore
import br.com.radardinamico.data.SurgeEvent
import br.com.radardinamico.data.SurgeParser
import br.com.radardinamico.data.SurgeRepository
import br.com.radardinamico.data.SurgeSource

/**
 * Leitor de tela (Serviço de Acessibilidade) — EXPERIMENTAL.
 * Lê apenas os textos visíveis do app Uber Driver (pacote configurado em accessibility_config.xml),
 * procura valores de dinâmico ("+R$ 6,00", "1,5x") e grava junto com a posição atual do motorista.
 * Nada é tocado ou clicado no app da Uber: é somente leitura.
 */
class UberReaderService : AccessibilityService() {

    private var lastScanAt = 0L

    override fun onAccessibilityEvent(event: AccessibilityEvent?) {
        val s = SettingsStore.state.value
        if (!s.sourceUberScreen) return
        val now = System.currentTimeMillis()
        if (now - lastScanAt < SCAN_INTERVAL_MS) return
        lastScanAt = now

        val root = rootInActiveWindow ?: return
        if (root.packageName?.toString() !in UBER_PACKAGES) return
        val texts = mutableListOf<String>()
        collect(root, texts, 0)
        RadarState.lastScreenReadAt.value = now

        val here = RadarState.location.value ?: return
        val repo = SurgeRepository.get(this)
        SurgeParser.best(texts).forEach { r ->
            val e = SurgeEvent(timestamp = now, lat = here.lat, lng = here.lng,
                kind = r.kind, value = r.value, source = SurgeSource.UBER_SCREEN)
            if (!repo.isDuplicate(e)) RadarService.shareIfEnabled(repo.insert(e))
        }
    }

    private fun collect(node: AccessibilityNodeInfo?, out: MutableList<String>, depth: Int) {
        if (node == null || depth > 40 || out.size > 400) return
        node.text?.let { if (it.isNotBlank()) out += it.toString() }
        node.contentDescription?.let { if (it.isNotBlank()) out += it.toString() }
        for (i in 0 until node.childCount) collect(node.getChild(i), out, depth + 1)
    }

    override fun onInterrupt() = Unit

    companion object {
        const val SCAN_INTERVAL_MS = 3_000L
        val UBER_PACKAGES = setOf("com.ubercab.driver")
    }
}
