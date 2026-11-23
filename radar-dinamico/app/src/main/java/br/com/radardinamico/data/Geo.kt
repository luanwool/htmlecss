package br.com.radardinamico.data

import kotlin.math.asin
import kotlin.math.atan2
import kotlin.math.cos
import kotlin.math.floor
import kotlin.math.pow
import kotlin.math.sin
import kotlin.math.sqrt

object Geo {
    private const val EARTH_RADIUS_KM = 6371.0

    fun distanceKm(a: GeoPoint, b: GeoPoint): Double {
        val dLat = Math.toRadians(b.lat - a.lat)
        val dLng = Math.toRadians(b.lng - a.lng)
        val h = sin(dLat / 2).pow(2) +
            cos(Math.toRadians(a.lat)) * cos(Math.toRadians(b.lat)) * sin(dLng / 2).pow(2)
        return 2 * EARTH_RADIUS_KM * asin(sqrt(h))
    }

    /** Direção cardinal (N, NE, L, SE, S, SO, O, NO) de [from] para [to]. */
    fun cardinal(from: GeoPoint, to: GeoPoint): String {
        val y = sin(Math.toRadians(to.lng - from.lng)) * cos(Math.toRadians(to.lat))
        val x = cos(Math.toRadians(from.lat)) * sin(Math.toRadians(to.lat)) -
            sin(Math.toRadians(from.lat)) * cos(Math.toRadians(to.lat)) * cos(Math.toRadians(to.lng - from.lng))
        val bearing = (Math.toDegrees(atan2(y, x)) + 360) % 360
        val names = listOf("N", "NE", "L", "SE", "S", "SO", "O", "NO")
        return names[((bearing + 22.5) / 45).toInt() % 8]
    }

    /** Célula de grade de ~1,1 km usada para agrupar regiões. */
    fun cellOf(p: GeoPoint, sizeDeg: Double = 0.01): String {
        val i = floor(p.lat / sizeDeg).toLong()
        val j = floor(p.lng / sizeDeg).toLong()
        return "$i:$j"
    }

    fun cellCenter(cell: String, sizeDeg: Double = 0.01): GeoPoint {
        val (i, j) = cell.split(":").map { it.toLong() }
        return GeoPoint((i + 0.5) * sizeDeg, (j + 0.5) * sizeDeg)
    }

    fun formatKm(km: Double): String =
        if (km < 1) "${(km * 1000).toInt()} m" else "%.1f km".format(km).replace('.', ',')
}
