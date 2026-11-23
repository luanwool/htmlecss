package br.com.radardinamico

import br.com.radardinamico.data.SurgeKind
import br.com.radardinamico.data.SurgeParser
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class SurgeParserTest {
    @Test fun moneyFormats() {
        assertEquals(6.0, SurgeParser.parse("+R$ 6,00").single().value, 0.001)
        assertEquals(6.0, SurgeParser.parse("+R$6").single().value, 0.001)
        assertEquals(12.5, SurgeParser.parse("Dinâmico + R$ 12,50 nesta área").single().value, 0.001)
    }
    @Test fun multiplierFormats() {
        val r = SurgeParser.parse("1,5x").single()
        assertEquals(SurgeKind.MULTIPLIER, r.kind); assertEquals(1.5, r.value, 0.001)
        assertEquals(2.0, SurgeParser.parse("2×").single().value, 0.001)
    }
    @Test fun ignoresNoise() {
        assertTrue(SurgeParser.parse("R$ 23,40 ganhos hoje").isEmpty()) // sem "+": não é dinâmico
        assertTrue(SurgeParser.parse("1x").isEmpty())
        assertTrue(SurgeParser.parse("12x sem juros").isEmpty())
        assertTrue(SurgeParser.parse("box").isEmpty())
    }
    @Test fun bestPicksMax() {
        val b = SurgeParser.best(listOf("+R$ 4,00", "+R$ 9,00", "1,3x", "1,7x"))
        assertEquals(9.0, b.first { it.kind == SurgeKind.MONEY }.value, 0.001)
        assertEquals(1.7, b.first { it.kind == SurgeKind.MULTIPLIER }.value, 0.001)
    }
}
