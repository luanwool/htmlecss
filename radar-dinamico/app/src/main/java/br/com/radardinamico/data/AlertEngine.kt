package br.com.radardinamico.data

import java.util.Calendar

/** Resultado de uma avaliação: o dinâmico que merece alerta e a distância até ele. */
data class AlertMatch(val event: SurgeEvent, val distanceKm: Double?, val cell: String)

/**
 * Regras de alerta (funções puras, testadas em AlertEngineTest).
 * O motorista configura tudo em [Settings].
 */
object AlertEngine {

    fun withinSchedule(s: Settings, now: Calendar): Boolean {
        if (!s.scheduleEnabled) return true
        if (now.get(Calendar.DAY_OF_WEEK) !in s.activeDays) return false
        val h = now.get(Calendar.HOUR_OF_DAY)
        return if (s.startHour <= s.endHour) h in s.startHour until s.endHour.coerceAtLeast(s.startHour + 1)
        else h >= s.startHour || h < s.endHour // faixa que atravessa a meia-noite, ex.: 20h–4h
    }

    fun passesThreshold(s: Settings, e: SurgeEvent): Boolean = when (e.kind) {
        SurgeKind.MONEY -> e.value >= s.minMoney
        SurgeKind.MULTIPLIER -> s.useMultiplier && e.value >= s.minMultiplier
    }

    /**
     * Retorna o melhor dinâmico que atende às regras, ou null.
     * [lastAlertByCell] guarda quando cada região foi alertada (para respeitar o intervalo).
     */
    fun evaluate(
        s: Settings,
        events: List<SurgeEvent>,
        here: GeoPoint?,
        nowMs: Long,
        now: Calendar,
        lastAlertByCell: Map<String, Long>,
    ): AlertMatch? {
        if (!s.alertsEnabled || !withinSchedule(s, now)) return null
        val freshMs = s.freshnessMin * 60_000L
        val cooldownMs = s.cooldownMin * 60_000L
        return events.asSequence()
            .filter { nowMs - it.timestamp in 0..freshMs }
            .filter { passesThreshold(s, it) }
            .map { e ->
                val p = GeoPoint(e.lat, e.lng)
                AlertMatch(e, here?.let { Geo.distanceKm(it, p) }, Geo.cellOf(p))
            }
            // sem GPS só alertamos leituras vindas do próprio Uber Driver
            .filter { m ->
                m.distanceKm?.let { it <= s.radiusKm }
                    ?: (m.event.source == SurgeSource.UBER_SCREEN || m.event.source == SurgeSource.UBER_NOTIFICATION)
            }
            .filter { m -> lastAlertByCell[m.cell]?.let { nowMs - it >= cooldownMs } ?: true }
            .maxWithOrNull(compareBy<AlertMatch> { normalized(it.event) }.thenBy { -(it.distanceKm ?: 0.0) })
    }

    /** Coloca R$ e multiplicador numa mesma escala para escolher o melhor (1,0x a mais ≈ R$ 10). */
    fun normalized(e: SurgeEvent) = if (e.kind == SurgeKind.MONEY) e.value else (e.value - 1.0) * 10.0
}
