package br.com.radardinamico.data

import java.util.Calendar

/** Região (célula de ~1 km) e o quanto ela costuma pagar no horário analisado. */
data class HotSpot(
    val cell: String,
    val center: GeoPoint,
    val avgMoney: Double?,
    val maxMoney: Double?,
    val avgMultiplier: Double?,
    val samples: Int,
    val distanceKm: Double?,
)

object Insights {
    /**
     * Ranking das regiões que historicamente pagam melhor em um horário parecido com [now]
     * (mesmo tipo de dia — útil ou fim de semana — e hora ±[hourWindow]).
     */
    fun bestSpots(
        history: List<SurgeEvent>,
        now: Calendar,
        here: GeoPoint?,
        hourWindow: Int = 1,
        maxDistanceKm: Double? = null,
        limit: Int = 10,
    ): List<HotSpot> {
        val hour = now.get(Calendar.HOUR_OF_DAY)
        val weekend = isWeekend(now.get(Calendar.DAY_OF_WEEK))
        val cal = Calendar.getInstance()
        val matching = history.filter {
            cal.timeInMillis = it.timestamp
            val h = cal.get(Calendar.HOUR_OF_DAY)
            val diff = minOf(Math.floorMod(h - hour, 24), Math.floorMod(hour - h, 24))
            diff <= hourWindow && isWeekend(cal.get(Calendar.DAY_OF_WEEK)) == weekend
        }
        return rank(matching, here, maxDistanceKm, limit)
    }

    fun rank(events: List<SurgeEvent>, here: GeoPoint?, maxDistanceKm: Double?, limit: Int): List<HotSpot> =
        events.groupBy { Geo.cellOf(GeoPoint(it.lat, it.lng)) }
            .map { (cell, list) ->
                val money = list.filter { it.kind == SurgeKind.MONEY }.map { it.value }
                val mult = list.filter { it.kind == SurgeKind.MULTIPLIER }.map { it.value }
                val center = Geo.cellCenter(cell)
                HotSpot(
                    cell = cell, center = center,
                    avgMoney = money.takeIf { it.isNotEmpty() }?.average(),
                    maxMoney = money.maxOrNull(),
                    avgMultiplier = mult.takeIf { it.isNotEmpty() }?.average(),
                    samples = list.size,
                    distanceKm = here?.let { Geo.distanceKm(it, center) },
                )
            }
            .filter { maxDistanceKm == null || it.distanceKm == null || it.distanceKm <= maxDistanceKm }
            .sortedWith(compareByDescending<HotSpot> { score(it) }.thenByDescending { it.samples })
            .take(limit)

    /** Pontuação: valor médio pesado levemente pela quantidade de amostras (mais amostras = mais confiável). */
    fun score(s: HotSpot): Double {
        val base = s.avgMoney ?: ((s.avgMultiplier ?: 1.0) - 1.0) * 10.0
        return base * (1 + kotlin.math.ln(s.samples.toDouble()) / 10)
    }

    private fun isWeekend(dow: Int) = dow == Calendar.SATURDAY || dow == Calendar.SUNDAY
}
