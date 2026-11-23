package br.com.radardinamico.service

import br.com.radardinamico.data.GeoPoint
import br.com.radardinamico.data.SurgeEvent
import br.com.radardinamico.data.SurgeKind
import br.com.radardinamico.data.SurgeSource
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

/**
 * Fonte opcional: servidor comunitário onde motoristas compartilham dinâmicos.
 * Contrato (veja README):
 *   GET  {url}/surges?lat=..&lng=..&radiusKm=..  -> [{"lat":..,"lng":..,"kind":"MONEY","value":6.0,"ts":1700000000000}]
 *   POST {url}/surges  body: {"lat":..,"lng":..,"kind":"MONEY","value":6.0,"ts":..}
 */
object CommunitySource {

    fun fetch(baseUrl: String, here: GeoPoint, radiusKm: Double): List<SurgeEvent> {
        val url = URL("${baseUrl.trimEnd('/')}/surges?lat=${here.lat}&lng=${here.lng}&radiusKm=$radiusKm")
        val conn = (url.openConnection() as HttpURLConnection).apply {
            connectTimeout = 8000; readTimeout = 8000
        }
        try {
            if (conn.responseCode !in 200..299) error("HTTP ${conn.responseCode}")
            return parse(conn.inputStream.bufferedReader().readText())
        } finally { conn.disconnect() }
    }

    fun parse(json: String): List<SurgeEvent> {
        val arr = JSONArray(json)
        return (0 until arr.length()).mapNotNull { i ->
            val o = arr.getJSONObject(i)
            runCatching {
                SurgeEvent(
                    timestamp = o.getLong("ts"), lat = o.getDouble("lat"), lng = o.getDouble("lng"),
                    kind = SurgeKind.valueOf(o.optString("kind", "MONEY")), value = o.getDouble("value"),
                    source = SurgeSource.COMMUNITY,
                )
            }.getOrNull()
        }
    }

    fun publish(baseUrl: String, e: SurgeEvent) {
        val conn = (URL("${baseUrl.trimEnd('/')}/surges").openConnection() as HttpURLConnection).apply {
            requestMethod = "POST"; doOutput = true; connectTimeout = 8000; readTimeout = 8000
            setRequestProperty("Content-Type", "application/json")
        }
        try {
            val body = JSONObject().put("lat", e.lat).put("lng", e.lng)
                .put("kind", e.kind.name).put("value", e.value).put("ts", e.timestamp)
            conn.outputStream.use { it.write(body.toString().toByteArray()) }
            conn.responseCode
        } finally { conn.disconnect() }
    }
}
