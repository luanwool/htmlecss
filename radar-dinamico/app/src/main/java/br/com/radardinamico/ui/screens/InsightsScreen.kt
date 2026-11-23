package br.com.radardinamico.ui.screens

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import br.com.radardinamico.data.Geo
import br.com.radardinamico.data.Insights
import br.com.radardinamico.data.SurgeRepository
import br.com.radardinamico.service.RadarState
import br.com.radardinamico.ui.theme.RadarColors
import java.util.Calendar

/** "Onde costuma pagar melhor": ranking de regiões por horário, a partir do histórico. */
@Composable
fun InsightsScreen(modifier: Modifier) {
    val ctx = LocalContext.current
    val here by RadarState.location.collectAsState()
    val recent by SurgeRepository.get(ctx).recent.collectAsState()
    var hour by remember { mutableIntStateOf(Calendar.getInstance().get(Calendar.HOUR_OF_DAY)) }
    var weekend by remember {
        val d = Calendar.getInstance().get(Calendar.DAY_OF_WEEK)
        mutableIntStateOf(if (d == Calendar.SATURDAY || d == Calendar.SUNDAY) 1 else 0)
    }
    val history = remember(recent.size) { SurgeRepository.get(ctx).since(System.currentTimeMillis() - 60L * 24 * 3600_000L) }
    val ref = Calendar.getInstance().apply {
        set(Calendar.HOUR_OF_DAY, hour)
        set(Calendar.DAY_OF_WEEK, if (weekend == 1) Calendar.SATURDAY else Calendar.WEDNESDAY)
    }
    val spots = remember(history, hour, weekend, here) { Insights.bestSpots(history, ref, here, limit = 15) }

    LazyColumn(modifier.fillMaxSize().padding(horizontal = 16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Spacer(Modifier.height(12.dp))
            Text("Onde paga melhor", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            Text("Baseado nas suas leituras dos últimos 60 dias (${history.size} registros).",
                color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        item {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                FilterChip(selected = weekend == 0, onClick = { weekend = 0 }, label = { Text("Dia útil") })
                FilterChip(selected = weekend == 1, onClick = { weekend = 1 }, label = { Text("Fim de semana") })
            }
            Spacer(Modifier.height(8.dp))
            LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                items((0..23).toList()) { h ->
                    FilterChip(selected = h == hour, onClick = { hour = h }, label = { Text("%02dh".format(h)) })
                }
            }
        }
        if (spots.isEmpty()) {
            item {
                SectionCard {
                    Text("Ainda não há dados para este horário.", fontWeight = FontWeight.SemiBold)
                    Text("Deixe o radar ligado enquanto roda: cada dinâmico lido ou marcado vira histórico " +
                        "e aqui aparece o ranking das regiões.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
        items(spots.withIndex().toList(), key = { it.value.cell }) { (i, s) ->
            SectionCard(modifier = Modifier.clickable {
                ctx.startActivity(Intent(Intent.ACTION_VIEW,
                    Uri.parse("geo:${s.center.lat},${s.center.lng}?q=${s.center.lat},${s.center.lng}(Região)")))
            }) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("${i + 1}º", fontSize = 22.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(44.dp))
                    Column(Modifier.weight(1f)) {
                        Text(
                            s.avgMoney?.let { "+R$ %.2f em média".format(it).replace('.', ',') }
                                ?: s.avgMultiplier?.let { "%.1fx em média".format(it).replace('.', ',') } ?: "-",
                            color = RadarColors.Surge, fontSize = 20.sp, fontWeight = FontWeight.Bold,
                        )
                        Text(buildString {
                            s.maxMoney?.let { append("máx. +R$ %.0f • ".format(it)) }
                            append("${s.samples} leituras")
                            s.distanceKm?.let { d -> append(" • ${Geo.formatKm(d)}"); here?.let { append(" ${Geo.cardinal(it, s.center)}") } }
                        }, color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
        }
        item { Spacer(Modifier.height(24.dp)) }
    }
}
