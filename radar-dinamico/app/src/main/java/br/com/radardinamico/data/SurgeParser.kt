package br.com.radardinamico.data

/**
 * Extrai valores de dinâmico de textos lidos da tela do app de motorista.
 * Formatos reconhecidos: "+R$ 6,00", "+R$6", "+ R$ 12,50", "1,5x", "1.8x", "2×".
 */
object SurgeParser {
    data class Reading(val kind: SurgeKind, val value: Double)

    private val money = Regex("""\+\s*R\$\s*(\d{1,3}(?:[.,]\d{1,2})?)""")
    private val mult = Regex("""(?<![\d.,])([1-5](?:[.,]\d{1,2})?)\s*[x×](?![a-zA-Z])""")

    fun parse(text: String): List<Reading> {
        val out = mutableListOf<Reading>()
        money.findAll(text).forEach { m ->
            m.groupValues[1].replace(',', '.').toDoubleOrNull()
                ?.takeIf { it in 0.5..200.0 }
                ?.let { out += Reading(SurgeKind.MONEY, it) }
        }
        mult.findAll(text).forEach { m ->
            m.groupValues[1].replace(',', '.').toDoubleOrNull()
                ?.takeIf { it > 1.0 && it <= 5.0 }
                ?.let { out += Reading(SurgeKind.MULTIPLIER, it) }
        }
        return out
    }

    /** Maior leitura de cada tipo em um conjunto de textos. */
    fun best(texts: Collection<String>): List<Reading> =
        texts.flatMap { parse(it) }
            .groupBy { it.kind }
            .map { (_, list) -> list.maxBy { it.value } }
}
