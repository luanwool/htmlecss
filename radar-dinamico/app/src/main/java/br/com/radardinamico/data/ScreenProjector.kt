package br.com.radardinamico.data

import kotlin.math.cos
import kotlin.math.pow

/**
 * Converte a posição de um rótulo de dinâmico na tela do mapa do Uber Driver em coordenada real.
 *
 * Premissas (calibráveis em Ajustes → Calibração do leitor):
 *  - o mapa do Uber está centralizado no carro do motorista, que fica no centro horizontal da tela
 *    e a [carYPercent]% da altura;
 *  - o mapa está com o norte para cima;
 *  - o nível de zoom do mapa é aproximadamente [zoom] (escala Web Mercator, blocos de 256 dp).
 */
object ScreenProjector {
    private const val METERS_PER_DP_ZOOM0 = 156_543.03392

    fun metersPerPixel(lat: Double, zoom: Double, density: Float): Double =
        METERS_PER_DP_ZOOM0 * cos(Math.toRadians(lat)) / 2.0.pow(zoom) / density

    fun project(
        driver: GeoPoint,
        labelX: Float, labelY: Float,
        screenW: Int, screenH: Int,
        density: Float, zoom: Double, carYPercent: Int,
    ): GeoPoint {
        val mpp = metersPerPixel(driver.lat, zoom, density)
        val dx = labelX - screenW / 2f
        val dy = labelY - screenH * carYPercent / 100f
        val north = -dy * mpp
        val east = dx * mpp
        val lat = driver.lat + north / 111_320.0
        val lng = driver.lng + east / (111_320.0 * cos(Math.toRadians(driver.lat)))
        return GeoPoint(lat, lng)
    }
}
