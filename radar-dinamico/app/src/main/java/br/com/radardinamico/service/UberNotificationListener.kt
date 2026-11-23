package br.com.radardinamico.service

import android.app.Notification
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import br.com.radardinamico.data.SettingsStore
import br.com.radardinamico.data.SurgeEvent
import br.com.radardinamico.data.SurgeParser
import br.com.radardinamico.data.SurgeRepository
import br.com.radardinamico.data.SurgeSource

/**
 * Lê as notificações que o próprio Uber Driver envia (ex.: "Alta demanda perto de você",
 * "Preços mais altos em Pinheiros"). Funciona mesmo com o mapa fora da tela, como durante uma corrida.
 */
class UberNotificationListener : NotificationListenerService() {

    override fun onNotificationPosted(sbn: StatusBarNotification) {
        try { handle(sbn) } catch (t: Throwable) { Diagnostics.error(this, "Avisos do Uber", t) }
    }

    private fun handle(sbn: StatusBarNotification) {
        if (sbn.packageName !in UberReaderService.UBER_PACKAGES) return
        val s = SettingsStore.state.value
        if (!s.sourceUberNotifications) return
        val ex = sbn.notification.extras
        val text = listOfNotNull(
            ex.getCharSequence(Notification.EXTRA_TITLE),
            ex.getCharSequence(Notification.EXTRA_TEXT),
            ex.getCharSequence(Notification.EXTRA_BIG_TEXT),
        ).joinToString(" • ").trim()
        if (text.isEmpty()) return

        val readings = SurgeParser.parse(text)
        val isDemand = DEMAND_WORDS.any { text.contains(it, ignoreCase = true) }
        if (readings.isEmpty() && !isDemand) return
        RadarState.lastUberNotification.value = text

        val here = RadarState.location.value
        if (here != null && readings.isNotEmpty()) {
            val repo = SurgeRepository.get(this)
            readings.groupBy { it.kind }.values.map { l -> l.maxBy { it.value } }.forEach { r ->
                val e = SurgeEvent(timestamp = System.currentTimeMillis(), lat = here.lat, lng = here.lng,
                    kind = r.kind, value = r.value, source = SurgeSource.UBER_NOTIFICATION, note = text.take(200))
                if (!repo.isDuplicate(e)) repo.insert(e)
            }
        } else if (s.alertsEnabled) {
            // Sem valor numérico: repassa o aviso do Uber com destaque (e voz, se ligada).
            Notifier.demandNotice(this, s, text)
        }
    }

    companion object {
        val DEMAND_WORDS = listOf("dinâmic", "demanda", "preços mais altos", "preço mais alto", "surge", "movimentad")
    }
}
