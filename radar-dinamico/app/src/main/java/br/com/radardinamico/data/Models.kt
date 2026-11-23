package br.com.radardinamico.data

/** Tipo de dinâmico: valor fixo em reais (ex.: +R$ 6,00) ou multiplicador (ex.: 1,5x). */
enum class SurgeKind { MONEY, MULTIPLIER }

/** De onde veio a leitura. */
enum class SurgeSource(val label: String) {
    UBER_SCREEN("Tela do Uber Driver"),
    UBER_NOTIFICATION("Notificação do Uber"),
    MANUAL("Registro manual"),
    COMMUNITY("Comunidade"),
}

data class SurgeEvent(
    val id: Long = 0,
    val timestamp: Long,
    val lat: Double,
    val lng: Double,
    val kind: SurgeKind,
    val value: Double,
    val source: SurgeSource,
    val note: String? = null,
) {
    fun label(): String = when (kind) {
        SurgeKind.MONEY -> "+R$ " + "%.2f".format(value).replace('.', ',')
        SurgeKind.MULTIPLIER -> "%.1fx".format(value).replace('.', ',')
    }
}

data class GeoPoint(val lat: Double, val lng: Double)
