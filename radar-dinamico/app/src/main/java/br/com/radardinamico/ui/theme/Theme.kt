package br.com.radardinamico.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

// Paleta escura de alto contraste (uso noturno no carro). Base: design system "Radar Dinâmico".
object RadarColors {
    val Background = Color(0xFF0F172A)
    val Card = Color(0xFF192134)
    val Muted = Color(0xFF94A3B8)
    val Accent = Color(0xFF3B82F6)
    val Surge = Color(0xFFF59E0B)     // destaque de valor de dinâmico
    val Good = Color(0xFF22C55E)
    val Danger = Color(0xFFEF4444)
}

private val scheme = darkColorScheme(
    primary = RadarColors.Accent,
    onPrimary = Color.White,
    secondary = RadarColors.Surge,
    onSecondary = Color(0xFF111827),
    background = RadarColors.Background,
    onBackground = Color.White,
    surface = RadarColors.Background,
    onSurface = Color.White,
    surfaceVariant = RadarColors.Card,
    onSurfaceVariant = Color(0xFFCBD5E1),
    surfaceContainer = RadarColors.Card,
    surfaceContainerHigh = Color(0xFF223049),
    outline = Color(0xFF334155),
    error = RadarColors.Danger,
)

@Composable
fun RadarTheme(content: @Composable () -> Unit) {
    MaterialTheme(colorScheme = scheme, content = content)
}
