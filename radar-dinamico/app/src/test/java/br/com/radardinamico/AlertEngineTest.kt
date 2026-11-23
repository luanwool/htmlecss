package br.com.radardinamico

import br.com.radardinamico.data.AlertEngine
import br.com.radardinamico.data.Geo
import br.com.radardinamico.data.GeoPoint
import br.com.radardinamico.data.Insights
import br.com.radardinamico.data.Settings
import br.com.radardinamico.data.SurgeEvent
import br.com.radardinamico.data.SurgeKind
import br.com.radardinamico.data.SurgeSource
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Test
import java.util.Calendar

class AlertEngineTest {
    private val here = GeoPoint(-23.5505, -46.6333)
    private val now = 1_700_000_000_000L
    private val cal = Calendar.getInstance().apply { set(2026, Calendar.NOVEMBER, 23, 18, 0) } // segunda 18h
    private val s = Settings(minMoney = 6.0, radiusKm = 5.0)

    private fun ev(value: Double, dLat: Double = 0.0, ageMin: Int = 1, kind: SurgeKind = SurgeKind.MONEY) =
        SurgeEvent(id = (value * 100).toLong(), timestamp = now - ageMin * 60_000L, lat = here.lat + dLat, lng = here.lng,
            kind = kind, value = value, source = SurgeSource.MANUAL)

    private fun eval(events: List<SurgeEvent>, settings: Settings = s, last: Map<String, Long> = emptyMap()) =
        AlertEngine.evaluate(settings, events, here, now, cal, last)

    @Test fun alertsAboveThresholdInsideRadius() {
        val m = eval(listOf(ev(4.0), ev(8.0, dLat = 0.02)))
        assertEquals(8.0, m!!.event.value, 0.0)
    }
    @Test fun ignoresOutsideRadius() { assertNull(eval(listOf(ev(10.0, dLat = 0.1)))) } // ~11 km
    @Test fun ignoresStale() { assertNull(eval(listOf(ev(10.0, ageMin = 30)))) }
    @Test fun respectsCooldown() {
        val e = ev(10.0)
        val cell = Geo.cellOf(GeoPoint(e.lat, e.lng))
        assertNull(eval(listOf(e), last = mapOf(cell to now - 60_000)))
        assertNotNull(eval(listOf(e), last = mapOf(cell to now - 10 * 60_000)))
    }
    @Test fun multiplierToggle() {
        val e = ev(1.8, kind = SurgeKind.MULTIPLIER)
        assertNotNull(eval(listOf(e)))
        assertNull(eval(listOf(e), s.copy(useMultiplier = false)))
    }
    @Test fun schedule() {
        val night = s.copy(scheduleEnabled = true, startHour = 20, endHour = 4)
        assertNull(eval(listOf(ev(10.0)), night)) // 18h fora da faixa
        val c = Calendar.getInstance().apply { set(2026, Calendar.NOVEMBER, 23, 23, 0) }
        assertEquals(true, AlertEngine.withinSchedule(night, c))
    }
    @Test fun disabled() { assertNull(eval(listOf(ev(20.0)), s.copy(alertsEnabled = false))) }

    @Test fun insightsRanksByAverage() {
        val t = cal.timeInMillis
        val hist = listOf(
            SurgeEvent(timestamp = t, lat = -23.55, lng = -46.63, kind = SurgeKind.MONEY, value = 5.0, source = SurgeSource.MANUAL),
            SurgeEvent(timestamp = t, lat = -23.605, lng = -46.705, kind = SurgeKind.MONEY, value = 12.0, source = SurgeSource.MANUAL),
            SurgeEvent(timestamp = t, lat = -23.606, lng = -46.706, kind = SurgeKind.MONEY, value = 10.0, source = SurgeSource.MANUAL),
        )
        val top = Insights.bestSpots(hist, cal, here)
        assertEquals(11.0, top.first().avgMoney!!, 0.001)
        assertEquals(2, top.first().samples)
    }
}
