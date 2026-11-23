package br.com.radardinamico

import br.com.radardinamico.data.Geo
import br.com.radardinamico.data.GeoPoint
import br.com.radardinamico.data.ScreenProjector
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class ScreenProjectorTest {
    private val driver = GeoPoint(-23.5505, -46.6333)
    private val w = 1080; private val h = 2400; private val density = 2.75f

    @Test fun labelAtCarPositionIsDriverPosition() {
        val p = ScreenProjector.project(driver, 540f, 1080f, w, h, density, 14.0, 45)
        assertTrue(Geo.distanceKm(driver, p) < 0.001)
    }

    @Test fun labelAboveCarIsNorth() {
        val p = ScreenProjector.project(driver, 540f, 780f, w, h, density, 14.0, 45) // 300 px acima
        assertTrue(p.lat > driver.lat)
        assertEquals("N", Geo.cardinal(driver, p))
        // zoom 14, lat -23,5: ~3,2 m/px → 300 px ≈ 0,96 km
        assertEquals(0.96, Geo.distanceKm(driver, p), 0.05)
    }

    @Test fun labelRightIsEastAndZoomScales() {
        val z14 = ScreenProjector.project(driver, 840f, 1080f, w, h, density, 14.0, 45)
        val z13 = ScreenProjector.project(driver, 840f, 1080f, w, h, density, 13.0, 45)
        assertEquals("L", Geo.cardinal(driver, z14))
        assertEquals(2.0, Geo.distanceKm(driver, z13) / Geo.distanceKm(driver, z14), 0.01)
    }
}
