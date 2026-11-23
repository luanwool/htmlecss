package br.com.radardinamico.ui.screens

import android.content.Context
import android.provider.Settings as AndroidSettings
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import br.com.radardinamico.service.UberReaderService
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@Composable
fun SectionCard(title: String? = null, modifier: Modifier = Modifier, content: @Composable ColumnScope.() -> Unit) {
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
    ) {
        Column(Modifier.padding(16.dp)) {
            if (title != null) {
                Text(title, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                androidx.compose.foundation.layout.Spacer(Modifier.padding(top = 8.dp))
            }
            content()
        }
    }
}

fun timeAgo(ts: Long, now: Long = System.currentTimeMillis()): String {
    val min = (now - ts) / 60_000
    return when {
        min < 1 -> "agora"
        min < 60 -> "há $min min"
        else -> SimpleDateFormat("dd/MM HH:mm", Locale("pt", "BR")).format(Date(ts))
    }
}

fun isReaderEnabled(ctx: Context): Boolean {
    val enabled = AndroidSettings.Secure.getString(
        ctx.contentResolver, AndroidSettings.Secure.ENABLED_ACCESSIBILITY_SERVICES,
    ) ?: return false
    return enabled.split(':').any { it.contains(ctx.packageName) && it.contains(UberReaderService::class.java.simpleName) }
}
