package br.com.radardinamico.service

import android.content.Context
import android.location.Address
import android.location.Geocoder
import br.com.radardinamico.data.GeoPoint
import java.util.Locale
import java.util.concurrent.ConcurrentHashMap
import kotlin.math.floor

/**
 * Descobre o bairro de uma coordenada com o Geocoder do Android (geocodificação reversa).
 * Resultados ficam em cache por célula de ~500 m (memória + aparelho), para não consultar a rede toda hora.
 */
object Neighborhood {
    private const val PREFS = "bairros"
    private const val CELL = 0.005
    private val cache = ConcurrentHashMap<String, String>()
    private var loaded = false

    private fun key(p: GeoPoint) = "${floor(p.lat / CELL).toLong()}:${floor(p.lng / CELL).toLong()}"

    fun cached(ctx: Context, p: GeoPoint): String? { load(ctx); return cache[key(p)]?.takeIf { it.isNotEmpty() } }

    /** Bloqueante (pode usar a rede): chame fora da thread principal. */
    fun resolve(ctx: Context, p: GeoPoint): String? {
        load(ctx)
        val k = key(p)
        cache[k]?.let { return it.takeIf { n -> n.isNotEmpty() } }
        if (!Geocoder.isPresent()) return null
        val address = runCatching {
            @Suppress("DEPRECATION")
            Geocoder(ctx, Locale("pt", "BR")).getFromLocation(p.lat, p.lng, 1)?.firstOrNull()
        }.getOrNull() ?: return null // falha de rede: tenta de novo na próxima vez
        val name = pick(address) ?: ""
        cache[k] = name
        ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putString(k, name).apply()
        return name.takeIf { it.isNotEmpty() }
    }

    private fun pick(a: Address): String? =
        listOf(a.subLocality, a.locality, a.subAdminArea).firstOrNull { !it.isNullOrBlank() }

    private fun load(ctx: Context) {
        if (loaded) return
        loaded = true
        ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE).all.forEach { (k, v) -> (v as? String)?.let { cache[k] = it } }
    }
}
